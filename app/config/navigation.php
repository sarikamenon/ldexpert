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
                'active' => 'admin.students.*',
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
                ],
            ],
            [
                'label' => 'Service Support Agreements',
                'route' => 'admin.ssas.index',
                'active' => 'admin.ssas.*',
            ],
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
            [
                'label' => 'Settings',
                'route' => 'admin.settings.index',
                'active' => 'admin.settings.*',
            ],
        ],
        'therapist' => [
            [
                'label' => 'My Dashboard',
                'route' => 'therapist.dashboard',
                'active' => 'therapist.dashboard',
            ],
            [
                'label' => 'My Schedule',
                'route' => 'therapist.schedule.index',
                'active' => 'therapist.schedule.*',
            ],
            [
                'label' => 'My Sessions',
                'route' => 'therapist.sessions.index',
                'active' => 'therapist.sessions.*',
            ],
            [
                'label' => 'My Bills',
                'route' => 'therapist.billing.index',
                'active' => 'therapist.billing.*',
            ],
        ],
        'student' => [
            [
                'label' => 'Dashboard',
                'route' => 'student.dashboard',
                'active' => 'student.dashboard',
            ],
            [
                'label' => 'Schedule Calendar',
                'route' => 'student.schedule.index',
                'active' => 'student.schedule.*',
            ],
            [
                'label' => 'Progress & Goals',
                'route' => 'student.goals.index',
                'active' => 'student.goals.*',
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
