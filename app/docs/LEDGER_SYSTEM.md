# Ledger System

Canonical reference for how `ledger_entries` is read, written, and maintained.

## 1. Mental model

The ledger is a single append-mostly table (`ledger_entries`) that records every event that changes a counterparty's balance. Two kinds of accounts share the table via `ledgerable_type` + `ledgerable_id`:

- **AR (accounts receivable)**: `ledgerable_type = App\Models\School`. Positive balance = the school owes us money.
- **AP (accounts payable)**: `ledgerable_type = App\Models\User` (therapist). Positive balance = we owe the therapist.

Each row stores `amount` (always positive) and `transaction_type` (an enum). The sign of its effect on the running balance is derived from `TransactionType::balanceDelta()`, never hardcoded.

## 2. Sign convention (single source of truth)

`App\Enums\TransactionType::balanceDelta()` returns +1 or -1 per case:

| transaction_type | delta | Direction |
|---|---|---|
| `invoice_generated` | +1 | School owes more |
| `bill_generated` | +1 | We owe therapist more |
| `payment_received` | -1 | School owes less |
| `payment_made` | -1 | We owe therapist less |
| `credit_note` | -1 | Forgive what counterparty owes |
| `refund` | +1 | Cash sent back / reverses prior credit position |
| `expense` | -1 | Money out (matches `ExpenseDemoSeeder` semantics) |

`LedgerEntry::signedAmount()` returns `amount * balanceDelta()`. Use it everywhere that needs a signed contribution to the balance.

## 3. The `balance_after` invariant

For every account, sorted by `(recorded_at, id)`:

```
balance_after = SUM(signedAmount of all earlier non-soft-deleted rows for the same account)
```

The invariant holds **per row**, not just at the tail: every row's stored `balance_after` must equal the running signed-amount total up to and including itself. `php artisan ledger:verify` checks this row-by-row.

## 4. `recorded_at` vs `created_at`

Two timestamps with very different meanings:

- **`recorded_at`** — when the underlying transaction *occurred*. User-controlled date (the form field on credit notes/refunds; backfilled from source-document dates for invoice/bill/payment/expense rows). May be backdated. Adjustments combine the user-picked **date** with the **current time-of-day** at submit so rows sort deterministically by insertion moment within their date and so the new row interleaves naturally with same-day invoices/payments.
- **`created_at`** — when the row was inserted into the database. DB-controlled. Never user-controlled.

**Naming gotcha**: `recorded_by_id` (the user who clicked Save) is unrelated to `recorded_at` (when the underlying transaction happened). Read these as two independent fields.

Chain ordering and balance computations always use `(recorded_at, id)`. `id` resolves intra-day ties deterministically.

## 5. Write rule

**All writes to `ledger_entries` MUST go through `App\Domain\Finance\Services\LedgerService`.** Direct `LedgerEntry::create(...)` calls outside the service are forbidden in production code (seeders excepted).

`LedgerService::createEntry(...)` is the single insertion path. It:

1. Locks the latest row of the account (`SELECT ... FOR UPDATE`).
2. If the new `recorded_at` is strictly greater than every existing row, inserts at the end with `balance_after = previousBalance + signed`. (Forward-dated insert — fast path.)
3. Otherwise (backdated): inserts with a placeholder `balance_after = 0`, then calls `recomputeChainFrom(recorded_at)` to rewrite the chain.

The four credit-note / refund methods (`createCreditNoteForSchool`, etc.) and the two source-document writers (`createInvoiceGeneratedEntry`, `createBillGeneratedEntry`) all funnel through `createEntry`. Payment writers (`InvoicePaymentService`, `TherapistBillPaymentService`) inject `LedgerService` and call `createEntry` directly.

## 6. The recompute walker

`LedgerService::recomputeChainFrom(string $ledgerableType, int $ledgerableId, CarbonInterface $from): void`

Inside `DB::transaction`:

1. `SELECT id, transaction_type, amount, balance_after FROM ledger_entries WHERE ledgerable_type = ? AND ledgerable_id = ? ORDER BY recorded_at, id FOR UPDATE`. Locks the entire chain (typically 50–350 rows for an active account).
2. Walk from row 1, accumulating `running += row.signedAmount()`.
3. For **every** row whose stored `balance_after` differs from `running`, write the corrected value.

The walker is fully self-healing: it rewrites any row in the chain that drifts, regardless of position. The `$from` parameter is retained in the signature for callers that want to communicate intent, but it does not gate which rows are eligible to heal — historical residue from earlier partial recomputes is corrected on the next touch.

Soft-deleted rows are excluded by Eloquent's `SoftDeletes` scope — they don't contribute and don't get rewritten.

The walker fires on:
- Backdated `createEntry` calls.
- `editAdjustment` (recomputes from the earlier of old/new `recorded_at`).
- `deleteAdjustment` (recomputes from the deleted row's `recorded_at`).
- `InvoicePaymentService::deletePayment` and `TherapistBillPaymentService::deletePayment` (cascade soft-delete + recompute).

## 7. Edit / delete matrix

Only `credit_note` and `refund` rows are mutable from the ledger UI. All other types must be edited via their source-document page.

| transaction_type | Edit on ledger UI | Source-document page |
|---|---|---|
| `invoice_generated` | ❌ | Invoices |
| `bill_generated` | ❌ | Therapist Bills |
| `payment_received` | ❌ | Invoices → Payments |
| `payment_made` | ❌ | Therapist Bills → Payments |
| `expense` | ❌ | Expenses |
| `credit_note` | ✅ | — |
| `refund` | ✅ | — |

The guard is enforced server-side in `LedgerAccountController::updateAdjustment` and `destroyAdjustment` (returns **403** on non-adjustment rows) and again inside `LedgerService::editAdjustment` / `deleteAdjustment` (throws `InvalidArgumentException`). UI is defense-in-depth; the controller is authoritative.

## 8. Soft-delete semantics

`LedgerEntry` uses `Illuminate\Database\Eloquent\SoftDeletes`. Soft-deleted rows are conceptually erased: they don't contribute to `signedAmount` sums, the recompute walker skips them, and the verify command ignores them. The audit trail (who/when, the original row) is preserved.

`InvoicePaymentService::deletePayment` and `TherapistBillPaymentService::deletePayment` switched from hard-delete to soft-delete + recompute in the credit-note / refund work. Pre-fix, deleting a payment hard-deleted its ledger row and left stale `balance_after` values on every later row.

## 9. Concurrency

`SELECT ... FOR UPDATE` over the whole chain serializes writes per account. Different accounts don't contend. Read paths use the default `REPEATABLE READ` snapshot — they never see a half-rewritten chain. Every transaction touches exactly one account, so deadlocks are not a concern.

## 10. The verify command

```
php artisan ledger:verify [--account-type=...] [--account-id=...] [--fix]
```

For each account: walk the chain in `(recorded_at, id)` order and compare each row's stored `balance_after` against the running signed-amount total. Reports the **first** drifted row per account. Without `--fix`, exits non-zero. With `--fix`, calls `recomputeChainFrom` for each drifted account, which heals every drifted row in the chain in one pass.

The per-row check matters because end-of-chain comparison alone can mask drift: an offset on intermediate rows that nets out by the last row would falsely pass.

Run on demand to audit. Recommended to schedule nightly as a safety net against future writers that bypass `LedgerService`.

## 11. Worked examples

### Backdated credit note

Before:

```
2026-04-25 invoice_generated  $500   bal +500
2026-04-29 refund              $50   bal +550
```

Admin records a credit note dated 2026-04-27 for $100. `createEntry` detects backdating (new key < latest key), inserts the row with placeholder balance, and runs the walker:

```
2026-04-25 invoice_generated  $500   bal +500
2026-04-27 credit_note        $100   bal +400  ← walked
2026-04-29 refund              $50   bal +450  ← walked
```

### Payment delete cascade

Before:

```
2026-04-25 invoice_generated  $500   bal +500
2026-04-26 payment_received   $100   bal +400
2026-04-29 refund              $50   bal +450
```

Admin deletes the payment. `InvoicePaymentService::deletePayment` soft-deletes the allocation and the ledger entry, then runs the walker from `recorded_at = 2026-04-26`:

```
2026-04-25 invoice_generated  $500   bal +500
[2026-04-26 payment_received  $100   soft-deleted, ignored]
2026-04-29 refund              $50   bal +550  ← walked
```

## 12. Future work (not in scope)

- **Forward-dating** — `recorded_at` is currently constrained to `<= today`. A separate feature could relax this for known scheduled transactions.
- **Automatic-vs-manual badge** — derivable from `reference->gateway` if a future audit view wants it.
- **B2 storage model** — drop stored `balance_after` and compute on read. Decided against in v2 because the recompute walker is small and `balance_after` is a useful historical record.
- **PHPStan rule banning `LedgerEntry::create()` outside `LedgerService`** — would close the only enforcement gap left by convention.
