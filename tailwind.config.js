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
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
            fontSize: {
                'sm': ['1.2rem', { lineHeight: '1.25rem' }],
            },
            colors: {
                'meo-teal': {
                    DEFAULT: '#00afcc',
                    '600': '#00afcc',
                    '700': '#0088a3',
                    '800': '#006b7f',
                },
            },
        },
    },

    plugins: [forms],
};
