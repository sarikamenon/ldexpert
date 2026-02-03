import $ from 'jquery';

// Set jQuery on window for select2 to use
window.jQuery = $;
window.$ = $;

// Import select2 CSS from local package
import 'select2/dist/css/select2.css';

const DATA_ATTRIBUTE = '[data-select-box]';

// Track select2 loading state
let select2Loaded = false;
let select2Loading = false;
let select2LoadPromise = null;

/**
 * Load Select2 library from CDN
 * This ensures proper UMD registration with jQuery
 * 
 * @returns {Promise} Promise that resolves when library is loaded
 */
const loadSelect2 = () => {
    // Return existing promise if already loading
    if (select2LoadPromise) {
        return select2LoadPromise;
    }

    // Return resolved promise if already loaded
    if (select2Loaded && typeof window.jQuery?.fn?.select2 === 'function') {
        return Promise.resolve();
    }

    select2Loading = true;
    select2LoadPromise = new Promise((resolve, reject) => {
        // Check if already loaded
        if (typeof window.jQuery?.fn?.select2 === 'function') {
            select2Loaded = true;
            select2Loading = false;
            resolve();
            return;
        }

        // Load CSS link if not already present
        if (!document.querySelector('link[href*="select2"]')) {
            const link = document.createElement('link');
            link.rel = 'stylesheet';
            link.href = 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css';
            document.head.appendChild(link);
        }

        // Load select2 script from CDN
        const script = document.createElement('script');
        script.src = 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.full.min.js';
        script.onload = () => {
            select2Loaded = true;
            select2Loading = false;
            // Small delay to ensure select2 has registered
            setTimeout(() => {
                resolve();
            }, 10);
        };
        script.onerror = (error) => {
            select2Loading = false;
            select2LoadPromise = null;
            console.error('Failed to load Select2 from CDN:', error);
            reject(error);
        };
        document.body.appendChild(script);
    });

    return select2LoadPromise;
};

// Track initialization state to prevent infinite retries
let isInitializing = false;
let maxRetries = 10; // Maximum 1 second of retries (10 * 100ms)
let retryCount = 0;

const toBoolean = (value, fallback = false) => {
    if (value === undefined || value === null || value === '') {
        return fallback;
    }

    if (typeof value === 'boolean') {
        return value;
    }

    return String(value).toLowerCase() === 'true';
};

/**
 * Determine the minimumResultsForSearch value based on searchable mode and threshold.
 * 
 * @param {string|boolean} searchable - 'auto', 'true', 'false', true, or false
 * @param {string|number} threshold - The threshold for auto mode (default: 10)
 * @returns {number} - 0 (always show search), Infinity (never show), or threshold number
 */
const getMinimumResultsForSearch = (searchable, threshold = 10) => {
    // Auto mode: show search only when options exceed threshold
    if (searchable === 'auto' || searchable === undefined || searchable === null || searchable === '') {
        return parseInt(threshold, 10) || 10;
    }
    // Explicit true: always show search
    // Explicit false: never show search
    return toBoolean(searchable) ? 0 : Infinity;
};

const initializeSelect = (element) => {
    const $el = window.jQuery(element);

    if (!$el.length || $el.data('select-initialized')) {
        return;
    }

    // Ensure select2 is available
    if (typeof $el.select2 !== 'function') {
        return;
    }

    const {
        placeholder: dataPlaceholder,
        width = '100%',
        dropdownParent,
        noResults,
        searchingMessage,
        tags,
        searchable,
        searchThreshold,
        allowClear,
        inline,
    } = element.dataset;

    // Use data-placeholder, or first empty option's text so Select2 shows default text when nothing selected
    let placeholder = dataPlaceholder ?? '';
    if (placeholder === '' && element.options.length > 0 && element.options[0].value === '') {
        placeholder = element.options[0].textContent.trim() || '';
    }

    // Handle 'fit' width for inline selects - use 'style' and add CSS width
    let selectWidth = width;
    if (width === 'fit' || inline === 'true') {
        // For inline/fit width, use 'style' and let CSS handle it
        selectWidth = 'style';
        // Add inline style for auto width
        element.style.width = 'auto';
    }

    const config = {
        width: selectWidth,
        placeholder,
        allowClear: toBoolean(allowClear, !!placeholder && !element.hasAttribute('multiple')),
        tags: toBoolean(tags),
        dropdownParent: dropdownParent ? window.jQuery(dropdownParent) : undefined,
        minimumResultsForSearch: getMinimumResultsForSearch(searchable, searchThreshold),
        language: {
            noResults: () => noResults ?? 'No results found',
            searching: () => searchingMessage ?? 'Searching...',
        },
    };

    try {
        $el.select2(config);
        $el.data('select-initialized', true);

        // For inline selects, add class to container and copy width/flex classes
        if (inline === 'true' || width === 'fit') {
            const $container = $el.next('.select2-container');
            $container.addClass('select2-container--inline');
            
            // Copy width, min-width, max-width, and flex classes from original select
            const selectClasses = element.className.split(' ');
            const widthClasses = selectClasses.filter(cls => 
                cls.startsWith('w-') ||
                cls.startsWith('min-w-') || 
                cls.startsWith('max-w-') || 
                cls.startsWith('flex-') ||
                cls === 'flex-1'
            );
            if (widthClasses.length > 0) {
                $container.addClass(widthClasses.join(' '));
            }
        }

        if (element.form) {
            element.form.addEventListener('reset', () => {
                // Delay to allow the browser to reset the native select first
                window.setTimeout(() => {
                    const value = element.hasAttribute('multiple') ? [] : null;
                    $el.val(value).trigger('change');
                }, 0);
            });
        }
    } catch (error) {
        console.error('Error initializing Select2:', error);
    }
};

export const initSelectBoxes = async () => {
    if (!window?.jQuery) {
        console.warn('Select2 requires jQuery to be loaded before initialization.');
        return;
    }

    // Load select2 from CDN if not already loaded
    try {
        await loadSelect2();
    } catch (error) {
        console.error('Failed to load Select2:', error);
        return;
    }

    // Check if select2 plugin is available
    if (typeof window.jQuery.fn.select2 !== 'function') {
        // Only retry if we haven't exceeded max retries and aren't already initializing
        if (!isInitializing && retryCount < maxRetries) {
            isInitializing = true;
            retryCount++;
            setTimeout(() => {
                isInitializing = false;
                initSelectBoxes();
            }, 100);
        } else if (retryCount >= maxRetries) {
            console.error('Select2 plugin failed to load after maximum retries.');
            console.error('Debug info:', {
                jQuery: !!window.jQuery,
                jQueryFn: !!window.jQuery?.fn,
                select2: typeof window.jQuery?.fn?.select2,
                select2Loaded,
                select2Loading
            });
        }
        return;
    }

    // Reset retry count on success
    retryCount = 0;
    isInitializing = false;

    // Initialize all select boxes
    window.jQuery(DATA_ATTRIBUTE).each((_, element) => initializeSelect(element));
};

// Wait for DOM to be ready before initializing
const initializeWhenReady = () => {
    const doInit = () => {
        initSelectBoxes().catch(error => {
            console.error('Error initializing select boxes:', error);
        });
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', doInit);
    } else {
        // DOM is already ready
        doInit();
    }
};

// Start initialization
initializeWhenReady();

// Allow pages that dynamically inject selects to re-run initialization.
window.initSelectBoxes = initSelectBoxes;

