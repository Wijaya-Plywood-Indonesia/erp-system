<x-filament-panels::page>
    <div class="space-y-6">

        {{-- FILTER BULAN --}}
        <div class="flex justify-end p-4 bg-white border border-gray-200 shadow-sm rounded-xl dark:bg-gray-800 dark:border-gray-700">
            <div class="flex items-center gap-3">
                <label for="filterBulan" class="text-sm font-medium text-gray-700 dark:text-gray-300">Periode:</label>
                <input type="month" 
                    wire:model.live="filterBulan" 
                    id="filterBulan"
                    class="block w-full text-sm border-gray-300 rounded-lg shadow-sm focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
            </div>
        </div>

        @foreach($indukAkuns as $induk)
        {{-- x-data diatur ke true agar langsung terbuka --}}
        <div x-data="{ open: true }" 
            class="overflow-hidden bg-white border border-gray-200 shadow-sm rounded-xl dark:bg-gray-900 dark:border-gray-700">

            {{-- HEADER INDUK --}}
            <div class="flex flex-col justify-between px-6 py-4 md:flex-row md:items-center bg-gray-50/50 dark:bg-gray-800/30 border-b border-gray-100 dark:border-gray-800">
                <div class="text-lg font-bold text-gray-900 dark:text-white">
                    <span class="text-primary-600 dark:text-primary-400">no akun:</span> 
                    {{ $induk->kode_induk_akun }} - {{ $induk->nama_induk_akun }}
                </div>

                <div class="flex items-center gap-2 mt-2 md:mt-0">
                    <span class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Sisa Saldo:</span>
                    <span class="text-lg font-extrabold text-primary-600 dark:text-primary-400">
                        Rp {{ number_format($induk->anakAkuns->sum(fn($a) => $this->getTotalRecursive($a)), 0, ',', '.') }}
                    </span>
                </div>
            </div>

            {{-- BODY --}}
            <div class="p-4 space-y-4">
                @foreach($induk->anakAkuns as $anak)
                    @include('filament.pages.partials.buku-besar-anak', ['akun' => $anak])
                @endforeach
            </div>
        </div>
        @endforeach
    </div>
</x-filament-panels::page>