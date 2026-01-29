<x-filament::widget>
    <div class="grid grid-cols-1 gap-4">
        {{-- Card Utama dengan perbaikan background --}}
        <x-filament::card class="dark:bg-gray-900 border-none shadow-sm bg-white">
            
            {{-- SECTION 1: HEADER STATS --}}
            <div class="grid grid-cols-2 gap-4 divide-x divide-gray-200 dark:divide-gray-700">
                <div class="text-center">
                    <div class="text-4xl font-extrabold text-orange-500">
                        {{ number_format($summary['totalAll'] ?? 0) }}
                    </div>
                    <div class="text-xs font-medium text-gray-500 dark:text-gray-400 mt-1 uppercase tracking-wider">
                        Total Produksi Graji Stik (Pcs)
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

            {{-- SECTION 2: GLOBAL UKURAN (SEMUA JENIS) --}}
            <div class="mt-8">
                <div class="flex items-center gap-2 mb-4">
                    <div class="h-4 w-1 bg-orange-500 rounded-full"></div>
                    <h3 class="text-sm font-bold text-gray-800 dark:text-gray-200 uppercase tracking-tight">
                        Global Ukuran (Semua Jenis)
                    </h3>
                </div>

                <div class="space-y-2">
                    @forelse ($summary['globalUkuran'] as $row)
                        {{-- Perbaikan: Ganti bg-gray-50 menjadi dark:bg-gray-800/50 agar tidak putih di dark mode --}}
                        <div class="flex justify-between items-center bg-gray-50 dark:bg-gray-800/50 px-5 py-4 rounded-xl border border-gray-100 dark:border-gray-700 hover:border-orange-300 dark:hover:border-orange-500/50 transition-colors">
                            <div class="flex items-center gap-3">
                                {{-- Perbaikan: Ganti bg-white menjadi dark:bg-gray-700 --}}
                                
                                <span class="text-sm font-semibold text-gray-700 dark:text-gray-300">
                                    {{ $row->ukuran }}
                                </span>
                            </div>
                            <div class="text-lg font-black text-orange-500">
                                {{ number_format($row->total) }}
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-6 bg-gray-50 dark:bg-gray-800/20 rounded-xl border border-dashed border-gray-200 dark:border-gray-700">
                            <p class="text-xs text-gray-400 italic font-medium">Belum ada data hasil produksi stik.</p>
                        </div>
                    @endforelse
                </div>
            </div>
        </x-filament::card>
    </div>
</x-filament::widget>