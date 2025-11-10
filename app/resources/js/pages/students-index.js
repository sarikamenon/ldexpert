/**
 * Students Index Page JavaScript
 * 
 * This file contains page-specific JavaScript for the therapist students list page
 */

import { loadDataTablesLibrary, initDataTable } from '../common/datatables.js';

/**
 * Initialize the students table
 */
async function initStudentsTable() {
    try {
        // Load DataTables library
        await loadDataTablesLibrary();
        
        // Initialize the table with custom options
        await initDataTable('#studentsTable', {
            // Page-specific customizations can go here
            pageLength: 10,
            order: [[0, 'asc']], // Sort by Name column by default
        });
        
        console.log('Students table initialized successfully');
    } catch (error) {
        console.error('Failed to initialize students table:', error);
    }
}

// Initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initStudentsTable);
} else {
    initStudentsTable();
}

