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
                // Dark navy-blue theme. Names are semantic roles, not lightness:
                ink: {
                    950: '#0B1530', // page background (deep navy)
                    900: '#111D3D', // panels / cards
                    800: '#18264B', // inputs, inner tiles
                    700: '#24345E', // borders
                    600: '#33467A', // strong borders
                    400: '#8B9CC4', // muted text (~6:1 on panels)
                    200: '#CBD5EE', // body text
                    100: '#FFFFFF', // headings / emphasis
                },
                bull: { DEFAULT: '#16C784', 400: '#3DDC9B', 600: '#0FA76D' },
                bear: { DEFAULT: '#EA3943', 400: '#F0626A' },
                gold: { DEFAULT: '#F5B942', 400: '#F8CB6B', 600: '#D99A1E' },
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
