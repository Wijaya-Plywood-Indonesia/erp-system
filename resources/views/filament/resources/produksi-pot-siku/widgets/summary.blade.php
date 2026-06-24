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
                    Total Produksi Pot Siku (Lembar)
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

        {{-- ================= GLOBAL UKURAN + KW ================= --}}
        <div class="space-y-4">
            <div class="font-semibold text-lg text-gray-900 dark:text-gray-100">
                Global Ukuran + KW
            </div>

            <div class="grid grid-cols-1 gap-3">
             @foreach (($summary['globalUkuranKw'] ?? []) as $row)
                    <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-sm
                                dark:bg-gray-800 dark:border-gray-700">
                        <div class="text-sm font-medium text-gray-700 dark:text-gray-300">
                            {{ $row->ukuran }}
                            <span class="text-xs text-gray-500 dark:text-gray-400">
                                • KW {{ $row->kw }}
                            </span>
                        </div>

                        <div class="text-lg font-bold text-gray-900 dark:text-white">
                            {{ number_format($row->total) }}
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
@if (false)
    

     {{-- ================= GLOBAL UKURAN (SEMUA KW) ================= --}}
<div class="space-y-4">
    <div class="font-semibold text-lg text-gray-900 dark:text-gray-100">
        Global Ukuran (Semua KW)
    </div>

    <div class="grid grid-cols-1 gap-3">
    @foreach (($summary['globalUkuranKw'] ?? []) as $row)
            <div
                class="flex items-center justify-between rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-sm
                       dark:bg-gray-800 dark:border-gray-700">
                <div class="text-sm font-medium text-gray-700 dark:text-gray-300">
                    {{ $row->ukuran }}
                </div>

                <div class="text-lg font-bold text-primary-600 dark:text-primary-400">
                    {{ number_format($row->total) }}
                </div>
            </div>
        @endforeach
    </div>
</div>
@endif
{{-- ================= RINGKASAN JENIS KAYU & UKURAN ================= --}}
@if (!empty($summary['globalJenisKayuUkuran']) && count($summary['globalJenisKayuUkuran']) > 0)
<div class="space-y-4 mt-6">
    <div class="font-semibold text-lg text-gray-900 dark:text-gray-100">
        Ringkasan Penggunaan Veneer & Ukuran Hasil
    </div>

    <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm">
        <table class="w-full text-left text-sm text-gray-600 dark:text-gray-300">
            <thead class="bg-gray-50 dark:bg-gray-900/50 text-gray-900 dark:text-white">
                <tr>
                    <th class="px-4 py-3 font-semibold">Jenis Kayu</th>
                    <th class="px-4 py-3 font-semibold">Ukuran Veneer</th>
                    <th class="px-4 py-3 font-semibold">kw</th>
                    <th class="px-4 py-3 font-semibold text-right">Hasil (Tinggi)</th>
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

{{-- ================= PROGRESS TARGET PEGAWAI ================= --}}
<div class="mt-6 space-y-4">
    <div class="font-semibold text-lg text-gray-900 dark:text-gray-100">
        Progress Target Pegawai
        <span class="text-sm font-normal text-gray-500 dark:text-gray-400">
            (Target 300 cm)
        </span>
    </div>

    @forelse ($summary['progressPegawai'] ?? [] as $item)
        @php
            // pastikan numeric & dibatasi max 100
            $progress = min(100, max(0, (float) $item['progress']));
        @endphp

        <div
            class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm
                   dark:bg-gray-800 dark:border-gray-700 space-y-2">

            {{-- Nama & Nilai --}}
            <div class="flex justify-between text-sm">
                <span class="font-medium text-gray-700 dark:text-gray-300">
                    {{ $item['pegawais'] }}
                </span>
                <span class="text-gray-600 dark:text-gray-400">
                    {{ number_format($item['total']) }} / {{ $item['target'] }} cm
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
            <div class="text-xs text-right text-gray-500 dark:text-gray-400">
                {{ number_format($progress, 1) }}%
            </div>
        </div>
    @empty
        <div class="text-sm text-gray-500 dark:text-gray-400 italic">
            Belum ada data progress pegawai.
        </div>
    @endforelse
</div>

    </x-filament::card>
</x-filament::widget>
