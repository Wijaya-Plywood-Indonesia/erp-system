<x-filament-panels::page>
    {{--
        FILAMENT PAGE VIEW: grading-page.blade.php
        Path: resources/views/filament/pages/grading-page.blade.php

        Tugas view ini sangat sederhana:
        Hanya membungkus komponen Livewire GradingWizard
        dan menghilangkan padding default Filament agar
        wizard bisa tampil full-screen yang bersih.

        x-filament-panels::page → komponen layout Filament
        yang menyediakan: header, breadcrumb, dan area konten.

        Kita pakai -mx dan -my negatif untuk "keluar" dari
        padding default panel Filament, sehingga wizard
        mengisi seluruh area konten.
    --}}

    <div class="-mx-4 -my-6 sm:-mx-6 lg:-mx-8 xl:-mx-12">
        @livewire('grading-wizard')
    </div>
</x-filament-panels::page>