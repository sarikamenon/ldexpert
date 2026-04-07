@props([
    'activeSSAs',
])

<div id="ssaSelectionModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center">
    <div class="bg-background rounded-lg shadow-xl max-w-md w-full mx-4">
        <div class="p-6">
            <h3 class="text-lg font-semibold text-foreground mb-4">Select Active SSA</h3>
            <p class="text-sm text-foreground/60 mb-4">Choose an active SSA to create a schedule for.</p>
            @if ($activeSSAs->count() > 0)
                <form id="ssaSelectionForm">
                    <div class="mb-4">
                        <label for="ssa_id" class="block text-sm font-medium text-foreground mb-2">SSA *</label>
                        <select id="ssa_id" name="ssa_id" data-select-box class="w-full" required>
                            <option value="">Select an SSA</option>
                            @foreach ($activeSSAs as $ssa)
                                <option value="{{ $ssa->id }}">
                                    SSA #{{ $ssa->id }} - {{ $ssa->student->name ?? 'N/A' }}
                                    ({{ $ssa->primaryService->name ?? 'N/A' }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex justify-end gap-3">
                        <button type="button" id="cancelSSASelection"
                            class="inline-flex items-center px-4 py-2 border border-border rounded-lg text-sm font-medium hover:bg-background/subtle">
                            Cancel
                        </button>
                        <button type="submit"
                            class="inline-flex items-center px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90 text-sm font-medium">
                            Continue
                        </button>
                    </div>
                </form>
            @else
                <div class="mb-4">
                    <p class="text-sm text-foreground/70">You don't have any active SSAs. Please contact your administrator to assign active SSAs.</p>
                </div>
                <div class="flex justify-end">
                    <button type="button" id="cancelSSASelection"
                        class="inline-flex items-center px-4 py-2 border border-border rounded-lg text-sm font-medium hover:bg-background/subtle">
                        Close
                    </button>
                </div>
            @endif
        </div>
    </div>
</div>
