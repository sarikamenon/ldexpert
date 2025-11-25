# Student Management Notes

## Overview

-   Student module mirrors therapist/school patterns.
-   Each student is a `users` record with role `student` + `student_profiles` entry.
-   Admin UI provides search, status filter, CSV export, SweetAlert status toggles.

## Seeding

-   `DatabaseSeeder` calls `StudentSeeder` to create 15 demo students with guardian/school data.
-   Run `php artisan db:seed --class=StudentSeeder` (or `db:seed`) to load sample data.
-   Seeder randomizes active vs inactive state so status filters always have data.

## Welcome Email

-   `WelcomeStudentMail` sends a compassionate onboarding message with login credentials.
-   Triggered from `StudentService::create` and delivered to `users.email`.
-   Content reassures families/students and reminds them to reset the temporary password.

## Testing

-   Unit: DTO/Repository/Service tests under `tests/Unit/...Student*.php`.
-   Feature: `tests/Feature/Admin/StudentManagementTest.php` covers CRUD, filters, export, authorization.
-   Dusk: `tests/Browser/AdminStudentsBrowserTest.php` automates UI flows with SweetAlert status toggles.
-   Run `php artisan test` for unit/feature suites, `php artisan dusk` for browser coverage.
