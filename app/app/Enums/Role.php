<?php

declare(strict_types=1);

namespace App\Enums;

enum Role: string
{
    case ADMIN = 'admin';
    case THERAPIST = 'therapist';
    case STUDENT = 'student';
    case PARENT = 'parent';
}
