import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';
import typography from '@tailwindcss/typography';

/** @type {import('tailwindcss').Config} */
export default {
    darkMode: 'class',
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './vendor/laravel/jetstream/**/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './app/Livewire/**/*.php',
    ],
    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
            colors: {
		bambu: {
                    green:        '#1DB954',
                    'green-dark': '#17A145',
                    dark:         '#161B22',
                    'dark-2':     '#1E2530',
                    'dark-3':     '#252D38',
                    'dark-4':     '#2D3748',
                    'dark-5':     '#374151',
                    text:         '#E6EDF3',
                    'text-dim':   '#8B949E',
                },
            },
        },
    },
    plugins: [forms, typography],
};
