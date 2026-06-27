<x-filament::widget>
    <x-filament::card class="w-full space-y-10 dark:bg-gray-900 dark:border-gray-800">

        {{-- ================================================================= --}}
        {{-- ⚠️ LOGIKA HITUNG DATA (PHP IN BLADE) ⚠️ --}}
        {{-- ================================================================= --}}
        @php
            // 1. Ambil data mentah dari Widget PHP
            $dataRaw = collect($summary['globalUkuranKw'] ?? []);

            // 2. Hitung Rekap per Grade/Jenis untuk Section Tengah
            $rekapGrade = $dataRaw
                ->groupBy('kw')
                ->map(function ($rows) {
                    return (object) [
                        'kw' => $rows->first()->kw,
                        'total' => $rows->sum('total'),
                    ];
                })
                ->sortKeys();

            // 3. Kelompokkan per Ukuran untuk Section Bawah
            $ukuranGrouped = $dataRaw->groupBy('ukuran');
        @endphp
        {{-- ================================================================= --}}


        {{-- ================= SECTION 1: HEADER STATISTIK ================= --}}
        <div class="grid grid-cols-2 gap-4 divide-x divide-gray-200 dark:divide-gray-700">

            {{-- KIRI: TOTAL HASIL --}}
            <div class="text-center py-2">
                <div class="text-4xl font-extrabold text-primary-600 dark:text-primary-500">
                    {{ number_format($summary['totalAll'] ?? 0) }}
                </div>
                <div class="text-sm font-medium text-gray-500 dark:text-gray-400">
                    Total Dempul (Pcs)
                </div>
            </div>

            {{-- KANAN: TOTAL PEGAWAI --}}
            <div class="text-center py-2">
                <div class="text-4xl font-extrabold text-green-600 dark:text-green-500">
                    {{ number_format($summary['totalPegawai'] ?? 0) }}
                </div>
                <div class="text-sm font-medium text-gray-500 dark:text-gray-400">
                    Total Tenaga Kerja (Org)
                </div>
            </div>

        </div>

        <hr class="border-gray-100 dark:border-gray-800">

        {{-- ================= TARGET PROGRESS (GERGAJI TRIPLEK) ================= --}}
        @php
            $target = (float) ($summary['target'] ?? 0);
            $totalAll = (float) ($summary['totalAll'] ?? 0);
            $globalProgressVal = (float) ($summary['globalProgress'] ?? 0);
            $globalProgressPercent = min(100, max(0, $globalProgressVal));

            // Get all unique sizes produced in this shift
            $sizesList = collect($summary['globalUkuran'] ?? [])
                ->pluck('ukuran')
                ->implode(', ');
            $sizesLabel = empty($sizesList) ? 'Gergaji Triplek' : 'Ukuran ' . $sizesList;
        @endphp

        <div class="space-y-4">
            <div class="font-semibold text-lg text-gray-900 dark:text-gray-100">
                Progress Target Gergaji Triplek
                <span class="text-sm font-normal text-gray-500 dark:text-gray-400">
                    ( Target {{ number_format($target, 4) }} )
                </span>
            </div>

            <div
                class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:bg-gray-800 dark:border-gray-700 space-y-2">
                {{-- Nama & Nilai --}}
                <div class="flex justify-between text-sm">
                    <span class="font-medium text-gray-700 dark:text-gray-300">
                        {{ $sizesLabel }}
                    </span>
                    <span class="text-gray-600 dark:text-gray-400 font-bold">
                        {{ number_format($totalAll) }} / {{ (float) $target }}
                    </span>
                </div>

                {{-- Progress Bar --}}
                <div class="w-full h-3 rounded-full bg-gray-200 dark:bg-gray-700 overflow-hidden">
                    <div class="h-full rounded-full transition-all duration-500"
                        style="
                            width: {{ $globalProgressPercent }}%;
                            background-color:
                                {{ $globalProgressPercent >= 100
                                    ? '#16a34a' /* green-600 */
                                    : ($globalProgressPercent >= 75
                                        ? '#2563eb' /* blue-600 */
                                        : '#f59e0b'); /* amber-500 */ }};
                        ">
                    </div>
                </div>

                {{-- Persentase --}}
                <div class="text-xs text-right text-gray-500 dark:text-gray-400 font-bold">
                    {{ number_format($globalProgressVal, 1) }}%
                </div>
            </div>
        </div>

        <hr class="border-gray-100 dark:border-gray-800">

        {{-- ================= SECTION 2: REKAP JENIS & GRADE ================= --}}
        @if (false)
            <div class="space-y-3">
                <div class="font-semibold text-lg text-gray-900 dark:text-gray-100">Rekap Grade</div>

                <div class="grid grid-cols-1 gap-4">
                    @foreach ($rekapGrade as $row)
                        <div
                            class="rounded-xl border border-gray-200 bg-white p-3 text-center shadow-sm dark:bg-gray-800 dark:border-gray-700">
                            <div class="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wide">
                                {{ $row->kw }}
                            </div>
                            <div class="text-xl font-bold text-gray-900 dark:text-white mt-1">
                                {{ number_format($row->total) }}
                            </div>
                        </div>
                    @endforeach

                    {{-- Handle jika kosong --}}
                    @if ($rekapGrade->isEmpty())
                        <div class="col-span-full text-center text-gray-400 text-sm italic">Belum ada data dempul.</div>
                    @endif
                </div>
            </div>
        @endif

        {{-- ================= SECTION 3: RINCIAN PER UKURAN ================= --}}
        <div class="space-y-3">
            <div class="font-semibold text-lg text-gray-900 dark:text-gray-100">
                Rincian per Ukuran
            </div>

            <div class="grid grid-cols-1 gap-5">
                @foreach ($ukuranGrouped as $namaUkuran => $items)
                    @php
                        // Hitung total gabungan untuk ukuran ini
                        $totalPerUkuran = collect($items)->sum('total');
                    @endphp

                    <div
                        class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:bg-gray-800 dark:border-gray-700">
                        {{-- Header Kartu: Nama Ukuran & Total --}}
                        <div
                            class="flex justify-between items-center mb-4 border-b border-gray-100 pb-2 dark:border-gray-700">
                            <div class="font-bold text-gray-800 dark:text-gray-200 text-md">
                                {{ $namaUkuran }}
                            </div>
                            <div class="font-bold text-primary-600 dark:text-primary-400 text-lg">
                                {{ number_format($totalPerUkuran) }}
                            </div>
                        </div>

                        {{-- Body Kartu: Daftar Grade/KW --}}
                        <div class="flex flex-wrap gap-2">
                            @foreach ($items as $row)
                                <div
                                    class="flex-1 min-w-[100px] rounded-lg bg-gray-50 px-3 py-2 text-center dark:bg-gray-900/50">

                                    {{-- Baris 1: Jumlah Pcs --}}
                                    <div class="font-semibold text-gray-900 dark:text-gray-100 text-lg">
                                        {{ number_format($row->total) }}
                                    </div>

                                    {{-- Baris 2: Label KW (Clean Style - Tanpa Background) --}}
                                    <div
                                        class="text-[10px] font-bold text-gray-500 dark:text-gray-400 uppercase mt-1 leading-tight">
                                        <span
                                            class="text-primary-400 dark:text-primary-600 mr-1">•</span>{{ $row->kw }}
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach

                {{-- Pesan jika data kosong --}}
                @if ($ukuranGrouped->isEmpty())
                    <div class="text-center text-gray-400 py-4 italic">Belum ada hasil dempul.</div>
                @endif
            </div>
            {{-- ================= SECTION 4: RINGKASAN JENIS KAYU & UKURAN ================= --}}
            @if (!empty($summary['globalJenisKayuUkuran']) && count($summary['globalJenisKayuUkuran']) > 0)
                <div class="space-y-4 pt-6 border-t border-gray-100 dark:border-gray-800">
                    <div class="font-semibold text-lg text-gray-900 dark:text-gray-100">
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
                                        <td class="px-4 py-3 text-right font-medium">{{ number_format($row->total) }}
                                        </td>
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
