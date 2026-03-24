function clearFilterForm(form) {
    const elements = form.querySelectorAll('input, select, textarea');

    elements.forEach((el) => {
        if (!(el instanceof HTMLElement)) {
            return;
        }

        if (el instanceof HTMLInputElement) {
            const type = (el.type || '').toLowerCase();

            if (type === 'hidden' || type === 'submit' || type === 'button' || type === 'image' || type === 'file') {
                return;
            }

            if (type === 'checkbox' || type === 'radio') {
                el.checked = false;
                return;
            }

            el.value = '';
            return;
        }

        if (el instanceof HTMLTextAreaElement) {
            el.value = '';
            return;
        }

        if (el instanceof HTMLSelectElement) {
            if (el.multiple) {
                Array.from(el.options).forEach((opt) => {
                    opt.selected = false;
                });

                if (typeof window.jQuery !== 'undefined') {
                    window.jQuery(el).val([]).trigger('change');
                } else {
                    el.dispatchEvent(new Event('change', { bubbles: true }));
                }
                return;
            }

            const defaultVal = el.getAttribute('data-default-value') || '';
            el.value = defaultVal;
            if (typeof window.jQuery !== 'undefined') {
                window.jQuery(el).val(defaultVal).trigger('change');
            } else {
                el.dispatchEvent(new Event('change', { bubbles: true }));
            }
        }
    });

    form.dispatchEvent(new Event('change', { bubbles: true }));
}

document.addEventListener('click', (event) => {
    const button = event.target?.closest?.('[data-filter-clear]');
    if (!button) {
        return;
    }

    event.preventDefault();

    const formId = button.getAttribute('data-filter-form');
    const form = formId ? document.getElementById(formId) : button.closest('form');
    if (!(form instanceof HTMLFormElement)) {
        return;
    }

    clearFilterForm(form);

    if (window.location.search && window.location.search !== '?') {
        const clearUrl = button.getAttribute('data-clear-url') || window.location.pathname;
        window.location.href = clearUrl;
    }
});

