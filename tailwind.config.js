// tailwind.config.js

// 1. Impor preset Filament di bagian atas
import preset from "./vendor/filament/filament/tailwind.config.js";

/** @type {import('tailwindcss').Config} */
export default {
    // 2. Terapkan preset Filament
    presets: [preset],

    // 3. Tambahkan semua path ini ke 'content'
    content: [
        "./app/Filament/**/*.php",
        "./resources/views/filament/**/*.blade.php",
        "./vendor/filament/**/*.blade.php",

        // Path default Laravel Anda
        "./resources/views/**/*.blade.php",
    ],
    theme: {
        extend: {},
    },
    plugins: [],
};
