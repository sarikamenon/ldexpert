# How to Create Tests Based on Your Flow

**Your Flow:**
```
Admin Login → Create School → Create Contract → Create Therapist → 
Create Student → Create SSA → Assign Therapist → 
Therapist Login → Create Schedule → Create Session Log → Submit Session →
Admin Login → Approve Session → Generate Invoice → Generate Therapist Bill
```

**How to Test It:** Create one test per step that verifies it works.

---

## 🎯 Mapping Flow to Tests

### **Flow Step → Test Example**

```
Step: Admin Login
Test: test_admin_can_login()
      Verify: Admin can login and see dashboard

Step: Create School
Test: test_admin_can_create_school()
      Verify: School created in database

Step: Create Contract
Test: test_admin_can_add_services_to_school()
      Verify: Services attached to school

Step: Create Therapist
Test: test_admin_can_create_therapist()
      Verify: Therapist user created

Step: Create Student
Test: test_admin_can_create_student()
      Verify: Student user created and linked to school

Step: Create SSA
Test: test_admin_can_create_ssa()
      Verify: SSA created with all fields

Step: Assign Therapist
Test: test_admin_can_assign_therapist_to_ssa()
      Verify: Therapist linked to SSA

Step: Therapist Login
Test: test_therapist_can_login()
      Verify: Therapist sees dashboard

Step: Create Schedule
Test: test_therapist_can_create_schedule()
      Verify: Schedule created

Step: Create Session Log
Test: test_therapist_can_create_session_log()
      Verify: Session log created with all fields

Step: Submit Session
Test: test_therapist_can_submit_session()
      Verify: Session status = Submitted

Step: Admin Approve
Test: test_admin_can_approve_session()
      Verify: Session status = Approved, served_minutes incremented

Step: Generate Invoice
Test: test_admin_can_generate_invoice()
      Verify: Invoice created and shows correct amount

Step: Generate Bill
Test: test_admin_can_generate_therapist_bill()
      Verify: Bill created and shows therapist hours
```

---

## 📝 Example: How to Write Each Test

### **Format: Pest Framework (Laravel Standard)**

This is the format Laravel uses. Simple and readable.

---

### **Test 1: Admin Login**

**File:** `tests/Feature/Admin/AuthenticationTest.php`

```php
<?php declare(strict_types=1);

namespace Tests\Feature\Admin;

use Tests\TestCase;
use App\Models\User;

class AuthenticationTest extends TestCase
{
    /** @test */
    public function admin_can_login()
    {
        // Setup: Create admin user
        $admin = User::factory()->admin()->create([
            'email' => 'admin@example.com',
            'password' => bcrypt('password123'),
        ]);

        // Action: Login
        $response = $this->post('/login', [
            'email' => 'admin@example.com',
            'password' => 'password123',
        ]);

        // Verify: Redirected to dashboard
        $response->assertRedirect('/admin/dashboard');
        
        // Verify: Admin is authenticated
        $this->assertAuthenticatedAs($admin);
    }
}
```

---

### **Test 2: Create School**

**File:** `tests/Feature/Admin/SchoolManagementTest.php`

```php
<?php declare(strict_types=1);

namespace Tests\Feature\Admin;

use Tests\TestCase;
use App\Models\User;
use App\Models\School;

class SchoolManagementTest extends TestCase
{
    /** @test */
    public function admin_can_create_school()
    {
        // Setup: Create and login admin
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        // Action: Create school
        $response = $this->post('/admin/schools', [
            'name' => 'Springfield Elementary',
            'address' => '123 Main St',
            'timezone' => 'America/New_York',
            'status' => 'active',
        ]);

        // Verify: Redirected
        $response->assertRedirect();

        // Verify: School created in database
        $this->assertDatabaseHas('schools', [
            'name' => 'Springfield Elementary',
        ]);

        // Verify: Can retrieve it
        $school = School::where('name', 'Springfield Elementary')->first();
        $this->assertNotNull($school);
        $this->assertEquals('123 Main St', $school->address);
    }
}
```

---

### **Test 3: Add Services to School (Contract)**

**File:** `tests/Feature/Admin/SchoolContractTest.php`

```php
<?php declare(strict_types=1);

namespace Tests\Feature\Admin;

use Tests\TestCase;
use App\Models\User;
use App\Models\School;
use App\Models\Service;

class SchoolContractTest extends TestCase
{
    /** @test */
    public function admin_can_add_services_to_school()
    {
        // Setup
        $admin = User::factory()->admin()->create();
        $school = School::factory()->create();
        $service = Service::factory()->create(['name' => 'Speech Therapy']);
        $this->actingAs($admin);

        // Action: Attach service to school
        $response = $this->post("/admin/schools/{$school->id}/services", [
            'service_id' => $service->id,
            'rate' => 50.00,
        ]);

        // Verify: Service attached
        $this->assertDatabaseHas('school_service', [
            'school_id' => $school->id,
            'service_id' => $service->id,
            'rate' => 50.00,
        ]);

        // Verify: Can retrieve it
        $this->assertTrue(
            $school->services()->where('service_id', $service->id)->exists()
        );
    }
}
```

---

### **Test 4: Create Therapist**

**File:** `tests/Feature/Admin/TherapistManagementTest.php`

```php
<?php declare(strict_types=1);

namespace Tests\Feature\Admin;

use Tests\TestCase;
use App\Models\User;

class TherapistManagementTest extends TestCase
{
    /** @test */
    public function admin_can_create_therapist()
    {
        // Setup
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        // Action: Create therapist
        $response = $this->post('/admin/therapists', [
            'name' => 'Dr. Sarah Chen',
            'email' => 'sarah@example.com',
            'role' => 'therapist',
        ]);

        // Verify: Created in database
        $this->assertDatabaseHas('users', [
            'name' => 'Dr. Sarah Chen',
            'email' => 'sarah@example.com',
            'role' => 'therapist',
        ]);

        // Verify: Can retrieve it
        $therapist = User::where('email', 'sarah@example.com')->first();
        $this->assertNotNull($therapist);
        $this->assertEquals('therapist', $therapist->role);
    }
}
```

---

### **Test 5: Create Student**

**File:** `tests/Feature/Admin/StudentManagementTest.php`

```php
<?php declare(strict_types=1);

namespace Tests\Feature\Admin;

use Tests\TestCase;
use App\Models\User;
use App\Models\School;

class StudentManagementTest extends TestCase
{
    /** @test */
    public function admin_can_create_student_linked_to_school()
    {
        // Setup
        $admin = User::factory()->admin()->create();
        $school = School::factory()->create();
        $this->actingAs($admin);

        // Action: Create student
        $response = $this->post('/admin/students', [
            'name' => 'Alice Johnson',
            'email' => 'alice@example.com',
            'school_id' => $school->id,
            'role' => 'student',
        ]);

        // Verify: Student created
        $this->assertDatabaseHas('users', [
            'name' => 'Alice Johnson',
            'email' => 'alice@example.com',
        ]);

        // Verify: Linked to school
        $student = User::where('email', 'alice@example.com')->first();
        $profile = $student->studentProfile;
        $this->assertEquals($school->id, $profile->school_id);
    }
}
```

---

### **Test 6: Create SSA**

**File:** `tests/Feature/Admin/SSAManagementTest.php`

```php
<?php declare(strict_types=1);

namespace Tests\Feature\Admin;

use Tests\TestCase;
use App\Models\User;
use App\Models\School;
use App\Models\Service;
use App\Models\ServiceSupportAgreement;

class SSAManagementTest extends TestCase
{
    /** @test */
    public function admin_can_create_ssa()
    {
        // Setup
        $admin = User::factory()->admin()->create();
        $school = School::factory()->create();
        $student = User::factory()->student()->create();
        $service = Service::factory()->create();
        $this->actingAs($admin);

        // Action: Create SSA
        $response = $this->post('/admin/ssas', [
            'student_id' => $student->id,
            'school_id' => $school->id,
            'service_id' => $service->id,
            'start_date' => '2026-01-15',
            'end_date' => '2026-06-15',
            'frequency_per_week' => 2,
            'session_minutes' => 30,
            'authorized_minutes' => 1200, // 20 hours
        ]);

        // Verify: SSA created with all fields
        $ssa = ServiceSupportAgreement::where('student_id', $student->id)->first();
        $this->assertNotNull($ssa);
        $this->assertEquals($school->id, $ssa->school_id);
        $this->assertEquals($service->id, $ssa->service_id);
        $this->assertEquals(1200, $ssa->authorized_minutes);
        $this->assertEquals(0, $ssa->served_minutes); // Initially 0
    }
}
```

---

### **Test 7: Assign Therapist to SSA**

**File:** `tests/Feature/Admin/TherapistAssignmentTest.php`

```php
<?php declare(strict_types=1);

namespace Tests\Feature\Admin;

use Tests\TestCase;
use App\Models\User;
use App\Models\ServiceSupportAgreement;

class TherapistAssignmentTest extends TestCase
{
    /** @test */
    public function admin_can_assign_therapist_to_ssa()
    {
        // Setup
        $admin = User::factory()->admin()->create();
        $therapist = User::factory()->therapist()->create();
        $ssa = ServiceSupportAgreement::factory()->create([
            'assigned_therapist_id' => null, // Initially unassigned
        ]);
        $this->actingAs($admin);

        // Action: Assign therapist
        $response = $this->patch("/admin/ssas/{$ssa->id}", [
            'assigned_therapist_id' => $therapist->id,
        ]);

        // Verify: Therapist assigned
        $ssa->refresh();
        $this->assertEquals($therapist->id, $ssa->assigned_therapist_id);
    }
}
```

---

### **Test 8: Therapist Can Log Session**

**File:** `tests/Feature/Therapist/SessionLoggingTest.php`

```php
<?php declare(strict_types=1);

namespace Tests\Feature\Therapist;

use Tests\TestCase;
use App\Models\User;
use App\Models\ServiceSupportAgreement;
use App\Models\SessionLog;

class SessionLoggingTest extends TestCase
{
    /** @test */
    public function therapist_can_create_session_log()
    {
        // Setup
        $therapist = User::factory()->therapist()->create();
        $ssa = ServiceSupportAgreement::factory()
            ->for($therapist, 'assignedTherapist')
            ->create();
        $this->actingAs($therapist);

        // Action: Create session log
        $response = $this->post('/therapist/session-logs', [
            'ssa_id' => $ssa->id,
            'session_date' => '2026-06-09',
            'start_time' => '09:00',
            'duration_minutes' => 30,
            'notes' => 'Student worked on articulation goals. Made good progress.',
            'outcome' => 'Services Administered',
        ]);

        // Verify: Session log created
        $this->assertDatabaseHas('session_logs', [
            'ssa_id' => $ssa->id,
            'duration_minutes' => 30,
            'status' => 'draft', // Initially draft
        ]);

        // Verify: Can retrieve it
        $session = SessionLog::where('ssa_id', $ssa->id)->first();
        $this->assertNotNull($session);
        $this->assertEquals('draft', $session->status);
    }
}
```

---

### **Test 9: Therapist Can Submit Session**

**File:** `tests/Feature/Therapist/SessionSubmissionTest.php`

```php
<?php declare(strict_types=1);

namespace Tests\Feature\Therapist;

use Tests\TestCase;
use App\Models\User;
use App\Models\SessionLog;

class SessionSubmissionTest extends TestCase
{
    /** @test */
    public function therapist_can_submit_session()
    {
        // Setup
        $therapist = User::factory()->therapist()->create();
        $session = SessionLog::factory()
            ->for($therapist, 'therapist')
            ->create(['status' => 'draft']);
        $this->actingAs($therapist);

        // Action: Submit session
        $response = $this->patch("/therapist/session-logs/{$session->id}", [
            'status' => 'submitted',
        ]);

        // Verify: Session status updated
        $session->refresh();
        $this->assertEquals('submitted', $session->status);
    }
}
```

---

### **Test 10: Admin Approves Session & Hours Increment**

**File:** `tests/Feature/Admin/SessionApprovalTest.php`

```php
<?php declare(strict_types=1);

namespace Tests\Feature\Admin;

use Tests\TestCase;
use App\Models\User;
use App\Models\SessionLog;

class SessionApprovalTest extends TestCase
{
    /** @test */
    public function admin_can_approve_session_and_hours_increment()
    {
        // Setup
        $admin = User::factory()->admin()->create();
        $session = SessionLog::factory()
            ->create(['status' => 'submitted', 'duration_minutes' => 30]);
        $ssa = $session->ssa;
        $ssa->update(['served_minutes' => 0]); // Start at 0
        $this->actingAs($admin);

        // Action: Approve session
        $response = $this->patch("/admin/session-logs/{$session->id}", [
            'status' => 'approved',
        ]);

        // Verify: Session approved
        $session->refresh();
        $this->assertEquals('approved', $session->status);

        // Verify: Hours incremented (THIS IS THE CRITICAL TEST)
        $ssa->refresh();
        $this->assertEquals(30, $ssa->served_minutes);
    }
}
```

---

### **Test 11: Generate Invoice**

**File:** `tests/Feature/Admin/InvoiceGenerationTest.php`

```php
<?php declare(strict_types=1);

namespace Tests\Feature\Admin;

use Tests\TestCase;
use App\Models\User;
use App\Models\School;
use App\Models\Invoice;

class InvoiceGenerationTest extends TestCase
{
    /** @test */
    public function admin_can_generate_invoice_for_school()
    {
        // Setup
        $admin = User::factory()->admin()->create();
        $school = School::factory()->create();
        // Assume sessions already logged and approved
        $this->actingAs($admin);

        // Action: Generate invoice
        $response = $this->post("/admin/invoices", [
            'school_id' => $school->id,
            'from_date' => '2026-06-01',
            'to_date' => '2026-06-30',
        ]);

        // Verify: Invoice created
        $this->assertDatabaseHas('invoices', [
            'school_id' => $school->id,
        ]);

        $invoice = Invoice::where('school_id', $school->id)->first();
        $this->assertNotNull($invoice);
    }
}
```

---

### **Test 12: Generate Therapist Bill**

**File:** `tests/Feature/Admin/TherapistBillingTest.php`

```php
<?php declare(strict_types=1);

namespace Tests\Feature\Admin;

use Tests\TestCase;
use App\Models\User;
use App\Models\TherapistBill;

class TherapistBillingTest extends TestCase
{
    /** @test */
    public function admin_can_generate_therapist_bill()
    {
        // Setup
        $admin = User::factory()->admin()->create();
        $therapist = User::factory()->therapist()->create();
        // Assume sessions already logged and approved
        $this->actingAs($admin);

        // Action: Generate bill
        $response = $this->post("/admin/therapist-bills", [
            'therapist_id' => $therapist->id,
            'from_date' => '2026-06-01',
            'to_date' => '2026-06-30',
        ]);

        // Verify: Bill created
        $this->assertDatabaseHas('therapist_bills', [
            'therapist_id' => $therapist->id,
        ]);

        $bill = TherapistBill::where('therapist_id', $therapist->id)->first();
        $this->assertNotNull($bill);
    }
}
```

---

## 🎯 Summary: How to Create Tests from Your Flow

**Step 1:** Map each flow step to a test
```
Flow step → Test function → Verify expected outcome
```

**Step 2:** Create test file
```
tests/Feature/[Role]/[FeatureTest].php
```

**Step 3:** Write test using this template
```php
/** @test */
public function description_of_what_it_tests()
{
    // Setup: Create necessary data
    $user = User::factory()->role()->create();
    
    // Action: Do the thing being tested
    $response = $this->post('/route', ['data' => 'value']);
    
    // Verify: Check it worked
    $this->assertDatabaseHas('table', ['field' => 'value']);
}
```

**Step 4:** Run tests
```bash
docker compose exec -T app php artisan pest
```

---

## 📁 File Structure for These Tests

```
tests/Feature/
├─ Admin/
│  ├─ AuthenticationTest.php
│  ├─ SchoolManagementTest.php
│  ├─ SchoolContractTest.php
│  ├─ TherapistManagementTest.php
│  ├─ StudentManagementTest.php
│  ├─ SSAManagementTest.php
│  ├─ TherapistAssignmentTest.php
│  ├─ SessionApprovalTest.php
│  ├─ InvoiceGenerationTest.php
│  └─ TherapistBillingTest.php
│
└─ Therapist/
   ├─ SessionLoggingTest.php
   └─ SessionSubmissionTest.php
```

---

## ✅ How to Run Tests

```bash
# Run all tests
docker compose exec -T app php artisan pest

# Run specific test file
docker compose exec -T app php artisan pest tests/Feature/Admin/SchoolManagementTest.php

# Run with verbose output
docker compose exec -T app php artisan pest --verbose

# Run and show which tests passed/failed
docker compose exec -T app php artisan pest tests/Feature/Admin/ --verbose
```

---

## 🎓 Key Points

1. **One test per flow step** - Each test verifies one thing
2. **Setup → Action → Verify** - Always follow this pattern
3. **Use factories** - `User::factory()->role()->create()`
4. **Test database state** - `$this->assertDatabaseHas(...)`
5. **Keep tests simple** - One assertion per test
6. **Run often** - Run tests after every feature

---

## ❓ Questions About Your Tests?

**Q: Should every test step require approval?**
A: No. Only critical steps like "approved sessions increment hours"

**Q: Do I need to test every validation rule?**
A: No. Just test happy path first (all data valid). Add validation tests later.

**Q: Do I write all tests at once?**
A: No. Write 1-2 critical tests first, make sure they pass, then add more.

**Q: What's most important to test?**
A: The approval workflow + hours increment. That's where bugs hide.

