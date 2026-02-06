import $ from 'jquery';
import { confirmDialog, errorAlert, successToast } from '../common/sweetalert';

$(() => {
    const $form = $('#document-upload-form');
    const $submitButton = $('#submit-document-btn');
    const $submitText = $('#submit-text');
    const $submitSpinner = $('#submit-spinner');
    const $toggleButton = $('#toggle-document-upload');
    const $uploadCard = $('#document-upload-card');

    if ($toggleButton.length && $uploadCard.length) {
        const setExpandedState = (isExpanded) => {
            $toggleButton.attr('aria-expanded', isExpanded ? 'true' : 'false');
            $toggleButton.find('[data-label]').text(isExpanded ? 'Hide Upload' : 'Add Document');
            $uploadCard.toggleClass('hidden', !isExpanded);
        };

        setExpandedState(! $uploadCard.hasClass('hidden'));

        $toggleButton.on('click', (event) => {
            event.preventDefault();
            setExpandedState($uploadCard.hasClass('hidden'));
        });
    }

    if ($form.length) {
        $form.on('submit', async (event) => {
            event.preventDefault();

            const formData = new FormData($form.get(0));

            $submitButton.prop('disabled', true);
            $submitText.addClass('hidden');
            $submitSpinner.removeClass('hidden');

            try {
                const response = await $.ajax({
                    url: $form.attr('action'),
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
                    window.location.reload();
                } else {
                    errorAlert(response.message || 'Failed to upload document. Please try again.');
                }
            } catch (error) {
                console.error('Document upload error:', error);
                const errorMessage = error.responseJSON?.message || 'An error occurred while uploading the document. Please try again.';
                errorAlert(errorMessage);
            } finally {
                $submitButton.prop('disabled', false);
                $submitText.removeClass('hidden');
                $submitSpinner.addClass('hidden');
            }
        });
    }

    $('.delete-document-btn').on('click', async function handleDelete(event) {
        event.preventDefault();

        const documentId = $(this).data('document-id');
        const sessionLogId = $(this).data('session-log-id');
        if (!documentId) {
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
                const isTherapistStudentDocument = $(this).data('therapist-student-document');
                const url = sessionLogId
                    ? `/therapist/session-logs/${sessionLogId}/documents/${documentId}`
                    : (isTherapistStudentDocument
                        ? `/therapist/student-documents/${documentId}`
                        : `/admin/student-documents/${documentId}`);
                const response = await $.ajax({
                    url: url,
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                    },
                    dataType: 'json',
                });

                if (response.success) {
                    await successToast(response.message || 'Document deleted successfully.');
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
