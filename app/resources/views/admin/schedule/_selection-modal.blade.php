@props([
    'therapists',
])

<div id="adminScheduleSelectionModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center">
    <div class="bg-background rounded-lg shadow-xl max-w-md w-full mx-4">
        <div class="p-6">
            <h3 class="text-lg font-semibold text-foreground mb-1">Add New Schedule</h3>
            <p class="text-sm text-foreground/60 mb-4">Select a therapist and one of their active SSAs.</p>

            <form id="adminScheduleSelectionForm"
                data-create-base="{{ route('admin.schedule.create') }}"
                data-ssas-url="{{ route('admin.schedule.therapist-ssas') }}">
                <div class="mb-4">
                    <label for="modal_therapist_id" class="block text-sm font-medium text-foreground mb-2">Therapist *</label>
                    <select id="modal_therapist_id" name="therapist_id" data-select-box class="w-full"
                        data-placeholder="Select a therapist" required>
                        <option value="">Select a therapist</option>
                        @foreach ($therapists as $therapist)
                            <option value="{{ $therapist->id }}">{{ $therapist->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="mb-4">
                    <label for="modal_ssa_id" class="block text-sm font-medium text-foreground mb-2">SSA *</label>
                    <select id="modal_ssa_id" name="ssa_id" data-select-box class="w-full"
                        data-placeholder="Select a therapist first" required disabled>
                        <option value="">Select a therapist first</option>
                    </select>
                    <p class="text-xs text-foreground/60 mt-1">Only active SSAs for the selected therapist are shown.</p>
                </div>

                <div class="flex justify-end gap-3">
                    <button type="button" id="cancelAdminScheduleSelection"
                        class="inline-flex items-center px-4 py-2 border border-border rounded-lg text-sm font-medium hover:bg-background/subtle">
                        Cancel
                    </button>
                    <button type="submit" id="adminScheduleSelectionContinue"
                        class="inline-flex items-center px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90 text-sm font-medium disabled:opacity-50 disabled:cursor-not-allowed"
                        disabled>
                        Continue
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
