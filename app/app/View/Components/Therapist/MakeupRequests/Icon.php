<?php

declare(strict_types=1);

namespace App\View\Components\Therapist\MakeupRequests;

use Illuminate\View\Component;
use Illuminate\View\View;

/**
 * Inline SVG icon set used by the therapist make-up requests UI. Keeping
 * the path registry in PHP (vs an `@php` block inside Blade) lets the view
 * file stay declarative per BLADE_GUIDELINES.
 */
final class Icon extends Component
{
    /** @var array<string, string> */
    private const PATHS = [
        'calendar' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>',
        'mail' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M3 8l9 6 9-6M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>',
        'warning' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M12 9v3m0 3.5h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>',
        'info' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>',
        'user-check' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M16 11a4 4 0 10-8 0 4 4 0 008 0zM3 21v-1a6 6 0 016-6h4m4 5l2 2 4-4"/>',
        'x-circle' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M10 14l4-4m0 4l-4-4m11 2a9 9 0 11-18 0 9 9 0 0118 0z"/>',
        'clock-slash' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M12 8v4m0 0l2 2m-9-2a9 9 0 1115.5 6.2M3 21l18-18"/>',
        'arrow-right' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M14 5l7 7m0 0l-7 7m7-7H3"/>',
        'x' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M6 18L18 6M6 6l12 12"/>',
        'minus-circle' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M9 12h6m-6 0a3 3 0 11-6 0 3 3 0 016 0zm12 0a3 3 0 11-6 0 3 3 0 016 0z"/>',
    ];

    public ?string $path;

    public function __construct(
        public string $name,
        public string $class = 'h-4 w-4 shrink-0',
    ) {
        $this->path = self::PATHS[$name] ?? null;
    }

    public function render(): View
    {
        return view('components.therapist.makeup-requests.icon');
    }
}
