# Flow — Daily billing automation (`billing:generate`)

Full runtime reference: [`.claude/rules/BILLING_AUTOMATION_RUNTIME.md`](../../../.claude/rules/BILLING_AUTOMATION_RUNTIME.md).

```mermaid
sequenceDiagram
    autonumber
    participant Cron as Scheduler (02:00 daily)
    participant Cmd as BillingGenerate
    participant Auto as BillingAutomationService
    participant Sched as BillingScheduleService
    participant Adv as AdvanceBillingService
    participant Inv as InvoiceService /<br/>TherapistBillService
    participant Ledger as LedgerService
    participant DB as MySQL

    Cron->>Cmd: billing:generate
    Cmd->>Auto: processAllDueSchedules()
    Auto->>DB: BillingSchedule::due()<br/>(is_active, auto_generate, next_run_at <= today)

    loop each due schedule
        alt billing_mode = advance (schools only)
            Auto->>Adv: processAdvanceSchedule(schedule)
            Adv->>Sched: resolveCompletedPeriod() + resolveUpcomingPeriod()
            Adv->>DB: schedules (status SCHEDULED, upcoming period) → charge lines
            Adv->>DB: session_logs (completed period) → adjustment lines
            Adv->>DB: create invoice (billing_mode = advance)<br/>+ invoice_line_items + carry-forward
        else billing_mode = standard
            Auto->>Sched: resolveCurrentPeriod()<br/>(last_period_end + 1, else billing_start_date, else now)
            Auto->>DB: sweep approved, billable, un-invoiced/un-billed<br/>session_logs with session_date <= period_end (no lower bound!)
            alt sweep empty
                Auto->>DB: log run SKIPPED_NO_SESSIONS
            else
                Auto->>Inv: generateInvoice() / generateBill()<br/>due_date = run_date + payment_terms_days
            end
        end
        opt auto_send (therapist bills, total > 0)
            Auto->>Auto: sendBill() — outside the transaction,<br/>try/catch, failure never fails generation
        end
        Auto->>DB: log run on billing_schedule_runs
        Auto->>Sched: advanceSchedule()<br/>last_period_end, next_run_at = calculateNextRunDate()
    end

    Note over Inv,Ledger: the ledger entry posts when the invoice is SENT,<br/>not at generation — see flows/invoice-lifecycle.md
    Note over Auto: failure path: FAILED run logged, schedule does NOT advance —<br/>next cron tick retries the same period
```

Facts worth remembering while reading code:

- `next_run_at` is the **only** "is it due" check — no frequency math happens at cron time.
- The sweep has **no lower date bound**: old un-billed approved sessions ride into the next bill by design.
- Only `AdvanceBillingService::createAdvanceInvoice()` ever writes `billing_mode = advance`.
