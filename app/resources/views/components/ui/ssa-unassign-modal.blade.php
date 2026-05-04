<div id="unassignTherapistModal"
    class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 p-4"
    role="dialog" aria-modal="true" aria-labelledby="unassignModalTitle">
    <div class="w-full max-w-md rounded-lg bg-background shadow-xl">

        <div class="flex items-center justify-between border-b border-border px-6 py-4">
            <h2 id="unassignModalTitle" class="text-lg font-semibold text-foreground">Unassign Therapist</h2>
            <button type="button" id="unassignModalClose"
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
                <span class="text-xs font-medium text-foreground/60 w-20">Student</span>
                <span id="unassignModalStudentName" class="text-sm font-medium text-foreground">—</span>
            </div>
            <div class="flex items-center gap-2">
                <span class="text-xs font-medium text-foreground/60 w-20">Service</span>
                <span id="unassignModalServiceName" class="text-sm text-foreground">—</span>
            </div>
            <div class="flex items-center gap-2">
                <span class="text-xs font-medium text-foreground/60 w-20">Therapist</span>
                <span id="unassignModalTherapistName" class="text-sm font-medium text-foreground">—</span>
            </div>
        </div>

        <div class="px-6 py-5 space-y-4">
            <p class="text-sm text-foreground/70">
                The SSA will revert to <span class="font-medium text-warning">Pending</span> status after unassigning.
            </p>

            <div>
                <label for="unassignReasonInput"
                    class="block text-xs font-medium text-foreground/70">
                    Reason <span class="text-foreground/40">(optional)</span>
                </label>
                <p id="unassignReasonHelp" class="mt-1 text-xs text-foreground/60">
                    Briefly describe why the therapist is being unassigned.
                </p>
                <input type="text" id="unassignReasonInput"
                    aria-describedby="unassignReasonHelp"
                    maxlength="1000"
                    placeholder="e.g. Therapist on leave"
                    class="mt-1 block w-full rounded-md border border-border bg-background px-3 py-2 text-sm text-foreground shadow-sm focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary" />
            </div>
        </div>

        <div class="flex justify-end gap-3 border-t border-border px-6 py-4">
            <button type="button" id="unassignModalCancel"
                class="inline-flex items-center rounded-md border border-border px-4 py-2 text-sm font-medium text-foreground hover:bg-secondary/10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring transition-colors">
                Cancel
            </button>
            <button type="button" id="unassignModalConfirm"
                class="inline-flex items-center rounded-md bg-danger px-4 py-2 text-sm font-medium text-white hover:bg-danger/90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                Unassign
            </button>
        </div>

    </div>
</div>
