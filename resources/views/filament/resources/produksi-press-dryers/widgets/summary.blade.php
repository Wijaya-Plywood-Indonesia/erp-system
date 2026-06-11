<x-filament::widget>
    <x-filament::card class="w-full space-y-8 dark:bg-gray-900 dark:border-gray-800">

        {{-- ================= STAT UTAMA ================= --}}
        <div class="space-y-3 text-center py-4">

            @php
                $isDryer = false;
                $firstMesin = $record->detailMesins->first();
                if ($firstMesin) {
                    $namaMesin = $firstMesin->mesin->nama_mesin
                        ?? $firstMesin->kategoriMesin->nama_kategori_mesin
                        ?? '';
                    $isDryer = stripos($namaMesin, 'DRYER') !== false;
                }
            @endphp

            @if ($isDryer)
                {{-- TOTAL PRODUKSI (KUBIKASI) --}}
                <div>
                    <div class="text-4xl font-extrabold text-primary-600 dark:text-primary-500">
                        {{ number_format($summary['totalKubikasi'] ?? 0, 4, ',', '.') }} m³
                    </div>
                    <div class="text-sm font-medium text-gray-500 dark:text-gray-400">
                        Total Produksi (Kubikasi)
                    </div>
                </div>

                {{-- TOTAL LEMBAR --}}
                <div style="margin-top: 1.5rem;">
                    <div class="text-3xl font-extrabold text-amber-600 dark:text-amber-500">
                        {{ number_format($summary['totalAll'] ?? 0) }} Lembar
                    </div>
                    <div class="text-sm font-medium text-gray-500 dark:text-gray-400">
                        Total Lembar Produksi
                    </div>
                </div>
            @else
                {{-- TOTAL PRODUKSI (LEMBAR) --}}
                <div>
                    <div class="text-4xl font-extrabold text-primary-600 dark:text-primary-500">
                        {{ number_format($summary['totalAll'] ?? 0) }} Lembar
                    </div>
                    <div class="text-sm font-medium text-gray-500 dark:text-gray-400">
                        Total Produksi (Lembar)
                    </div>
                </div>
            @endif

            {{-- TOTAL PEGAWAI --}}
            <div style="margin-top: 1.5rem;">
                <div class="text-2xl font-bold text-success-600 dark:text-success-500">
                    {{ number_format($summary['totalPegawai'] ?? 0) }} Orang
                </div>
                <div class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">
                    Total Pegawai pada Produksi Ini
                </div>
            </div>

        </div>

        {{-- ================= TARGET PROGRESS ================= --}}
        @if ($summary['targetSummary']['hasTarget'])
        @php
            $target = $summary['targetSummary'];
            $progress = $target['progress'];
            $isDryerUnit = $target['unit'] === 'm³';
        @endphp
        <div class="space-y-4 py-4 border-t dark:border-gray-700">
            <div class="font-semibold text-lg text-gray-900 dark:text-gray-100 flex items-center justify-between">
                <span>Progress Target ({{ $target['targetName'] }})</span>
                <span class="text-xs font-normal text-gray-500 dark:text-gray-400">
                    Target: {{ $isDryerUnit ? number_format($target['targetValue'], 4, ',', '.') : number_format($target['targetValue'], 0, ',', '.') }} {{ $target['unit'] }}
                </span>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:bg-gray-800 dark:border-gray-700 space-y-2">
                <div class="flex justify-between text-sm">
                    <span class="font-medium text-gray-700 dark:text-gray-300">
                        Pencapaian Aktual
                    </span>
                    <span class="font-mono text-gray-600 dark:text-gray-400 font-bold">
                        {{ $isDryerUnit ? number_format($target['actualValue'], 4, ',', '.') : number_format($target['actualValue'], 0, ',', '.') }} 
                        / 
                        {{ $isDryerUnit ? number_format($target['targetValue'], 4, ',', '.') : number_format($target['targetValue'], 0, ',', '.') }} 
                        {{ $target['unit'] }}
                    </span>
                </div>

                {{-- Progress Bar --}}
                <div class="w-full h-3 rounded-full bg-gray-200 dark:bg-gray-700 overflow-hidden">
                    <div
                        class="h-full rounded-full transition-all duration-500"
                        style="
                            width: {{ $progress }}%;
                            background-color:
                                {{ $progress >= 100
                                    ? '#16a34a'   /* green-600 */
                                    : ($progress >= 75
                                        ? '#2563eb' /* blue-600 */
                                        : '#f59e0b' /* amber-500 */) }};
                        ">
                    </div>
                </div>

                {{-- Persentase --}}
                <div class="text-xs text-right text-gray-500 dark:text-gray-400 font-bold">
                    {{ number_format($progress, 1) }}%
                </div>
            </div>
        </div>
        @else
        <div class="space-y-4 py-4 border-t dark:border-gray-700">
            <div class="font-semibold text-lg text-gray-900 dark:text-gray-100">
                Progress Target
            </div>
            <div class="text-sm text-center text-gray-500 dark:text-gray-400 italic py-2">
                Target tidak terdaftar untuk mesin / shift ini.
            </div>
        </div>
        @endif

        {{-- ================= GLOBAL UKURAN + KW ================= --}}
        <div class="space-y-4">
    <div class="font-semibold text-lg text-gray-900 dark:text-gray-100">
        Global Ukuran + KW
    </div>

    <div class="grid grid-cols-1 gap-3">
        @foreach ($summary['globalUkuranKw'] as $row)
        <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-sm dark:bg-gray-800 dark:border-gray-700">
            <div class="text-sm font-medium text-gray-700 dark:text-gray-300">
                {{ $row->ukuran }}
                <span class="text-xs text-gray-500 dark:text-gray-400">• KW {{ $row->kw }}</span>
                {{-- TAMBAHAN: Jenis Kayu --}}
                <span class="ml-1 text-xs font-semibold text-blue-500 dark:text-blue-400">
                    • {{ $row->jenis_kayu }}
                </span>
            </div>
            <div class="text-lg font-bold text-gray-900 dark:text-white">
                {{ number_format($row->total) }}
            </div>
        </div>
        @endforeach
    </div>
</div>

        {{-- ================= GLOBAL UKURAN ================= --}}
        <div class="space-y-4">
    <div class="font-semibold text-lg text-gray-900 dark:text-gray-100">
        Global Ukuran (Semua KW)
    </div>

    <div class="grid grid-cols-1 gap-3">
        @foreach ($summary['globalUkuran'] as $row)
        <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-sm dark:bg-gray-800 dark:border-gray-700">
            <div class="text-sm font-medium text-gray-700 dark:text-gray-300">
                {{ $row->ukuran }}
                {{-- TAMBAHAN: Jenis Kayu --}}
                <span class="ml-1 text-xs font-semibold text-blue-500 dark:text-blue-400">
                    • {{ $row->jenis_kayu }}
                </span>
            </div>
            <div class="text-lg font-bold text-primary-600 dark:text-primary-400">
                {{ number_format($row->total) }}
            </div>
        </div>
        @endforeach
    </div>
</div>

        {{-- ================= RINGKASAN JENIS KAYU & UKURAN ================= --}}
        @if (!empty($summary['globalJenisKayuUkuran']) && count($summary['globalJenisKayuUkuran']) > 0)
        <div class="space-y-4 mt-6">
            <div class="font-semibold text-lg text-gray-900 dark:text-gray-100">
                Ringkasan Penggunaan Kayu & Ukuran Hasil
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

    </x-filament::card>
</x-filament::widget>