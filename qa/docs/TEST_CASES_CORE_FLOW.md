# QA Test Cases - Core Business Flow

**Purpose:** Define test cases for the complete LD Expert flow to be executed via QA automation framework

**Complete Flow:**
```
Admin Login 
  ↓
Create School 
  ↓
Create Contract (Services) 
  ↓
Create Therapist 
  ↓
Create Student 
  ↓
Create SSA 
  ↓
Assign Therapist to SSA
  ↓
Therapist Login
  ↓
Create Schedule
  ↓
Log Session (Save as Draft)
  ↓
Submit Session (Status = Submitted)
  ↓
Admin Login (Back to Admin)
  ↓
Approve Session (Status = Approved, Hours Auto-Increment)
  ↓
Generate Invoice
  ↓
Generate Therapist Bill
```

**Test Case Organization:**
- **Admin Tests:** TC-A001 to TC-A011 (Admin setup, approval, billing)
- **Therapist Tests:** TC-T001 to TC-T005 (Therapist login, scheduling, session work)
- **E2E Tests:** TC-E001 (Complete end-to-end flow)

---

## Test Case Template Format

For `qa/LD-Expert-QA.xlsx`, use this format:

| TC-ID | Title | Module | Steps | Expected Result | Role | Priority |
|-------|-------|--------|-------|-----------------|------|----------|
| TC-A001 | Admin logs in | Authentication | 1. Navigate to /login, 2. Enter email, 3. Enter password, 4. Click Login | Dashboard displays with admin menu | Admin | P1 |
| TC-A002 | Create school | School Setup | 1. Click Schools, 2. Click Create, 3. Fill form, 4. Click Save | School appears in list | Admin | P1 |

---

## Test Cases by Role

---

## 👨‍💼 ADMIN TEST CASES (TC-A001 to TC-A011)

**Role:** Admin  
**Purpose:** Setup system, create data, approve sessions, generate billing

### **PHASE 1: ADMIN SETUP**

#### TC-A001: Admin Login
```
Title: Admin user can login to system
Module: Authentication
Steps:
  1. Navigate to http://localhost/login
  2. Enter email: admin@example.com
  3. Enter password: password
  4. Click "Login" button

Expected Result:
  - Redirected to /admin/dashboard
  - Admin menu visible
  - User name displayed in top right
  
Role: Admin
Priority: P1 (Critical)
```

---

#### TC-A002: Create School
```
Title: Admin can create a new school
Module: School Management
Steps:
  1. Click "Schools" in admin menu
  2. Click "Create School" button
  3. Fill "School Name": Springfield Elementary
  4. Fill "Address": 123 Main St
  5. Fill "City": Springfield
  6. Fill "State": IL
  7. Fill "Timezone": America/Chicago
  8. Click "Save" button

Expected Result:
  - Success message: "School created successfully"
  - New school appears in schools list
  - Can see: Springfield Elementary, 123 Main St
  
Role: Admin
Priority: P1 (Critical)
```

---

#### TC-A003: Create Contract (Add Services to School)
```
Title: Admin can add services to school contract
Module: School Contracts
Steps:
  1. Navigate to Schools
  2. Click "Springfield Elementary" school
  3. Click "Services" or "Add Service" tab
  4. Click "Add Service" button
  5. Select Service: "Speech Therapy"
  6. Fill Rate: 50.00
  7. Fill Effective Date: 2026-01-01
  8. Click "Save" button
  9. Repeat for "Occupational Therapy" at rate 60.00

Expected Result:
  - Services list shows:
    ✓ Speech Therapy - $50.00
    ✓ Occupational Therapy - $60.00
  - Both services linked to Springfield Elementary
  
Role: Admin
Priority: P1 (Critical)
```

---

#### TC-A004: Create Therapist User
```
Title: Admin can create a new therapist account
Module: User Management
Steps:
  1. Click "Therapists" in admin menu
  2. Click "Create Therapist" button
  3. Fill "Name": Dr. Sarah Chen
  4. Fill "Email": sarah.chen@example.com
  5. Fill "Password": SecurePass123!
  6. Fill "Phone": (555) 123-4567
  7. Click "Save" button

Expected Result:
  - Success message: "Therapist created successfully"
  - Dr. Sarah Chen appears in therapists list
  - Can see therapist profile
  
Role: Admin
Priority: P1 (Critical)
```

---

#### TC-A005: Create Student User
```
Title: Admin can create a new student linked to school
Module: User Management
Steps:
  1. Click "Students" in admin menu
  2. Click "Create Student" button
  3. Fill "Name": Alice Johnson
  4. Fill "Email": alice.johnson@example.com
  5. Select "School": Springfield Elementary
  6. Fill "Grade": 3rd
  7. Click "Save" button

Expected Result:
  - Success message: "Student created successfully"
  - Alice Johnson appears in students list
  - Linked to Springfield Elementary
  
Role: Admin
Priority: P1 (Critical)
```

---

#### TC-A006: Create Service Support Agreement (SSA)
```
Title: Admin can create a service support agreement
Module: SSA Management
Steps:
  1. Click "Service Agreements" in admin menu
  2. Click "Create Agreement" button
  3. Select "Student": Alice Johnson
  4. Select "School": Springfield Elementary
  5. Select "Service": Speech Therapy
  6. Fill "Start Date": 2026-01-15
  7. Fill "End Date": 2026-06-15
  8. Fill "Frequency per Week": 2
  9. Fill "Session Duration (minutes)": 30
  10. Fill "Total Authorized Hours": 20
  11. Click "Create" button

Expected Result:
  - Success message: "Agreement created successfully"
  - SSA appears in list showing:
    ✓ Student: Alice Johnson
    ✓ Service: Speech Therapy
    ✓ School: Springfield Elementary
    ✓ Duration: 20 hours
    ✓ Therapist: (Unassigned)
  
Role: Admin
Priority: P1 (Critical)
```

---

#### TC-A007: Assign Therapist to SSA
```
Title: Admin can assign therapist to service agreement
Module: SSA Management
Steps:
  1. Click "Service Agreements" in admin menu
  2. Find "Alice Johnson - Speech Therapy" SSA
  3. Click "Assign Therapist" button or pencil icon
  4. Select "Therapist": Dr. Sarah Chen
  5. Click "Assign" button

Expected Result:
  - SSA now shows "Therapist: Dr. Sarah Chen"
  - Therapist status: Active
  - Assignment date recorded
  
Role: Admin
Priority: P1 (Critical)
```

---

## 👨‍⚕️ THERAPIST TEST CASES (TC-T001 to TC-T005)

**Role:** Therapist  
**Purpose:** View assignments, create schedules, log sessions, submit for approval

### **PHASE 2: THERAPIST SERVICE DELIVERY**

#### TC-T001: Therapist Login
```
Title: Therapist can login to system
Module: Authentication
Steps:
  1. Logout if necessary
  2. Navigate to http://localhost/login
  3. Enter email: sarah.chen@example.com
  4. Enter password: SecurePass123!
  5. Click "Login" button

Expected Result:
  - Redirected to /therapist/dashboard
  - Therapist menu visible
  - Shows "Dr. Sarah Chen" in top right
  - Dashboard displays assigned students
  
Role: Therapist
Priority: P1 (Critical)
```

---

#### TC-T002: Therapist Views Assigned SSAs
```
Title: Therapist can see their assigned service agreements
Module: Dashboard
Steps:
  1. Login as therapist (Dr. Sarah Chen)
  2. Click "My Service Agreements" or "SSAs"
  3. Look for Alice Johnson in the list

Expected Result:
  - SSA list displays:
    ✓ Student: Alice Johnson
    ✓ Service: Speech Therapy
    ✓ School: Springfield Elementary
    ✓ Authorized: 20 hours
    ✓ Served: 0 hours
  
Role: Therapist
Priority: P1 (Critical)
```

---

#### TC-T003: Therapist Creates Schedule
```
Title: Therapist can create schedule for student
Module: Scheduling
Steps:
  1. Login as therapist
  2. Click "Schedule" in menu
  3. Click "Create Schedule"
  4. Select "Student": Alice Johnson
  5. Select "School": Springfield Elementary
  6. Select "Service": Speech Therapy
  7. Fill "Start Date": 2026-06-01
  8. Fill "Frequency": Weekly, Monday 9:00 AM
  9. Click "Save"

Expected Result:
  - Schedule created
  - Calendar shows session at Monday 9:00 AM
  - Can see recurring pattern
  
Role: Therapist
Priority: P2 (Important)
```

---

#### TC-T004: Therapist Logs Session
```
Title: Therapist can log a therapy session with all details
Module: Session Logging
Steps:
  1. Login as therapist
  2. Click "Session Logs" in menu
  3. Click "Create Session Log"
  4. Verify form displays all required fields:
     ✓ Student (dropdown with assigned students)
     ✓ SSA (Service Support Agreement dropdown)
     ✓ Service (service type dropdown)
     ✓ Session Date (date field)
     ✓ Start Time (time field)
     ✓ Duration (minutes) (numeric field)
     ✓ End Time (auto-calculated based on start time + duration)
     ✓ Notes (text area, minimum 20 characters required)
     ✓ Session Outcome (dropdown)
  5. Select "Student": Alice Johnson
  6. Select "SSA": Alice Johnson - Speech Therapy (SSA #147)
  7. Verify Service auto-populates: "Speech Therapy"
  8. Fill "Session Date": 2026-06-09
  9. Fill "Start Time": 09:00 AM
  10. Fill "Duration (minutes)": 30
  11. Verify "End Time" auto-calculates: 09:30 AM
  12. Fill "Notes": "Worked on articulation. Good progress on /s/ sounds."
  13. Select "Session Outcome": Services Administered
  14. Click "Create"

Expected Result:
  - Success message: "Session log created successfully"
  - Session appears in list with Status: "Draft"
  - Session can be edited (pencil icon visible)
  - Shows in list: Date, Entry Date, Student, Therapist & Service, School Amount, Therapist Amount, Status: Draft
  - All entered data is preserved correctly
  
Role: Therapist
Priority: P1 (Critical)
```

---

#### TC-T005: Therapist Edits Draft Session
```
Title: Therapist can edit session before submitting
Module: Session Logging
Steps:
  1. Login as therapist
  2. Click "Session Logs"
  3. Find session in Draft status
  4. Click "Edit" button (pencil icon) on the session row
  5. Session detail page opens showing all fields in edit mode:
     ✓ All form fields are editable
     ✓ Student, SSA, Service, Date, Start Time, Duration, Notes, Outcome can be modified
  6. Change one field (e.g., Duration from 30 to 35 minutes)
  7. Verify End Time auto-recalculates (09:35 AM)
  8. Click "Save" button

Expected Result:
  - Changes saved successfully
  - Session remains in "Draft" status (not submitted)
  - Updated data is displayed in the Session Logs list
  - Session can still be edited or submitted
  - Edit button still available
  
Role: Therapist
Priority: P1 (Critical)
```

---

#### TC-T006: Therapist Submits Session
```
Title: Therapist can submit session for approval
Module: Session Logging
Steps:
  1. Login as therapist
  2. Click "Session Logs"
  3. Find the session log in Draft status (e.g., Alice Johnson, June 9, 2026, 9:00 AM)
  4. Verify session row shows Status badge: "Draft"
  5. Click "Submit" button (up arrow icon on the right side of the row)
  6. Confirmation modal dialog appears with:
     ✓ Title: "Submit session?"
     ✓ Message: "Submit this session for approval"
     ✓ Cancel button (red)
     ✓ Yes button (blue)
  7. Click "Yes" button to confirm submission

Expected Result:
  - Confirmation modal closes
  - Success message displays: "Session submitted successfully" (or similar)
  - Session status changes from "Draft" to "Submitted"
  - Status badge now shows: "Submitted"
  - Session is locked (can no longer edit)
  - Submit/Edit buttons no longer available for this session
  - Session remains in the Session Logs list
  
Role: Therapist
Priority: P1 (Critical)
```

---

### **PHASE 3: ADMIN APPROVAL & BILLING** (Back to Admin)

#### TC-A008: Admin Reviews Submitted Sessions
```
Title: Admin can view submitted sessions waiting for approval
Module: Session Approval
Steps:
  1. Login as admin
  2. Click "Session Logs" in menu
  3. Filter: Status = "Submitted"
  4. Look for Alice Johnson session from 2026-06-09

Expected Result:
  - Session visible in list
  - Shows: Date, Time, Duration, Student, Therapist, Status: "Submitted"
  - Has "Approve" button visible
  
Role: Admin
Priority: P1 (Critical)
```

---

#### TC-A009: Admin Approves Session & Hours Auto-Increment
```
Title: Admin can approve session and hours auto-increment correctly
Module: Session Approval
Steps:
  1. Login as admin
  2. Click "Session Logs" in menu
  3. Filter or find submitted sessions (Status = "Submitted")
  4. Locate Alice Johnson's session from 2026-06-09 (9:00 AM, 30 minutes)
  5. Click on session row to open detail view
  6. Verify session details display:
     ✓ Student: Alice Johnson
     ✓ Service: Speech Therapy
     ✓ Date: 2026-06-09
     ✓ Time: 9:00 AM - 9:30 AM
     ✓ Duration: 30 minutes
     ✓ Status: Submitted
  7. Click "Approve" button
  8. Confirmation dialog appears: "Approve session?"
  9. Click "Yes, Approve" button
  10. **VERIFY HOURS AUTO-INCREMENT:**
      - Navigate to SSAs list
      - Find Alice Johnson's SSA (Speech Therapy)
      - Check "Served" hours field:
        BEFORE approval: 0.00 hours
        AFTER approval: 0.50 hours (auto-incremented from 30 minutes)

Expected Result:
  - Session status changes from "Submitted" to "Approved"
  - Confirmation message shown: "Session approved successfully"
  - **CRITICAL - Hours Auto-Increment:** 
    ✓ SSA.served_minutes increments by 30 (5 minutes = 0.5 hours)
    ✓ SSA "Served" field updates automatically
    ✓ Update is real-time (visible immediately)
  - Session detail shows "Approved" status
  - Session is locked (no further edits possible)
  
Role: Admin
Priority: P1 (Critical - Hours tracking is fundamental)
```

---

#### TC-A010: Generate Invoice for School
```
Title: Admin can generate invoice for school services
Module: Billing
Steps:
  1. Login as admin
  2. Click "Invoices" in menu
  3. Click "Generate Invoice"
  4. Select "School": Springfield Elementary
  5. Fill "From Date": 2026-06-01
  6. Fill "To Date": 2026-06-30
  7. Click "Generate"

Expected Result:
  - Invoice created
  - Shows:
    ✓ School: Springfield Elementary
    ✓ Period: June 1-30, 2026
    ✓ Services delivered (Speech Therapy: 0.5 hours @ $50 = $25)
    ✓ Total amount due
  - Invoice number generated (e.g., INV-2026-0001)
  
Role: Admin
Priority: P1 (Critical)
```

---

#### TC-A011: Generate Therapist Bill
```
Title: Admin can generate bill for therapist hours worked
Module: Billing
Steps:
  1. Login as admin
  2. Click "Therapist Bills" in menu
  3. Click "Generate Bill"
  4. Select "Therapist": Dr. Sarah Chen
  5. Fill "From Date": 2026-06-01
  6. Fill "To Date": 2026-06-30
  7. Click "Generate"

Expected Result:
  - Bill created
  - Shows:
    ✓ Therapist: Dr. Sarah Chen
    ✓ Period: June 1-30, 2026
    ✓ Sessions approved: 1
    ✓ Hours worked: 0.5
    ✓ Rate per hour: (varies by service/contract)
    ✓ Total amount owed to therapist
  - Bill number generated
  
Role: Admin
Priority: P1 (Critical)
```

---

## 🔄 END-TO-END TEST CASES (TC-E001)

**Role:** Multi-role (Admin + Therapist)  
**Purpose:** Verify complete business flow from setup to billing

#### TC-E001: Complete Flow Execution
```
Title: Complete end-to-end flow execution with data verification
Module: End-to-End
Steps:
  ADMIN SETUP:
    1. Admin login
    2. Create school (Springfield Elementary)
    3. Add services to school (Speech Therapy @ $50)
    4. Create therapist (Dr. Sarah Chen)
    5. Create student (Alice Johnson) linked to school
    6. Create SSA (Alice → Speech Therapy, 20 hours)
    7. Assign therapist (Dr. Sarah) to SSA
  
  THERAPIST WORK:
    8. Therapist (Dr. Sarah) login
    9. View assigned SSA (Alice Johnson - Speech Therapy)
    10. Create schedule (Weekly, Monday 9:00 AM)
    11. Log session (June 9, 2026, 9:00 AM, 30 minutes) → Draft status
    12. Edit draft session (verify changes save correctly)
    13. Submit session for approval via confirmation modal → Submitted status
  
  ADMIN APPROVAL & BILLING:
    13. Admin login
    14. Review submitted sessions
    15. Approve session
    16. Verify hours auto-increment (0 → 0.5)
    17. Generate invoice for school
    18. Generate therapist bill
    19. Verify data integrity across entire flow

Expected Result:
  ✓ School created with services
  ✓ Therapist and student created and linked
  ✓ SSA created with correct dates (Jan 15 - Jun 15, 20 hours authorized)
  ✓ Therapist assigned to SSA successfully
  ✓ Therapist sees assigned schools and SSAs on dashboard
  ✓ Session logged as Draft with all form fields:
    - Student, SSA, Service, Date, Start Time (9:00 AM)
    - Duration (30 minutes), End Time (auto-calc 9:30 AM)
    - Notes, Outcome (Services Administered)
  ✓ Draft session can be edited before submission
  ✓ Session submitted via confirmation modal → Status changes to Submitted
  ✓ Admin reviews submitted sessions and approves it
  ✓ **CRITICAL:** Hours auto-increment:
    - Before approval: Served = 0.00 hours
    - After approval: Served = 0.50 hours (30 min auto-added)
  ✓ Invoice generated with correct amount ($25 = 0.5 hrs × $50/hr)
  ✓ Therapist bill generated with 0.5 hours worked
  ✓ All data preserved across workflow
  ✓ No data loss or corruption
  ✓ Complete audit trail maintained throughout
  
Role: Admin + Therapist (Multi-role)
Priority: P1 (Critical - Complete system test)
```

---

## Summary

**Test Case Distribution:**
```
ADMIN Tests:       11 test cases (TC-A001 to TC-A011)
├─ Admin Setup:      7 cases
├─ Admin Approval:    3 cases
└─ Billing:          1 case (Generate Invoice, Bill)

THERAPIST Tests:    6 test cases (TC-T001 to TC-T006)
├─ Authentication:   1 case
├─ Dashboard:        1 case
├─ Scheduling:       1 case
└─ Session Work:     3 cases (Log, Edit Draft, Submit)

E2E Tests:          1 test case (TC-E001)
└─ Complete flow:    1 comprehensive case

TOTAL:             18 test cases (including Edit Draft Session)
```

**Updates from Therapist Screenshots:**
- ✅ TC-T004: Session logging with all form fields detailed
- ✅ TC-T005: NEW - Edit Draft session before submitting
- ✅ TC-T006: Session submission with confirmation modal
- ✅ TC-A009: Enhanced with specific hours auto-increment verification

**Coverage:**
- ✅ Complete flow from school creation to final billing
- ✅ All critical business steps covered
- ✅ All user roles tested (Admin, Therapist)
- ✅ E2E verification of system integrity
- ✅ Hours tracking and auto-increment verified

**All test cases are Priority P1 (Critical)** - this is the core business flow

**How to use:**
1. Enter test cases into `qa/LD-Expert-QA.xlsx` (organized by role)
2. Run `/qa-generate-tests` to generate PHP test files
3. Execute with `php artisan dusk tests/BrowserQA/`
4. View reports in `qa/reports/`
