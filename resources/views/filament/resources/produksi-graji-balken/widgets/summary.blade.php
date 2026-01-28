<x-filament::widget>
    <div class="grid grid-cols-1 gap-4">
        {{-- SECTION HEADER: TOTAL PRODUKSI & PEGAWAI --}}
        <x-filament::card class="dark:bg-gray-900 border-none shadow-sm">
            <div class="grid grid-cols-2 gap-4 divide-x divide-gray-200 dark:divide-gray-700">
                <div class="text-center">
                    <div class="text-4xl font-extrabold text-orange-500">
                        {{ number_format($summary['totalAll'] ?? 0) }}
                    </div>
                    <div class="text-xs font-medium text-gray-500 dark:text-gray-400 mt-1">
                        Total Produksi Graji Balken (Pcs)
                    </div>
                </div>

                <div class="text-center">
                    <div class="text-4xl font-extrabold text-green-500">
                        {{ number_format($summary['totalPegawai'] ?? 0) }}
                    </div>
                    <div class="text-xs font-medium text-gray-500 dark:text-gray-400 mt-1">
                        Total Pegawai pada Produksi Ini (Orang)
                    </div>
                </div>
            </div>

            <div class="mt-8 space-y-6">
                {{-- 1. GLOBAL UKURAN + JENIS KAYU --}}
                <div>
                    <h3 class="text-sm font-bold text-gray-800 dark:text-gray-200 mb-3">Global Ukuran + Jenis Kayu</h3>
                    <div class="space-y-2">
                        @forelse ($summary['globalUkuranJenis'] as $row)
                            <div class="flex justify-between items-center bg-gray-50 dark:bg-gray-800/50 px-4 py-3 rounded-xl border border-gray-100 dark:border-gray-700">
                                <div class="text-sm">
                                    <span class="font-medium text-gray-700 dark:text-gray-300">{{ $row->ukuran }}</span>
                                    <span class="mx-1 text-gray-400">•</span>
                                    <span class="text-gray-500 text-xs uppercase italic">{{ $row->jenis_kayu }}</span>
                                </div>
                                <div class="font-black text-gray-900 dark:text-white">
                                    {{ number_format($row->total) }}
                                </div>
                            </div>
                        @empty
                            <p class="text-xs text-gray-400 italic">Tidak ada data ukuran + jenis kayu.</p>
                        @endforelse
                    </div>
                </div>

                {{-- 2. GLOBAL UKURAN (SEMUA JENIS KAYU) --}}
                <div>
                    <h3 class="text-sm font-bold text-gray-800 dark:text-gray-200 mb-3">Global Ukuran (Semua Jenis Kayu)</h3>
                    <div class="space-y-2">
                        @forelse ($summary['globalUkuranSemua'] as $row)
                            <div class="flex justify-between items-center bg-gray-50 dark:bg-gray-800/50 px-4 py-3 rounded-xl border border-gray-100 dark:border-gray-700">
                                <div class="text-sm font-medium text-gray-700 dark:text-gray-300">
                                    {{ $row->ukuran }}
                                </div>
                                <div class="font-black text-orange-500">
                                    {{ number_format($row->total) }}
                                </div>
                            </div>
                        @empty
                            <p class="text-xs text-gray-400 italic">Tidak ada data ukuran global.</p>
                        @endforelse
                    </div>
                </div>
            </div>
        </x-filament::card>
    </div>
</x-filament::widget>