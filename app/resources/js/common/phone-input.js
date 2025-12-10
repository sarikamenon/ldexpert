/**
 * Phone input mask utility
 * Restricts input to digits and dashes only (US phone format)
 */

/**
 * Initialize phone input masking on all elements with data-phone-input attribute
 */
export function initPhoneInputs() {
    const phoneInputs = document.querySelectorAll('[data-phone-input]');
    
    phoneInputs.forEach(input => {
        // Prevent non-digit and non-dash characters
        input.addEventListener('keydown', (e) => {
            // Allow: backspace, delete, tab, escape, enter, home, end, left, right arrows
            if ([8, 9, 27, 13, 46, 35, 36, 37, 39].indexOf(e.keyCode) !== -1 ||
                // Allow: Ctrl+A, Ctrl+C, Ctrl+V, Ctrl+X
                (e.keyCode === 65 && e.ctrlKey === true) ||
                (e.keyCode === 67 && e.ctrlKey === true) ||
                (e.keyCode === 86 && e.ctrlKey === true) ||
                (e.keyCode === 88 && e.ctrlKey === true)) {
                return;
            }
            
            // Ensure that it is a number or dash and stop the keypress
            if ((e.shiftKey || (e.keyCode < 48 || e.keyCode > 57)) && 
                (e.keyCode < 96 || e.keyCode > 105) && 
                e.keyCode !== 189 && e.keyCode !== 109) { // 189/109 = dash/minus
                e.preventDefault();
            }
        });
        
        // Also handle paste events
        input.addEventListener('paste', (e) => {
            e.preventDefault();
            const pastedData = (e.clipboardData || window.clipboardData).getData('text');
            // Remove all characters except digits and dashes
            const cleaned = pastedData.replace(/[^\d-]/g, '');
            const currentValue = input.value;
            const start = input.selectionStart;
            const end = input.selectionEnd;
            const newValue = currentValue.substring(0, start) + cleaned + currentValue.substring(end);
            input.value = newValue;
            input.dispatchEvent(new Event('input'));
        });
        
        // Handle input event to filter out invalid characters
        input.addEventListener('input', (e) => {
            const value = e.target.value;
            // Remove all characters except digits and dashes
            const cleaned = value.replace(/[^\d-]/g, '');
            if (value !== cleaned) {
                e.target.value = cleaned;
            }
        });
    });
}

/**
 * Initialize phone inputs when DOM is ready
 */
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initPhoneInputs);
} else {
    initPhoneInputs();
}
