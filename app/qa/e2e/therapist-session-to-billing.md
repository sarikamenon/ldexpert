# E2E: Therapist Session to Billing

**Flow:** Therapist creates schedule → Submits session log → Admin approves → Admin generates bill → Admin sends → Admin records payment → Ledger verified

**Dusk test:** `tests/BrowserQA/E2E/QaTherapistSessionToBillingBrowserTest.php`

## Steps

| # | Actor | Action | Verify |
|---|-------|--------|--------|
| 1 | Therapist | Creates schedule for assigned student | Schedule on calendar |
| 2 | Therapist | Submits session log (DRAFT → SUBMITTED) | Log status = SUBMITTED |
| 3 | Admin | Approves session log | Log status = APPROVED |
| 4 | Admin | Creates therapist bill from approved sessions | Bill in DRAFT with correct total |
| 5 | Admin | Sends therapist bill | Status → SENT, `sent_at` set |
| 6 | Admin | Records payment against bill | Status → PAID |
| 7 | System | Ledger verify | `php artisan ledger:verify` — no drift |

## Pass Criteria
- [ ] Bill total matches session log billable amount
- [ ] `ledger_entries` row created on payment
- [ ] `balance_after` is consistent
- [ ] Therapist can view paystub after payment recorded

## Key DB Assertions
```php
// $sessionLog is created in TD-E002 factory setup
$this->assertDatabaseHas('therapist_bills', ['status' => 'paid']);
$this->assertDatabaseHas('ledger_entries', ['amount' => $sessionLog->therapist_billable_amount]);
```
