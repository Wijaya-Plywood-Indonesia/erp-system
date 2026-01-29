<x-filament::widget>
    <div class="grid grid-cols-1 gap-4">
        <x-filament::card class="dark:bg-gray-900 border-none shadow-sm bg-white">
            {{-- HEADER: TOTAL PRODUKSI & PEGAWAI --}}
            <div class="grid grid-cols-2 gap-4 divide-x divide-gray-200 dark:divide-gray-700">
                <div class="text-center">
                    <div class="text-4xl font-extrabold text-orange-500">
                        {{ number_format($summary['totalAll'] ?? 0) }}
                    </div>
                    <div class="text-xs font-medium text-gray-500 dark:text-gray-400 mt-1 uppercase tracking-wider">
                        Total Produksi Pilih Veneer (Lembar)
                    </div>
                </div>

                <div class="text-center">
                    <div class="text-4xl font-extrabold text-green-500">
                        {{ number_format($summary['totalPegawai'] ?? 0) }}
                    </div>
                    <div class="text-xs font-medium text-gray-500 dark:text-gray-400 mt-1 uppercase tracking-wider">
                        Total Pegawai pada Produksi Ini (Orang)
                    </div>
                </div>
            </div>

            <div class="mt-8 space-y-6">
                {{-- 1. GLOBAL UKURAN + KW --}}
                <div>
                    <div class="flex items-center gap-2 mb-3">
                        <div class="h-4 w-1 bg-orange-500 rounded-full"></div>
                        <h3 class="text-sm font-bold text-gray-800 dark:text-gray-200 uppercase tracking-tight">
                            Global Ukuran + KW
                        </h3>
                    </div>
                    <div class="space-y-2">
                        @forelse ($summary['globalUkuranKw'] as $row)
                            <div class="flex justify-between items-center bg-gray-50 dark:bg-gray-800/50 px-5 py-3 rounded-xl border border-gray-100 dark:border-gray-700">
                                <div class="text-sm font-semibold text-gray-700 dark:text-gray-300">
                                    {{ $row->ukuran }} 
                                    <span class="mx-1 text-gray-400 font-normal">•</span> 
                                    <span class="text-gray-500 text-xs italic font-medium uppercase">Grade KW {{ $row->kw }}</span>
                                </div>
                                <div class="font-black text-gray-900 dark:text-white">
                                    {{ number_format($row->total) }}
                                </div>
                            </div>
                        @empty
                            <p class="text-xs text-gray-400 italic">Data rincian KW belum tersedia.</p>
                        @endforelse
                    </div>
                </div>

                {{-- 2. GLOBAL UKURAN (SEMUA KW) --}}
                <div>
                    <div class="flex items-center gap-2 mb-3">
                        <div class="h-4 w-1 bg-orange-500 rounded-full"></div>
                        <h3 class="text-sm font-bold text-gray-800 dark:text-gray-200 uppercase tracking-tight">
                            Global Ukuran (Semua KW)
                        </h3>
                    </div>
                    <div class="space-y-2">
                        @forelse ($summary['globalUkuranSemua'] as $row)
                            <div class="flex justify-between items-center bg-gray-50 dark:bg-gray-800/50 px-5 py-3 rounded-xl border border-gray-100 dark:border-gray-700">
                                <div class="text-sm font-bold text-gray-700 dark:text-gray-300">
                                    {{ $row->ukuran }}
                                </div>
                                <div class="font-black text-orange-500">
                                    {{ number_format($row->total) }}
                                </div>
                            </div>
                        @empty
                            <p class="text-xs text-gray-400 italic">Data global ukuran belum tersedia.</p>
                        @endforelse
                    </div>
                </div>
            </div>
        </x-filament::card>
    </div>
</x-filament::widget>