import { initSelectBoxes } from '../common/select-box';

(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', () => {
        initSelectBoxes();

        const studentSelect = document.getElementById('student_ids');
        const serviceSelect = document.getElementById('service_id');
        const scheduleDateInput = document.getElementById('schedule_date');
        const startTimeInput = document.getElementById('start_time');
        const endTimeInput = document.getElementById('end_time');
        const notesInput = document.getElementById('notes');
        const recurrenceType = document.getElementById('recurrence_type');
        const occurrenceCount = document.getElementById('occurrence_count');
        const occurrenceWrapper = document.getElementById('occurrenceCountWrapper');
        const recurrenceEndWrapper = document.getElementById('recurrenceEndWrapper');
        const recurrencePreview = document.getElementById('recurrence_end_preview');
        const recurrenceEndInput = document.getElementById('recurrence_end_date');
        const groupBadge = document.getElementById('groupBadge');
        const summaryStudents = document.getElementById('summaryStudents');
        const summaryService = document.getElementById('summaryService');
        const summaryRecurrence = document.getElementById('summaryRecurrence');
        const summaryDate = document.getElementById('summaryDate');

        if (! studentSelect || ! serviceSelect) {
            return;
        }

        const mappings = JSON.parse(document.getElementById('student-service-mappings')?.textContent || '[]');
        const serviceOptions = JSON.parse(document.getElementById('service-options-json')?.textContent || '[]');
        const state = JSON.parse(document.getElementById('schedule-create-state')?.textContent || '{}');

        const mappingByStudent = mappings.reduce((carry, entry) => {
            carry[entry.student_id] = entry.services ?? [];
            return carry;
        }, {});

        const servicesById = serviceOptions.reduce((carry, service) => {
            const id = Number(service.service_id ?? service.id);
            carry[id] = service;
            return carry;
        }, {});

        function unique(values) {
            return [...new Set(values)];
        }

        function formatDate(date) {
            return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
        }

        function addInterval(date, type) {
            const next = new Date(date);
            switch (type) {
                case 'daily':
                    next.setDate(next.getDate() + 1);
                    break;
                case 'weekly':
                    next.setDate(next.getDate() + 7);
                    break;
                case 'bi_weekly':
                    next.setDate(next.getDate() + 14);
                    break;
                case 'monthly':
                    next.setMonth(next.getMonth() + 1);
                    break;
                default:
                    break;
            }
            return next;
        }

        function updateSummary() {
            const studentNames = Array.from(studentSelect.selectedOptions).map((option) => option.text);
            summaryStudents.textContent = studentNames.length
                ? `Students: ${studentNames.join(', ')}`
                : 'Students: —';

            summaryService.textContent = `Service: ${serviceSelect.selectedOptions[0]?.text || '—'}`;

            const type = recurrenceType.value;
            const count = occurrenceCount.value;
            summaryRecurrence.textContent = type === 'none'
                ? 'Recurrence: Does not repeat'
                : `Recurrence: ${type.replace('_', ' ')} · ${count ? `${count} occurrences` : 'occurrence count required'}`;

            if (scheduleDateInput.value) {
                const date = new Date(`${scheduleDateInput.value}T00:00:00`);
                summaryDate.textContent = `Date: ${date.toLocaleDateString('en-US', {
                    month: 'long',
                    day: 'numeric',
                    year: 'numeric',
                })}`;
            }
        }

        function updateRecurrencePreview() {
            const type = recurrenceType.value;
            const count = Number(occurrenceCount.value || 0);
            const startValue = scheduleDateInput.value;

            if (! startValue || type === 'none' || count < 2) {
                recurrenceEndInput.value = '';
                recurrencePreview.textContent = type === 'none'
                    ? 'Does not repeat.'
                    : 'Enter a recurrence count (≥ 2) to calculate end date.';
                updateSummary();
                return;
            }

            let cursor = new Date(`${startValue}T00:00:00`);
            for (let i = 1; i < count; i += 1) {
                cursor = addInterval(cursor, type);
            }

            recurrenceEndInput.value = formatDate(cursor);
            recurrencePreview.textContent = `Repeats ${count} times, ending on ${cursor.toLocaleDateString('en-US', {
                month: 'long',
                day: 'numeric',
                year: 'numeric',
            })}.`;

            updateSummary();
        }

        function toggleRecurrenceFields() {
            const type = recurrenceType.value;
            const shouldShow = type !== 'none';

            occurrenceWrapper.style.display = shouldShow ? 'block' : 'none';
            recurrenceEndWrapper.style.display = shouldShow ? 'block' : 'none';

            if (! shouldShow) {
                occurrenceCount.value = '';
                recurrenceEndInput.value = '';
                recurrencePreview.textContent = 'Does not repeat.';
            } else {
                updateRecurrencePreview();
            }
        }

        function getSharedServiceIds(studentIds) {
            if (! studentIds.length) {
                return new Set();
            }

            return studentIds.reduce((shared, studentId, index) => {
                const services = mappingByStudent[studentId] || [];
                const ids = services.map((service) => Number(service.service_id));

                if (index === 0) {
                    return new Set(ids);
                }

                return new Set(ids.filter((id) => shared.has(id)));
            }, new Set());
        }

        function renderServiceOptions(sharedIds) {
            serviceSelect.innerHTML = '';

            if (! sharedIds.size) {
                serviceSelect.disabled = true;
                const option = document.createElement('option');
                option.value = '';
                option.textContent = 'No shared services available for selected students';
                serviceSelect.appendChild(option);
                return;
            }

            serviceSelect.disabled = false;
            const placeholder = document.createElement('option');
            placeholder.value = '';
            placeholder.textContent = 'Select service';
            serviceSelect.appendChild(placeholder);

            sharedIds.forEach((id) => {
                const metadata = servicesById[id] || {};
                const option = document.createElement('option');
                option.value = String(id);
                option.textContent = metadata.service_name || metadata.name || `Service #${id}`;
                serviceSelect.appendChild(option);
            });
        }

        function updateServiceOptions() {
            const selectedStudents = Array.from(studentSelect.selectedOptions).map((option) => Number(option.value));

            if (! selectedStudents.length) {
                serviceSelect.innerHTML = '<option value="">Select a service after choosing students</option>';
                serviceSelect.disabled = true;
                groupBadge.style.display = 'none';
                updateSummary();
                return;
            }

            groupBadge.style.display = selectedStudents.length > 1 ? 'inline-flex' : 'none';

            const sharedIds = getSharedServiceIds(selectedStudents);
            renderServiceOptions(sharedIds);

            if (serviceSelect.dataset.initialValue) {
                serviceSelect.value = serviceSelect.dataset.initialValue;
                serviceSelect.dataset.initialValue = '';
            }

            updateSummary();
        }

        studentSelect.addEventListener('change', () => {
            serviceSelect.dataset.initialValue = '';
            updateServiceOptions();
        });

        serviceSelect.addEventListener('change', updateSummary);

        if (scheduleDateInput) {
            scheduleDateInput.addEventListener('change', () => {
                updateSummary();
                updateRecurrencePreview();
            });
        }

        [startTimeInput, endTimeInput, notesInput].forEach((input) => {
            if (input) {
                input.addEventListener('input', updateSummary);
            }
        });

        recurrenceType.addEventListener('change', toggleRecurrenceFields);
        occurrenceCount.addEventListener('input', updateRecurrencePreview);

        if (state.selected_students?.length) {
            $(studentSelect).val(unique(state.selected_students.map((id) => String(id)))).trigger('change');
        }

        serviceSelect.dataset.initialValue = state.selected_service || '';
        updateServiceOptions();

        if (state.recurrence_type) {
            recurrenceType.value = state.recurrence_type;
        }

        if (state.occurrence_count) {
            occurrenceCount.value = state.occurrence_count;
        }

        toggleRecurrenceFields();
        updateRecurrencePreview();
        updateSummary();
    });
})();

