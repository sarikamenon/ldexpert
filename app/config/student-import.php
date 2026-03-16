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
            ],
            'optional_columns' => [
                'city',
                'state',
                'zip_code',
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
            'required_columns' => [
                'Identity ID',
                'Last Name',
                'First Name',
                'Gender',
                'Grade',
                'School Name',
                'Parent Email',
            ],
            'optional_columns' => [
                'City',
                'Zip',
                'Address',
                'Phone',
                'Parent Last Name',
                'Parent First Name',
                'Timezone',
                'Date of Birth',
            ],
            'column_mapping' => [
                'Identity ID' => 'id_number',
                'Last Name' => 'last_name',
                'First Name' => 'first_name',
                'Gender' => 'gender',
                'Grade' => 'grade_level',
                'School Name' => 'school_name',
                'Address' => 'address',
                'City' => 'city',
                'Zip' => 'zip_code',
                'Phone' => 'parent_guardian_phone',
                'Parent Email' => 'parent_guardian_email',
                'Parent Last Name' => 'parent_guardian_last_name',
                'Parent First Name' => 'parent_guardian_first_name',
                'timezone' => 'timezone',
                'Date of Birth' => 'date_of_birth',
            ],
            'field_sources' => [
                'email' => 'parent_guardian_email',
            ],
            'transformations' => [
                [
                    'type' => 'combine',
                    'target' => 'parent_guardian_name',
                    'sources' => ['parent_guardian_first_name', 'parent_guardian_last_name'],
                    'separator' => ' ',
                ],
            ],
        ],

        'MARVIN' => [
            // To be implemented later
            'required_columns' => [],
            'optional_columns' => [],
            'column_mapping' => [],
        ],

        'TUTORBIRD' => [
            'required_columns' => [
                'First Name',
                'Last Name',
                'TutorBird Student ID',
                'School',
            ],
            'optional_columns' => [
                'Email',
                'Birthday',
                'Address',
                'Gender',
                'Mobile Phone',
                'Parent Contact 1 Last Name',
                'Parent Contact 1 First Name',
                'Parent Contact 1 Email',
                'Parent Contact 1 Mobile Phone',
            ],
            'column_mapping' => [
                'First Name' => 'first_name',
                'Last Name' => 'last_name',
                'TutorBird Student ID' => 'id_number',
                'Email' => 'email',
                'Birthday' => 'date_of_birth',
                'Address' => 'address',
                'Gender' => 'gender',
                'Mobile Phone' => 'student_mobile_phone',
                'Parent Contact 1 Last Name' => 'parent_guardian_last_name',
                'Parent Contact 1 First Name' => 'parent_guardian_first_name',
                'Parent Contact 1 Email' => 'parent_guardian_email',
                'Parent Contact 1 Mobile Phone' => 'parent_guardian_phone',
                'School' => 'school_name',
            ],
            'field_sources' => [
                'email' => 'parent_guardian_email',
            ],
            'transformations' => [
                [
                    'type' => 'combine',
                    'target' => 'parent_guardian_name',
                    'sources' => ['parent_guardian_first_name', 'parent_guardian_last_name'],
                    'separator' => ' ',
                ],
            ],
            'defaults' => [
                'timezone' => 'America/Chicago',
                'gender' => 'Male',
                'grade_level' => '1',
            ],
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
    'defaults' => [],
];
