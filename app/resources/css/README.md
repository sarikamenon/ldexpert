# CSS and Styling Guidelines

## Email Templates

**Important**: Email templates (`resources/views/emails/*.blade.php`) **must use inline styles** for email client compatibility. Most email clients strip out `<style>` tags and external stylesheets.

The inline styles in email templates use NOVA brand colors:
- Primary: `#14b8a6` (Teal)
- Accent: `#a855f7` (Purple)
- Gradient: `linear-gradient(135deg, #14b8a6 0%, #a855f7 100%)`

These colors are defined in `tailwind.config.js` and should be kept consistent.

## Chart Colors

Chart colors are centralized in JavaScript. Each analytics view includes a `novaColors` object with NOVA brand colors. For consistency, always use these colors instead of hardcoded hex values.

See `resources/js/common/chart-colors.js` for the centralized color configuration.

## Tailwind Configuration

All brand colors are defined in `tailwind.config.js`:
- `primary`: Teal (#14b8a6) - Primary brand color
- `accent`: Purple (#a855f7) - Accent brand color
- `gradient-nova`: Full gradient (teal → cyan → purple)
- `gradient-nova-primary`: Primary gradient (teal → purple)

Use these Tailwind classes instead of hardcoded hex values:
- `bg-primary` instead of `bg-[#14b8a6]`
- `text-primary` instead of `text-[#14b8a6]`
- `bg-gradient-nova` for gradient backgrounds
- etc.

## Best Practices

1. **Never use inline styles in views** (except email templates)
2. **Use Tailwind classes** from our config instead of arbitrary values
3. **Use chart color constants** in JavaScript instead of hardcoded colors
4. **Keep email inline styles** but ensure colors match brand config

