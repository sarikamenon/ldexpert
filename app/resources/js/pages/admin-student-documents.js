import $ from 'jquery';
import { confirmDialog, errorAlert, successToast } from '../common/sweetalert';

document.addEventListener('DOMContentLoaded', () => {
    // Handle document deletion
    document.querySelectorAll('.delete-document-btn').forEach((button) => {
        button.addEventListener('click', async (e) => {
            e.preventDefault();

            const documentId = button.dataset.documentId;
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
