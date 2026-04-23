{{-- resources/views/filament/pages/opname-stok-kayu.blade.php --}}

<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Info Banner --}}
        <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
            <div class="flex items-start gap-3">
                <div class="flex-shrink-0">
                    <svg class="w-5 h-5 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                </div>
                <div>
                    <h3 class="text-sm font-semibold text-blue-800 dark:text-blue-300">Panduan Stok Opname Kayu</h3>
                    <ul class="text-sm text-blue-700 dark:text-blue-400 mt-1 list-disc list-inside space-y-0.5">
                        <li>Pilih Lahan, Jenis Kayu, dan Panjang yang akan diopname</li>
                        <li>Sistem akan menampilkan stok saat ini di sistem</li>
                        <li>Masukkan hasil pengecekan stok fisik di lapangan</li>
                        <li>Sistem akan menghitung selisih secara otomatis</li>
                        <li>Setiap perubahan akan dicatat di Log HPP</li>
                    </ul>
                </div>
            </div>
        </div>

        {{-- Form --}}
        {{ $this->schema }}
    </div>
</x-filament-panels::page>