@props([
    'action',
    'goal' => null,
    'cancelUrl' => null,
    'returnTo' => null,
])

<form method="POST" action="{{ $action }}" class="space-y-6">
    @csrf
    @if ($goal)
        @method('PUT')
    @endif
    @if ($returnTo)
        <input type="hidden" name="return_to" value="{{ $returnTo->value }}" />
    @endif

    @if ($errors->has('goal'))
        <x-ui::alert variant="danger">{{ $errors->first('goal') }}</x-ui::alert>
    @endif

    <div>
        <x-input-label for="number" value="Goal Number *" />
        <p id="goal_number_help" class="mt-1 text-xs text-foreground/60">A short identifier for this goal.</p>
        <x-ui::input id="number" name="number" type="text" maxlength="50" required
            class="mt-1 block w-full"
            value="{{ old('number', $goal?->number) }}"
            aria-describedby="goal_number_help" />
        <x-input-error :messages="$errors->get('number')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="goal_text" value="Goal *" />
        <p id="goal_text_help" class="mt-1 text-xs text-foreground/60">The goal the student is working toward.</p>
        <textarea id="goal_text" name="goal" rows="4" maxlength="5000" required
            class="mt-1 block w-full rounded-base border border-input bg-background px-3 py-2 text-foreground placeholder:text-foreground/50 focus:ring-2 focus:ring-ring focus:border-ring"
            aria-describedby="goal_text_help">{{ old('goal', $goal?->goal) }}</textarea>
        <x-input-error :messages="$errors->get('goal')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="objective" value="Objectives" />
        <p id="goal_objective_help" class="mt-1 text-xs text-foreground/60">Optional. Specific objectives or benchmarks under this goal.</p>
        <textarea id="objective" name="objective" rows="4" maxlength="5000"
            class="mt-1 block w-full rounded-base border border-input bg-background px-3 py-2 text-foreground placeholder:text-foreground/50 focus:ring-2 focus:ring-ring focus:border-ring"
            aria-describedby="goal_objective_help">{{ old('objective', $goal?->objective) }}</textarea>
        <x-input-error :messages="$errors->get('objective')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="progress" value="Progress" />
        <p id="goal_progress_help" class="mt-1 text-xs text-foreground/60">Optional snapshot of how the student is progressing on this goal. Update as work proceeds.</p>
        <textarea id="progress" name="progress" rows="3" maxlength="1000"
            class="mt-1 block w-full rounded-base border border-input bg-background px-3 py-2 text-foreground placeholder:text-foreground/50 focus:ring-2 focus:ring-ring focus:border-ring"
            aria-describedby="goal_progress_help">{{ old('progress', $goal?->progress) }}</textarea>
        <x-input-error :messages="$errors->get('progress')" class="mt-2" />
    </div>

    <div class="flex items-center justify-end gap-3">
        @if ($cancelUrl)
            <a href="{{ $cancelUrl }}"
                class="inline-flex items-center rounded-md border border-border bg-background px-4 py-2 text-sm font-medium text-foreground hover:bg-muted transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring">
                Cancel
            </a>
        @endif
        <x-ui::button type="submit" variant="primary">
            {{ $goal ? 'Save Changes' : 'Add Goal' }}
        </x-ui::button>
    </div>
</form>
