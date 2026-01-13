<x-filament::widget>
    <x-filament::card class="w-full space-y-10 dark:bg-gray-900 dark:border-gray-800">

        {{-- ================= SECTION ATAS: STATISTIK UTAMA ================= --}}
        <div class="grid grid-cols-2 gap-4 divide-x divide-gray-200 dark:divide-gray-700">

            {{-- KIRI: TOTAL BARANG --}}
            <div class="text-center py-2">
                <div class="text-4xl font-extrabold text-primary-600 dark:text-primary-500">
                    {{ number_format($summary["totalAll"] ?? 0) }}
                </div>
                <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Produksi (Lembar)</div>
            </div>

            {{-- KANAN: TOTAL PEGAWAI (UNIK) --}}
            <div class="text-center py-2">
                <div class="text-4xl font-extrabold text-success-600 dark:text-success-500">
                    {{ number_format($summary["totalPegawai"] ?? 0) }}
                </div>
                <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Tenaga Kerja (Org)</div>
            </div>

        </div>

        {{-- ================= SECTION BAWAH: REKAP KW (Otomatis) ================= --}}
        @php
        $dataRaw = collect($summary['globalUkuranKw'] ?? []);
        $rekapKw = $dataRaw->groupBy('kw')->map(function ($rows) {
        return (object) [
        'kw' => $rows->first()->kw,
        'total' => $rows->sum('total')
        ];
        })->sortKeys();
        @endphp

        <div class="space-y-3">
            <div class="font-semibold text-lg text-gray-900 dark:text-gray-100">Rekap per KW</div>
            <div class="grid grid-cols-1 gap-4">
                @foreach ($rekapKw as $row)
                <div class="rounded-xl border border-gray-200 bg-white p-4 text-center shadow-sm dark:bg-gray-800 dark:border-gray-700">
                    <div class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">KW {{ $row->kw }}</div>
                    <div class="text-xl font-bold text-gray-900 dark:text-white">
                        {{ number_format($row->total) }}
                    </div>
                </div>
                @endforeach
            </div>
        </div>

        {{-- ================= SECTION BAWAH: REKAP UKURAN ================= --}}
        <div class="space-y-3">
            <div class="font-semibold text-lg text-gray-900 dark:text-gray-100">Rekap per Ukuran</div>
            @php
            $ukuranGrouped = [];
            foreach ($summary['globalUkuranKw'] ?? [] as $row) {
            $ukuranGrouped[$row->ukuran][] = $row;
            }
            @endphp

            <div class="grid grid-cols-1 gap-5">
                @foreach ($ukuranGrouped as $namaUkuran => $items)
                @php $totalPerUkuran = collect($items)->sum('total'); @endphp
                <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:bg-gray-800 dark:border-gray-700">
                    <div class="flex justify-between items-center mb-4">
                        <div class="font-semibold text-gray-800 dark:text-gray-200">{{ $namaUkuran }}</div>
                        <div class="font-bold text-primary-600 dark:text-primary-400">{{ number_format($totalPerUkuran) }}</div>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        @foreach ($items as $row)
                        <div class="flex-1 min-w-[80px] rounded-lg bg-gray-50 px-3 py-2 text-center dark:bg-gray-900/50">
                            <div class="text-[10px] font-medium text-gray-500 dark:text-gray-400 uppercase">KW {{ $row->kw }}</div>
                            <div class="font-semibold text-lg text-gray-900 dark:text-gray-100">{{ number_format($row->total) }}</div>
                        </div>
                        @endforeach
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </x-filament::card>
</x-filament::widget>