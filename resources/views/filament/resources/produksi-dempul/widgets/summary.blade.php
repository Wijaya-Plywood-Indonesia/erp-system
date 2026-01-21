<x-filament::widget>
    <x-filament::card class="w-full space-y-8 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 shadow-sm">

        {{-- ========================================================== --}}
        {{-- LOGIC PENGOLAHAN DATA (Otomatis) --}}
        {{-- ========================================================== --}}
        @php
        // Ambil data mentah (Ukuran + KW)
        $dataRaw = collect($summary['globalUkuranKw'] ?? []);

        // Grouping untuk 'Global Ukuran (Semua KW)'
        $globalUkuran = $dataRaw->groupBy('ukuran')->map(function ($rows) {
        return (object) [
        'ukuran' => $rows->first()->ukuran,
        'total' => $rows->sum('total')
        ];
        })->values();
        @endphp

        {{-- ========================================================== --}}
        {{-- [SECTION 1] STATISTIK UTAMA --}}
        {{-- ========================================================== --}}
        <div class="space-y-6 text-center py-2">

            {{-- TOTAL DEMPUL --}}
            <div>
                <div class="text-5xl font-extrabold text-primary-600 dark:text-primary-500 tracking-tight drop-shadow-sm">
                    {{ number_format($summary['totalAll'] ?? 0) }}
                </div>
                <div class="mt-2 text-sm font-bold text-gray-500 dark:text-gray-400">
                    Total Dempul (Pcs)
                </div>
            </div>

            <hr class="w-1/3 mx-auto border-gray-200 dark:border-gray-700/50">

            {{-- TOTAL PEGAWAI --}}
            <div>
                <div class="text-3xl font-bold text-success-600 dark:text-success-500">
                    {{ number_format($summary['totalPegawai'] ?? 0) }}
                </div>
                <div class="mt-1 text-xs font-bold text-gray-400 dark:text-gray-500 uppercase tracking-wider">
                    Total Tenaga Kerja (Orang)
                </div>
            </div>
        </div>

        <hr class="border-gray-100 dark:border-gray-800">

        {{-- ========================================================== --}}
        {{-- [SECTION 2] GLOBAL UKURAN + KW (DETAIL) --}}
        {{-- ========================================================== --}}
        <div class="space-y-4">
            <div class="flex items-center gap-2 font-bold text-lg text-gray-800 dark:text-gray-100">
                <x-heroicon-m-clipboard-document-list class="w-5 h-5 text-gray-400 dark:text-gray-500" />
                Global Ukuran + KW
            </div>

            <div class="grid grid-cols-1 gap-3">
                @forelse ($dataRaw as $row)
                {{-- Card Item --}}
                <div class="group flex items-center justify-between rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-sm transition duration-200 ease-in-out dark:bg-gray-800 dark:border-gray-700 dark:hover:border-primary-500">

                    {{-- KIRI: Ukuran & KW --}}
                    <div class="flex flex-col">
                        <span class="text-sm font-bold text-gray-700 group-hover:text-primary-700 dark:text-gray-200 dark:group-hover:text-primary-400 transition-colors">
                            {{ $row->ukuran }} + {{ $row->kw }}
                        </span>
                        <span class="text-xs font-semibold text-gray-500 dark:text-gray-400 flex items-center gap-1.5 mt-0.5">
                            <span class="inline-block w-1.5 h-1.5 rounded-full bg-primary-500"></span>

                        </span>
                    </div>

                    {{-- KANAN: Total --}}
                    <div class="text-lg font-bold text-gray-900 dark:text-white group-hover:scale-105 transition-transform">
                        {{ number_format($row->total) }}
                    </div>
                </div>
                @empty
                <div class="rounded-lg border border-dashed border-gray-300 dark:border-gray-700 p-6 text-center">
                    <div class="text-sm text-gray-500 dark:text-gray-400 italic">Belum ada data dempul.</div>
                </div>
                @endforelse
            </div>
        </div>

        {{-- ========================================================== --}}
        {{-- [SECTION 3] GLOBAL UKURAN (REKAP SEMUA KW) --}}
        {{-- ========================================================== --}}
        <div class="space-y-4 pt-6 border-t border-gray-100 dark:border-gray-800">
            <div class="flex items-center gap-2 font-bold text-lg text-gray-800 dark:text-gray-100">
                <x-heroicon-m-square-2-stack class="w-5 h-5 text-gray-400 dark:text-gray-500" />
                Global Ukuran (Semua KW)
            </div>

            <div class="grid grid-cols-1 gap-3">
                @foreach ($globalUkuran as $row)
                {{-- Card Item (Rekap) --}}
                <div class="flex items-center justify-between rounded-xl  bg-primary-50/40 px-4 py-3 shadow-sm dark:bg-gray-800 dark:border-gray-700 transition duration-200 hover:bg-primary-50 dark:hover:bg-gray-700">

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