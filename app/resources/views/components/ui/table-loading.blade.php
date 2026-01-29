@props([
    'columns' => 3,
    'rows' => 5,
    'show' => true,
])

@if($show)
    <div class="space-y-3">
        @for($i = 0; $i < $rows; $i++)
            <div class="flex gap-4">
                @for($j = 0; $j < $columns; $j++)
                    <x-ui::skeleton class="flex-1 h-4" />
                @endfor
            </div>
        @endfor
    </div>
@endif