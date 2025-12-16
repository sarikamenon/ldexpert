@props([
    'columns' => [],
    'rows' => [],
])

<div class="overflow-x-auto">
    <table class="min-w-full divide-y divide-gray-200 session-log-table">
        <thead class="bg-gray-50">
            <tr>
                @foreach ($columns as $column)
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">
                        {{ $column['label'] }}
                    </th>
                @endforeach
            </tr>
        </thead>
        <tbody class="bg-white divide-y divide-gray-200">
            @forelse ($rows as $row)
                <tr>
                    @foreach ($columns as $column)
                        @php
                            $key = $column['key'];
                        @endphp
                        @if ($key === 'actions')
                            <td class="px-4 py-2 text-sm text-gray-900 space-x-2 whitespace-nowrap">
                                @foreach ($row['actions'] as $action)
                                    @if ($action['type'] === 'link')
                                        <a href="{{ $action['url'] }}"
                                            class="text-indigo-600 hover:text-indigo-900">
                                            {{ $action['label'] }}
                                        </a>
                                    @elseif ($action['type'] === 'form')
                                        <form method="POST" action="{{ $action['url'] }}"
                                            class="inline">
                                            @csrf
                                            @if (strtolower($action['method']) !== 'post')
                                                @method($action['method'])
                                            @endif
                                            <button type="submit" class="text-indigo-600 hover:text-indigo-900"
                                                data-confirm-title="{{ $action['confirm']['title'] ?? '' }}"
                                                data-confirm-text="{{ $action['confirm']['text'] ?? '' }}"
                                                data-confirm-icon="{{ $action['confirm']['icon'] ?? 'question' }}">
                                                {{ $action['label'] }}
                                            </button>
                                        </form>
                                    @endif
                                @endforeach
                            </td>
                        @else
                            <td class="px-4 py-2 text-sm text-gray-900 whitespace-nowrap">
                                {{ $row[$key] ?? '-' }}
                            </td>
                        @endif
                    @endforeach
                </tr>
            @empty
                <tr>
                    <td colspan="{{ count($columns) }}" class="px-4 py-4 text-center text-sm text-gray-500">
                        No session logs found.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

