<x-filament::widget>
    <x-filament::card class="w-full space-y-10 dark:bg-gray-900 dark:border-gray-800">

        {{-- ================= STATISTIK UTAMA ================= --}}
        <div class="grid grid-cols-2 gap-4 divide-x divide-gray-200 dark:divide-gray-700">

            {{-- TOTAL PRODUKSI --}}
            <div class="text-center py-2">
                <div class="text-4xl font-extrabold text-primary-600 dark:text-primary-500">
                    {{ number_format($summary['totalAll'] ?? 0) }}
                </div>
                <div class="text-sm font-medium text-gray-500 dark:text-gray-400">
                    Total Produksi
                </div>
            </div>

            {{-- TOTAL PEGAWAI --}}
            <div class="text-center py-2">
                <div class="text-4xl font-extrabold text-success-600 dark:text-success-500">
                    {{ number_format($summary['totalPegawai'] ?? 0) }}
                </div>
                <div class="text-sm font-medium text-gray-500 dark:text-gray-400">
                    Total Pegawai pada Produksi Ini (Orang)
                </div>
            </div>

        </div>

        {{-- ================= GLOBAL UKURAN + KW ================= --}}
        <div class="space-y-4">
            <div class="font-semibold text-lg text-gray-900 dark:text-gray-100">
                Global Ukuran + KW
            </div>

            <div class="grid grid-cols-1 gap-3">
                @foreach ($summary['globalUkuranKw'] as $row)
                    <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-sm
                                dark:bg-gray-800 dark:border-gray-700">
                        <div class="text-sm font-medium text-gray-700 dark:text-gray-300">
                            {{ $row->ukuran }}
                            <span class="text-xs text-gray-500 dark:text-gray-400">
                                • KW {{ $row->kw }}
                            </span>
                        </div>

                        <div class="text-lg font-bold text-gray-900 dark:text-white">
                            {{ number_format($row->total) }}
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- ================= GLOBAL UKURAN (SEMUA KW) ================= --}}
        <div class="space-y-4">
            <div class="font-semibold text-lg text-gray-900 dark:text-gray-100">
                Global Ukuran (Semua KW)
            </div>

            <div class="grid grid-cols-1 gap-3">
                @foreach ($summary['globalUkuran'] as $row)
                    <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-sm
                                dark:bg-gray-800 dark:border-gray-700">
                        <div class="text-sm font-medium text-gray-700 dark:text-gray-300">
                            {{ $row->ukuran }}
                        </div>

                        <div class="text-lg font-bold text-primary-600 dark:text-primary-400">
                            {{ number_format($row->total) }}
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

    </x-filament::card>
</x-filament::widget>
