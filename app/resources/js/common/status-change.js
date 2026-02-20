import { confirmDialog, successToast, errorAlert, showLoading, closeAlert } from './sweetalert';

/**
 * Configuration for different entity types
 */
const entityConfigs = {
    school: {
        routePrefix: '/admin/schools',
        entityName: 'School',
        useFormSubmission: false,
        statusLabels: {
            active: 'Activate',
            inactive: 'Deactivate',
        },
    },
    student: {
        routePrefix: '/admin/students',
        entityName: 'Student',
        useFormSubmission: false,
        statusLabels: {
            active: 'Activate',
            inactive: 'Deactivate',
        },
    },
    therapist: {
        routePrefix: '/admin/therapists',
        entityName: 'Therapist',
        useFormSubmission: false,
        statusLabels: {
            active: 'Activate',
            inactive: 'Deactivate',
        },
    },
    ssa: {
        routePrefix: '/admin/ssas',
        entityName: 'SSA',
        useFormSubmission: false,
        statusLabels: {
            active: 'Activate',
            completed: 'Complete',
            deactivated: 'Deactivate',
            pending: 'Pending',
        },
        requiresReason: (status) => status === 'completed' || status === 'deactivated',
        reasonText: (status) => {
            if (status === 'completed') {
                return 'Service has been completed successfully.';
            }
            return 'Student has left in between of service.';
        },
    },
};

/**
 * Setup status change buttons for a specific entity type
 * @param {string} entityType - The entity type (school, student, therapist, ssa)
 * @param {string} selector - CSS selector for the status change buttons (default: '.change-status-btn')
 * @param {Object} options - Additional options
 * @param {string} options.idAttribute - Data attribute name for the entity ID (e.g., 'school-id', 'student-id')
 */
export function setupStatusChanges(entityType, selector = '.change-status-btn', options = {}) {
    const config = entityConfigs[entityType];
    if (!config) {
        console.error(`Unknown entity type: ${entityType}`);
        return;
    }

    const buttons = document.querySelectorAll(selector);
    if (buttons.length === 0) {
        return;
    }

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
    if (!csrfToken) {
        console.error('CSRF token not found');
        return;
    }

    // Determine ID attribute name
    const idAttribute = options.idAttribute || `${entityType}-id`;
    
    buttons.forEach((button) => {
        button.addEventListener('click', async () => {
            // Get entity ID from various possible data attributes
            const entityId = button.dataset[idAttribute] || 
                           button.dataset[`${entityType}Id`] || 
                           button.dataset[`${entityType}_id`] ||
                           button.dataset[entityType];
            
            if (!entityId) {
                console.error(`Entity ID not found for ${entityType}`);
                return;
            }

            // Get status from button
            const status = button.dataset.status;
            if (!status) {
                console.error('Status not found in button dataset');
                return;
            }

            // Get action label
            const action = config.statusLabels[status] || status.charAt(0).toUpperCase() + status.slice(1);
            const actionLower = action.toLowerCase();

            // Determine if reason is required
            const requiresReason = config.requiresReason 
                ? config.requiresReason(status) 
                : true; // Default to requiring reason

            // Get reason text if available
            const reasonText = config.reasonText 
                ? config.reasonText(status) 
                : `Provide a reason to ${actionLower}...`;

            // Show confirmation dialog
            const isOptionalReason = entityType === 'ssa' && requiresReason;
            const result = await confirmDialog({
                title: `${action} ${config.entityName}?`,
                text: requiresReason 
                    ? `You are about to ${actionLower} this ${config.entityName.toLowerCase()}. ${isOptionalReason ? 'Please provide a reason (optional).' : 'Please provide a reason.'}`
                    : `You are about to ${actionLower} this ${config.entityName.toLowerCase()}.`,
                icon: 'warning',
                confirmButtonText: `Yes, ${actionLower}`,
                showInput: requiresReason,
                inputPlaceholder: requiresReason ? (isOptionalReason ? `Reason (optional, e.g., ${reasonText})` : reasonText) : '',
                inputValidator: requiresReason ? () => null : undefined, // Optional reason - return null means valid
            });

            if (!result.isConfirmed) {
                return;
            }

            // Validate reason if required (SSA allows optional reason for completed/deactivated)
            if (requiresReason && !result.value && entityType !== 'ssa') {
                errorAlert('Please provide a reason for this status change.');
                return;
            }

            // Handle form submission (for schools)
            if (config.useFormSubmission) {
                try {
                    showLoading(`Updating ${config.entityName.toLowerCase()} status...`);
                    
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = `${config.routePrefix}/${entityId}/status`;
                    
                    const methodInput = document.createElement('input');
                    methodInput.type = 'hidden';
                    methodInput.name = '_method';
                    methodInput.value = 'PATCH';
                    form.appendChild(methodInput);
                    
                    const statusInput = document.createElement('input');
                    statusInput.type = 'hidden';
                    statusInput.name = 'status';
                    statusInput.value = status;
                    form.appendChild(statusInput);
                    
                    const reasonInput = document.createElement('input');
                    reasonInput.type = 'hidden';
                    reasonInput.name = 'reason';
                    reasonInput.value = result.value || '';
                    form.appendChild(reasonInput);
                    
                    const csrfInput = document.createElement('input');
                    csrfInput.type = 'hidden';
                    csrfInput.name = '_token';
                    csrfInput.value = csrfToken;
                    form.appendChild(csrfInput);
                    
                    document.body.appendChild(form);
                    form.submit();
                } catch (error) {
                    console.error(`Failed to update ${config.entityName.toLowerCase()} status`, error);
                    closeAlert();
                    errorAlert('An unexpected error occurred.');
                }
                return;
            }

            // Handle AJAX submission (for students, therapists, SSAs)
            try {
                showLoading(`Updating ${config.entityName.toLowerCase()} status...`);
                
                const response = await fetch(`${config.routePrefix}/${entityId}/status`, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        Accept: 'application/json',
                    },
                    body: JSON.stringify({
                        status,
                        reason: result.value || null,
                    }),
                });

                const data = await response.json();

                if (response.ok && data.success) {
                    await successToast(data.message || `${config.entityName} ${actionLower}d successfully.`);
                    window.location.reload();
                } else {
                    errorAlert(data.message || `Failed to update ${config.entityName.toLowerCase()} status.`);
                }
            } catch (error) {
                console.error(`Failed to update ${config.entityName.toLowerCase()} status`, error);
                errorAlert('An unexpected error occurred.');
            } finally {
                closeAlert();
            }
        });
    });
}

/**
 * Setup status toggles for index pages (toggles between active/inactive)
 * @param {string} entityType - The entity type (school, student, therapist)
 * @param {string} selector - CSS selector for the toggle buttons
 * @param {Object} options - Additional options
 */
export function setupStatusToggles(entityType, selector, options = {}) {
    const config = entityConfigs[entityType];
    if (!config) {
        console.error(`Unknown entity type: ${entityType}`);
        return;
    }

    const buttons = document.querySelectorAll(selector);
    if (buttons.length === 0) {
        return;
    }

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
    if (!csrfToken) {
        console.error('CSRF token not found');
        return;
    }

    // Determine ID attribute name
    const idAttribute = options.idAttribute || entityType;
    
    buttons.forEach((button) => {
        button.addEventListener('click', async () => {
            // Get entity ID
            const entityId = button.dataset[idAttribute] || 
                           button.dataset[`${entityType}Id`] || 
                           button.dataset[`${entityType}_id`];
            
            if (!entityId) {
                console.error(`Entity ID not found for ${entityType}`);
                return;
            }

            // Get current status and calculate next status
            const currentStatus = button.dataset.status;
            const nextStatus = currentStatus === 'active' ? 'inactive' : 'active';
            const action = nextStatus === 'active' ? 'activate' : 'deactivate';

            // Show confirmation dialog
            const result = await confirmDialog({
                title: `${action.charAt(0).toUpperCase() + action.slice(1)} ${config.entityName}?`,
                text: `You are about to ${action} this ${config.entityName.toLowerCase()}.`,
                icon: 'warning',
                confirmButtonText: `Yes, ${action}`,
                showInput: true,
                inputPlaceholder: `Provide a reason to ${action}...`,
            });

            if (!result.isConfirmed || !result.value) {
                return;
            }

            // Handle form submission (for schools)
            if (config.useFormSubmission) {
                const statusForm = document.getElementById(`${entityType}StatusForm`);
                const statusInput = document.getElementById('statusInput');
                const statusReasonInput = document.getElementById('statusReasonInput');

                if (statusForm && statusInput && statusReasonInput) {
                    statusInput.value = nextStatus;
                    statusReasonInput.value = result.value;
                    statusForm.action = `${config.routePrefix}/${entityId}/status`;
                    statusForm.submit();
                } else {
                    console.error('Status form elements not found');
                }
                return;
            }

            // Handle AJAX submission
            try {
                const response = await fetch(`${config.routePrefix}/${entityId}/status`, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        Accept: 'application/json',
                    },
                    body: JSON.stringify({
                        status: nextStatus,
                        reason: result.value,
                    }),
                });

                const data = await response.json();

                if (data.success) {
                    await successToast(data.message || `${config.entityName} ${action}d successfully.`);
                    window.location.reload();
                } else {
                    errorAlert(data.message || `Failed to update ${config.entityName.toLowerCase()} status.`);
                }
            } catch (error) {
                console.error(`Failed to update ${config.entityName.toLowerCase()} status`, error);
                errorAlert(`An error occurred while updating ${config.entityName.toLowerCase()} status.`);
            }
        });
    });
}

