import { confirmDialog, successToast, errorAlert, showLoading, closeAlert } from './sweetalert';

/**
 * Confirm and POST a "Send Login Details" request for the given student ids.
 *
 * @param {string} url - The send-welcome-email endpoint.
 * @param {Array<number|string>} studentIds - One or more student user ids.
 * @returns {Promise<boolean>} true when emails were sent.
 */
export async function sendLoginDetails(url, studentIds) {
    const ids = (studentIds || []).map((id) => Number(id)).filter((id) => Number.isInteger(id));

    if (!ids.length) {
        await errorAlert('Select at least one student.');
        return false;
    }

    const count = ids.length;
    const result = await confirmDialog({
        title: count === 1 ? 'Send login details?' : `Send login details to ${count} students?`,
        text: 'The selected student(s) will receive an email with their username and a link to set their password.',
        icon: 'question',
        confirmButtonText: 'Send',
    });

    if (!result.isConfirmed) {
        return false;
    }

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

    try {
        showLoading('Sending…');

        const response = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken ?? '',
            },
            body: JSON.stringify({ student_ids: ids }),
        });

        const data = await response.json().catch(() => ({}));
        closeAlert();

        if (!response.ok) {
            await errorAlert(data.message || 'Something went wrong while sending the login details.');
            return false;
        }

        await successToast(data.message || 'Login details sent.');
        return true;
    } catch {
        closeAlert();
        await errorAlert('Something went wrong while sending the login details.');
        return false;
    }
}
