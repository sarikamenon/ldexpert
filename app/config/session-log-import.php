<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Import Templates by Type
    |--------------------------------------------------------------------------
    |
    | Each import type has its own template configuration.
    | Templates define required columns, optional columns, and column mappings.
    |
    */

    'templates' => [
        'RSM' => [
            'required_columns' => [
                'Entry_KEYID',
                'School',
                'STUDENTID',
                'Therapist Email',
                'Date of Service',
                'Service Name',
                'Hours',
                'Type1',
                'Therapy Start',
                'Therapy End',
            ],
            'optional_columns' => [
                'First Name',
                'Last Name',
                'Therapist',
                'Service_KEYID',
                'Type2',
                'Cancellation Notification Time',
                'Session Narrative',
                'Goals Progress',
                'Inserted On',
                'Error',
                'Error Details',
                'Queued for an Invoice?',
                'Already on Invoice',
                'Invoice Status',
                'Alt 1 Service Name',
                'Alt 1 Hours',
                'Alt 2 Service Name',
                'Alt 2 Hours',
                'Num Students',
            ],
            'column_mapping' => [
                'Entry_KEYID' => 'reference_id',
                'School' => 'school_name',
                'STUDENTID' => 'student_id_number',
                'First Name' => 'student_first_name',
                'Last Name' => 'student_last_name',
                'Therapist Email' => 'therapist_email',
                'Date of Service' => 'session_date',
                'Service Name' => 'primary_service_name',
                'Hours' => 'hours',
                'Type1' => 'type1',
                'Therapy Start' => 'start_time',
                'Therapy End' => 'end_time',
                'Session Narrative' => 'notes',
                'Goals Progress' => 'goals_progress',
                'Error' => 'error_flag',
                'Error Details' => 'error_details',
                'Num Students' => 'num_students',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Storage Configuration
    |--------------------------------------------------------------------------
    */

    'storage' => [
        'path_prefix' => 'session-log-imports',
    ],

    /*
    |--------------------------------------------------------------------------
    | Import Settings
    |--------------------------------------------------------------------------
    */

    'settings' => [
        'max_file_size' => 10240, // 10MB in KB
        'allowed_mime_types' => [
            'text/csv',
            'text/plain',
            'application/csv',
        ],
    ],
];
