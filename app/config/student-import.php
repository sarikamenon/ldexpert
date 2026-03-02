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
                'first_name',
                'last_name',
                'email',
                'gender',
                'school_name',
                'id_number',
                'timezone',
                'grade_level',
                'city',
                'state',
                'zip_code',
            ],
            'optional_columns' => [
                'middle_name',
                'date_of_birth',
                'address',
                'parent_guardian_name',
                'parent_guardian_email',
                'parent_guardian_phone',
            ],
            'column_mapping' => [
                'first_name' => 'first_name',
                'last_name' => 'last_name',
                'email' => 'email',
                'gender' => 'gender',
                'date_of_birth' => 'date_of_birth',
                'school_name' => 'school_name',
                'id_number' => 'id_number',
                'timezone' => 'timezone',
                'grade_level' => 'grade_level',
                'city' => 'city',
                'state' => 'state',
                'zip_code' => 'zip_code',
                'middle_name' => 'middle_name',
                'address' => 'address',
                'parent_guardian_name' => 'parent_guardian_name',
                'parent_guardian_email' => 'parent_guardian_email',
                'parent_guardian_phone' => 'parent_guardian_phone',
            ],
        ],

        'RSM' => [
            // To be implemented later
            'required_columns' => [],
            'optional_columns' => [],
            'column_mapping' => [],
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
        'path_prefix' => 'student-imports',
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
        'duplicate_check_fields' => ['email', 'id_number'],
    ],
];
