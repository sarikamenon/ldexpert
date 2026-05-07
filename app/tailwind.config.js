import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.js',
    ],

    theme: {
        extend: {
            colors: {
                // NOVA brand colors - blue/purple primary with teal secondary
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
                    DEFAULT: '#5563b8', // Blue-purple - primary brand color
                    foreground: '#ffffff',
                    50: '#eef0f9',
                    100: '#d4d9f0',
                    200: '#a9b3e1',
                    300: '#7e8dd2',
                    400: '#6a7bc9',
                    500: '#5563b8',
                    600: '#4a56a0',
                    700: '#3d4683',
                    800: '#303766',
                    900: '#232849',
                },
                secondary: {
                    DEFAULT: '#14b8a6', // Teal - secondary brand color
                    foreground: '#ffffff',
                    50: '#f0fdfa',
                    100: '#ccfbf1',
                    200: '#99f6e4',
                    300: '#5eead4',
                    400: '#2dd4bf',
                    500: '#14b8a6',
                    600: '#0d9488',
                    700: '#0f766e',
                    800: '#115e59',
                    900: '#134e4a',
                },
                accent: {
                    DEFAULT: '#a855f7', // Purple-500 - accent brand color
                    foreground: '#ffffff',
                    50: '#faf5ff',
                    100: '#f3e8ff',
                    200: '#e9d5ff',
                    300: '#d8b4fe',
                    400: '#c084fc',
                    500: '#a855f7',
                    600: '#9333ea',
                    700: '#7e22ce',
                    800: '#6b21a8',
                    900: '#581c87',
                },
                // Gradient colors for NOVA branding
                gradient: {
                    start: '#5563b8', // Primary blue-purple
                    mid: '#14b8a6', // Secondary teal
                    end: '#a855f7', // Accent purple
                    endAlt: '#d946ef', // Fuchsia/Magenta
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
                ring: '#5563b8', // Primary blue-purple ring
            },
            borderRadius: {
                base: '0.5rem',
                md: '0.625rem',
                lg: '0.75rem',
            },
            fontFamily: {
                sans: ['Inter', 'Figtree', ...defaultTheme.fontFamily.sans],
            },
            backgroundImage: {
                'gradient-nova': 'linear-gradient(135deg, #5563b8 0%, #14b8a6 50%, #a855f7 100%)',
                'gradient-nova-primary': 'linear-gradient(135deg, #5563b8 0%, #a855f7 100%)',
            },
        },
    },

    plugins: [forms],
};
