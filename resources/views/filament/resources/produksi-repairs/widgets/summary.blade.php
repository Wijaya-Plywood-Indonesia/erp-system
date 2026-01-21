<x-filament::widget>
    <x-filament::card class="w-full space-y-8 dark:bg-gray-900 dark:border-gray-800">

        {{-- ========================================================== --}}
        {{-- LOGIC PENGOLAHAN DATA (Otomatis) --}}
        {{-- ========================================================== --}}
        @php
        // Ambil data mentah
        $dataRaw = collect($summary['globalUkuranKw'] ?? []);

        // 1. Grouping untuk 'Global Ukuran (Semua KW)'
        // Kita gabungkan data yang ukurannya sama, lalu jumlahkan totalnya
        $globalUkuran = $dataRaw->groupBy('ukuran')->map(function ($rows) {
        return (object) [
        'ukuran' => $rows->first()->ukuran,
        'total' => $rows->sum('total')
        ];
        })->values();
        @endphp

        {{-- ========================================================== --}}
        {{-- [SECTION 1] STATISTIK UTAMA (VERTICAL CENTER) --}}
        {{-- ========================================================== --}}
        <div class="space-y-6 text-center py-2">

            {{-- TOTAL PRODUKSI --}}
            <div>
                <div class="text-5xl font-extrabold text-primary-600 dark:text-primary-500 tracking-tight">
                    {{ number_format($summary['totalAll'] ?? 0) }}
                </div>
                <div class="mt-1 text-sm font-semibold text-gray-500 dark:text-gray-400">
                    Total Produksi (Lembar)
                </div>
            </div>

            <hr class="w-1/3 mx-auto border-gray-200 dark:border-gray-700">

            {{-- TOTAL PEGAWAI --}}
            <div>
                <div class="text-3xl font-bold text-success-600 dark:text-success-500">
                    {{ number_format($summary['totalPegawai'] ?? 0) }}
                </div>
                <div class="mt-1 text-xs font-semibold text-gray-400 dark:text-gray-500 uppercase tracking-wider">
                    Total Tenaga Kerja (Orang)
                </div>
            </div>
        </div>

        <hr class="border-gray-200 dark:border-gray-700">

        {{-- ========================================================== --}}
        {{-- [SECTION 2] GLOBAL UKURAN + KW (DETAIL) --}}
        {{-- ========================================================== --}}
        <div class="space-y-4">
            <div class="flex items-center gap-2 font-semibold text-lg text-gray-900 dark:text-gray-100">
                <x-heroicon-m-clipboard-document-list class="w-5 h-5 text-gray-400" />
                Global Ukuran + KW
            </div>

            <div class="grid grid-cols-1 gap-3">
                @forelse ($dataRaw as $row)
                <div class="flex items-center justify-between rounded-xl  bg-white px-4 py-3 shadow-sm  dark:bg-gray-800 dark:border-gray-700 transition">

                    {{-- KIRI: Ukuran & KW --}}
                    <div class="flex flex-col">
                        <span class="text-sm font-bold text-gray-800 dark:text-gray-200">
                            {{ $row->ukuran }} + KW {{ $row->kw }}
                        </span>
                    </div>

                    {{-- KANAN: Total --}}
                    <div class="text-lg font-bold text-gray-900 dark:text-white">
                        {{ number_format($row->total) }}
                    </div>
                </div>
                @empty
                <div class="text-center text-sm text-gray-500 py-4 italic">Belum ada data produksi.</div>
                @endforelse
            </div>
        </div>

        {{-- ========================================================== --}}
        {{-- [SECTION 3] GLOBAL UKURAN (REKAP SEMUA KW) --}}
        {{-- ========================================================== --}}
        <div class="space-y-4 pt-4 border-t border-gray-100 dark:border-gray-800">
            <div class="flex items-center gap-2 font-semibold text-lg text-gray-900 dark:text-gray-100">
                <x-heroicon-m-square-2-stack class="w-5 h-5 text-gray-400" />
                Global Ukuran (Semua KW)
            </div>

            <div class="grid grid-cols-1 gap-3">
                @foreach ($globalUkuran as $row)
                <div class="flex items-center justify-between rounded-xl  bg-primary-50/30 px-4 py-3 shadow-sm dark:bg-gray-800 dark:border-gray-700">

                    {{-- KIRI: Ukuran Saja --}}
                    <div class="text-sm font-bold text-gray-800 dark:text-gray-200">
                        {{ $row->ukuran }}
                    </div>

                    {{-- KANAN: Total Akumulasi --}}
                    <div class="text-lg font-extrabold text-primary-600 dark:text-primary-400">
                        {{ number_format($row->total) }}
                    </div>
                </div>
                @endforeach
            </div>
        </div>

    </x-filament::card>
</x-filament::widget>