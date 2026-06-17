# QA Test Cases Changelog

Track when new QA test cases are added, which version they cover, and which feature areas they belong to.

**Last Updated:** 2026-06-08

---

## Versioning

- **Release Version Format:** `v1.0.0`, `v1.1.0`, etc. (matches app releases)
- **Test Suite Version:** Independent from app releases; incremented when significant QA coverage is added
- **Entry Format:** Date | Test Code Range | Feature Areas | Version | Status

---

## Version History

### Test Suite v1.0 (Current)
**Released:** 2026-06-08  
**Test Cases:** 125+ across all roles  
**Coverage:** Admin, Therapist, Student, Finance, E2E flows

#### Entry 1.0.0 — Initial QA Framework & Admin Tests
- **Date Added:** 2026-06-08
- **Test Codes:** TC-A001 to TC-A030 (Admin)
- **Feature Areas:**
  - Authentication (TC-A001–A005)
  - Schools CRUD (TC-A006–A015)
  - Students CRUD (TC-A016–A025)
  - Therapists CRUD (TC-A026–A030)
- **App Version:** v1.0.0+
- **Source:** `qa/admin/test-plan.md`, `qa/LD-Expert-QA.xlsx` — Admin sheet
- **Status:** ✅ Generated & Passing
- **Notes:** Initial rollout covering core admin onboarding flows

#### Entry 1.0.1 — Therapist Role Tests
- **Date Added:** 2026-06-08
- **Test Codes:** TC-T001 to TC-T025 (Therapist)
- **Feature Areas:**
  - Authentication (TC-T001–T005)
  - Schedule Management (TC-T006–T010)
  - Calendar View (TC-T011–T015)
  - Session Logs (TC-T016–T020)
  - Student Management (TC-T021–T025)
- **App Version:** v1.0.0+
- **Source:** `qa/therapist/test-plan.md`, `qa/LD-Expert-QA.xlsx` — Therapist sheet
- **Status:** ✅ Generated & Passing
- **Notes:** Core therapist workflows; calendar timezone handling tested

#### Entry 1.0.2 — Student Role Tests
- **Date Added:** 2026-06-08
- **Test Codes:** TC-S001 to TC-S020 (Student)
- **Feature Areas:**
  - Authentication (TC-S001–S005)
  - Dashboard (TC-S006–S010)
  - Schedule View (TC-S011–S015)
  - Goal Tracking (TC-S016–S020)
- **App Version:** v1.0.0+
- **Source:** `qa/student/test-plan.md`, `qa/LD-Expert-QA.xlsx` — Student sheet
- **Status:** ✅ Generated & Passing
- **Notes:** Student-facing features; read-only operations

#### Entry 1.0.3 — Finance & Billing Tests
- **Date Added:** 2026-06-08
- **Test Codes:** TC-F001 to TC-F010 (Finance)
- **Feature Areas:**
  - Invoice Creation (TC-F001–F004)
  - Payment Processing (TC-F005–F007)
  - Therapist Billing (TC-F008–F010)
- **App Version:** v1.0.0+
- **Source:** `qa/finance/test-plan.md`, `qa/LD-Expert-QA.xlsx` — Finance sheet
- **Status:** ✅ Generated & Passing
- **Notes:** Ledger system integration verified; UTC date handling tested

#### Entry 1.0.4 — End-to-End Cross-Role Flows
- **Date Added:** 2026-06-08
- **Test Codes:** TC-E001 to TC-E015 (E2E)
- **Feature Areas:**
  - Student Journey (TC-E001–E005)
  - Therapist Session to Billing (TC-E006–E010)
  - Admin Audit Flow (TC-E011–E015)
- **App Version:** v1.0.0+
- **Source:** `qa/e2e/` folder, `qa/LD-Expert-QA.xlsx` — E2E sheet
- **Status:** ✅ Generated & Passing
- **Notes:** Validates complete workflows across role boundaries; integrates all prior suites

#### Entry 1.0.5 — Smoke Test Suite
- **Date Added:** 2026-06-08
- **Test Codes:** Subset of all roles marked with `.group('smoke')`
- **Feature Areas:**
  - Critical paths only (login, dashboard, CRUD operations)
  - Estimated: TC-A001, A006, T001, S001, F001, E001, etc.
- **App Version:** v1.0.0+
- **Source:** `app/tests/BrowserQA/Smoke/`
- **Status:** ✅ Passing (~10 min runtime)
- **Notes:** Fast sanity check before deeper QA runs; CI baseline

---

## Planned Additions

### Test Suite v1.1 (Planned)
**Target Release Date:** 2026-07-15  
**Estimated New Test Cases:** 20–30

#### Feature Coverage Gaps to Address
- [ ] SSA Management (currently under Admin; needs dedicated tests)
- [ ] Session Log Approval Workflows (currently scattered)
- [ ] Therapist Bill Payment Recording (currently basic)
- [ ] CSV Import Validation (schools, students, SSAs)
- [ ] Report Generation (caseload, SSA utilization, revenue)
- [ ] Role Isolation / Authorization (cross-role access denial)
- [ ] Timezone Handling Edge Cases (Dusk tests verifying UTC/local conversions)

#### Proposed Test Codes
- TC-A031–A040: SSA Management, Services, Contracts
- TC-T026–T030: Payroll, Time Tracking
- TC-S021–S025: Goals Progress, Messaging
- TC-F011–F015: Payment Refunds, Ledger Audits
- TC-E016–E020: Finance Reconciliation, Cascading Updates

#### Documentation Updates Needed
- [ ] Update `qa/admin/test-plan.md` with SSA & contract sections
- [ ] Create `qa/admin/contracts-test-plan.md`
- [ ] Add timezone test strategy to `qa/SETUP.md`
- [ ] Update `qa/LD-Expert-QA.xlsx` with new sheets

---

## Running Test Suites

### By Version
```bash
# Run all current tests (v1.0)
/qa-admin
/qa-therapist
/qa-student
/qa-finance
/qa-e2e

# Run smoke tests only (fast sanity check)
/qa-smoke
```

### View Reports
```
qa/reports/
├── admin-2026-06-08-1430.md
├── admin-2026-06-08-1430.html
├── therapist-2026-06-08-1500.md
├── therapist-2026-06-08-1500.html
└── ...
```

---

## Test Case Organization

| Role | Suite | Count | Status | Last Run |
|------|-------|-------|--------|----------|
| Admin | TC-A* | 30+ | ✅ | 2026-06-08 |
| Therapist | TC-T* | 25+ | ✅ | 2026-06-08 |
| Student | TC-S* | 20+ | ✅ | 2026-06-08 |
| Finance | TC-F* | 10+ | ✅ | 2026-06-08 |
| E2E | TC-E* | 15+ | ✅ | 2026-06-08 |
| **TOTAL** | — | **125+** | ✅ | **2026-06-08** |

---

## Release Mapping

| App Version | QA Suite Version | Test Codes Introduced | Release Date |
|---|---|---|---|
| v1.0.0 | v1.0.0–v1.0.5 | TC-A001–A030, TC-T001–T025, TC-S001–S020, TC-F001–F010, TC-E001–E015 | 2026-06-08 |
| v1.1.0 | v1.1.0+ | TC-A031–A040, TC-T026–T030, TC-S021–S025, TC-F011–F015, TC-E016–E020 (planned) | TBD |

---

## How to Add New Test Cases

1. **Update `qa/LD-Expert-QA.xlsx`**
   - Add row to appropriate sheet (Admin, Therapist, Student, Finance, E2E)
   - Include: Test Code, Description, Steps, Expected Result, Priority

2. **Update corresponding `qa/{role}/test-plan.md`**
   - Add feature area or update existing section
   - Cross-reference test code to PRD (e.g., `wiki/admin/dashboard.md`)

3. **Run `/qa-generate-tests`**
   - Skill auto-generates `.php` files from Excel
   - New test cases appear in `app/tests/BrowserQA/{Role}/`

4. **Add entry to this changelog**
   - Record test code range, date, feature areas, app version
   - Update version number if this is a significant addition (e.g., v1.0.5 → v1.1.0)

5. **Run tests & document results**
   ```bash
   /qa-{role}
   # Results in: qa/reports/{role}-YYYY-MM-DD-HHMM.md
   ```

6. **Commit both files**
   - `qa/LD-Expert-QA.xlsx` (updated test plan)
   - `qa/CHANGELOG.md` (this file)

---

## Notes

- **Changelog scope:** QA test additions only, not app feature releases (use `app/CHANGELOG.md` for that)
- **Retention:** All entries kept for audit trail; do not delete
- **Test code format:** `TC-{ROLE}{NUMBER}` (e.g., TC-A001, TC-T015)
- **Date format:** ISO 8601 (YYYY-MM-DD)
- **Status indicators:**
  - ✅ Passing / Complete
  - ⏳ In Progress
  - ⚠️ Blocked / Known Issues
  - ❌ Deprecated / Removed

---

## See Also

- [`qa/LD-Expert-QA.xlsx`](./LD-Expert-QA.xlsx) — Master test plan (Excel)
- [`qa/admin/test-plan.md`](./admin/test-plan.md) — Admin feature areas
- [`qa/therapist/test-plan.md`](./therapist/test-plan.md) — Therapist feature areas
- [`qa/student/test-plan.md`](./student/test-plan.md) — Student feature areas
- [`qa/finance/test-plan.md`](./finance/test-plan.md) — Finance feature areas
- [`qa/SETUP.md`](./SETUP.md) — QA framework setup & dependencies
- [`app/tests/BrowserQA/`](../app/tests/BrowserQA/) — Generated Dusk test files
