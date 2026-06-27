<x-filament::widget>
    <x-filament::card class="w-full space-y-10 dark:bg-gray-900 dark:border-gray-800">

        {{-- ================= STATISTIK UTAMA ================= --}}
        <div class="grid grid-cols-2 gap-4 divide-x divide-gray-200 dark:divide-gray-700">

            {{-- TOTAL PRODUKSI --}}
            <div class="text-center py-2">
                <div class="text-4xl font-extrabold text-primary-600 dark:text-primary-500">
                    {{ number_format($summary['totalAll'] ?? 0) }}
                </div>
                <div class="text-sm font-medium text-gray-500 dark:text-gray-400">
                    Total Produksi Stik (Lembar)
                </div>
            </div>

            {{-- TOTAL PEGAWAI --}}
            <div class="text-center py-2">
                <div class="text-4xl font-extrabold text-success-600 dark:text-success-500">
                    {{ number_format($summary['totalPegawai'] ?? 0) }}
                </div>
                <div class="text-sm font-medium text-gray-500 dark:text-gray-400">
                    Total Pegawai pada Produksi Ini (Orang)
                </div>
            </div>

        </div>

        {{-- ================= TARGET PROGRESS ================= --}}
        @if (!empty($summary['targetProgress']))
        @php
        $item = $summary['targetProgress'];
        $progress = min(100, max(0, (float) $item['progress']));
        @endphp
        <div class="space-y-3 py-6 border-t dark:border-gray-700">
            <div class="font-semibold text-lg text-gray-900 dark:text-gray-100 flex justify-between items-center">
                <span>Progress Target Produksi Stik</span>
                <span class="text-sm font-normal text-gray-500 dark:text-gray-400">
                    ( Target {{ $item['hasTarget'] ? number_format($item['target']) . ' lbr' : 'Belum di Set (Ukuran 0x0x0)' }} )
                </span>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:bg-gray-800 dark:border-gray-700 space-y-2">
                {{-- Nama & Nilai --}}
                <div class="flex justify-between text-sm">
                    <span class="font-medium text-gray-700 dark:text-gray-300">
                        Total Pencapaian Aktual (Semua Ukuran)
                    </span>
                    <span class="text-gray-600 dark:text-gray-400 font-mono font-bold">
                        {{ number_format($item['actual']) }}
                        / {{ $item['hasTarget'] ? number_format($item['target']) . ' lbr' : '-' }}
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

                {{-- Persentase & Info --}}
                <div class="flex justify-between items-center text-xs text-gray-500 dark:text-gray-400">
                    <div>
                        @if (!$item['hasTarget'])
                        <span class="text-amber-600 dark:text-amber-400 italic font-medium">
                            * Silakan atur target untuk ukuran 0x0x0 di menu target mesin STIK
                        </span>
                        @else
                        <span class="text-gray-400">
                            Tenaga: {{ $item['orang'] !== '-' ? $item['orang'] . ' org' : '-' }} | Jam Kerja: {{ $item['jam'] !== '-' ? $item['jam'] . ' jam' : '-' }}
                        </span>
                        @endif
                    </div>
                    <div class="font-bold">
                        {{ number_format($progress, 1) }}%
                    </div>
                </div>
            </div>
        </div>
        @endif

        {{-- ================= GLOBAL UKURAN + KW ================= --}}
        {{-- ================= 1. RINGKASAN HASIL VENEER ================= --}}
        <div class="space-y-4">
            <div class="font-semibold text-lg text-gray-900 dark:text-gray-100 flex items-center gap-2">
                <span>Ringkasan Hasil Veneer</span>
            </div>

            <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm">
                {{-- Tambahkan w-full dan table-fixed agar ukuran kolom konsisten --}}
                <table class="w-full table-fixed text-left text-sm text-gray-600 dark:text-gray-300">
                    <thead class="bg-gray-50 dark:bg-gray-900/50 text-gray-900 dark:text-white">
                        <tr>
                            {{-- Atur persentase lebar yang sama persis dengan tabel bawah --}}
                            <th class="w-1/4 px-4 py-3 font-semibold">Jenis Kayu</th>
                            <th class="w-1/3 px-4 py-3 font-semibold">Ukuran</th>
                            <th class="w-1/6 px-4 py-3 font-semibold">KW</th>
                            <th class="w-1/4 px-4 py-3 font-semibold text-right">Hasil (Total Lembar)</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @php $totalGlobalKw = 0; @endphp
                        @foreach (($summary['globalUkuranKw'] ?? []) as $row)
                        @php $totalGlobalKw += $row->total; @endphp
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors">
                            <td class="px-4 py-3 truncate">{{ $row->jenis_kayu }}</td>
                            <td class="px-4 py-3 text-gray-900 dark:text-white truncate">{{ $row->ukuran }}</td>
                            <td class="px-4 py-3">{{ $row->kw }}</td>
                            <td class="px-4 py-3 text-right font-bold text-primary-600 dark:text-primary-400">{{ number_format($row->total) }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{-- ================= 2. RINGKASAN MODAL VENEER ================= --}}
        @if (!empty($summary['globalJenisKayuUkuran']) && count($summary['globalJenisKayuUkuran']) > 0)
        <div class="space-y-4 mt-6">
            <div class="font-semibold text-lg text-gray-900 dark:text-gray-100">
                Ringkasan Modal Veneer
            </div>

            <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm">
                {{-- Tambahkan w-full dan table-fixed agar ukuran kolom konsisten --}}
                <table class="w-full table-fixed text-left text-sm text-gray-600 dark:text-gray-300">
                    <thead class="bg-gray-50 dark:bg-gray-900/50 text-gray-900 dark:text-white">
                        <tr>
                            {{-- Persentase di sini disamakan persis dengan tabel di atas --}}
                            <th class="w-1/4 px-4 py-3 font-semibold">Jenis Kayu</th>
                            <th class="w-1/3 px-4 py-3 font-semibold">Ukuran Veneer</th>
                            <th class="w-1/6 px-4 py-3 font-semibold">KW</th>
                            <th class="w-1/4 px-4 py-3 font-semibold text-right">Total Bahan</th> {{-- Diubah ke text-right agar lurus dengan kolom angka di atas --}}
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @php $grandTotal = 0; @endphp
                        @foreach (($summary['globalJenisKayuUkuran'] ?? []) as $row)
                        @php $grandTotal += $row->total_bahan ?? 0; @endphp
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors">
                            <td class="px-4 py-3 truncate">{{ $row->jenis_kayu }}</td>
                            <td class="px-4 py-3 truncate">{{ $row->ukuran }}</td>
                            <td class="px-4 py-3">{{ $row->kw }}</td>
                            <td class="px-4 py-3 text-right font-medium text-amber-500">{{ number_format($row->total_bahan ?? 0) }}</td> {{-- Diubah ke text-right agar lurus dengan angka di atas --}}
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif

    </x-filament::card>
</x-filament::widget>