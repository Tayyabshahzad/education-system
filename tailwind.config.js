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
            fontFamily: {
                sans: ['Inter', ...defaultTheme.fontFamily.sans],
                display: ['"Space Grotesk"', 'Inter', ...defaultTheme.fontFamily.sans],
                mono: ['"JetBrains Mono"', ...defaultTheme.fontFamily.mono],
            },
            colors: {
                // Light theme. Names are semantic roles, not lightness:
                ink: {
                    950: '#F4F6FA', // page background
                    900: '#FFFFFF', // panels / cards
                    800: '#F1F4F8', // inputs, inner tiles
                    700: '#E2E8F0', // borders
                    600: '#CBD5E1', // strong borders
                    400: '#5B6B82', // muted text (5:1 on white)
                    200: '#334155', // body text
                    100: '#0F172A', // headings / emphasis
                },
                // Darker than the dark-theme versions so text stays readable on white.
                bull: { DEFAULT: '#059669', 400: '#047857', 600: '#065F46' },
                bear: { DEFAULT: '#DC2626', 400: '#B91C1C' },
                gold: { DEFAULT: '#B07410', 400: '#966209', 600: '#7C5108' },
            },
            keyframes: {
                ticker: { '0%': { transform: 'translateX(0)' }, '100%': { transform: 'translateX(-50%)' } },
                draw: { '0%': { strokeDashoffset: '1200' }, '100%': { strokeDashoffset: '0' } },
            },
            animation: {
                ticker: 'ticker 40s linear infinite',
                draw: 'draw 3s ease-out forwards',
            },
        },
    },

    plugins: [forms],
};
