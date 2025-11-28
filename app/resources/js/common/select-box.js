import $ from 'jquery';

// Ensure Select2 registers against the same jQuery instance everywhere.
if (!window.jQuery) {
    window.jQuery = $;
}

if (!window.$) {
    window.$ = $;
}

import 'select2/dist/js/select2.full.js';
import 'select2/dist/css/select2.css';

const DATA_ATTRIBUTE = '[data-select-box]';

const toBoolean = (value, fallback = false) => {
    if (value === undefined || value === null || value === '') {
        return fallback;
    }

    if (typeof value === 'boolean') {
        return value;
    }

    return String(value).toLowerCase() === 'true';
};

const initializeSelect = (element) => {
    const $el = window.jQuery(element);

    if (!$el.length || $el.data('select-initialized')) {
        return;
    }

    const {
        placeholder,
        width = '100%',
        dropdownParent,
        noResults,
        searchingMessage,
        tags,
        searchable,
        allowClear,
    } = element.dataset;

    const config = {
        width,
        placeholder: placeholder ?? '',
        allowClear: toBoolean(allowClear, !!placeholder && !element.hasAttribute('multiple')),
        tags: toBoolean(tags),
        dropdownParent: dropdownParent ? window.jQuery(dropdownParent) : undefined,
        minimumResultsForSearch: toBoolean(searchable, true) ? 0 : Infinity,
        language: {
            noResults: () => noResults ?? 'No results found',
            searching: () => searchingMessage ?? 'Searching...',
        },
    };

    $el.select2(config);
    $el.data('select-initialized', true);

    if (element.form) {
        element.form.addEventListener('reset', () => {
            // Delay to allow the browser to reset the native select first
            window.setTimeout(() => {
                const value = element.hasAttribute('multiple') ? [] : null;
                $el.val(value).trigger('change');
            }, 0);
        });
    }
};

export const initSelectBoxes = () => {
    if (!window?.jQuery) {
        console.warn('Select2 requires jQuery to be loaded before initialization.');
        return;
    }

    window.jQuery(DATA_ATTRIBUTE).each((_, element) => initializeSelect(element));
};

document.addEventListener('DOMContentLoaded', () => initSelectBoxes());

// Allow pages that dynamically inject selects to re-run initialization.
window.initSelectBoxes = initSelectBoxes;

