<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class SchoolCalendarEventCollection extends ResourceCollection
{
    public static $wrap = null;

    public $collects = SchoolCalendarEventResource::class;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'events' => parent::toArray($request),
        ];
    }
}
