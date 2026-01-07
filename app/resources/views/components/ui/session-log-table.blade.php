@props([
    'columns' => [],
    'rows' => [],
])

@php
    use App\Enums\SessionLogStatus;
@endphp

<div class="overflow-x-auto">
    <table class="min-w-full divide-y divide-border session-log-table">
        <thead class="bg-background/subtle">
            <tr>
                @foreach ($columns as $column)
                    <th class="px-4 py-2 text-left text-xs font-medium text-foreground/70 uppercase">
                        {{ $column['label'] }}
                    </th>
                @endforeach
            </tr>
        </thead>
        <tbody class="bg-white divide-y divide-border">
            @foreach ($rows as $row)
                <tr>
                    @foreach ($columns as $column)
                        @php
                            $key = $column['key'];
                        @endphp
                        @if ($key === 'actions')
                            <td class="px-4 py-2 text-sm text-gray-900 whitespace-nowrap">
                                <div class="flex items-center gap-1">
                                    @foreach ($row['actions'] as $action)
                                        @php
                                            $variant = $action['variant'] ?? 'ghost';
                                            $baseClasses =
                                                'inline-flex items-center justify-center w-8 h-8 rounded transition-colors';
                                            $variantClasses = match ($variant) {
                                                'primary' => 'bg-primary text-primary-foreground hover:bg-primary/90',
                                                'secondary'
                                                    => 'bg-secondary text-secondary-foreground hover:bg-secondary/90',
                                                'primary-outline'
                                                    => 'border border-primary text-primary hover:bg-primary/10',
                                                default
                                                    => 'border border-border text-foreground hover:bg-background/subtle',
                                            };
                                            $icon = $action['icon'] ?? null;
                                        @endphp

                                        @if ($action['type'] === 'link')
                                            <a href="{{ $action['url'] }}"
                                                class="{{ $baseClasses }} {{ $variantClasses }}"
                                                title="{{ $action['label'] }}">
                                                @if ($icon === 'eye')
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4"
                                                        viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                                        <circle cx="12" cy="12" r="3"></circle>
                                                    </svg>
                                                @elseif ($icon === 'edit')
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4"
                                                        viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                        <path
                                                            d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7">
                                                        </path>
                                                        <path
                                                            d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z">
                                                        </path>
                                                    </svg>
                                                @elseif ($icon === 'check')
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4"
                                                        viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                        <polyline points="20 6 9 17 4 12"></polyline>
                                                    </svg>
                                                @elseif ($icon === 'x')
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4"
                                                        viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                        <line x1="18" y1="6" x2="6"
                                                            y2="18"></line>
                                                        <line x1="6" y1="6" x2="18"
                                                            y2="18"></line>
                                                    </svg>
                                                @else
                                                    <span class="text-xs font-medium">{{ $action['label'] }}</span>
                                                @endif
                                            </a>
                                        @elseif ($action['type'] === 'form')
                                            <form method="POST" action="{{ $action['url'] }}" class="inline">
                                                @csrf
                                                @if (strtolower($action['method']) !== 'post')
                                                    @method($action['method'])
                                                @endif
                                                <button type="submit" class="{{ $baseClasses }} {{ $variantClasses }}"
                                                    title="{{ $action['label'] }}"
                                                    data-confirm-title="{{ $action['confirm']['title'] ?? '' }}"
                                                    data-confirm-text="{{ $action['confirm']['text'] ?? '' }}"
                                                    data-confirm-icon="{{ $action['confirm']['icon'] ?? 'question' }}">
                                                    @if (($action['icon'] ?? null) === 'send')
                                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4"
                                                            viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                                            stroke-width="2" stroke-linecap="round"
                                                            stroke-linejoin="round">
                                                            <line x1="22" y1="2" x2="11"
                                                                y2="13"></line>
                                                            <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
                                                        </svg>
                                                    @elseif (($action['icon'] ?? null) === 'check')
                                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4"
                                                            viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                                            stroke-width="2" stroke-linecap="round"
                                                            stroke-linejoin="round">
                                                            <polyline points="20 6 9 17 4 12"></polyline>
                                                        </svg>
                                                    @elseif (($action['icon'] ?? null) === 'x')
                                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4"
                                                            viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                                            stroke-width="2" stroke-linecap="round"
                                                            stroke-linejoin="round">
                                                            <line x1="18" y1="6" x2="6"
                                                                y2="18"></line>
                                                            <line x1="6" y1="6" x2="18"
                                                                y2="18"></line>
                                                        </svg>
                                                    @else
                                                        <span
                                                            class="text-xs font-medium">{{ $action['label'] }}</span>
                                                    @endif
                                                </button>
                                            </form>
                                        @endif
                                    @endforeach
                                </div>
                            </td>
                        @elseif ($key === 'status')
                            @php
                                $statusLabel = $row[$key] ?? null;
                                $variant = match ($statusLabel) {
                                    SessionLogStatus::APPROVED->label() => 'success',
                                    SessionLogStatus::SUBMITTED->label() => 'warning',
                                    SessionLogStatus::CANCELLED->label() => 'danger',
                                    default => 'secondary',
                                };
                            @endphp
                            <td class="px-4 py-2 text-sm whitespace-nowrap">
                                @if ($statusLabel)
                                    <x-ui::badge :variant="$variant">
                                        {{ $statusLabel }}
                                    </x-ui::badge>
                                @else
                                    <span class="text-gray-500">-</span>
                                @endif
                            </td>
                        @elseif ($key === 'date_time' && is_array($row[$key] ?? null))
                            <td class="px-4 py-2 text-sm">
                                @php
                                    $dateTime = $row[$key];
                                @endphp
                                <div class="flex flex-col space-y-1">
                                    @if (!empty($dateTime['date']))
                                        <span class="text-foreground font-medium">{{ $dateTime['date'] }}</span>
                                    @endif
                                    @if (!empty($dateTime['time']))
                                        <span class="text-foreground">{{ $dateTime['time'] }}</span>
                                    @endif
                                    @if (!empty($dateTime['duration']))
                                        <span class="text-xs text-foreground/60">{{ $dateTime['duration'] }}</span>
                                    @endif
                                    @if (empty(array_filter($dateTime)))
                                        <span class="text-gray-500">-</span>
                                    @endif
                                </div>
                            </td>
                        @elseif ($key === 'entry_info' && is_array($row[$key] ?? null))
                            <td class="px-4 py-2 text-sm">
                                @php
                                    $entryInfo = $row[$key];
                                @endphp
                                <div class="flex flex-col space-y-1">
                                    @if (!empty($entryInfo['created_date']))
                                        <span class="text-xs text-foreground/60">
                                            Entered on {{ $entryInfo['created_date'] }}
                                        </span>
                                    @endif
                                    @if (!empty($entryInfo['entry_difference']))
                                        <x-ui::badge variant="warning" class="w-fit">
                                            {{ $entryInfo['entry_difference'] }}
                                        </x-ui::badge>
                                    @endif
                                    @if (empty(array_filter($entryInfo)))
                                        <span class="text-gray-500">-</span>
                                    @endif
                                </div>
                            </td>
                        @elseif ($key === 'student_service_school' && is_array($row[$key] ?? null))
                            <td class="px-4 py-2 text-sm">
                                @php
                                    $studentServiceSchool = $row[$key];
                                @endphp
                                <div class="flex flex-col">
                                    @if (!empty($studentServiceSchool['student']))
                                        <span
                                            class="font-medium text-foreground">{{ $studentServiceSchool['student'] }}</span>
                                    @endif
                                    @if (!empty($studentServiceSchool['service']))
                                        <span
                                            class="text-sm text-foreground/70">{{ $studentServiceSchool['service'] }}</span>
                                    @endif
                                    @if (!empty($studentServiceSchool['school']))
                                        <span
                                            class="text-xs text-foreground/60 mt-1">{{ $studentServiceSchool['school'] }}</span>
                                    @endif
                                    @if (empty(array_filter($studentServiceSchool)))
                                        <span class="text-gray-500">-</span>
                                    @endif
                                </div>
                            </td>
                        @else
                            <td class="px-4 py-2 text-sm text-gray-900 whitespace-nowrap">
                                {{ $row[$key] ?? '-' }}
                            </td>
                        @endif
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
