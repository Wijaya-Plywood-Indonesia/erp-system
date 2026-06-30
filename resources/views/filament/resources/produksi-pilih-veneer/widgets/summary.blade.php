<x-filament::widget>
    <div class="grid grid-cols-1 gap-4">
        <x-filament::card class="dark:bg-gray-900 border-none shadow-sm bg-white relative overflow-hidden">

            {{-- INDIKATOR REAL-TIME --}}
            <div class="absolute top-2 right-3 flex items-center gap-1.5" title="Terhubung ke Reverb">
                <span class="relative flex h-2 w-2">
                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-orange-400 opacity-75"></span>
                    <span class="relative inline-flex rounded-full h-2 w-2 bg-orange-500"></span>
                </span>
                <span class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Live</span>
            </div>

            {{-- HEADER: TOTAL PRODUKSI & PEGAWAI --}}
            <div class="grid grid-cols-2 gap-4 divide-x divide-gray-200 dark:divide-gray-700">
                <div class="text-center">
                    {{-- Tambahkan transition agar perubahan angka terasa halus --}}
                    <div class="text-4xl font-extrabold text-orange-500 transition-all duration-500 ease-in-out">
                        {{ number_format($summary['totalAll'] ?? 0) }}
                    </div>
                    <div class="text-xs font-medium text-gray-500 dark:text-gray-400 mt-1 uppercase tracking-wider">
                        Total Produksi Pilih Veneer (Lembar)
                    </div>
                </div>

                <div class="text-center">
                    <div class="text-4xl font-extrabold text-green-500 transition-all duration-500 ease-in-out">
                        {{ number_format($summary['totalPegawai'] ?? 0) }}
                    </div>
                    <div class="text-xs font-medium text-gray-500 dark:text-gray-400 mt-1 uppercase tracking-wider">
                        Total Pegawai pada Produksi Ini (Orang)
                    </div>
                </div>
            </div>

            <div class="mt-8 space-y-6">
                {{-- 1. GLOBAL UKURAN + KW --}}
                @if(false)
                <div>
                    <div class="flex items-center gap-2 mb-3">
                        <div class="h-4 w-1 bg-orange-500 rounded-full"></div>
                        <h3 class="text-sm font-bold text-gray-800 dark:text-gray-200 uppercase tracking-tight">
                            Global Ukuran + KW
                        </h3>
                    </div>
                    <div class="space-y-2">
                        @forelse ($summary['globalUkuranKw'] as $row)
                        <div class="flex justify-between items-center bg-gray-50 dark:bg-gray-800/50 px-5 py-3 rounded-xl border border-gray-100 dark:border-gray-700 transition-all duration-300">
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
                @endif

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
                        <div class="flex justify-between items-center bg-gray-50 dark:bg-gray-800/50 px-5 py-3 rounded-xl border border-gray-100 dark:border-gray-700 transition-all duration-300">
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

                {{-- 3. RINGKASAN JENIS KAYU & UKURAN --}}
                @if (!empty($summary['globalJenisKayuUkuran']) && count($summary['globalJenisKayuUkuran']) > 0)
                <div>
                    <div class="flex items-center gap-2 mb-3">
                        <div class="h-4 w-1 bg-orange-500 rounded-full"></div>
                        <h3 class="text-sm font-bold text-gray-800 dark:text-gray-200 uppercase tracking-tight">
                            Ringkasan Penggunaan Veneer & Ukuran Hasil
                        </h3>
                    </div>
                    <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm">
                        <table class="w-full text-left text-sm text-gray-600 dark:text-gray-300">
                            <thead class="bg-gray-50 dark:bg-gray-900/50 text-gray-900 dark:text-white">
                                <tr>
                                    <th class="px-4 py-3 font-semibold">Jenis Kayu</th>
                                    <th class="px-4 py-3 font-semibold">Ukuran Veneer</th>
                                    <th class="px-4 py-3 font-semibold">kw</th>
                                    <th class="px-4 py-3 font-semibold text-right">Hasil (Lembar)</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                @php $grandTotal = 0; @endphp
                                @foreach (($summary['globalJenisKayuUkuran'] ?? []) as $row)
                                    @php $grandTotal += $row->total; @endphp
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors">
                                        <td class="px-4 py-3">{{ $row->jenis_kayu }}</td>
                                        <td class="px-4 py-3">{{ $row->ukuran }}</td>
                                        <td class="px-4 py-3">{{ $row->kw }}</td>
                                        <td class="px-4 py-3 text-right font-medium">{{ number_format($row->total) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot class="bg-gray-50 dark:bg-gray-900/50 text-gray-900 dark:text-white font-bold">
                                <tr>
                                    <td colspan="3" class="px-4 py-3 text-right border-t dark:border-gray-700">Total Keseluruhan</td>
                                    <td class="px-4 py-3 text-right border-t dark:border-gray-700">{{ number_format($grandTotal) }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
                @endif
            </div>
        </x-filament::card>
    </div>
</x-filament::widget>