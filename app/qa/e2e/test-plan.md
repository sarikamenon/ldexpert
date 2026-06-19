# E2E Test Plan

> Source: app/wiki/*/*.md (+ route inventory for features not yet documented)
> Generated: 2026-06-12 (auto-derived from app/qa/LD-Expert-QA.xlsx - E2E sheet)
> Coverage: 8 features, 18 test cases (12 Valid / 4 Invalid / 2 Edge)

## Scope

Cross-role workflows that span Admin, Therapist and Finance in a single flow - onboarding, session-to-billing, substitute coverage, billing automation, role isolation and timezone display.

---

## Feature Areas

| Area | Priority | Test Cases | Count | Wiki Reference |
|------|----------|-----------|-------|----------------|
| School Onboarding | P1 | TC-E001, TC-E002, TC-E003 | 3 | wiki/admin/* |
| Contract Lifecycle | P1 | TC-E004 | 1 | wiki/admin/contracts.md |
| Session to Billing | P1 | TC-E005, TC-E006, TC-E007, TC-E008, TC-E009 | 5 | wiki/finance/invoicing.md |
| Therapist Bill Flow | P1 | TC-E010, TC-E011 | 2 | wiki/finance/billing.md |
| Sub Coverage | P1 | TC-E012, TC-E013, TC-E014 | 3 | wiki/therapist/sub-coverage.md |
| Billing Automation | P1 | TC-E015, TC-E016 | 2 | wiki/finance/billing-automation.md |
| Role Isolation | P1 | TC-E017 | 1 | - |
| Timezone Display | P2 | TC-E018 | 1 | - |

---

## Test Cases by Feature

### School Onboarding

| TC ID | Condition | Test Name | Expected Result |
|-------|-----------|-----------|-----------------|
| TC-E001 | Valid | Onboard school, therapist, student and SSA | School, therapist, student, SSA all linked; SSA activatable |
| TC-E002 | Valid | Assigned student appears in therapist caseload | The student appears read-only in therapist caseload |
| TC-E003 | Invalid | Cannot create student under deactivated school | School not selectable / blocked - only active schools |

### Contract Lifecycle

| TC ID | Condition | Test Name | Expected Result |
|-------|-----------|-----------|-----------------|
| TC-E004 | Valid | School contract drives invoice rates | Invoice school_invoice_amount derives from contract rate |

### Session to Billing

| TC ID | Condition | Test Name | Expected Result |
|-------|-----------|-----------|-----------------|
| TC-E005 | Valid | Therapist log to approved to invoiced to paid | Status flows DRAFT->SUBMITTED->APPROVED; invoice DRAFT->SENT->PAID; ledger entry created |
| TC-E006 | Valid | Approved log appears as billable line | Approved log selectable with correct school_invoice_amount |
| TC-E008 | Valid | Send back blocks billing until corrected | Sent-back log excluded; therapist must correct and resubmit |
| TC-E007 | Invalid | Submitted-but-not-approved log not billable | Log not available for invoicing until approved |
| TC-E009 | Edge | Cancelled log removed from billing pipeline | Cancelled log not billable |

### Therapist Bill Flow

| TC ID | Condition | Test Name | Expected Result |
|-------|-----------|-----------|-----------------|
| TC-E010 | Valid | Approved logs to therapist bill to paid | Bill DRAFT->SENT->PAID; ledger entry created; ledger:verify passes |
| TC-E011 | Valid | Therapist views their bill after generation | Therapist sees the bill and can download PDF |

### Sub Coverage

| TC ID | Condition | Test Name | Expected Result |
|-------|-----------|-----------|-----------------|
| TC-E012 | Valid | Sub request accepted and performer billed | Schedule shows Covered by B; B is performer (therapist_id=B, original_therapist_id=A); billing follows B |
| TC-E013 | Valid | Original therapist Bill affordance suppressed | A sees 'Covered by B' badge; Bill action suppressed for A |
| TC-E014 | Invalid | Concurrent accept - only first wins | C gets 422 already-accepted error; single performer of record |

### Billing Automation

| TC ID | Condition | Test Name | Expected Result |
|-------|-----------|-----------|-----------------|
| TC-E015 | Valid | Scheduled run generates draft invoice | Draft invoice generated, run logged success with line per session |
| TC-E016 | Edge | Advance billing reconciles prior period | New invoice includes adjustment lines (e.g. no-show credit) plus next advance charges |

### Role Isolation

| TC ID | Condition | Test Name | Expected Result |
|-------|-----------|-----------|-----------------|
| TC-E017 | Invalid | Each role blocked from others' areas | All cross-role visits redirect (not 200) |

### Timezone Display

| TC ID | Condition | Test Name | Expected Result |
|-------|-----------|-----------|-----------------|
| TC-E018 | Valid | Session time shows in each viewer's timezone | Each viewer sees the time converted to their own timezone, not raw UTC |
