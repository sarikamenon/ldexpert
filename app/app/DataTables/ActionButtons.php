<?php

declare(strict_types=1);

namespace App\DataTables;

/**
 * Unified action button HTML generator for DataTable row transformers.
 *
 * Provides consistent icon-button styling across all DataTables.
 * Every button is a w-8 h-8 rounded icon button with focus ring and aria-label.
 */
final class ActionButtons
{
    // ── SVG Icons ──────────────────────────────────────────────

    private const ICON_VIEW = '<svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>';

    private const ICON_EDIT = '<svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>';

    private const ICON_DOWNLOAD = '<svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1"></path><path d="M12 12v9"></path><path d="M8 16l4 4 4-4"></path><path d="M12 3v9"></path></svg>';

    private const ICON_DELETE = '<svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"></path><path d="M10 11v6"></path><path d="M14 11v6"></path><path d="M9 6V4a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v2"></path></svg>';

    private const ICON_ACTIVATE = '<svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>';

    private const ICON_DEACTIVATE = '<svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>';

    private const ICON_SUBMIT = '<svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"></line><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon></svg>';

    // ── Base CSS classes ───────────────────────────────────────

    private const BTN_BASE = 'inline-flex items-center justify-center w-8 h-8 rounded transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-ring';

    // ── Variant CSS classes ────────────────────────────────────

    private const VARIANT_VIEW = 'bg-background text-foreground border border-border hover:bg-background/subtle';

    private const VARIANT_PRIMARY = 'bg-primary text-primary-foreground hover:bg-primary/90';

    private const VARIANT_SUCCESS = 'bg-success text-success-foreground hover:bg-success/90';

    private const VARIANT_DANGER = 'bg-danger text-danger-foreground hover:bg-danger/90';

    private const VARIANT_OUTLINE = 'border border-primary text-primary hover:bg-primary/10';

    // ── Public API ─────────────────────────────────────────────

    /**
     * Wrap multiple action button strings in a consistent flex container.
     */
    public static function wrap(string ...$buttons): string
    {
        return '<div class="flex items-center gap-1">'.implode('', $buttons).'</div>';
    }

    /**
     * View button (link).
     */
    public static function view(string $url, string $label = 'View', array $attrs = []): string
    {
        return self::link($url, self::ICON_VIEW, self::VARIANT_PRIMARY, $label, $attrs);
    }

    /**
     * Edit button (link).
     */
    public static function edit(string $url, string $label = 'Edit', array $attrs = []): string
    {
        return self::link($url, self::ICON_EDIT, self::VARIANT_SUCCESS, $label, $attrs);
    }

    /**
     * Download button (link).
     */
    public static function download(string $url, string $label = 'Download PDF', array $attrs = []): string
    {
        return self::link($url, self::ICON_DOWNLOAD, self::VARIANT_PRIMARY, $label, $attrs);
    }

    /**
     * Activate button (generic button element).
     */
    public static function activate(string $label = 'Activate', array $attrs = []): string
    {
        return self::button(self::ICON_ACTIVATE, self::VARIANT_SUCCESS, $label, $attrs);
    }

    /**
     * Deactivate button (generic button element).
     */
    public static function deactivate(string $label = 'Deactivate', array $attrs = []): string
    {
        return self::button(self::ICON_DEACTIVATE, self::VARIANT_DANGER, $label, $attrs);
    }

    /**
     * Delete button inside a POST form with method spoofing.
     */
    public static function delete(
        string $actionUrl,
        string $label = 'Delete',
        ?string $confirmTitle = null,
        ?string $confirmText = null,
        array $attrs = [],
    ): string {
        $confirmAttrs = [];
        if ($confirmTitle) {
            $confirmAttrs['data-confirm-title'] = $confirmTitle;
        }
        if ($confirmText) {
            $confirmAttrs['data-confirm-text'] = $confirmText;
        }

        return self::formButton(
            $actionUrl,
            'DELETE',
            self::ICON_DELETE,
            self::VARIANT_DANGER,
            $label,
            array_merge($confirmAttrs, $attrs),
        );
    }

    /**
     * Approve button inside a POST form.
     */
    public static function approve(
        string $actionUrl,
        string $label = 'Approve',
        ?string $confirmTitle = 'Approve session?',
        ?string $confirmText = 'This will mark the session as approved.',
    ): string {
        return self::formButton($actionUrl, 'POST', self::ICON_ACTIVATE, self::VARIANT_PRIMARY, $label, [
            'data-confirm-title' => $confirmTitle,
            'data-confirm-text' => $confirmText,
            'data-confirm-icon' => 'question',
        ]);
    }

    /**
     * Cancel button inside a POST form (outline style).
     */
    public static function cancel(
        string $actionUrl,
        string $label = 'Cancel',
        ?string $confirmTitle = 'Cancel session?',
        ?string $confirmText = 'This will cancel the submitted session.',
    ): string {
        return self::formButton($actionUrl, 'POST', self::ICON_DEACTIVATE, self::VARIANT_OUTLINE, $label, [
            'data-confirm-title' => $confirmTitle,
            'data-confirm-text' => $confirmText,
            'data-confirm-icon' => 'warning',
        ]);
    }

    /**
     * Submit button inside a POST form (outline style).
     */
    public static function submit(
        string $actionUrl,
        string $label = 'Submit',
        ?string $confirmTitle = 'Submit session?',
        ?string $confirmText = 'Submit this session for approval.',
    ): string {
        return self::formButton($actionUrl, 'POST', self::ICON_SUBMIT, self::VARIANT_OUTLINE, $label, [
            'data-confirm-title' => $confirmTitle,
            'data-confirm-text' => $confirmText,
            'data-confirm-icon' => 'question',
        ]);
    }

    // ── Private helpers ────────────────────────────────────────

    /**
     * Render an anchor (<a>) action button.
     */
    private static function link(string $url, string $icon, string $variant, string $title, array $attrs = []): string
    {
        $classes = self::buildClasses($variant, $attrs);
        $extra = self::renderAttrs($attrs);

        return '<a href="'.e($url).'" class="'.$classes.'" title="'.e($title).'" aria-label="'.e($title).'"'.$extra.'>'.$icon.'</a>';
    }

    /**
     * Render a <button> action button (no form).
     */
    private static function button(string $icon, string $variant, string $title, array $attrs = []): string
    {
        $classes = self::buildClasses($variant, $attrs);
        $extra = self::renderAttrs($attrs);

        return '<button type="button" class="'.$classes.'" title="'.e($title).'" aria-label="'.e($title).'"'.$extra.'>'.$icon.'</button>';
    }

    /**
     * Render a <form> containing a single action button.
     */
    private static function formButton(
        string $actionUrl,
        string $method,
        string $icon,
        string $variant,
        string $title,
        array $attrs = [],
    ): string {
        $token = e(csrf_token());
        $formClass = self::extractAttr($attrs, 'form-class', 'inline');
        $classes = self::buildClasses($variant, $attrs);
        $extra = self::renderAttrs($attrs);

        $html = '<form method="POST" action="'.e($actionUrl).'" class="'.$formClass.'">';
        $html .= '<input type="hidden" name="_token" value="'.$token.'">';
        if ($method !== 'POST') {
            $html .= '<input type="hidden" name="_method" value="'.e($method).'">';
        }
        $html .= '<button type="submit" class="'.$classes.'" title="'.e($title).'" aria-label="'.e($title).'"'.$extra.'>'.$icon.'</button>';
        $html .= '</form>';

        return $html;
    }

    /**
     * Build the full class string, merging BTN_BASE + variant + any extra 'class' attr.
     */
    private static function buildClasses(string $variant, array &$attrs): string
    {
        $extraClass = '';
        if (isset($attrs['class'])) {
            $extraClass = ' '.$attrs['class'];
            unset($attrs['class']);
        }

        return self::BTN_BASE.' '.$variant.$extraClass;
    }

    /**
     * Extract and remove a custom key from attrs, returning its value or a default.
     */
    private static function extractAttr(array &$attrs, string $key, string $default = ''): string
    {
        if (isset($attrs[$key])) {
            $value = $attrs[$key];
            unset($attrs[$key]);

            return $value;
        }

        return $default;
    }

    /**
     * Render extra HTML attributes from a key-value array.
     */
    private static function renderAttrs(array $attrs): string
    {
        $html = '';
        foreach ($attrs as $key => $value) {
            $html .= ' '.e($key).'="'.e((string) $value).'"';
        }

        return $html;
    }
}
