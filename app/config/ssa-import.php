<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Import Templates by Type
    |--------------------------------------------------------------------------
    |
    | Each import type (NOVA, RSM, MARVIN) has its own template configuration.
    | Templates define required columns, optional columns, and column mappings.
    |
    */

    'templates' => [
        'NOVA' => [
            'required_columns' => [
                'student_email',
                'primary_service_name',
                'start_date',
                'end_date',
                'minutes_per_session',
                'tho_minutes',
            ],
            'optional_columns' => [
                'student_id_number',
                'school_name',
                'additional_service_names',
                'frequency',
                'sessions_per_frequency',
                'assigned_therapist_email',
                'calculated_minutes',
                'adjusted_minutes',
                'adjustment_notes',
            ],
            'column_mapping' => [
                'student_email' => 'student_email',
                'student_id_number' => 'student_id_number',
                'school_name' => 'school_name',
                'primary_service_name' => 'primary_service_name',
                'additional_service_names' => 'additional_service_names',
                'start_date' => 'start_date',
                'end_date' => 'end_date',
                'minutes_per_session' => 'minutes_per_session',
                'frequency' => 'frequency',
                'sessions_per_frequency' => 'sessions_per_frequency',
                'tho_minutes' => 'tho_minutes',
                'calculated_minutes' => 'calculated_minutes',
                'adjusted_minutes' => 'adjusted_minutes',
                'adjustment_notes' => 'adjustment_notes',
                'assigned_therapist_email' => 'assigned_therapist_email',
            ],
        ],

        'RSM' => [
            'required_columns' => [
                'Identity ID',
                'School Name',
                'Begin Date',
                'End Date',
                'Hours',
                'Total Hrs Owed',
            ],
            'optional_columns' => [
                'Service',
                'How Often',
                'Per',
                'Notes',
                'Therapist Email',
            ],
            'column_mapping' => [
                'Identity ID' => 'student_id_number',
                'School Name' => 'school_name',
                'Service' => 'primary_service_name',
                'Begin Date' => 'start_date',
                'End Date' => 'end_date',
                'Hours' => 'hours_per_session',
                'How Often' => 'sessions_per_frequency',
                'Per' => 'frequency_label',
                'Notes' => 'adjustment_notes',
                'Therapist Email' => 'assigned_therapist_email',
                'Total Hrs Owed' => 'tho_hours',
            ],
        ],

        'MARVIN' => [
            // To be implemented later
            'required_columns' => [],
            'optional_columns' => [],
            'column_mapping' => [],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | S3 Storage Configuration
    |--------------------------------------------------------------------------
    */

    's3' => [
        'disk' => 's3',
        'path_prefix' => 'ssa-imports',
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
