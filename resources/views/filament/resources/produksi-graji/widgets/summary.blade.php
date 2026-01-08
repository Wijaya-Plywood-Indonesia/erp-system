<x-filament::widget>
    <x-filament::card class="w-full space-y-10 dark:bg-gray-900 dark:border-gray-800">

        {{-- ================================================================= --}}
        {{-- ⚠️ LOGIKA HITUNG DATA (PHP IN BLADE) ⚠️ --}}
        {{-- ================================================================= --}}
        @php
        // 1. Ambil data mentah dari Widget PHP
        // Data ini sekarang berisi kolom 'kw' yang sudah digabung (Jenis + Grade)
        $dataRaw = collect($summary['globalUkuranKw'] ?? []);

        // 2. Hitung Rekap per Grade/Jenis untuk Section Tengah
        $rekapGrade = $dataRaw->groupBy('kw')->map(function ($rows) {
        return (object) [
        'kw' => $rows->first()->kw,
        'total' => $rows->sum('total')
        ];
        })->sortKeys();

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
                    Total Hasil Graji (Pcs)
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

        {{-- ================= SECTION 2: REKAP JENIS & GRADE ================= --}}
        <div class="space-y-3">
            <div class="font-semibold text-lg text-gray-900 dark:text-gray-100">Rekap Jenis & Grade</div>

            <div class="grid grid-cols-1 gap-4">
                @foreach ($rekapGrade as $row)
                <div class="rounded-xl border border-gray-200 bg-white p-3 text-center shadow-sm dark:bg-gray-800 dark:border-gray-700">
                    <div class="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wide">
                        {{-- Ini akan menampilkan misal: "PLYWOOD KW A" --}}
                        {{ $row->kw }}
                    </div>
                    <div class="text-xl font-bold text-gray-900 dark:text-white mt-1">
                        {{ number_format($row->total) }}
                    </div>
                </div>
                @endforeach

                {{-- Handle jika kosong --}}
                @if($rekapGrade->isEmpty())
                <div class="col-span-full text-center text-gray-400 text-sm italic">Belum ada data graji.</div>
                @endif
            </div>
        </div>

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

                <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:bg-gray-800 dark:border-gray-700">
                    {{-- Header Kartu: Nama Ukuran & Total --}}
                    <div class="flex justify-between items-center mb-4 border-b border-gray-100 pb-2 dark:border-gray-700">
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
                        <div class="flex-1 min-w-[100px] rounded-lg bg-gray-50 px-3 py-2 text-center dark:bg-gray-900/50">

                            {{-- Baris 1: Jumlah Pcs --}}
                            <div class="font-semibold text-gray-900 dark:text-gray-100 text-lg">
                                {{ number_format($row->total) }}
                            </div>

                            {{-- Baris 2: Label KW (Clean Style - Tanpa Background) --}}
                            <div class="text-[10px] font-bold text-gray-500 dark:text-gray-400 uppercase mt-1 leading-tight">
                                <span class="text-primary-400 dark:text-primary-600 mr-1">•</span>{{ $row->kw }}
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
                @endforeach

                {{-- Pesan jika data kosong --}}
                @if($ukuranGrouped->isEmpty())
                <div class="text-center text-gray-400 py-4 italic">Belum ada hasil graji.</div>
                @endif
            </div>
        </div>

    </x-filament::card>
</x-filament::widget>