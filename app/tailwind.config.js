import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {
            colors: {
                // Minimal, shadcn-like neutrals and brand
                background: {
                    DEFAULT: '#ffffff',
                    muted: '#f6f7f8',
                    subtle: '#f2f4f7',
                },
                foreground: {
                    DEFAULT: '#0b1220',
                    muted: '#475569',
                },
                primary: {
                    DEFAULT: '#2563eb',
                    foreground: '#ffffff',
                    50: '#eff6ff',
                    100: '#dbeafe',
                    200: '#bfdbfe',
                    300: '#93c5fd',
                    400: '#60a5fa',
                    500: '#3b82f6',
                    600: '#2563eb',
                    700: '#1d4ed8',
                    800: '#1e40af',
                    900: '#1e3a8a',
                },
                accent: {
                    DEFAULT: '#0ea5e9',
                    foreground: '#ffffff',
                },
                success: {
                    DEFAULT: '#22c55e',
                    foreground: '#ffffff',
                },
                warning: {
                    DEFAULT: '#f59e0b',
                    foreground: '#111827',
                },
                danger: {
                    DEFAULT: '#ef4444',
                    foreground: '#ffffff',
                },
                border: '#e5e7eb',
                input: '#e5e7eb',
                ring: '#0ea5e9',
            },
            borderRadius: {
                base: '0.5rem',
                md: '0.625rem',
                lg: '0.75rem',
            },
            fontFamily: {
                sans: ['Inter', 'Figtree', ...defaultTheme.fontFamily.sans],
            },
        },
    },

    plugins: [forms],
};
