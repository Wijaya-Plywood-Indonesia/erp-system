<x-filament-panels::page wire:init="initLoad">
    @if($isLoading)
        <div class="flex items-center justify-center min-h-[60vh]">
            <div class="flex flex-col items-center gap-4 text-primary-600">
                <svg class="w-10 h-10 animate-spin" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10"
                        stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor"
                        d="M4 12a8 8 0 018-8v8z">
                    </path>
                </svg>
                <span class="text-lg font-semibold">
                    Memuat Buku Besar...
                </span>
                <span class="text-sm text-gray-500 dark:text-gray-400">
                    Mohon tunggu, sedang menghitung saldo & transaksi
                </span>
            </div>
        </div>
    @else
    
    <div class="space-y-6">

        {{-- FILTER BULAN --}}
        <div
            class="flex justify-end p-4 bg-white border border-gray-200 shadow-sm rounded-xl dark:bg-gray-800 dark:border-gray-700">
            <div class="flex items-center gap-3">
                <label for="filterBulan" class="text-sm font-medium text-gray-700 dark:text-gray-300">Periode:</label>
                <input type="month" wire:model.live="filterBulan" id="filterBulan"
                    class="block w-full text-sm border-gray-300 rounded-lg shadow-sm focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
            </div>
        </div>

        @foreach($indukAkuns as $induk)
        @php
        // Cek apakah ada satupun anak akun di bawah induk ini yang punya aktivitas
        $hasActivity = $induk->anakAkuns->contains(function($anak) {
        return $this->getSaldoAwal($anak->kode_anak_akun) != 0 ||
        $this->getTotalRecursive($anak) != 0 ||
        $this->getTransaksiByKode($anak->kode_anak_akun)->count() > 0;
        });
        @endphp

        @if($hasActivity)
        <div x-data="{ open: true }"
            class="overflow-hidden bg-white border border-gray-200 shadow-sm rounded-xl dark:bg-gray-900 dark:border-gray-700">

            {{-- HEADER INDUK --}}
            <div
                class="flex flex-col justify-between px-6 py-4 md:flex-row md:items-center bg-gray-50/50 dark:bg-gray-800/30 border-b border-gray-100 dark:border-gray-800">
                <div class="text-lg font-bold text-gray-900 dark:text-white">
                    <span class="text-primary-600 dark:text-primary-400">no akun:</span>
                    {{ $induk->kode_induk_akun }} - {{ $induk->nama_induk_akun }}
                </div>

                <div class="flex items-center gap-2 mt-2 md:mt-0">
                    <span class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Sisa Saldo:</span>
                    <span class="text-lg font-extrabold text-primary-600 dark:text-primary-400">
                        Rp {{ number_format($induk->anakAkuns
    ->whereNull('parent')
    ->sum(fn($a) => $this->getTotalRecursive($a)), 0, ',', '.')
                        }}
                    </span>
                </div>
            </div>

            <div class="p-4 space-y-4">
                @foreach($induk->anakAkuns->whereNull('parent') as $anak)
                @include('filament.pages.partials.buku-besar-anak', ['akun' => $anak])
                @endforeach
            </div>
        </div>
        @endif
        @endforeach
    </div>
    @endif
</x-filament-panels::page>