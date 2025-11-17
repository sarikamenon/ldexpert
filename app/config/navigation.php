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
                'active' => 'admin.schools.*',
            ],
            [
                'label' => 'Therapists',
                'route' => 'admin.therapists.index',
                'active' => 'admin.therapists.*',
            ],
            [
                'label' => 'Students',
                'route' => 'admin.students.index',
                'active' => 'admin.students.*',
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
