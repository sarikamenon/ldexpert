<?php

return [
    'menus' => [
        'admin' => [
            [
                'label' => 'Dashboard',
                'route' => 'dashboard',
                'active' => 'dashboard',
            ],
            [
                'label' => 'Schools',
                'route' => 'admin.schools.index',
                'active' => ['admin.schools.*', 'admin.contracts.schools.*'],
                'children' => [
                    [
                        'label' => 'List',
                        'route' => 'admin.schools.index',
                        'active' => 'admin.schools.index',
                    ],
                    [
                        'label' => 'Create',
                        'route' => 'admin.schools.create',
                        'active' => 'admin.schools.create',
                    ],
                    [
                        'label' => 'Contracts',
                        'route' => 'admin.contracts.schools.index',
                        'active' => 'admin.contracts.schools.*',
                    ],
                ],
            ],
            [
                'label' => 'Therapists',
                'route' => 'admin.therapists.index',
                'active' => ['admin.therapists.*', 'admin.contracts.therapists.*'],
                'children' => [
                    [
                        'label' => 'List',
                        'route' => 'admin.therapists.index',
                        'active' => 'admin.therapists.index',
                    ],
                    [
                        'label' => 'Create',
                        'route' => 'admin.therapists.create',
                        'active' => 'admin.therapists.create',
                    ],
                    [
                        'label' => 'Contracts',
                        'route' => 'admin.contracts.therapists.index',
                        'active' => 'admin.contracts.therapists.*',
                    ],
                ],
            ],
            [
                'label' => 'Students',
                'route' => 'admin.students.index',
                'active' => ['admin.students.*', 'admin.student-documents.*', 'admin.ssas.*'],
                'children' => [
                    [
                        'label' => 'Student List',
                        'route' => 'admin.students.index',
                        'active' => 'admin.students.index',
                    ],
                    [
                        'label' => 'Student Create',
                        'route' => 'admin.students.create',
                        'active' => 'admin.students.create',
                    ],
                    [
                        'label' => 'Student Import',
                        'route' => 'admin.students.import',
                        'active' => ['admin.students.import', 'admin.students.imports.*'],
                    ],
                    [
                        'label' => 'Documents',
                        'route' => 'admin.student-documents.index',
                        'active' => 'admin.student-documents.*',
                    ],
                    [
                        'label' => 'SSA List',
                        'route' => 'admin.ssas.index',
                        'active' => 'admin.ssas.index',
                    ],
                    [
                        'label' => 'SSA Create',
                        'route' => 'admin.ssas.create',
                        'active' => 'admin.ssas.create',
                    ],
                    [
                        'label' => 'SSA Import',
                        'route' => 'admin.ssas.import',
                        'active' => ['admin.ssas.import', 'admin.ssas.imports.*'],
                    ],
                ],
            ],
            [
                'label' => 'Session Logs',
                'route' => 'admin.session-logs.index',
                'active' => 'admin.session-logs.*',
                'children' => [
                    [
                        'label' => 'Submitted',
                        'route' => 'admin.session-logs.index',
                        'query' => ['status' => 'submitted'],
                        'active' => ['admin.session-logs.index', 'admin.session-logs.show', 'admin.session-logs.edit'],
                    ],
                    [
                        'label' => 'Approved',
                        'route' => 'admin.session-logs.index',
                        'query' => ['status' => 'approved'],
                        'active' => ['admin.session-logs.index', 'admin.session-logs.show', 'admin.session-logs.edit'],
                    ],
                    [
                        'label' => 'Cancelled',
                        'route' => 'admin.session-logs.index',
                        'query' => ['status' => 'cancelled'],
                        'active' => ['admin.session-logs.index', 'admin.session-logs.show', 'admin.session-logs.edit'],
                    ],
                ],
            ],
            [
                'label' => 'Finance',
                'route' => 'admin.finance.dashboard',
                'active' => ['admin.finance.*', 'admin.invoices.*', 'admin.billing.therapist-bills.*', 'admin.expenses.*', 'admin.payments.*', 'admin.ledger.*'],
                'children' => [
                    [
                        'label' => 'Dashboard',
                        'route' => 'admin.finance.dashboard',
                        'active' => 'admin.finance.dashboard',
                    ],
                    [
                        'label' => 'Accounts Ledger',
                        'route' => 'admin.ledger.accounts.index',
                        'query' => ['type' => 'schools'],
                        'active' => 'admin.ledger.accounts.*',
                    ],
                    [
                        'label' => 'Invoices',
                        'route' => 'admin.invoices.index',
                        'active' => ['admin.invoices.index', 'admin.invoices.show'],
                    ],
                    [
                        'label' => 'Invoice Payments',
                        'route' => 'admin.payments.invoices.index',
                        'active' => 'admin.payments.invoices.*',
                    ],
                    [
                        'label' => 'Therapist Billing',
                        'route' => 'admin.billing.therapist-bills.index',
                        'active' => ['admin.billing.therapist-bills.index', 'admin.billing.therapist-bills.show'],
                    ],
                    [
                        'label' => 'Bill Payments',
                        'route' => 'admin.payments.therapist-bills.index',
                        'active' => 'admin.payments.therapist-bills.*',
                    ],
                    [
                        'label' => 'Expenses',
                        'route' => 'admin.expenses.index',
                        'active' => ['admin.expenses.*'],
                    ],
                    [
                        'label' => 'Pay Stub Report',
                        'route' => 'admin.finance.pay-stub-report.index',
                        'active' => 'admin.finance.pay-stub-report.*',
                    ],
                ],
            ],
            [
                'label' => 'Reports',
                'route' => 'admin.reports.ssa.utilization.index',
                'active' => ['admin.reports.*', 'admin.analytics.*'],
                'children' => [
                    [
                        'label' => 'Utilization & Compliance',
                        'route' => 'admin.reports.ssa.utilization.index',
                        'active' => 'admin.reports.ssa.utilization.*',
                    ],
                    [
                        'label' => 'Caseload & Assignment',
                        'route' => 'admin.reports.ssa.caseload.index',
                        'active' => 'admin.reports.ssa.caseload.*',
                    ],
                    [
                        'label' => 'Expirations & Pipeline',
                        'route' => 'admin.reports.ssa.expirations.index',
                        'active' => 'admin.reports.ssa.expirations.*',
                    ],
                    [
                        'label' => 'Analytics',
                        'route' => 'admin.analytics.index',
                        'active' => 'admin.analytics.*',
                    ],
                ],
            ],
            [
                'label' => 'Settings',
                'route' => 'admin.settings.index',
                'active' => ['admin.settings.*', 'admin.services.*', 'admin.positions.*'],
                'children' => [
                    [
                        'label' => 'Services',
                        'route' => 'admin.services.index',
                        'active' => 'admin.services.*',
                    ],
                    [
                        'label' => 'Expense Categories',
                        'route' => 'admin.settings.expense-categories.index',
                        'active' => 'admin.settings.expense-categories.*',
                    ],
                    [
                        'label' => 'Positions',
                        'route' => 'admin.positions.index',
                        'active' => 'admin.positions.*',
                    ],
                ],
            ],
        ],
        'therapist' => [
            [
                'label' => 'Dashboard',
                'route' => 'therapist.dashboard',
                'active' => 'therapist.dashboard',
            ],
            [
                'label' => 'Schedule',
                'route' => 'therapist.schedule.index',
                'active' => 'therapist.schedule.*',
                'children' => [
                    [
                        'label' => 'Calendar',
                        'route' => 'therapist.schedule.calendar',
                        'active' => 'therapist.schedule.calendar',
                    ],
                    [
                        'label' => 'Pending Schedule',
                        'route' => 'therapist.schedule.pending',
                        'active' => 'therapist.schedule.pending',
                    ],
                ],
            ],
            [
                'label' => 'Session Logs',
                'route' => 'therapist.session-logs.index',
                'active' => 'therapist.session-logs.*',
                'children' => [
                    [
                        'label' => 'My Session Logs',
                        'route' => 'therapist.session-logs.index',
                        'active' => 'therapist.session-logs.index',
                    ],
                    [
                        'label' => 'Add Non-Schedule Log',
                        'route' => 'therapist.session-logs.select-ssa',
                        'active' => ['therapist.session-logs.select-ssa', 'therapist.session-logs.create'],
                    ],
                ],
            ],
            [
                'label' => 'Billing',
                'route' => 'therapist.billing.index',
                'active' => 'therapist.billing.*',
                'children' => [
                    [
                        'label' => 'My Bills',
                        'route' => 'therapist.billing.index',
                        'active' => 'therapist.billing.index',
                    ],
                ],
            ],
            [
                'label' => 'SSAs',
                'route' => 'therapist.ssas.index',
                'active' => 'therapist.ssas.*',
            ],
            [
                'label' => 'Students',
                'route' => 'therapist.students.index',
                'active' => 'therapist.students.*',
            ],
        ],
        'student' => [
            [
                'label' => 'Dashboard',
                'route' => 'student.dashboard',
                'active' => 'student.dashboard',
            ],
        ],
        'default' => [
            [
                'label' => 'Dashboard',
                'route' => 'dashboard',
                'active' => 'dashboard',
            ],
        ],
    ],
];
