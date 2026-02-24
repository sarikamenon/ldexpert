/**
 * Common DataTables Initialization Module
 *
 * This module provides a reusable function to initialize DataTables
 * with common configuration options, and server-side support.
 */

import { errorAlert } from './sweetalert';

/**
 * Get CSRF token from layout meta tag.
 * @returns {string|null}
 */
function getCsrfToken() {
    const meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.getAttribute('content') : null;
}

/**
 * Initialize DataTable with common configuration
 *
 * @param {string} selector - jQuery selector for the table
 * @param {Object} customOptions - Custom options to merge with defaults
 * @returns {Promise<Object>} DataTable instance
 */
export function initDataTable(selector, customOptions = {}) {
    // Wait for jQuery and DataTables to be loaded
    return new Promise((resolve, reject) => {
        function checkAndInit() {
            if (typeof window.jQuery !== 'undefined' && typeof window.jQuery.fn.dataTable !== 'undefined') {
                console.log('Initializing DataTable for:', selector);
                
                const defaultOptions = {
                    pageLength: 10,
                    lengthMenu: [5, 10, 25, 50, 100],
                    order: [],
                    columnDefs: [{
                        orderable: false,
                        targets: -1 // Last column (Actions) not sortable
                    }],
                    language: {
                        search: "Search:",
                        lengthMenu: "Show _MENU_ entries",
                        info: "Showing _START_ to _END_ of _TOTAL_ entries",
                        infoEmpty: "Showing 0 to 0 of 0 entries",
                        infoFiltered: "(filtered from _TOTAL_ total entries)",
                        zeroRecords: "No matching records found",
                        emptyTable: "No records found.",
                        paginate: {
                            first: "First",
                            last: "Last",
                            next: "Next",
                            previous: "Previous"
                        }
                    }
                };

                // Merge custom options with defaults
                const options = { ...defaultOptions, ...customOptions };

                try {
                    const table = window.jQuery(selector).DataTable(options);
                    console.log('DataTable initialized successfully');
                    resolve(table);
                } catch (error) {
                    console.error('Error initializing DataTable:', error);
                    reject(error);
                }
            } else {
                console.log('Waiting for jQuery/DataTables...');
                setTimeout(checkAndInit, 100);
            }
        }

        checkAndInit();
    });
}

/**
 * Load DataTables library from CDN
 * 
 * @returns {Promise} Promise that resolves when library is loaded
 */
export function loadDataTablesLibrary() {
    return new Promise((resolve, reject) => {
        // Check if already loaded
        if (typeof window.jQuery !== 'undefined' && typeof window.jQuery.fn.dataTable !== 'undefined') {
            console.log('DataTables already loaded');
            resolve();
            return;
        }

        const script = document.createElement('script');
        script.src = 'https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js';
        script.onload = () => {
            console.log('DataTables script loaded');
            resolve();
        };
        script.onerror = (error) => {
            console.error('Failed to load DataTables:', error);
            reject(error);
        };
        document.body.appendChild(script);
    });
}

/**
 * Initialize DataTable with server-side processing.
 * Uses POST, CSRF token, processing indicator, and optional error callback.
 *
 * @param {string} selector - jQuery selector for the table
 * @param {string} ajaxUrl - URL for the data endpoint
 * @param {Object} customOptions - Additional options (order, pageLength, columnDefs, etc.)
 * @param {Function} [customOptions.getExtraData] - Optional function(d) that adds extra params to the request (e.g. form filters)
 * @returns {Promise<Object>} DataTable instance
 */
export function initServerSideDataTable(selector, ajaxUrl, customOptions = {}) {
    const { getExtraData, ...restOptions } = customOptions;

    const options = {
        serverSide: true,
        processing: true,
        ajax: {
            url: ajaxUrl,
            type: 'POST',
            data: function (d) {
                const token = getCsrfToken();
                if (token) {
                    d._token = token;
                }
                if (typeof getExtraData === 'function') {
                    getExtraData(d);
                }
            },
            error: function () {
                errorAlert('Failed to load table data. Please try again.');
            },
        },
        ...restOptions,
    };

    return initDataTable(selector, options);
}

