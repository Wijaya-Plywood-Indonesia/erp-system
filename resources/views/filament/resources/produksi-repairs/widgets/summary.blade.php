<x-filament::widget>
    <x-filament::card class="w-full space-y-8 dark:bg-gray-900 dark:border-gray-800">

        {{-- [SECTION 1] STATISTIK UTAMA --}}
        <div class="space-y-6 text-center py-2">
            <div>
                <div class="text-5xl font-extrabold text-primary-600 dark:text-primary-500 tracking-tight">
                    {{ number_format($summary['totalAll'] ?? 0) }}
                </div>
                <div class="mt-1 text-sm font-semibold text-gray-500 dark:text-gray-400">
                    Total Produksi (Lembar)
                </div>
            </div>

            <hr class="w-1/3 mx-auto border-gray-200 dark:border-gray-700">

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

        {{-- [SECTION 4] RINGKASAN JENIS KAYU & UKURAN (RESPONSIVE) --}}
        @if (!empty($summary['globalJenisKayuUkuran']) && count($summary['globalJenisKayuUkuran']) > 0)
        <div class="space-y-4 pt-6 border-t border-gray-100 dark:border-gray-800">
            <div class="flex items-center gap-2 font-semibold text-lg text-gray-900 dark:text-gray-100">
                <x-heroicon-m-table-cells class="w-5 h-5 text-gray-400" />
                Hasil Produksi
            </div>

            @php $grandTotal = collect($summary['globalJenisKayuUkuran'])->sum('total'); @endphp

            {{-- 1. TAMPILAN KARTU / CARD VIEW (KHUSUS MOBILE: < 640px / sm) --}}
            <div class="block sm:hidden space-y-3">
                @foreach ($summary['globalJenisKayuUkuran'] ?? [] as $row)
                <div class="p-4 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm space-y-2">
                    <div class="flex justify-between items-start">
                        <div>
                            <div class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Jenis Kayu</div>
                            <div class="text-sm font-bold text-gray-800 dark:text-gray-200">{{ $row->jenis_kayu }}</div>
                        </div>
                        <span class="inline-flex items-center rounded-md bg-gray-100 dark:bg-gray-700 px-2 py-1 text-xs font-semibold text-gray-600 dark:text-gray-300">
                            KW {{ $row->kw }}
                        </span>
                    </div>

                    <div class="flex justify-between items-end pt-2 border-t border-gray-100 dark:border-gray-700/50">
                        <div>
                            <div class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Ukuran</div>
                            <div class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ $row->ukuran }}</div>
                        </div>
                        <div class="text-right">
                            <div class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Hasil</div>
                            <div class="text-base font-extrabold text-primary-600 dark:text-primary-400">
                                {{ number_format($row->total) }} <span class="text-xs font-normal text-gray-500 dark:text-gray-400">Lbr</span>
                            </div>
                        </div>
                    </div>
                </div>
                @endforeach

                {{-- TOTAL KESELURUHAN (MOBILE) --}}
                <div class="p-4 rounded-xl border border-primary-200 dark:border-primary-900/50 bg-primary-50/50 dark:bg-primary-950/30 flex justify-between items-center font-bold">
                    <span class="text-sm text-gray-800 dark:text-gray-200">Total Keseluruhan</span>
                    <span class="text-lg text-primary-600 dark:text-primary-400">{{ number_format($grandTotal) }} Lbr</span>
                </div>
            </div>

            {{-- 2. TAMPILAN TABEL STANDAR (DESKTOP/TABLET: >= 640px / sm) --}}
            <div class="hidden sm:block overflow-hidden rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm">
                <table class="w-full text-left text-sm text-gray-600 dark:text-gray-300">
                    <thead class="bg-gray-50 dark:bg-gray-900/50 text-gray-900 dark:text-white">
                        <tr>
                            <th class="px-4 py-3 font-semibold">Jenis Kayu</th>
                            <th class="px-4 py-3 font-semibold">Ukuran</th>
                            <th class="px-4 py-3 font-semibold">KW</th>
                            <th class="px-4 py-3 font-semibold text-right">Hasil (Lembar)</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach ($summary['globalJenisKayuUkuran'] ?? [] as $row)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors">
                            <td class="px-4 py-3 font-medium text-gray-800 dark:text-gray-200">{{ $row->jenis_kayu }}</td>
                            <td class="px-4 py-3">{{ $row->ukuran }}</td>
                            <td class="px-4 py-3">{{ $row->kw }}</td>
                            <td class="px-4 py-3 text-right font-medium text-gray-900 dark:text-white">{{ number_format($row->total) }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="bg-gray-50 dark:bg-gray-900/50 text-gray-900 dark:text-white font-bold">
                        <tr>
                            <td colspan="3" class="px-4 py-3 text-right border-t dark:border-gray-700">Total Keseluruhan</td>
                            <td class="px-4 py-3 text-right border-t dark:border-gray-700">
                                {{ number_format($grandTotal) }}
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
        @endif

    </x-filament::card>
</x-filament::widget>