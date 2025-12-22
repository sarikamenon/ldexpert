<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Notification;

use Illuminate\Foundation\Http\FormRequest;

class DeleteNotificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [];
    }
}
