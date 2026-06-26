<x-filament::widget>
    <x-filament::card class="w-full space-y-10 dark:bg-gray-900 dark:border-gray-800">

        {{-- ================= STATISTIK UTAMA ================= --}}
        <div
            class="grid grid-cols-1 md:grid-cols-3 gap-4 divide-y md:divide-y-0 md:divide-x divide-gray-200 dark:divide-gray-700">

            {{-- TOTAL PRODUKSI (LEMBAR) --}}
            <div class="text-center py-2">
                <div class="text-4xl font-extrabold text-primary-600 dark:text-primary-500">
                    {{ number_format($summary['totalAll'] ?? 0) }}
                </div>
                <div class="text-sm font-medium text-gray-500 dark:text-gray-400 mt-1">
                    Total Produksi Pot Siku (Lembar)
                </div>
            </div>

            {{-- TOTAL VOLUME PRODUKSI (M3) --}}
            <div class="text-center py-2">
                <div class="text-4xl font-extrabold text-amber-600 dark:text-amber-500">
                    {{ number_format($summary['totalVolumeM3'] ?? 0, 4) }} m³
                </div>
                <div class="text-sm font-medium text-gray-500 dark:text-gray-400 mt-1">
                    Total Volume Hasil Produksi (m³)
                </div>
            </div>

            {{-- TOTAL PEGAWAI --}}
            <div class="text-center py-2">
                <div class="text-4xl font-extrabold text-success-600 dark:text-success-500">
                    {{ number_format($summary['totalPegawai'] ?? 0) }}
                </div>
                <div class="text-sm font-medium text-gray-500 dark:text-gray-400 mt-1">
                    Total Pegawai pada Produksi Ini (Orang)
                </div>
            </div>

        </div>

        {{-- ================= GLOBAL UKURAN (SEMUA KW) ================= --}}
        @if (false)
            <div class="space-y-4">
                <div class="font-semibold text-lg text-gray-900 dark:text-gray-100">
                    Global Ukuran (Semua KW)
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    @forelse (($summary['globalUkuran'] ?? []) as $row)
                        <div
                            class="flex items-center justify-between rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-sm dark:bg-gray-800 dark:border-gray-700">
                            <div class="text-sm font-medium text-gray-700 dark:text-gray-300">
                                {{ $row->ukuran }}
                            </div>

                            <div class="text-right">
                                <div class="text-base font-bold text-primary-600 dark:text-primary-400">
                                    {{ number_format($row->total) }} Lembar
                                </div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">
                                    {{ number_format($row->total_m³, 4) }} m³
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="text-sm text-gray-500 dark:text-gray-400 italic">
                            Belum ada data ukuran.
                        </div>
                    @endforelse
                </div>
            </div>
        @endif

        {{-- ================= RINGKASAN JENIS KAYU & UKURAN ================= --}}
        @if (!empty($summary['globalJenisKayuUkuran']) && count($summary['globalJenisKayuUkuran']) > 0)
            <div class="space-y-4 mt-6">
                <div class="font-semibold text-lg text-gray-900 dark:text-gray-100">
                    Ringkasan Penggunaan Veneer & Ukuran Hasil
                </div>

                <div
                    class="overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm">
                    <table class="w-full text-left text-sm text-gray-600 dark:text-gray-300">
                        <thead class="bg-gray-50 dark:bg-gray-900/50 text-gray-900 dark:text-white">
                            <tr>
                                <th class="px-4 py-3 font-semibold">Jenis Kayu</th>
                                <th class="px-4 py-3 font-semibold">Ukuran Veneer</th>
                                <th class="px-4 py-3 font-semibold text-center">KW</th>
                                <th class="px-4 py-3 font-semibold text-right">Hasil (Tinggi)</th>
                                <th class="px-4 py-3 font-semibold text-right">Hasil (Volume)</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            @php
                                $grandTotalTinggi = 0;
                                $grandTotalM3 = 0;
                            @endphp
                            @foreach ($summary['globalJenisKayuUkuran'] ?? [] as $row)
                                @php
                                    $grandTotalTinggi += $row->total;
                                    $grandTotalM3 += $row->total_m³;
                                @endphp
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors">
                                    <td class="px-4 py-3 font-medium">{{ $row->jenis_kayu }}</td>
                                    <td class="px-4 py-3">{{ $row->ukuran }}</td>
                                    <td class="px-4 py-3 text-center">
                                        <span
                                            class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-200">
                                            KW {{ $row->kw }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-right">{{ number_format($row->total) }} cm</td>
                                    <td class="px-4 py-3 text-right font-medium text-amber-600 dark:text-amber-400">
                                        {{ number_format($row->total_m³, 4) }} m³
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="bg-gray-50 dark:bg-gray-900/50 text-gray-900 dark:text-white font-bold">
                            <tr>
                                <td colspan="3" class="px-4 py-3 text-right border-t dark:border-gray-700">Total
                                    Keseluruhan</td>
                                <td class="px-4 py-3 text-right border-t dark:border-gray-700">
                                    {{ number_format($grandTotalTinggi) }} cm</td>
                                <td
                                    class="px-4 py-3 text-right border-t dark:border-gray-700 text-amber-600 dark:text-amber-400">
                                    {{ number_format($grandTotalM3, 4) }} m³
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        @endif

        {{-- ================= PROGRESS TARGET PEGAWAI ================= --}}
        <div class="mt-6 space-y-4">
            <div class="font-semibold text-lg text-gray-900 dark:text-gray-100">
                Progress Target Pegawai
                <span class="text-sm font-normal text-gray-500 dark:text-gray-400">
                    (Target {{ $summary['progressPegawai'][0]['target'] ?? 300 }} cm)
                </span>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                @forelse ($summary['progressPegawai'] ?? [] as $item)
                    @php
                        $progress = min(100, max(0, (float) $item['progress']));
                    @endphp

                    <div
                        class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:bg-gray-800 dark:border-gray-700 space-y-3">

                        {{-- Nama, Keterangan, & Nilai --}}
                        <div class="flex justify-between items-start text-sm">
                            <div class="space-y-1">
                                <span class="font-semibold text-gray-800 dark:text-gray-200 block">
                                    {{ $item['pegawais'] }}
                                </span>
                                {{-- Keterangan Pegawai --}}
                                @if (!empty($item['keterangan']))
                                    <span
                                        class="inline-flex items-center text-xs text-gray-500 dark:text-gray-400 bg-gray-100 dark:bg-gray-900 px-2 py-0.5 rounded italic">
                                        Ket: {{ $item['keterangan'] }}
                                    </span>
                                @else
                                    <span class="text-xs text-gray-400 dark:text-gray-500 italic block">
                                        Tanpa keterangan kerja
                                    </span>
                                @endif
                            </div>
                            <span class="text-gray-600 dark:text-gray-400 font-medium whitespace-nowrap">
                                {{ number_format($item['total']) }} / {{ $item['target'] }} cm
                            </span>
                        </div>

                        {{-- Progress Bar --}}
                        <div class="w-full h-3 rounded-full bg-gray-200 dark:bg-gray-700 overflow-hidden">
                            <div class="h-full rounded-full transition-all duration-500"
                                style="width: {{ $progress }}%; background-color: {{ $progress >= 100 ? '#16a34a' : ($progress >= 75 ? '#2563eb' : '#f59e0b') }};">
                            </div>
                        </div>

                        {{-- Persentase --}}
                        <div class="text-xs text-right font-semibold text-gray-500 dark:text-gray-400">
                            {{ number_format($progress, 1) }}%
                        </div>
                    </div>
                @empty
                    <div class="text-sm text-gray-500 dark:text-gray-400 italic col-span-full">
                        Belum ada data progress pegawai.
                    </div>
                @endforelse
            </div>
        </div>

    </x-filament::card>
</x-filament::widget>
