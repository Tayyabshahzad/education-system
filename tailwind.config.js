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
                ink: {
                    950: '#05080F', // page background
                    900: '#0A0F1C', // panels
                    800: '#111829', // cards / inputs
                    700: '#1A2338', // borders
                    600: '#26314A',
                    400: '#7C8AA5', // muted text
                    200: '#C5CEE0',
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
