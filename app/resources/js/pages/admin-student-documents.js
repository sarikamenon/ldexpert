import $ from 'jquery';
import { confirmDialog, errorAlert, successToast } from '../common/sweetalert';

$(() => {
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

    $('.delete-document-btn').on('click', async function handleDelete(event) {
        event.preventDefault();

        const documentId = $(this).data('document-id');
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
                const response = await $.ajax({
                    url: `/admin/student-documents/${documentId}`,
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
