<?php

declare(strict_types=1);

/*
 * Configuration for `php artisan diagrams:erd`.
 *
 * Every application table MUST be listed in exactly one group — the command
 * fails on unmapped tables so the ERD can never silently omit a new table.
 * Framework/vendor tables that carry no domain meaning go in `exclude`.
 */
return [

    'output_path' => base_path('../docs/diagrams/erd'),

    'groups' => [
        '01-people' => [
            'title' => 'People & Profiles',
            'tables' => [
                'users',
                'admin_profiles',
                'parent_profiles',
                'student_profiles',
                'therapist_profiles',
                'positions',
                'position_service',
                'therapist_student',
                'student_comments',
                'student_documents',
                'student_imports',
                'student_import_rows',
            ],
        ],
        '02-schools' => [
            'title' => 'Schools, Contracts & Leads',
            'tables' => [
                'schools',
                'school_calendar_events',
                'school_contracts',
                'school_contract_services',
                'leads',
                'lead_notes',
            ],
        ],
        '03-ssas' => [
            'title' => 'SSAs & Services',
            'tables' => [
                'service_support_agreements',
                'ssa_services',
                'ssa_goals',
                'ssa_assignment_history',
                'ssa_imports',
                'ssa_import_rows',
                'services',
                'service_aliases',
            ],
        ],
        '04-scheduling' => [
            'title' => 'Scheduling & Make-Up',
            'tables' => [
                'schedules',
                'schedule_email_logs',
                'schedule_sub_requests',
                'schedule_sub_request_invitees',
                'schedule_sub_ssas',
                'schedule_makeup_requests',
                'schedule_makeup_request_email_logs',
                'schedule_makeup_availabilities',
                'schedules_timezone_backfill_backup',
            ],
        ],
        '05-session-logs' => [
            'title' => 'Session Logs',
            'tables' => [
                'session_logs',
                'session_log_comments',
                'session_log_imports',
                'session_log_import_rows',
            ],
        ],
        '06-billing' => [
            'title' => 'Billing, Invoices & Therapist Bills',
            'tables' => [
                'invoices',
                'invoice_line_items',
                'invoice_email_logs',
                'invoice_payments',
                'invoice_payment_allocations',
                'therapist_bills',
                'therapist_bill_payments',
                'therapist_bill_payment_allocations',
                'billing_schedules',
                'billing_schedule_runs',
                'billing_settings',
                'billing_reminders',
                'advance_reconciliations',
                'therapist_contracts',
                'therapist_contract_services',
            ],
        ],
        '07-finance' => [
            'title' => 'Finance & Ledger',
            'tables' => [
                'ledger_entries',
                'expenses',
                'expense_categories',
                'payment_gateway_logs',
                'payment_gateway_transactions',
            ],
        ],
        '09-credentials' => [
            'title' => 'Therapist Credential Management',
            'tables' => [
                'credential_types',
                'credential_rules',
                'credential_documents',
                'credential_requests',
                'credential_reminder_policies',
                'credential_reminder_logs',
                'credential_email_logs',
                'credential_document_access_logs',
                'therapist_state_eligibilities',
            ],
        ],
        '08-platform' => [
            'title' => 'Platform (Audit, Settings, QGlob)',
            'tables' => [
                'audits',
                'settings',
                'qglob_requests',
                'notifications',
            ],
        ],
    ],

    // Framework tables with no domain meaning — never drawn.
    'exclude' => [
        'migrations',
        'cache',
        'cache_locks',
        'jobs',
        'job_batches',
        'failed_jobs',
        'sessions',
        'password_reset_tokens',
        'telescope_entries',
        'telescope_entries_tags',
        'telescope_monitoring',
    ],

    /*
     * Polymorphic relations are invisible to FK introspection — declare them
     * here so the ERD draws dashed edges to every morph target.
     */
    'morphs' => [
        'audits' => [
            'name' => 'auditable',
            'targets' => [
                'school_contracts', 'school_contract_services', 'therapist_contracts',
                'therapist_contract_services', 'service_support_agreements',
                'ledger_entries', 'schools', 'users', 'student_profiles',
                'therapist_profiles', 'parent_profiles', 'admin_profiles',
                'services', 'positions', 'settings', 'expenses', 'expense_categories',
                'schedule_makeup_requests',
            ],
        ],
        'ledger_entries' => [
            'name' => 'ledgerable',
            'targets' => ['schools', 'users'],
        ],
        'student_documents' => [
            'name' => 'documentable',
            'targets' => ['student_profiles', 'session_logs'],
        ],
    ],
];
