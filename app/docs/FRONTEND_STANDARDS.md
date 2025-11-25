# Frontend Development Standards

## User Interactions & Confirmations

### Asset Build Requirement

After completing any task that modifies files under `resources/js`, `resources/css`, or any other frontend asset directories, run `make assets-build` from the repository root. This single command installs/upgrades Node dependencies as needed and executes the Vite production build so the manifest stays in sync before QA or deployment.

### SweetAlert2 Integration

This project uses **SweetAlert2** for all user confirmations, alerts, and dialogs. Never use native browser methods like `alert()`, `confirm()`, or `prompt()`.

#### Installation

SweetAlert2 is already installed via npm:

```bash
npm install sweetalert2
```

#### Usage

Import the helper functions from `resources/js/common/sweetalert.js`:

```javascript
import {
    confirmDialog,
    successToast,
    errorAlert,
    showLoading,
    closeAlert,
} from "../common/sweetalert";
```

#### Available Helper Functions

##### 1. `confirmDialog(options)`

Shows a confirmation dialog with optional input field for collecting user feedback (like a reason).

**Options:**

-   `title` (string): Dialog title (default: "Are you sure?")
-   `text` (string): Dialog description text
-   `icon` (string): Icon type - 'warning', 'error', 'success', 'info', 'question' (default: 'warning')
-   `confirmButtonText` (string): Text for confirm button (default: "Yes")
-   `cancelButtonText` (string): Text for cancel button (default: "Cancel")
-   `showCancelButton` (boolean): Show cancel button (default: true)
-   `showInput` (boolean): Show text input field (default: false)
-   `inputPlaceholder` (string): Placeholder for input field
-   `inputValidator` (function): Custom validator for input field

**Example - Simple Confirmation:**

```javascript
const result = await confirmDialog({
    title: "Delete User?",
    text: "This action cannot be undone.",
    icon: "warning",
    confirmButtonText: "Yes, delete",
});

if (result.isConfirmed) {
    // User confirmed, proceed with deletion
}
```

**Example - Confirmation with Reason Input:**

```javascript
const result = await confirmDialog({
    title: "Deactivate Therapist?",
    text: "You are about to deactivate this therapist.",
    icon: "warning",
    confirmButtonText: "Yes, deactivate",
    showInput: true,
    inputPlaceholder: "Provide a reason to deactivate...",
});

if (result.isConfirmed && result.value) {
    const reason = result.value;
    // Proceed with the action using the reason
}
```

##### 2. `successToast(message, title)`

Shows a success notification as a toast in the top-right corner.

**Parameters:**

-   `message` (string): Success message to display
-   `title` (string): Toast title (default: "Success!")

**Example:**

```javascript
await successToast("Therapist updated successfully!", "Updated!");
```

##### 3. `errorAlert(message, title)`

Shows an error dialog with a red color scheme.

**Parameters:**

-   `message` (string): Error message to display
-   `title` (string): Dialog title (default: "Error!")

**Example:**

```javascript
try {
    await saveData();
} catch (error) {
    errorAlert("Failed to save data. Please try again.");
}
```

##### 4. `showLoading(title)`

Shows a loading indicator. Useful for long-running operations.

**Parameters:**

-   `title` (string): Loading message (default: "Please wait...")

**Example:**

```javascript
showLoading("Processing your request...");

// Perform async operation
await longRunningOperation();

closeAlert(); // Close the loading dialog
```

##### 5. `closeAlert()`

Closes any currently open SweetAlert2 dialog.

---

## Real-World Examples from the Codebase

### Status Toggle with Reason

```javascript
// From admin-therapists-index.js
const result = await confirmDialog({
    title: `${action.charAt(0).toUpperCase() + action.slice(1)} Therapist?`,
    text: `You are about to ${action} this therapist.`,
    icon: "warning",
    confirmButtonText: `Yes, ${action}`,
    showInput: true,
    inputPlaceholder: `Provide a reason to ${action}...`,
});

if (!result.isConfirmed || !result.value) {
    return;
}

// Make API call with reason
const response = await fetch(`/admin/therapists/${id}/status`, {
    method: "PATCH",
    headers: {
        "Content-Type": "application/json",
        "X-CSRF-TOKEN": csrfToken,
    },
    body: JSON.stringify({
        status: nextStatus,
        reason: result.value,
    }),
});

if (response.ok) {
    await successToast("Status updated successfully!");
    window.location.reload();
}
```

### Bulk Actions with Dynamic Messages

```javascript
// From admin-schools-index.js
let title = `${
    action.charAt(0).toUpperCase() + action.slice(1)
} ${count} School(s)?`;
let text = `You are about to ${action} ${count} school(s).`;
let icon = "warning";

if (action === "delete") {
    title = "Delete Schools?";
    text = `WARNING: This will permanently delete ${count} school(s). This action cannot be undone!`;
    icon = "error";
}

const result = await confirmDialog({
    title,
    text,
    icon,
    confirmButtonText: `Yes, ${action}`,
    showInput: requiresReason,
    inputPlaceholder: requiresReason ? "Provide a reason (optional)..." : "",
    inputValidator: null, // Optional for bulk actions
});
```

---

## Styling

SweetAlert2 is configured to work seamlessly with Tailwind CSS:

-   All buttons use `rounded-lg` styling
-   Custom color schemes follow the project's design system
-   Toast notifications appear in the top-right corner
-   Dialogs are centered with appropriate padding

---

## Best Practices

1. **Always use async/await** with SweetAlert2 dialogs to handle user responses properly.

2. **Check for confirmation** before proceeding with destructive actions:

    ```javascript
    if (result.isConfirmed) {
        // Proceed with action
    }
    ```

3. **Validate input** when using `showInput`:

    ```javascript
    if (result.isConfirmed && result.value) {
        // User provided input
    }
    ```

4. **Use appropriate icons**:

    - `warning` - For confirmations or potentially destructive actions
    - `error` - For critical warnings or errors
    - `success` - For successful operations
    - `info` - For informational messages
    - `question` - For questions or prompts

5. **Provide context** in dialog text:

    - Bad: "Are you sure?"
    - Good: "You are about to deactivate this therapist. This action can be reversed later."

6. **Use toast for non-critical feedback**:
    - Use `successToast()` for quick success messages
    - Use `errorAlert()` for errors that require acknowledgment

---

## Migration Guide

If you find any native browser dialogs in the codebase, replace them as follows:

### Replace `alert()`

```javascript
// ❌ Old way
alert("User saved successfully");

// ✅ New way
successToast("User saved successfully");
```

### Replace `confirm()`

```javascript
// ❌ Old way
if (confirm("Delete this user?")) {
    deleteUser();
}

// ✅ New way
const result = await confirmDialog({
    title: "Delete User?",
    text: "This action cannot be undone.",
    icon: "warning",
});

if (result.isConfirmed) {
    deleteUser();
}
```

### Replace `prompt()`

```javascript
// ❌ Old way
const reason = prompt("Provide a reason:");
if (reason) {
    deactivate(reason);
}

// ✅ New way
const result = await confirmDialog({
    title: "Deactivate User?",
    icon: "warning",
    showInput: true,
    inputPlaceholder: "Provide a reason...",
});

if (result.isConfirmed && result.value) {
    deactivate(result.value);
}
```

---

## Resources

-   [SweetAlert2 Official Documentation](https://sweetalert2.github.io/)
-   [SweetAlert2 GitHub Repository](https://github.com/sweetalert2/sweetalert2)
