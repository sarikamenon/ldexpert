import $ from 'jquery';
import { confirmDialog, errorAlert, successToast } from '../common/sweetalert';

document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('session-log-document-upload-form');
    const submitButton = document.getElementById('submit-document-btn');
    const submitText = document.getElementById('submit-text');
    const submitSpinner = document.getElementById('submit-spinner');

    if (!form) {
        return;
    }

    form.addEventListener('submit', async (e) => {
        e.preventDefault();

        const formData = new FormData(form);

        // Show loading state
        submitButton.disabled = true;
        submitText.classList.add('hidden');
        submitSpinner.classList.remove('hidden');

        try {
            const response = await $.ajax({
                url: form.action,
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                },
                dataType: 'json',
            });

            if (response.success) {
                await successToast(response.message || 'Document uploaded successfully.');
                // Reload page to show new document
                window.location.reload();
            } else {
                errorAlert(response.message || 'Failed to upload document. Please try again.');
            }
        } catch (error) {
            console.error('Document upload error:', error);
            const errorMessage = error.responseJSON?.message || 'An error occurred while uploading the document. Please try again.';
            errorAlert(errorMessage);
        } finally {
            // Reset button state
            submitButton.disabled = false;
            submitText.classList.remove('hidden');
            submitSpinner.classList.add('hidden');
        }
    });

    // Handle document deletion
    document.querySelectorAll('.delete-document-btn').forEach((button) => {
        button.addEventListener('click', async (e) => {
            e.preventDefault();

            const documentId = button.dataset.documentId;
            const sessionLogId = button.dataset.sessionLogId;
            if (!documentId || !sessionLogId) {
                return;
            }

            const result = await confirmDialog({
                title: 'Delete Document?',
                text: 'Are you sure you want to delete this document? This action cannot be undone.',
                icon: 'warning',
                confirmButtonText: 'Yes, delete',
            });

            if (result.isConfirmed) {
                try {
                    const response = await $.ajax({
                        url: `/therapist/session-logs/${sessionLogId}/documents/${documentId}`,
                        method: 'DELETE',
                        headers: {
                            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                        },
                        dataType: 'json',
                    });

                    if (response.success) {
                        await successToast(response.message || 'Document deleted successfully.');
                        // Reload page to refresh list
                        window.location.reload();
                    } else {
                        errorAlert(response.message || 'Failed to delete document. Please try again.');
                    }
                } catch (error) {
                    console.error('Document deletion error:', error);
                    const errorMessage = error.responseJSON?.message || 'An error occurred while deleting the document. Please try again.';
                    errorAlert(errorMessage);
                }
            }
        });
    });
});
