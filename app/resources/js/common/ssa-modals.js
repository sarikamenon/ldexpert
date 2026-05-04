import { successToast, errorAlert, showLoading, closeAlert } from './sweetalert';

// ── Helpers ───────────────────────────────────────────────────────────────────

function openModal(modal) {
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    document.body.style.overflow = 'hidden';
}

function closeModal(modal) {
    modal.classList.add('hidden');
    modal.classList.remove('flex');
    document.body.style.overflow = '';
}

async function fetchTherapists(serviceIds, csrfToken) {
    const urlEl = document.getElementById('therapists-for-service-url');
    if (!urlEl) return null;

    const baseUrl = JSON.parse(urlEl.textContent);
    const params = new URLSearchParams();
    serviceIds.forEach((id) => params.append('service_ids[]', id));
    const url = serviceIds.length ? `${baseUrl}?${params.toString()}` : baseUrl;

    const response = await fetch(url, {
        headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken },
    });

    if (!response.ok) return null;
    return response.json();
}

function populateTherapistSelect(select, therapists) {
    select.innerHTML = '<option value="">Select a therapist…</option>';
    therapists.forEach((t) => {
        const opt = document.createElement('option');
        opt.value = t.id;
        opt.textContent = t.name;
        select.appendChild(opt);
    });
}

// ── Assign modal ──────────────────────────────────────────────────────────────

/**
 * @param {{ onSuccess: () => void }} options
 */
export function setupAssignModal({ onSuccess }) {
    const modal = document.getElementById('assignTherapistModal');
    if (!modal) return;

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
    const select = document.getElementById('assignTherapistSelect');
    const errorEl = document.getElementById('assignTherapistError');
    const confirmBtn = document.getElementById('assignModalConfirm');

    let currentSsaId = null;

    function close() {
        closeModal(modal);
        select.innerHTML = '<option value="">Select a therapist…</option>';
        errorEl.textContent = '';
        errorEl.classList.add('hidden');
        currentSsaId = null;
    }

    document.getElementById('assignModalClose')?.addEventListener('click', close);
    document.getElementById('assignModalCancel')?.addEventListener('click', close);
    modal.addEventListener('click', (e) => { if (e.target === modal) close(); });
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && !modal.classList.contains('hidden')) close();
    });

    // Event delegation — works for buttons injected by DataTables too
    document.addEventListener('click', async (e) => {
        const btn = e.target.closest('.assign-therapist-btn');
        if (!btn) return;

        currentSsaId = btn.dataset.ssaId;
        const serviceIds = JSON.parse(btn.dataset.serviceIds || '[]');

        document.getElementById('assignModalStudentName').textContent = btn.dataset.ssaName ?? '—';
        document.getElementById('assignModalServiceName').textContent = btn.dataset.serviceName ?? '—';
        document.getElementById('assignModalStatus').textContent = btn.dataset.ssaStatus ?? '—';

        openModal(modal);
        confirmBtn.disabled = true;
        select.innerHTML = '<option value="">Loading therapists…</option>';

        const therapists = await fetchTherapists(serviceIds, csrfToken);

        if (!therapists || therapists.length === 0) {
            close();
            errorAlert('No therapists available for the services in this SSA.');
            return;
        }

        populateTherapistSelect(select, therapists);
        confirmBtn.disabled = false;
    });

    confirmBtn?.addEventListener('click', async () => {
        errorEl.classList.add('hidden');

        if (!select.value) {
            errorEl.textContent = 'Please select a therapist.';
            errorEl.classList.remove('hidden');
            return;
        }

        confirmBtn.disabled = true;
        showLoading('Assigning therapist…');

        try {
            const response = await fetch(`/admin/ssas/${currentSsaId}/assign-therapist`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    Accept: 'application/json',
                },
                body: JSON.stringify({ therapist_id: select.value }),
            });

            const data = await response.json();

            if (response.ok && data.success) {
                close();
                await successToast(data.message);
                onSuccess();
            } else {
                errorEl.textContent = data.message || 'Failed to assign therapist.';
                errorEl.classList.remove('hidden');
                confirmBtn.disabled = false;
            }
        } catch {
            errorEl.textContent = 'An unexpected error occurred.';
            errorEl.classList.remove('hidden');
            confirmBtn.disabled = false;
        } finally {
            closeAlert();
        }
    });
}

// ── Unassign modal ────────────────────────────────────────────────────────────

/**
 * @param {{ onSuccess: () => void }} options
 */
export function setupUnassignModal({ onSuccess }) {
    const modal = document.getElementById('unassignTherapistModal');
    if (!modal) return;

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
    const reasonInput = document.getElementById('unassignReasonInput');
    const confirmBtn = document.getElementById('unassignModalConfirm');

    let currentSsaId = null;

    function close() {
        closeModal(modal);
        reasonInput.value = '';
        currentSsaId = null;
    }

    document.getElementById('unassignModalClose')?.addEventListener('click', close);
    document.getElementById('unassignModalCancel')?.addEventListener('click', close);
    modal.addEventListener('click', (e) => { if (e.target === modal) close(); });
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && !modal.classList.contains('hidden')) close();
    });

    // Delegated — works for buttons anywhere on the page (list, show header, assignment tab)
    document.addEventListener('click', (e) => {
        const btn = e.target.closest('.unassign-therapist-btn');
        if (!btn) return;

        currentSsaId = btn.dataset.ssaId;
        document.getElementById('unassignModalStudentName').textContent = btn.dataset.ssaName ?? '—';
        document.getElementById('unassignModalServiceName').textContent = btn.dataset.serviceName ?? '—';
        document.getElementById('unassignModalTherapistName').textContent = btn.dataset.therapistName ?? '—';
        openModal(modal);
    });

    confirmBtn?.addEventListener('click', async () => {
        confirmBtn.disabled = true;
        showLoading('Unassigning therapist…');

        try {
            const response = await fetch(`/admin/ssas/${currentSsaId}/unassign-therapist`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    Accept: 'application/json',
                },
                body: JSON.stringify({ reason: reasonInput.value || null }),
            });

            const data = await response.json();

            if (response.ok && data.success) {
                close();
                await successToast(data.message);
                onSuccess();
            } else {
                errorAlert(data.message || 'Failed to unassign therapist.');
                confirmBtn.disabled = false;
            }
        } catch {
            errorAlert('An unexpected error occurred.');
            confirmBtn.disabled = false;
        } finally {
            closeAlert();
        }
    });
}
