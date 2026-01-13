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
                'active' => ['admin.students.*', 'admin.student-documents.*'],
                'children' => [
                    [
                        'label' => 'List',
                        'route' => 'admin.students.index',
                        'active' => 'admin.students.index',
                    ],
                    [
                        'label' => 'Create',
                        'route' => 'admin.students.create',
                        'active' => 'admin.students.create',
                    ],
                    [
                        'label' => 'Documents',
                        'route' => 'admin.student-documents.index',
                        'active' => 'admin.student-documents.*',
                    ],
                ],
            ],
            [
                'label' => 'SSAs',
                'route' => 'admin.ssas.index',
                'active' => 'admin.ssas.*',
                'children' => [
                    [
                        'label' => 'List',
                        'route' => 'admin.ssas.index',
                        'active' => 'admin.ssas.index',
                    ],
                    [
                        'label' => 'Create',
                        'route' => 'admin.ssas.create',
                        'active' => 'admin.ssas.create',
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
                'route' => 'admin.invoices.index',
                'active' => ['admin.invoices.*', 'admin.billing.therapist-bills.*'],
                'children' => [
                    [
                        'label' => 'Invoices',
                        'route' => 'admin.invoices.index',
                        'active' => ['admin.invoices.index', 'admin.invoices.show'],
                    ],
                    [
                        'label' => 'Create Invoice',
                        'route' => 'admin.invoices.create',
                        'active' => 'admin.invoices.create',
                    ],
                    [
                        'label' => 'Therapist Billing',
                        'route' => 'admin.billing.therapist-bills.index',
                        'active' => 'admin.billing.therapist-bills.*',
                    ],
                ],
            ],
            [
                'label' => 'Settings',
                'route' => 'admin.settings.index',
                'active' => ['admin.settings.*', 'admin.services.*', 'admin.activity-logs.*', 'admin.analytics.*'],
                'children' => [
                    [
                        'label' => 'Services',
                        'route' => 'admin.services.index',
                        'active' => 'admin.services.*',
                    ],
                    [
                        'label' => 'Activity Logs',
                        'route' => 'admin.activity-logs.index',
                        'active' => 'admin.activity-logs.*',
                    ],
                    [
                        'label' => 'Analytics',
                        'route' => 'admin.analytics.index',
                        'active' => 'admin.analytics.*',
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
