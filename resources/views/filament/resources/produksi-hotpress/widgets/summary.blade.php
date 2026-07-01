<x-filament::widget>
    <x-filament::card class="w-full space-y-6 dark:bg-gray-900 dark:border-gray-800">

        {{-- ================= HEADER: JUDUL & TOTAL PEGAWAI ================= --}}
        <div class="flex items-center justify-between border-b border-gray-100 pb-4 dark:border-gray-700">
            <div>
                <h2 class="text-lg font-bold text-gray-800 dark:text-gray-100">Hasil Produksi Hotpress</h2>
                <p class="text-xs text-gray-500">Output: Platform & Triplek</p>
            </div>
            <div class="text-right">
                <div class="text-2xl font-extrabold text-green-600 dark:text-green-500">
                    {{ number_format($summary['totalPegawai'] ?? 0) }}
                </div>
                <div class="text-xs font-medium text-gray-500 dark:text-gray-400">Total Tenaga Kerja</div>
            </div>
        </div>

        {{-- ================= GRID 2 KOLOM (PLATFORM vs TRIPLEK) ================= --}}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">

            {{-- 1. KOLOM KIRI: HASIL PLATFORM --}}
            <div class="space-y-4">
                {{-- Header Kolom --}}
                <div class="flex justify-between items-center bg-blue-50 p-3 rounded-lg dark:bg-blue-900/20">
                    <span class="font-bold text-blue-700 dark:text-blue-400">HASIL PLATFORM</span>
                    <span class="font-extrabold text-xl text-blue-700 dark:text-blue-400">
                        {{ number_format($summary['totalPlatform'] ?? 0) }} <span class="text-xs font-normal">Pcs</span>
                    </span>
                </div>

                {{-- List Detail --}}
                <div class="space-y-3">
                    @if (isset($summary['listPlatform']) && count($summary['listPlatform']) > 0)
                    @foreach ($summary['listPlatform'] as $row)
                    <div
                        class="flex justify-between items-center border-b border-gray-100 pb-2 last:border-0 dark:border-gray-700">
                        <div>
                            <div class="text-sm font-semibold text-gray-800 dark:text-gray-200">
                                {{ $row->ukuran }} - {{ $row->kw }}
                            </div>
                        </div>
                        <div class="font-bold text-gray-700 dark:text-gray-300">
                            {{ number_format($row->total) }}
                        </div>
                    </div>
                    @endforeach
                    @else
                    <div class="text-center text-xs text-gray-400 italic py-2">Belum ada hasil Platform</div>
                    @endif
                </div>
            </div>

            {{-- 2. KOLOM KANAN: HASIL TRIPLEK --}}
            <div class="space-y-4">
                {{-- Header Kolom --}}
                <div class="flex justify-between items-center bg-purple-50 p-3 rounded-lg dark:bg-purple-900/20">
                    <span class="font-bold text-purple-700 dark:text-purple-400">HASIL TRIPLEK</span>
                    <span class="font-extrabold text-xl text-purple-700 dark:text-purple-400">
                        {{ number_format($summary['totalTriplek'] ?? 0) }} <span class="text-xs font-normal">Pcs</span>
                    </span>
                </div>

                {{-- List Detail --}}
                <div class="space-y-3">
                    @if (isset($summary['listTriplek']) && count($summary['listTriplek']) > 0)
                    @foreach ($summary['listTriplek'] as $row)
                    <div
                        class="flex justify-between items-center border-b border-gray-100 pb-2 last:border-0 dark:border-gray-700">
                        <div>
                            <div class="text-sm font-semibold text-gray-800 dark:text-gray-200">
                                {{ $row->ukuran }} - {{ $row->kw }}
                            </div>
                        </div>
                        <div class="font-bold text-gray-700 dark:text-gray-300">
                            {{ number_format($row->total) }}
                        </div>
                    </div>
                    @endforeach
                    @else
                    <div class="text-center text-xs text-gray-400 italic py-2">Belum ada hasil Triplek</div>
                    @endif
                </div>
            </div>

        </div>

        {{-- ================= TARGET PROGRESS ================= --}}
        {{-- ================= TARGET PROGRESS ================= --}}
        @if (isset($summary['globalActualAchievement']))
        <div class="space-y-6 py-6 border-t dark:border-gray-700">
            <div class="space-y-3">
                <div class="font-bold text-lg text-gray-900 dark:text-gray-100">
                    <span>Progress Target Produksi Hot Press (Global)</span>
                </div>

                @php
                $globalProgressVal = (float) ($summary['globalProgress'] ?? 0);
                $globalProgressPercent = min(100, max(0, $globalProgressVal));
                @endphp

                <div
                    class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:bg-gray-800 dark:border-gray-700 space-y-3">
                    {{-- Nama & Nilai --}}
                    <div class="flex justify-between items-end text-sm">
                        <span class="font-semibold text-gray-700 dark:text-gray-300">
                            Total Hasil Produksi (Aktual)
                        </span>
                        <span class="text-gray-600 dark:text-gray-400 font-mono font-bold text-base">
                            {{ number_format($summary['globalActualAchievement']) }} pcs
                        </span>
                    </div>

                    {{-- Progress Bar --}}
                    <div
                        class="w-full h-4 rounded-full bg-gray-100 dark:bg-gray-700 overflow-hidden shadow-inner flex">
                        <div class="h-full rounded-full transition-all duration-500 ease-out"
                            style="
                                width: {{ $globalProgressPercent }}%;
                                background: linear-gradient(90deg, 
                                    {{ $globalProgressPercent >= 100
                                        ? '#16a34a, #22c55e' /* green gradient */
                                        : ($globalProgressPercent >= 75
                                            ? '#2563eb, #3b82f6' /* blue gradient */
                                            : '#f59e0b, #fbbf24') }} /* amber gradient */
                                );
                            ">
                        </div>
                    </div>

                    {{-- Persentase & Info --}}
                    <div class="flex justify-between items-center text-xs text-gray-500 dark:text-gray-400">
                        <div>
                            @if (!$summary['hasTargetGlobal'])
                            <span class="text-amber-600 dark:text-amber-400 italic font-medium">
                                * Silakan atur target untuk ukuran-ukuran ini di menu target mesin HOTPRESS
                            </span>
                            @else
                            <span class="text-gray-500 dark:text-gray-400 font-semibold">
                                Bebas potongan gaji jika target global mencapai 100% (tidak harus 100% per
                                ukuran).
                            </span>
                            @endif
                        </div>
                        <div
                            class="font-extrabold text-sm {{ $globalProgressVal >= 100 ? 'text-green-600 dark:text-green-500' : ($globalProgressVal >= 75 ? 'text-blue-600 dark:text-blue-500' : 'text-amber-500') }}">
                            @if ($globalProgressVal >= 100)
                            Target Tercapai
                            @else
                            {{ number_format($globalProgressVal, 1) }}%
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            {{-- Breakdown per Ukuran Table --}}
            @if (!empty($summary['targetProgress']))
            <div class="space-y-3 mt-4">
                <div class="font-semibold text-sm text-gray-700 dark:text-gray-300">
                    Breakdown Pencapaian per Ukuran
                </div>
                <div
                    class="overflow-hidden rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm">
                    <table class="w-full text-left text-xs text-gray-600 dark:text-gray-300">
                        <thead
                            class="bg-gray-50 dark:bg-gray-900/50 text-gray-900 dark:text-white font-semibold">
                            <tr>
                                <th class="px-4 py-2">Ukuran</th>
                                <th class="px-3 py-2 text-center">Tebal (mm)</th>
                                <th class="px-3 py-2 text-right">Hasil (pcs)</th>
                                <th class="px-3 py-2 text-right">Target (pcs)</th>
                                <th class="px-3 py-2 text-center">Progress</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach ($summary['targetProgress'] as $item)
                            @php
                            $sizeProgress =
                            $item['hasTarget'] && $item['target'] > 0
                            ? ($item['actual'] / $item['target']) * 100
                            : 0;
                            @endphp
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/30 transition-colors">
                                <td class="px-4 py-2.5 font-medium text-gray-950 dark:text-gray-200">
                                    {{ $item['ukuran'] }}
                                </td>
                                <td class="px-3 py-2.5 text-center font-mono font-medium">
                                    {{ number_format($item['tebal'], 1) }}
                                </td>
                                <td class="px-3 py-2.5 text-right font-mono">
                                    {{ number_format($item['actual']) }}
                                </td>
                                <td
                                    class="px-3 py-2.5 text-right font-mono text-gray-500 dark:text-gray-400">
                                    {{ $item['hasTarget'] ? number_format($item['target']) : '-' }}
                                </td>
                                <td
                                    class="px-3 py-2.5 text-center font-mono font-bold {{ $sizeProgress >= 100 ? 'text-green-600 dark:text-green-500' : 'text-gray-900 dark:text-gray-100 font-medium' }}">
                                    @if (!$item['hasTarget'])
                                    -
                                    @else
                                    +{{ number_format($sizeProgress, 1) }}%
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            @endif
        </div>
        @endif

        {{-- ================= RINGKASAN JENIS KAYU & UKURAN ================= --}}
        @if (!empty($summary['globalJenisKayuUkuran']) && count($summary['globalJenisKayuUkuran']) > 0)
        <div class="space-y-4 mt-8">
            <div class="font-semibold text-lg text-gray-900 dark:text-gray-100">
                Hasil Produksi (Platform & Triplek)
            </div>

            <div
                class="overflow-hidden rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm">
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