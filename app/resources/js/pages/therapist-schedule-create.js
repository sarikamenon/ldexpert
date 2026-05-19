import { initSelectBoxes } from '../common/select-box';
import { errorAlert } from '../common/sweetalert';

(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', () => {
        // initSelectBoxes is already called by select-box.js, but we call it again
        // to ensure selects on this page are initialized (in case they're added dynamically)
        initSelectBoxes();

        const serviceSelect = document.getElementById('service_id');
        const startTimeInput = document.getElementById('start_time');
        const durationSelect = document.getElementById('duration_minutes');
        const form = document.getElementById('scheduleCreateForm');

        if (form) {
            form.addEventListener('submit', function(e) {
                const startTime = startTimeInput.value;
                const duration = durationSelect?.value;

                if (! startTime) {
                    e.preventDefault();
                    errorAlert('Start time is required.');
                    return;
                }

                // Validate duration presence
                if (! duration) {
                    e.preventDefault();
                    errorAlert('Duration is required.');
                    return;
                }

            });
        }

        if (! serviceSelect) {
            return;
        }

        const mappings = JSON.parse(document.getElementById('student-service-mappings')?.textContent || '[]');
        const serviceOptions = JSON.parse(document.getElementById('service-options-json')?.textContent || '[]');
        const ssaServicesJson = document.getElementById('ssa-services-json');
        const ssaServices = ssaServicesJson ? JSON.parse(ssaServicesJson.textContent || '[]') : null;
        const state = JSON.parse(document.getElementById('schedule-create-state')?.textContent || '{}');

        const mappingByStudent = mappings.reduce((carry, entry) => {
            carry[entry.student_id] = entry.services ?? [];
            return carry;
        }, {});

        const allServices = [...serviceOptions, ...(ssaServices ?? [])];
        const servicesById = allServices.reduce((carry, service) => {
            const id = Number(service.service_id ?? service.id);
            carry[id] = service;
            return carry;
        }, {});




        function getServiceIdsForStudent(studentId) {
            if (! studentId) {
                return new Set();
            }

            const services = mappingByStudent[studentId] || [];
            return new Set(services.map((service) => Number(service.service_id)));
        }

        function renderServiceOptions(serviceIds) {
            serviceSelect.innerHTML = '';

            // If SSA services are provided, use those directly
            if (ssaServices && Array.isArray(ssaServices) && ssaServices.length > 0) {
                serviceSelect.disabled = false;
                const placeholder = document.createElement('option');
                placeholder.value = '';
                placeholder.textContent = 'Select service';
                serviceSelect.appendChild(placeholder);

                ssaServices.forEach((service) => {
                    const option = document.createElement('option');
                    option.value = String(service.service_id);
                    const serviceName = service.service_name || service.name || `Service #${service.service_id}`;
                    option.textContent = service.is_primary ? `${serviceName} (Primary)` : serviceName;
                    serviceSelect.appendChild(option);
                });
                return;
            }

            // Otherwise, use the serviceIds set
            if (! serviceIds || ! serviceIds.size) {
                serviceSelect.disabled = true;
                const option = document.createElement('option');
                option.value = '';
                option.textContent = 'No services available for selected student';
                serviceSelect.appendChild(option);
                return;
            }

            serviceSelect.disabled = false;
            const placeholder = document.createElement('option');
            placeholder.value = '';
            placeholder.textContent = 'Select service';
            serviceSelect.appendChild(placeholder);

            serviceIds.forEach((id) => {
                const metadata = servicesById[id] || {};
                const option = document.createElement('option');
                option.value = String(id);
                option.textContent = metadata.service_name || metadata.name || `Service #${id}`;
                serviceSelect.appendChild(option);
            });
        }

        const recurrenceCard = document.getElementById('recurrence_card');

        function toggleRecurrenceCard() {
            const selectedId = Number(serviceSelect.value);
            const service = servicesById[selectedId];
            const isIndirect = service && service.is_direct_service === false;
            if (recurrenceCard) {
                recurrenceCard.classList.toggle('hidden', isIndirect);
            }
        }

        // select2 triggers a jQuery change event after committing the value;
        // bind via jQuery once it is available on window (loaded async by select-box.js)
        const bindSelect2Change = () => {
            if (window.jQuery) {
                window.jQuery(serviceSelect).on('change', toggleRecurrenceCard);
            } else {
                setTimeout(bindSelect2Change, 50);
            }
        };
        bindSelect2Change();

        function updateServiceOptions() {
            // If SSA services are available, render them directly
            if (ssaServices && Array.isArray(ssaServices) && ssaServices.length > 0) {
                renderServiceOptions(null);
                if (serviceSelect.dataset.initialValue) {
                    serviceSelect.value = serviceSelect.dataset.initialValue;
                    serviceSelect.dataset.initialValue = '';
                }
                return;
            }

            // Otherwise, get services based on student selection (fallback for non-SSA mode)
            const selectedStudent = document.querySelector('[name="student_ids[]"]')?.value;
            const studentId = selectedStudent ? Number(selectedStudent) : null;

            if (! studentId) {
                serviceSelect.innerHTML = '<option value="">Select a service after choosing student</option>';
                serviceSelect.disabled = true;
                return;
            }

            const serviceIds = getServiceIdsForStudent(studentId);
            renderServiceOptions(serviceIds);

            if (serviceSelect.dataset.initialValue) {
                serviceSelect.value = serviceSelect.dataset.initialValue;
                serviceSelect.dataset.initialValue = '';
            }
        }

        // Initialize service options
        serviceSelect.dataset.initialValue = state.selected_service || '';
        updateServiceOptions();
        toggleRecurrenceCard();

        // Sub coverage checkbox toggle
        const requestSubCheckbox = document.getElementById('request_sub');
        const subReasonContainer = document.getElementById('sub_reason_container');
        if (requestSubCheckbox && subReasonContainer) {
            requestSubCheckbox.addEventListener('change', () => {
                subReasonContainer.classList.toggle('hidden', !requestSubCheckbox.checked);
            });
        }
    });
})();

