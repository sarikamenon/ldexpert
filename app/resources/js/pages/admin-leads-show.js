import { confirmDialog, successToast, errorAlert } from '../common/sweetalert';

function getCsrfToken() {
    const meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.getAttribute('content') : '';
}

function setupNoteForm() {
    const form = document.getElementById('addNoteForm');
    if (!form) return;

    form.addEventListener('submit', async (e) => {
        e.preventDefault();

        const noteInput = form.querySelector('#note');
        const note = noteInput?.value?.trim();
        if (!note) return;

        const leadId = noteInput.dataset.leadId;
        const csrfToken = getCsrfToken();

        try {
            const response = await fetch(`/admin/leads/${leadId}/notes`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    Accept: 'application/json',
                },
                body: JSON.stringify({ note }),
            });

            const data = await response.json();

            if (data.success) {
                const notesList = document.getElementById('notesList');
                const emptyMsg = notesList?.querySelector('p.text-center');
                if (emptyMsg) emptyMsg.remove();

                const noteHtml = `
                    <div class="border border-border rounded-base p-4">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-sm font-medium">${escapeHtml(data.note.author_name)}</span>
                            <span class="text-xs text-foreground/60">${escapeHtml(data.note.created_at)}</span>
                        </div>
                        <p class="text-sm text-foreground">${escapeHtml(data.note.note)}</p>
                    </div>
                `;

                if (notesList) {
                    notesList.insertAdjacentHTML('afterbegin', noteHtml);
                }

                noteInput.value = '';
                await successToast(data.message);
            } else {
                errorAlert(data.message || 'Failed to add note.');
            }
        } catch {
            errorAlert('An error occurred while adding the note.');
        }
    });
}

function setupStatusChange() {
    document.body.addEventListener('click', async (event) => {
        const button = event.target.closest('.change-lead-status');
        if (!button) return;

        const leadId = button.dataset.leadId;
        const status = button.dataset.status;
        const label = button.dataset.label;
        const isNegative = button.dataset.isNegative === '1';

        const confirmOptions = {
            title: `Set status to "${label}"?`,
            text: isNegative ? 'This will mark the lead as closed.' : `This will update the lead status to "${label}".`,
            icon: 'warning',
            confirmButtonText: `Yes, set to ${label}`,
            showInput: isNegative,
            inputPlaceholder: 'Reason (optional)...',
        };

        const result = await confirmDialog(confirmOptions);
        if (!result.isConfirmed) return;

        const csrfToken = getCsrfToken();
        const body = { status };
        if (isNegative && result.value) {
            body.status_reason = result.value;
        }

        try {
            const response = await fetch(`/admin/leads/${leadId}/status`, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    Accept: 'application/json',
                },
                body: JSON.stringify(body),
            });

            const data = await response.json();
            if (data.success) {
                await successToast(data.message);
                window.location.reload();
            } else {
                errorAlert(data.message || 'Failed to update status.');
            }
        } catch {
            errorAlert('An error occurred while updating the status.');
        }
    });
}

function setupDeleteButton() {
    const btn = document.getElementById('deleteLeadBtn');
    if (!btn) return;

    btn.addEventListener('click', async () => {
        const leadId = btn.dataset.leadId;

        const result = await confirmDialog({
            title: 'Delete Lead?',
            text: 'This action cannot be undone. The lead will be permanently removed.',
            icon: 'warning',
            confirmButtonText: 'Yes, delete',
        });

        if (!result.isConfirmed) return;

        const csrfToken = getCsrfToken();

        try {
            const response = await fetch(`/admin/leads/${leadId}`, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    Accept: 'application/json',
                },
            });

            const data = await response.json();
            if (data.success) {
                await successToast(data.message);
                window.location.href = '/admin/leads';
            } else {
                errorAlert(data.message || 'Failed to delete lead.');
            }
        } catch {
            errorAlert('An error occurred while deleting the lead.');
        }
    });
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

document.addEventListener('DOMContentLoaded', () => {
    setupNoteForm();
    setupStatusChange();
    setupDeleteButton();
});
