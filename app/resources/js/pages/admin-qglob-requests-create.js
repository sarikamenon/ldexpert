function getCsrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

function parseConfig() {
    const el = document.getElementById('admin-qglob-create-config');
    if (!el?.textContent) {
        return null;
    }
    try {
        return JSON.parse(el.textContent);
    } catch {
        return null;
    }
}

/**
 * @param {HTMLSelectElement} studentSelect
 * @param {{ id: number; name: string }[]} students
 * @param {string|null|undefined} selectedId
 */
function fillStudentOptions(studentSelect, students, selectedId) {
    studentSelect.innerHTML = '';
    const placeholder = document.createElement('option');
    placeholder.value = '';
    placeholder.textContent = students.length ? 'Select a student' : 'No eligible students';
    studentSelect.appendChild(placeholder);

    students.forEach((s) => {
        const opt = document.createElement('option');
        opt.value = String(s.id);
        opt.textContent = s.name;
        if (selectedId && String(s.id) === String(selectedId)) {
            opt.selected = true;
        }
        studentSelect.appendChild(opt);
    });

    studentSelect.disabled = false;
}

async function loadEligibleStudents(url, therapistId, studentSelect, selectedStudentId) {
    if (!therapistId) {
        studentSelect.innerHTML = '<option value="">Select a therapist first</option>';
        studentSelect.disabled = true;
        return;
    }

    studentSelect.disabled = true;
    studentSelect.innerHTML = '<option value="">Loading…</option>';

    const fullUrl = `${url}?${new URLSearchParams({ therapist_id: String(therapistId) })}`;
    const response = await fetch(fullUrl, {
        method: 'GET',
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': getCsrfToken(),
        },
        credentials: 'same-origin',
    });

    if (!response.ok) {
        studentSelect.innerHTML = '<option value="">Unable to load students</option>';
        studentSelect.disabled = true;
        return;
    }

    /** @type {{ students?: { id: number; name: string }[] }} */
    const data = await response.json();
    const students = Array.isArray(data.students) ? data.students : [];
    fillStudentOptions(studentSelect, students, selectedStudentId);
}

document.addEventListener('DOMContentLoaded', () => {
    const config = parseConfig();
    const therapistSelect = document.getElementById('therapist_id');
    const studentSelect = document.getElementById('student_id');
    if (!config?.eligibleStudentsUrl || !therapistSelect || !(studentSelect instanceof HTMLSelectElement)) {
        return;
    }

    therapistSelect.addEventListener('change', () => {
        void loadEligibleStudents(config.eligibleStudentsUrl, therapistSelect.value, studentSelect, null);
    });

    if (config.oldTherapistId) {
        therapistSelect.value = String(config.oldTherapistId);
        void loadEligibleStudents(
            config.eligibleStudentsUrl,
            config.oldTherapistId,
            studentSelect,
            config.oldStudentId,
        );
    }
});
