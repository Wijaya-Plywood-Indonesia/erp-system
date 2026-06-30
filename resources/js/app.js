import "./bootstrap";

const initEcho = () => {
    window.LaravelEcho = window.Echo;
    console.log('Echo dipasang ulang...');
};

// Jalankan saat load pertama
initEcho();

// Jalankan setiap kali Filament pindah halaman (SPA)
document.addEventListener('livewire:navigated', () => {
    initEcho();
});

/*
    Command Alphine untuk menghindari pemanggilan ganda
*/

//  resources/js/app.js
// import Alpine from "alpinejs";

// window.Alpine = Alpine;
// Alpine.start();