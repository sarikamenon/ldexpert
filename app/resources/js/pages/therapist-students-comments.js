import $ from 'jquery';
import { errorAlert, successToast } from '../common/sweetalert';

document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('comment-form');
    const submitButton = document.getElementById('submit-comment-btn');
    const submitText = document.getElementById('submit-text');
    const submitSpinner = document.getElementById('submit-spinner');
    const commentInput = document.getElementById('comment');

    if (!form) {
        return;
    }

    form.addEventListener('submit', async (e) => {
        e.preventDefault();

        const comment = commentInput.value.trim();

        if (!comment) {
            errorAlert('Please enter a comment.');
            return;
        }

        // Show loading state
        submitButton.disabled = true;
        submitText.classList.add('hidden');
        submitSpinner.classList.remove('hidden');

        try {
            const response = await $.ajax({
                url: form.action,
                method: 'POST',
                data: {
                    comment: comment,
                    _token: $('meta[name="csrf-token"]').attr('content'),
                },
                dataType: 'json',
            });

            if (response.success) {
                await successToast(response.message || 'Comment added successfully.');
                // Clear form
                commentInput.value = '';
                // Reload page to show new comment
                window.location.reload();
            } else {
                errorAlert(response.message || 'Failed to add comment. Please try again.');
            }
        } catch (error) {
            console.error('Comment submission error:', error);
            const errorMessage = error.responseJSON?.message || 'An error occurred while adding the comment. Please try again.';
            errorAlert(errorMessage);
        } finally {
            // Reset button state
            submitButton.disabled = false;
            submitText.classList.remove('hidden');
            submitSpinner.classList.add('hidden');
        }
    });
});
