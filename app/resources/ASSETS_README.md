# Assets Organization Guide

This document explains how CSS and JavaScript assets are organized in this Laravel application.

## Directory Structure

```
resources/
├── css/
│   ├── app.css                      # Main application styles
│   └── common/
│       └── datatables.css           # Reusable DataTables styling
├── js/
│   ├── app.js                       # Main application JavaScript
│   ├── common/
│   │   └── datatables.js            # Reusable DataTables initialization module
│   └── pages/
│       └── students-index.js        # Students list page specific JavaScript
└── views/
    └── therapist/
        └── students/
            └── index.blade.php      # Students list view
```

## File Organization Principles

### Common Files (`common/`)
Place reusable CSS and JavaScript that can be used across multiple pages here.

**Examples:**
- `common/datatables.css` - DataTables styling used on any page with tables
- `common/datatables.js` - DataTables initialization functions
- `common/modals.css` - Modal styling
- `common/forms.js` - Form validation helpers

### Page-Specific Files (`pages/`)
Place JavaScript specific to individual pages here.

**Naming Convention:** `{page-name}.js`

**Examples:**
- `pages/students-index.js` - Students list page
- `pages/students-create.js` - Students create page
- `pages/dashboard.js` - Dashboard page

## Adding New Assets

### 1. Create Your Files

**For common assets:**
```bash
# CSS
resources/css/common/your-component.css

# JavaScript
resources/js/common/your-module.js
```

**For page-specific assets:**
```bash
# JavaScript
resources/js/pages/your-page.js
```

### 2. Update Vite Configuration

Edit `vite.config.js` and add your files to the input array:

```javascript
export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                // Common files
                'resources/css/common/datatables.css',
                'resources/css/common/your-component.css',  // Add here
                // Page-specific files
                'resources/js/pages/students-index.js',
                'resources/js/pages/your-page.js',           // Add here
            ],
            refresh: true,
        }),
    ],
});
```

### 3. Include in Blade Templates

**For CSS:**
```blade
<x-slot name="styles">
    @vite(['resources/css/common/your-component.css'])
</x-slot>
```

**For JavaScript:**
```blade
<x-slot name="scripts">
    @vite(['resources/js/pages/your-page.js'])
</x-slot>
```

### 4. Build Assets

```bash
# Development (with hot reload)
npm run dev

# Production build
npm run build
```

## Using the DataTables Module

### Example: Initialize a table

**In your page-specific JS file (`pages/your-page.js`):**

```javascript
import { loadDataTablesLibrary, initDataTable } from '../common/datatables.js';

async function initYourTable() {
    try {
        await loadDataTablesLibrary();
        await initDataTable('#yourTableId', {
            pageLength: 10,
            order: [[0, 'asc']],
            // Custom options here
        });
    } catch (error) {
        console.error('Failed to initialize table:', error);
    }
}

// Initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initYourTable);
} else {
    initYourTable();
}
```

**In your Blade template:**

```blade
<x-slot name="styles">
    @vite(['resources/css/common/datatables.css'])
</x-slot>

<table id="yourTableId" class="w-full display">
    <!-- Your table content -->
</table>

<x-slot name="scripts">
    @vite(['resources/js/pages/your-page.js'])
</x-slot>
```

## Best Practices

1. **Keep Common Files Generic** - Don't add page-specific logic to common files
2. **Use Module Exports** - Export functions from common JS files for reusability
3. **Minimize Inline Styles/Scripts** - Always prefer external files
4. **Document Complex Functions** - Add JSDoc comments to your functions
5. **Build After Changes** - Remember to run `npm run build` after editing assets
6. **Clear Cache** - Run `php artisan view:clear` if changes don't appear

## Troubleshooting

### Assets not loading?
1. Check Vite config includes your file
2. Run `npm run build`
3. Run `php artisan view:clear`
4. Clear browser cache

### DataTables not initializing?
1. Check console for errors (F12)
2. Verify jQuery is loaded
3. Ensure table has correct ID
4. Check DataTables library loaded successfully

## Development vs Production

**Development:**
```bash
npm run dev
```
- Hot module replacement
- Source maps enabled
- Faster rebuilds

**Production:**
```bash
npm run build
```
- Minified assets
- Optimized for performance
- Hashed filenames for cache busting

