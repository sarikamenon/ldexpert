# Student Management Workflow Change

## Overview

This document explains the architectural change from therapist-managed students to SSA-based student management.

---

## OLD WORKFLOW (Being Removed)

```
┌─────────────┐
│  Therapist  │
└──────┬──────┘
       │ Direct CRUD
       ▼
┌─────────────────┐
│ Create Student  │ ← Therapist fills form
├─────────────────┤
│ - Personal Info │
│ - Contact Info  │
│ - Parent Info   │
│ - School        │
└────────┬────────┘
         │
         ▼
┌─────────────────────┐
│ Student Created     │
│ + User Account      │
│ + Student Profile   │
│ + Auto-assigned to  │
│   creating therapist│
└─────────────────────┘
```

**Problems with Old Workflow:**

- ❌ Therapists shouldn't manage student accounts directly
- ❌ No formal SSA (Student Service Agreement) process
- ❌ No administrative oversight
- ❌ Inconsistent data entry
- ❌ Compliance concerns

---

## NEW WORKFLOW (SSA-Based)

```
┌─────────────┐
│    Admin    │
└──────┬──────┘
       │
       ▼
┌─────────────────────┐
│  Create SSA Record  │ ← Admin creates SSA first
├─────────────────────┤
│ - School            │
│ - Student Details   │
│ - Service Agreement │
│ - Parent/Guardian   │
└──────┬──────────────┘
       │
       ▼
┌─────────────────────┐
│ Student Account     │
│ Created via SSA     │
└──────┬──────────────┘
       │
       ▼
┌─────────────────────┐
│ Admin Assigns       │
│ Student → Therapist │
└──────┬──────────────┘
       │
       ▼
┌─────────────────────┐
│  Therapist Sees     │
│  Assigned Student   │ ← READ ONLY
├─────────────────────┤
│ Can:                │
│ ✓ View student info │
│ ✓ Create schedules  │
│ ✓ Write session notes│
│                     │
│ Cannot:             │
│ ✗ Create students   │
│ ✗ Edit student info │
│ ✗ Delete students   │
└─────────────────────┘
```

**Benefits of New Workflow:**

- ✅ Centralized admin control
- ✅ SSA compliance from start
- ✅ Proper documentation workflow
- ✅ Audit trail for student creation
- ✅ Consistent data quality
- ✅ Separation of concerns

---

## What Changes for Each Role

### 👨‍💼 Admin Users

**NEW Capabilities:**

- Create and manage SSA records
- Create student accounts through SSA process
- Assign students to therapists
- Manage student-therapist relationships
- Full student CRUD operations

### 🧑‍⚕️ Therapist Users

**REMOVED:**

- ❌ Create new students
- ❌ Edit student profile information
- ❌ Delete or deactivate students

**RETAINED/NEW:**

- ✅ View assigned students (read-only)
- ✅ Create schedules for assigned students
- ✅ Write session notes
- ✅ Track student progress
- ✅ Communicate with parents (future)

### 👨‍👩‍👧 Student/Parent Users

**No Changes:**

- Student portal access (future feature)
- View schedules and session notes
- Communication with therapists

---

## Technical Impact

### Removed Components

| Component Type | Files Removed                                 | Count |
| -------------- | --------------------------------------------- | ----- |
| Controllers    | `Therapist\StudentController`                 | 1     |
| Requests       | `StoreStudentRequest`, `UpdateStudentRequest` | 2     |
| Policies       | `StudentProfilePolicy`                        | 1     |
| DTOs           | Student profile DTOs                          | 2-3   |
| Views          | `therapist/students/*`                        | 4     |
| JavaScript     | `students-index.js`                           | 1     |
| Tests          | Therapist student tests                       | 6     |
| Routes         | `therapist.students.*`                        | 8     |

### Preserved Components

| Component Type            | Purpose                         |
| ------------------------- | ------------------------------- |
| `StudentProfile` model    | Core student data (used by SSA) |
| `User` model              | Authentication & relationships  |
| `therapist_student` pivot | Assignment tracking             |
| Database migrations       | Schema definitions              |
| Factories                 | Testing support                 |

---

## Migration Timeline

### Phase 1: Removal (This Plan)

- Remove therapist student CRUD
- Clean up routes, views, controllers
- Remove tests and documentation

### Phase 2: SSA Implementation (Next)

- Create SSA module
- Admin student management
- Student-therapist assignment system

### Phase 3: Therapist Integration

- Read-only student list for therapists
- Integration with scheduling
- Integration with session notes

### Phase 4: Student Portal

- Student authentication
- Schedule viewing
- Progress tracking

---

## Database Schema (Unchanged)

```sql
-- These tables remain intact and are reused by SSA workflow

users
├── id
├── email
├── role (student, therapist, admin, parent)
└── status (active, inactive)

student_profiles
├── id
├── user_id (FK → users)
├── parent_id (FK → users)
├── first_name, middle_name, last_name
├── date_of_birth
├── grade_level
├── school, id_number
├── timezone, gender
├── address, city, state, zip_code
├── parent_guardian_name, email, phone
└── timestamps, deleted_at

therapist_student (pivot)
├── therapist_id (FK → users)
├── student_id (FK → users)
├── assigned_at
├── status
└── timestamps
```

---

## URLs & Routes

### ❌ REMOVED (404 after change)

```
GET    /therapist/students
GET    /therapist/students/create
POST   /therapist/students
GET    /therapist/students/{id}
GET    /therapist/students/{id}/edit
PATCH  /therapist/students/{id}
PATCH  /therapist/students/{id}/status/activate
PATCH  /therapist/students/{id}/status/deactivate
```

### ✅ FUTURE SSA Routes (To Be Implemented)

```
Admin:
GET    /admin/ssa
POST   /admin/ssa
GET    /admin/ssa/{id}/assign-therapist
POST   /admin/ssa/{id}/assign-therapist

GET    /admin/students
GET    /admin/students/{id}
PATCH  /admin/students/{id}

Therapist (Read-Only):
GET    /therapist/my-students
GET    /therapist/my-students/{id}
```

---

## Developer Checklist

Before considering this change complete:

- [ ] All files from removal list are deleted
- [ ] Routes updated and verified
- [ ] Navigation menus updated
- [ ] Vite config updated
- [ ] All tests pass
- [ ] No console errors on therapist pages
- [ ] Documentation updated
- [ ] Team notified of breaking changes
- [ ] SSA module requirements documented
- [ ] Database verified intact

---

## Questions & Answers

**Q: What happens to existing students in the database?**
A: They remain intact. The SSA module will need to handle existing students or provide a migration path.

**Q: Can therapists still see their assigned students?**
A: Yes, but only through the new read-only view (to be implemented in SSA phase).

**Q: What if a therapist needs to update student information?**
A: They must request changes through admin, maintaining proper oversight.

**Q: Are any automated tests affected?**
A: Yes, all therapist student CRUD tests are removed. New tests will be created for SSA workflow.

**Q: Is this reversible?**
A: Yes, via git history, but it's designed to be a permanent architectural improvement.

---

## Related Documents

- `remove-student-crud.plan.md` - Detailed removal implementation plan
- `app/wiki/admin/ssa.md` - SSA module specification (if exists)
- `app/wiki/admin/students.md` - Admin student management spec
- `admin.plan.md` - Pattern reference for admin CRUD modules

---

## Contact & Support

For questions about this change:

- Technical Lead: [Architecture decisions]
- Product Owner: [Workflow & requirements]
- Development Team: [Implementation support]
