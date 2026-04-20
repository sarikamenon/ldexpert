<div id="assignTherapistModal"
    class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 p-4"
    role="dialog" aria-modal="true" aria-labelledby="assignModalTitle">
    <div class="w-full max-w-md rounded-lg bg-background shadow-xl">

        <div class="flex items-center justify-between border-b border-border px-6 py-4">
            <h2 id="assignModalTitle" class="text-lg font-semibold text-foreground">Assign Therapist</h2>
            <button type="button" id="assignModalClose"
                class="rounded p-1 text-foreground/60 hover:bg-secondary/10 hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring transition-colors"
                aria-label="Close modal">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none"
                    stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>
        </div>

        {{-- SSA context info — populated by JS --}}
        <div class="border-b border-border bg-secondary/5 px-6 py-3 space-y-1">
            <div class="flex items-center gap-2">
                <span class="text-xs font-medium text-foreground/60 w-16">Student</span>
                <span id="assignModalStudentName" class="text-sm font-medium text-foreground">—</span>
            </div>
            <div class="flex items-center gap-2">
                <span class="text-xs font-medium text-foreground/60 w-16">Service</span>
                <span id="assignModalServiceName" class="text-sm text-foreground">—</span>
            </div>
            <div class="flex items-center gap-2">
                <span class="text-xs font-medium text-foreground/60 w-16">Status</span>
                <span id="assignModalStatus" class="text-sm text-foreground">—</span>
            </div>
        </div>

        <div class="px-6 py-5 space-y-4">
            {{-- TODO: When reassignment is re-enabled, show a warning here if the SSA already has
                 sessions under the current therapist, e.g.:
                 "This SSA has N existing sessions logged under [Therapist].
                  Reassigning will not affect those sessions — they remain under the previous therapist." --}}

            <div>
                <label for="assignTherapistSelect"
                    class="block text-xs font-medium text-foreground/70">Therapist</label>
                <p id="assignTherapistSelectHelp" class="mt-1 text-xs text-foreground/60">
                    Only therapists linked to this SSA's service are shown.
                </p>
                <select id="assignTherapistSelect"
                    aria-describedby="assignTherapistSelectHelp"
                    class="mt-1 block w-full rounded-md border border-border bg-background px-3 py-2 text-sm text-foreground shadow-sm focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary">
                    <option value="">Select a therapist…</option>
                </select>
                <p id="assignTherapistError" class="mt-1 hidden text-xs text-danger"></p>
            </div>
        </div>

        <div class="flex justify-end gap-3 border-t border-border px-6 py-4">
            <button type="button" id="assignModalCancel"
                class="inline-flex items-center rounded-md border border-border px-4 py-2 text-sm font-medium text-foreground hover:bg-secondary/10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring transition-colors">
                Cancel
            </button>
            <button type="button" id="assignModalConfirm"
                class="inline-flex items-center rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                Assign
            </button>
        </div>

    </div>
</div>
