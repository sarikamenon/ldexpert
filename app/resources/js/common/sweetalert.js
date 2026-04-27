import Swal from 'sweetalert2';

/**
 * Show a confirmation dialog
 * @param {Object} options - Dialog options
 * @returns {Promise}
 */
export async function confirmDialog({
    title = 'Are you sure?',
    text = '',
    icon = 'warning',
    confirmButtonText = 'Yes',
    cancelButtonText = 'Cancel',
    showCancelButton = true,
    inputPlaceholder = '',
    showInput = false,
    inputValidator = null,
} = {}) {
    const config = {
        title,
        text,
        icon,
        showCancelButton,
        confirmButtonText,
        cancelButtonText,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        reverseButtons: true,
        customClass: {
            popup: 'rounded-lg',
            confirmButton: 'rounded-lg px-4 py-2',
            cancelButton: 'rounded-lg px-4 py-2',
        },
    };

    if (showInput) {
        config.input = 'text';
        config.inputPlaceholder = inputPlaceholder;
        config.inputValidator = inputValidator || ((value) => {
            if (!value) {
                return 'You need to provide a reason!';
            }
        });
    }

    return await Swal.fire(config);
}

/**
 * Show a success message
 */
export function successToast(message, title = 'Success!') {
    return Swal.fire({
        icon: 'success',
        title,
        text: message,
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true,
        customClass: {
            popup: 'rounded-lg',
        },
    });
}

/**
 * Show an error message
 */
export function errorAlert(message, title = 'Error!') {
    return Swal.fire({
        icon: 'error',
        title,
        text: message,
        confirmButtonText: 'OK',
        confirmButtonColor: '#d33',
        customClass: {
            popup: 'rounded-lg',
            confirmButton: 'rounded-lg px-4 py-2',
        },
    });
}

/**
 * Show a warning dialog with action buttons.
 * @param {Object} options
 * @returns {Promise}
 */
export function actionAlert({
    title = 'Action required',
    text = '',
    icon = 'warning',
    confirmButtonText = 'Continue',
    denyButtonText = '',
    cancelButtonText = 'Close',
    denyButtonColor = undefined,
    cancelButtonColor = undefined,
    showDenyButton = false,
    showCancelButton = true,
    reverseButtons = true,
} = {}) {
    return Swal.fire({
        title,
        text,
        icon,
        showDenyButton,
        showCancelButton,
        confirmButtonText,
        denyButtonText,
        cancelButtonText,
        denyButtonColor,
        cancelButtonColor,
        reverseButtons,
        customClass: {
            popup: 'rounded-lg',
            confirmButton: 'rounded-lg px-4 py-2',
            denyButton: 'rounded-lg px-4 py-2',
            cancelButton: 'rounded-lg px-4 py-2',
        },
    });
}

/**
 * Show a loading indicator
 */
export function showLoading(title = 'Please wait...') {
    Swal.fire({
        title,
        allowOutsideClick: false,
        allowEscapeKey: false,
        showConfirmButton: false,
        didOpen: () => {
            Swal.showLoading();
        },
    });
}

/**
 * Close any open SweetAlert2 dialogs
 */
export function closeAlert() {
    Swal.close();
}

