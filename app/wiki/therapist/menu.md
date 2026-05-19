# Therapist Menu Specification

Last Updated: 26 Mar 2026

## Purpose
Define the therapist-facing navigation for the NOVA workspace. Menu is config-driven via `config/navigation.php`.

## Current Menu Structure (from config/navigation.php)

| Top-Level | Children | Status | Routes |
|-----------|----------|--------|--------|
| Dashboard | — | Implemented | `/therapist/dashboard` |
| Schedule | Calendar, Full Calendar, Pending Schedule | Implemented | `/therapist/schedule`, `/therapist/schedule/full-calendar`, `/therapist/schedule/pending` |
| Session Logs | My Session Logs, Add Non-Schedule Log | Implemented | `/therapist/session-logs`, `/therapist/session-logs/create` |
| Sub Requests | Invited, My Requests | Implemented | `/therapist/sub-requests` (see [Substitute Coverage](./sub-coverage.md)) |
| Billing | My Bills | Implemented | `/therapist/billing` |
| SSAs | — | Implemented (read-only) | `/therapist/ssas` |
| Students | — | Implemented (read-only) | `/therapist/students` |

## Implementation Notes
1. **Navigation Layout** — Rendered from `config/navigation.php` with `auth` + `role:therapist` middleware.
2. **Session Logs** — Full lifecycle: create from schedule or standalone, edit drafts/sent-back, submit for approval. View sent-back comments and respond.
3. **Billing** — Therapists can view their bills, download PDFs. Implemented at `/therapist/billing` and `/therapist/billing/{bill}` and `/therapist/billing/{bill}/download`.
4. **Students & SSAs** — Read-only views of assigned students and SSA details. Therapists cannot create or edit.
5. **Responsive** — Designed for tablet use (1024px+ width).
