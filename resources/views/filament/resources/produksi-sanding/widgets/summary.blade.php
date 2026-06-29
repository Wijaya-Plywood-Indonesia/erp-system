<x-filament::widget>
    <x-filament::card
        class="w-full space-y-8 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 shadow-sm">

        @php
            $dataRaw = collect($summary['globalUkuranKw'] ?? []);

            $globalUkuran = $dataRaw
                ->groupBy('ukuran')
                ->map(function ($rows) {
                    return (object) [
                        'ukuran' => $rows->first()->ukuran,
                        'total' => $rows->sum('total'),
                    ];
                })
                ->values();
        @endphp

        {{-- [SECTION 1] STATISTIK UTAMA --}}
        <div class="space-y-6 text-center py-2">
            <div>
                <div
                    class="text-5xl font-extrabold text-primary-600 dark:text-primary-500 tracking-tight drop-shadow-sm">
                    {{ number_format($summary['totalAll'] ?? 0) }}
                </div>
                <div class="mt-2 text-sm font-bold text-gray-500 dark:text-gray-400">
                    Total Hasil Sanding (Pcs)
                </div>
            </div>

            <hr class="w-1/3 mx-auto border-gray-200 dark:border-gray-700/50">

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

        {{-- ================= TARGET PROGRESS (SANDING) ================= --}}
        @php
            $target = (float) ($summary['target'] ?? 250);
            $totalAll = (float) ($summary['totalAll'] ?? 0);
            $globalProgressVal = (float) ($summary['globalProgress'] ?? 0);
            $globalProgressPercent = min(100, max(0, $globalProgressVal));
            $potonganPerOrang = (float) ($summary['potonganPerOrang'] ?? 0);

            if ($globalProgressPercent >= 100) {
                $progressColor = '#16a34a';
            } elseif ($globalProgressPercent >= 75) {
                $progressColor = '#2563eb';
            } else {
                $progressColor = '#f59e0b';
            }
        @endphp

        <div class="space-y-4">
            <div class="font-semibold text-lg text-gray-900 dark:text-gray-100">
                Progress Target Sanding
                <span class="text-sm font-normal text-gray-500 dark:text-gray-400">
                    ( Target {{ number_format($target, 4) }} )
                </span>
            </div>

            <div
                class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:bg-gray-800 dark:border-gray-700 space-y-2">
                <div class="flex justify-between text-sm">
                    <span class="font-medium text-gray-700 dark:text-gray-300">
                        Mesin {{ $record->mesin?->nama_mesin ?? 'SANDING' }}
                    </span>
                    <span class="text-gray-600 dark:text-gray-400 font-bold">
                        {{ number_format($totalAll) }} / {{ (float) $target }}
                    </span>
                </div>

                {{-- Progress Bar --}}
                <div class="w-full h-3 rounded-full bg-gray-200 dark:bg-gray-700 overflow-hidden">
                    <div class="h-full rounded-full transition-all duration-500"
                        style="width: {{ $globalProgressPercent }}%; background-color: {{ $progressColor }};">
                    </div>
                </div>

                <div class="text-xs text-right text-gray-500 dark:text-gray-400 font-bold">
                    {{ number_format($globalProgressVal, 1) }}%
                </div>
            </div>
        </div>

        <hr class="border-gray-100 dark:border-gray-800">

        {{-- [SECTION 2] GLOBAL UKURAN + KW (DETAIL) --}}
        <div class="space-y-4">
            <div class="flex items-center gap-2 font-bold text-lg text-gray-800 dark:text-gray-100">
                <x-heroicon-m-clipboard-document-list class="w-5 h-5 text-gray-400 dark:text-gray-500" />
                Global Ukuran + KW (Sanding)
            </div>

            <div class="grid grid-cols-1 gap-3">
                @forelse ($dataRaw as $row)
                    <div
                        class="group flex items-center justify-between rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-sm transition duration-200 ease-in-out dark:bg-gray-800 dark:border-gray-700 dark:hover:border-primary-500">
                        <div class="flex flex-col">
                            <span
                                class="text-sm font-bold text-gray-700 group-hover:text-primary-700 dark:text-gray-200 dark:group-hover:text-primary-400 transition-colors">
                                {{ $row->ukuran }} + {{ $row->kw }}
                            </span>
                        </div>
                        <div
                            class="text-lg font-bold text-gray-900 dark:text-white group-hover:scale-105 transition-transform">
                            {{ number_format($row->total) }}
                        </div>
                    </div>
                @empty
                    <div class="rounded-lg border border-dashed border-gray-300 dark:border-gray-700 p-6 text-center">
                        <div class="text-sm text-gray-500 dark:text-gray-400 italic">Belum ada data sanding.</div>
                    </div>
                @endforelse
            </div>
        </div>

        @if (false)
            {{-- [SECTION 3] GLOBAL UKURAN (REKAP SEMUA KW) --}}
            <div class="space-y-4 pt-6 border-t border-gray-100 dark:border-gray-800">
                <div class="flex items-center gap-2 font-bold text-lg text-gray-800 dark:text-gray-100">
                    <x-heroicon-m-square-2-stack class="w-5 h-5 text-gray-400 dark:text-gray-500" />
                    Global Ukuran Sanding (Semua KW)
                </div>

                <div class="grid grid-cols-1 gap-3">
                    @foreach ($globalUkuran as $row)
                        <div
                            class="flex items-center justify-between rounded-xl bg-primary-50/40 px-4 py-3 shadow-sm dark:bg-gray-800 dark:border-gray-700 transition duration-200 hover:bg-primary-50 dark:hover:bg-gray-700">
                            <div class="text-sm font-bold text-gray-800 dark:text-gray-200">
                                {{ $row->ukuran }}
                            </div>
                            <div class="text-lg font-extrabold text-primary-600 dark:text-primary-400">
                                {{ number_format($row->total) }}
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- [SECTION 4] RINGKASAN JENIS KAYU & UKURAN --}}
        @if (!empty($summary['globalJenisKayuUkuran']) && count($summary['globalJenisKayuUkuran']) > 0)
            <div class="space-y-4 pt-6 border-t border-gray-100 dark:border-gray-800">
                <div class="flex items-center gap-2 font-bold text-lg text-gray-800 dark:text-gray-100">
                    <x-heroicon-m-table-cells class="w-5 h-5 text-gray-400 dark:text-gray-500" />
                    Hasil Produksi
                </div>

                <div
                    class="overflow-hidden rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm">
                    <table class="w-full text-left text-sm text-gray-600 dark:text-gray-300">
                        <thead class="bg-gray-50 dark:bg-gray-900/50 text-gray-900 dark:text-white">
                            <tr>
                                <th class="px-4 py-3 font-semibold">Jenis Kayu</th>
                                <th class="px-4 py-3 font-semibold">Ukuran</th>
                                <th class="px-4 py-3 font-semibold">kw</th>
                                <th class="px-4 py-3 font-semibold text-right">Hasil (Pcs)</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            @php $grandTotal = 0; @endphp
                            @foreach ($summary['globalJenisKayuUkuran'] ?? [] as $row)
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
                                <td colspan="3" class="px-4 py-3 text-right border-t dark:border-gray-700">Total
                                    Keseluruhan</td>
                                <td class="px-4 py-3 text-right border-t dark:border-gray-700">
                                    {{ number_format($grandTotal) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        @endif

    </x-filament::card>
</x-filament::widget>
