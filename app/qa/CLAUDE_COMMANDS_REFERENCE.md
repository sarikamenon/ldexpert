# Claude Commands Reference

Complete list of available Claude Code commands and skills for the LD Expert Bird project.

---

## QA Testing Commands

### `/qa` - Developer Code Quality Pipeline
**What it does:** Run Pint + PHPStan + Pest code quality checks

```bash
/qa

Runs:
├─ Pint (code formatting)
├─ PHPStan (static analysis, Level 8)
└─ Pest (unit/feature tests)

Output:
├─ Format issues
├─ Type errors
└─ Test failures
```

**Use when:** Before committing, to ensure code quality

---

### `/qa-smoke` - Quick Sanity Check
**What it does:** Run fastest smoke tests across all roles (~3 minutes)

```bash
/qa-smoke

Tests:
├─ Admin login
├─ Therapist login
├─ Student login
├─ Home redirect
└─ Role isolation

Pass Rate: 11/11 ✅
```

**Use when:** Quickly verify app is alive, before deeper testing

---

### `/qa-admin` - Admin Role Tests
**What it does:** Run all admin workflow tests

```bash
/qa-admin

Tests:
├─ Schools (create, edit, delete)
├─ Students (create, enroll)
├─ Therapists (create, assign)
├─ SSAs (create, manage)
├─ Session logs (approve)
├─ Invoices (create, send)
├─ Payments (record)
└─ Reports (view)

Duration: 10-15 minutes
```

**Use when:** Testing admin functionality

---

### `/qa-therapist` - Therapist Role Tests
**What it does:** Run all therapist workflow tests

```bash
/qa-therapist

Tests:
├─ Login
├─ Schedule management
├─ Calendar view
├─ Session logs
├─ Student access
└─ Paystubs

Duration: 8-10 minutes
```

**Use when:** Testing therapist features

---

### `/qa-student` - Student Role Tests
**What it does:** Run all student workflow tests

```bash
/qa-student

Tests:
├─ Login
├─ Dashboard
├─ Schedule view
├─ Goals tracking
└─ Messages

Duration: 5-8 minutes
```

**Use when:** Testing student portal

---

### `/qa-finance` - Finance/Billing Tests
**What it does:** Run all financial workflow tests

```bash
/qa-finance

Tests:
├─ Invoice creation
├─ Payment recording
├─ Therapist billing
├─ Ledger entries
└─ Reconciliation

Duration: 5-8 minutes
```

**Use when:** Testing billing workflows

---

### `/qa-e2e` - End-to-End Cross-Role Tests
**What it does:** Run cross-role integration tests

```bash
/qa-e2e

Tests:
├─ Student Journey
├─ Therapist Session to Billing
└─ Admin Audit Flow

Duration: 10-15 minutes
```

**Use when:** Testing complete workflows across roles

---

## Code & Architecture Commands

### `/feature` - Create Feature Branch
**What it does:** Create and set up a feature branch with conventions

```bash
/feature

Creates:
├─ Feature branch
├─ Conventional branch naming
└─ Setup tracking
```

---

### `/review` - Code Review
**What it does:** Review code changes for bugs and quality

```bash
/review

Checks:
├─ Correctness bugs
├─ Code reuse
├─ Simplification opportunities
└─ Efficiency
```

---

### `/deploy-check` - Pre-Deployment Verification
**What it does:** Verify branch is ready to deploy

```bash
/deploy-check

Checks:
├─ All tests passing
├─ No uncommitted changes
├─ No conflicts
└─ Ready for deployment
```

---

## QA Planning & Generation Commands

### `/qa-create-scenarios` - Generate Test Scenarios from Wiki
**What it does:** Read wiki PRDs and generate test scenarios into Excel

```bash
/qa-create-scenarios admin

Reads: app/wiki/admin/*.md
Writes:
├─ app/qa/admin/test-plan.md
├─ app/qa/admin/test-data.md
└─ Rows in app/qa/LD-Expert-QA.xlsx
```

**Trigger patterns:** "create test plan", "plan QA for", "write test scenarios"

---

### `/qa-generate-tests` - Generate Dusk Tests from Excel
**What it does:** Read Excel test cases and auto-generate PHP Dusk tests

```bash
/qa-generate-tests

Reads: app/qa/LD-Expert-QA.xlsx (all sheets)
Writes: app/tests/BrowserQA/{Role}/*.php

Generates:
├─ Admin tests (TC-A*)
├─ Therapist tests (TC-T*)
├─ Student tests (TC-S*)
├─ Finance tests (TC-F*)
└─ E2E tests (TC-E*)
```

**Use when:** Excel rows added/updated, need PHP test files

---

### `/qa-test-strategy` - QA Test Strategy
**What it does:** Plan QA testing strategy for a feature

```bash
/qa-test-strategy

Helps with:
├─ Test coverage planning
├─ Role matrix planning
├─ Edge case identification
├─ Data setup planning
└─ Success criteria definition
```

---

### `/qa-review-tests` - Review QA Test Cases
**What it does:** Review test cases for quality and coverage

```bash
/qa-review-tests

Reviews:
├─ Test case clarity
├─ Step precision
├─ Coverage completeness
├─ Precondition clarity
└─ Assertions validity
```

---

## Frontend & Blade Commands

### `/blade-component` - Create Blade Component
**What it does:** Generate a Blade component with proper structure

```bash
/blade-component

Creates:
├─ Component class
├─ Blade template
├─ Proper slots/props
└─ Type hints
```

---

### `/frontend-design` - Design Frontend Feature
**What it does:** Design frontend UI/UX for a feature

```bash
/frontend-design

Helps with:
├─ UI mockup
├─ Component breakdown
├─ Interaction design
├─ Accessibility
└─ Responsive design
```

---

## Laravel Commands

### `/laravel-scaffold` - Generate Laravel Scaffolding
**What it does:** Generate controllers, models, migrations, etc.

```bash
/laravel-scaffold

Generates:
├─ Models
├─ Controllers
├─ Migrations
├─ Form Requests
├─ Tests
└─ Routes
```

---

### `/hookify` - Convert to Hooks/Events
**What it does:** Refactor code to use Laravel events/hooks

```bash
/hookify

Converts:
├─ Direct calls → Events
├─ Logic → Listeners
└─ Tight coupling → Loose coupling
```

---

### `/phpstan-fixer` - Fix PHPStan Errors
**What it does:** Automatically fix PHPStan Level 8 errors

```bash
/phpstan-fixer

Fixes:
├─ Missing return types
├─ Undefined properties
├─ Type mismatches
├─ Argument type errors
└─ PHPDoc issues
```

---

## Utility Commands

### `/wiki-update` - Update Wiki
**What it does:** Update or create wiki documentation

```bash
/wiki-update

Updates:
├─ Feature PRDs
├─ API docs
├─ Architecture docs
└─ Test plans
```

---

### `/migrate-dt` - Migrate DataTable
**What it does:** Migrate DataTable from client-side to server-side

```bash
/migrate-dt

Handles:
├─ Selector migration
├─ Filtering logic
├─ Sorting setup
├─ Server endpoint creation
└─ Tests
```

**Trigger patterns:** "migrate datatable", "server-side table", "datatable is slow"

---

### `/copyreview` - Copy Review
**What it does:** Review copy/text content for clarity and consistency

```bash
/copyreview

Reviews:
├─ Clarity
├─ Tone
├─ Consistency
├─ Grammar
└─ Brand voice
```

---

### `/plugin-dev` - Plugin Development
**What it does:** Guide plugin development process

```bash
/plugin-dev

Helps with:
├─ Plugin architecture
├─ Hook integration
├─ Testing strategy
└─ Documentation
```

---

### `/playground` - Interactive Playground
**What it does:** Interactive testing environment

```bash
/playground

Use for:
├─ Quick testing
├─ Prototyping
├─ Exploration
└─ Experimentation
```

---

## Reference Commands

### `/claude-md-management` - Manage Claude.md
**What it does:** Help manage project documentation in CLAUDE.md

```bash
/claude-md-management

Manages:
├─ Project conventions
├─ Team guidelines
├─ Architecture standards
└─ Development rules
```

---

### `/claude-code-setup` - Claude Code Setup
**What it does:** Help set up Claude Code environment

```bash
/claude-code-setup

Helps with:
├─ Installation
├─ Configuration
├─ Settings
├─ Keybindings
└─ Extensions
```

---

### `/ld-expert-domain` - Domain Knowledge
**What it does:** Reference LD Expert domain knowledge

```bash
/ld-expert-domain

Includes:
├─ Business domain
├─ Role definitions
├─ Entity relationships
├─ Workflow rules
└─ UI selectors (ui-selectors.md)
```

---

### `/dusk-pest-best-practices` - Dusk/Pest Best Practices
**What it does:** Guide for writing quality Dusk and Pest tests

```bash
/dusk-pest-best-practices

Covers:
├─ Dusk best practices
├─ Pest syntax
├─ Test organization
├─ Common pitfalls
└─ Performance tips
```

---

### `/test-writer` - Write Tests
**What it does:** Generate unit and feature tests

```bash
/test-writer

Generates:
├─ Unit tests
├─ Feature tests
├─ Dusk tests
├─ Factories
└─ Seeders
```

---

## Command Categories Summary

```
QA Testing (10 commands):
├─ /qa                 (Code quality)
├─ /qa-smoke          (Quick check)
├─ /qa-admin          (Admin tests)
├─ /qa-therapist      (Therapist tests)
├─ /qa-student        (Student tests)
├─ /qa-finance        (Finance tests)
├─ /qa-e2e            (End-to-end tests)
├─ /qa-create-scenarios
├─ /qa-generate-tests
└─ /qa-test-strategy

Code & Architecture (3 commands):
├─ /feature           (Create feature branch)
├─ /review            (Code review)
└─ /deploy-check      (Pre-deploy check)

Frontend & Blade (2 commands):
├─ /blade-component   (Create component)
└─ /frontend-design   (Design UI)

Laravel (3 commands):
├─ /laravel-scaffold  (Generate scaffolding)
├─ /hookify           (Use hooks/events)
└─ /phpstan-fixer     (Fix type errors)

Utilities (2 commands):
├─ /wiki-update       (Update docs)
└─ /migrate-dt        (DataTable migration)

Reference (4 commands):
├─ /ld-expert-domain  (Domain knowledge)
├─ /dusk-pest-best-practices
├─ /test-writer       (Generate tests)
└─ /claude-code-setup (Setup help)

Other (5 commands):
├─ /copyreview
├─ /plugin-dev
├─ /playground
├─ /claude-md-management
└─ /qa-review-tests
```

---

## Quick Reference by Use Case

### I'm starting work
```
1. /feature          - Create feature branch
2. /ld-expert-domain - Understand domain
```

### I'm writing code
```
1. /laravel-scaffold - Generate boilerplate
2. /phpstan-fixer    - Fix type errors
3. /test-writer      - Generate tests
```

### I'm testing
```
1. /qa-smoke         - Quick check (3 min)
2. /qa-{role}        - Role-specific tests (8-15 min)
3. /qa               - Full quality check
```

### I'm planning QA
```
1. /qa-test-strategy - Plan approach
2. /qa-create-scenarios - Generate scenarios
3. /qa-generate-tests - Generate test code
```

### I'm designing frontend
```
1. /frontend-design   - UI/UX design
2. /blade-component   - Create component
3. /qa-smoke          - Verify it works
```

### I'm reviewing code
```
1. /review            - Code review
2. /phpstan-fixer     - Fix type errors
3. /qa                - Quality check
```

### I'm ready to deploy
```
1. /deploy-check      - Pre-deploy verification
2. /qa-smoke          - Final smoke test
```

---

## Summary

**Total Commands Available:** 31

**Most Used:**
- `/qa-smoke` - Quick verification
- `/qa-{role}` - Role-specific testing
- `/qa` - Code quality
- `/feature` - Start new work
- `/review` - Code review

**Remember:**
- QA commands run in Docker
- Use `/qa-smoke` first (fast)
- Use role-specific `/qa-*` for detailed testing
- Use `/qa` before committing (code quality)
