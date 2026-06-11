// "Read more / Read less" toggle for the 2-line clamped notes cells rendered
// by the session-log row transformers. The toggle is revealed only on notes
// that actually overflow the clamp; a MutationObserver on the tbody re-runs
// the check on every DataTables redraw (paging/search/sort re-render the
// rows). Rows are re-rendered on every redraw, so the click handler is
// delegated to the body and bound once.
export function initSessionLogNotes(table) {
    const revealOverflowingNotes = () => {
        document.querySelectorAll('[data-notes-cell]').forEach((cell) => {
            const text = cell.querySelector('[data-notes-text]');
            const toggle = cell.querySelector('[data-notes-toggle]');
            if (!text || !toggle) return;
            if (text.classList.contains('notes-expanded')) return;
            toggle.classList.toggle('hidden', text.scrollHeight <= text.clientHeight + 1);
        });
    };

    const tbody = table?.querySelector('tbody');
    if (tbody) {
        new MutationObserver(revealOverflowingNotes).observe(tbody, { childList: true });
    }
    revealOverflowingNotes();

    document.body.addEventListener('click', (event) => {
        const toggle = event.target.closest('[data-notes-toggle]');
        if (!toggle) return;

        const text = toggle.closest('[data-notes-cell]')?.querySelector('[data-notes-text]');
        if (!text) return;

        const expanded = text.classList.toggle('notes-expanded');
        toggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
        toggle.textContent = expanded ? 'Read less' : 'Read more';
    });
}
