{{-- resources/views/filament/pages/stok-kayu-page.blade.php --}}
<x-filament-panels::page>

    @php
    $summaries = $this->summaries;
    $grouped = $this->groupedSummaries;
    // Kita ambil semua lahan untuk ditampilkan di tabel detail
    $allLahans = \App\Models\Lahan::orderBy('kode_lahan')->get();
    // Kelompokkan summaries berdasarkan id_lahan agar mudah dipanggil di loop lahan
    $summariesByLahan = $summaries->groupBy('id_lahan');
    @endphp

    <div class="flex flex-col gap-8">
        {{-- SECTION 1: RINGKASAN STOK (TABLE PER PANJANG - HASIL GABUNGAN) --}}
        <div class="space-y-8">
            @forelse($grouped as $panjang => $rows)
            <div class="space-y-3">
                <div class="flex items-center gap-3">
                    <span class="bg-gray-800 dark:bg-gray-100 text-white dark:text-gray-900 text-[10px] font-black px-4 py-1.5 rounded uppercase tracking-widest shadow-sm">
                        Ukuran Panjang {{ $panjang }}
                    </span>
                    <div class="h-px flex-1 bg-gray-100 dark:bg-gray-900"></div>
                </div>

                <div class="bg-white dark:bg-gray-900 rounded border border-gray-200 dark:border-gray-800 shadow-sm overflow-hidden">
                    <table class="w-full text-sm text-left border-separate border-spacing-0">
                        <thead>
                            <tr class="text-gray-400 dark:text-gray-400 uppercase text-[9px] tracking-widest font-black bg-gray-50/50 dark:bg-gray-800/50">
                                <th class="px-6 py-3 text-center border-b border-gray-100 dark:border-gray-800 w-16">No</th>
                                <th class="px-6 py-3 border-b border-gray-100 dark:border-gray-800">Panjang</th>
                                <th class="px-6 py-3 border-b border-gray-100 dark:border-gray-800">Jenis Kayu</th>
                                <th class="px-6 py-3 text-center border-b border-gray-100 dark:border-gray-800">Total Batang</th>
                                <th class="px-6 py-3 text-right border-b border-gray-100 dark:border-gray-800">Kubikasi</th>
                                <th class="px-6 py-3 text-right border-b border-gray-100 dark:border-gray-800">HPP Average</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50 dark:divide-gray-800">
                            {{-- Kita kelompokkan lagi berdasarkan id_jenis_kayu untuk menggabung hasil --}}
                            @foreach($rows->groupBy('id_jenis_kayu') as $jenisId => $items)
                            @php
                            $firstRow = $items->first();
                            $mergedBatang = $items->sum('stok_batang');
                            $mergedKubikasi = $items->sum('stok_kubikasi');
                            $mergedNilai = $items->sum('nilai_stok');

                            // HPP Average Gabungan (Weighted Average)
                            $mergedHpp = $mergedKubikasi > 0 ? $mergedNilai / $mergedKubikasi : 0;
                            @endphp
                            <tr class="group hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors">
                                <td class="px-6 py-4 text-center text-gray-300 dark:text-gray-600 font-mono text-xs">{{ $loop->iteration }}</td>
                                <td class="px-6 py-4">
                                    <span class="font-black text-gray-800 dark:text-gray-200 text-base">{{ $panjang }}</span>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-2">
                                        <div @class([ 'w-2 h-2 rounded-sm' , 'bg-emerald-500'=> str_contains(strtolower($firstRow->jenisKayu->nama_kayu), 'sengon'),
                                            'bg-amber-500' => !str_contains(strtolower($firstRow->jenisKayu->nama_kayu), 'sengon'),
                                            ])></div>
                                        <span class="font-bold text-gray-700 dark:text-gray-300 uppercase tracking-tight">
                                            {{ $firstRow->jenisKayu->nama_kayu }}
                                        </span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <span class="font-black text-gray-700 dark:text-gray-300 tabular-nums text-lg">
                                        {{ number_format($mergedBatang) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-right font-mono font-black text-blue-600 dark:text-blue-400 text-base">
                                    {{ number_format($mergedKubikasi, 4) }}
                                    <span class="text-sm text-gray-500 dark:text-gray-400 font-normal uppercase">m³</span>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <span class="font-black text-emerald-600 dark:text-emerald-400 tabular-nums">
                                        Rp {{ number_format($mergedHpp, 0, ',', '.') }}
                                    </span>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            @empty
            <div class="py-12 text-center text-gray-400 dark:text-gray-600 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded">
                Tidak ada data ringkasan tersedia
            </div>
            @endforelse
        </div>

        {{-- SECTION 2: DETAIL RINCIAN LAHAN (SEMUA LAHAN) --}}
        <div class="space-y-4">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div>
                    <h2 class="text-lg font-black text-gray-800 dark:text-gray-200 tracking-tight uppercase">Detail Rincian Lahan</h2>
                </div>
                <div class="flex items-center gap-2">
                    <span class="text-[10px] font-black text-gray-100 dark:text-gray-900 bg-gray-800 dark:bg-gray-100 px-2 py-0.5 rounded uppercase tracking-widest">
                        {{ $allLahans->count() }} Lahan Terdaftar
                    </span>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-900 rounded border border-gray-200 dark:border-gray-800 shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left border-separate border-spacing-0">
                        <thead>
                            <tr class="text-gray-500 dark:text-gray-400 uppercase text-[10px] tracking-[0.2em] font-black bg-gray-50/50 dark:bg-gray-800/50">
                                <th class="px-6 py-5 text-center border-b border-gray-100 dark:border-gray-800 w-24">Aksi</th>
                                <th class="px-6 py-5 text-center border-b border-gray-100 dark:border-gray-800 w-16">No</th>
                                <th class="px-6 py-5 border-b border-gray-100 dark:border-gray-800 w-40">Lahan</th>
                                <th class="px-6 py-5 border-b border-gray-100 dark:border-gray-800">Kombinasi Ukuran — Jenis</th>
                                <th class="px-6 py-5 text-center border-b border-gray-100 dark:border-gray-800 w-32">Batang</th>
                                <th class="px-6 py-5 text-right border-b border-gray-100 dark:border-gray-800 w-40">Volume (m³)</th>
                                <th class="px-6 py-5 text-right border-b border-gray-100 dark:border-gray-800 w-48">Poin Estimasi</th>
                                <th class="px-6 py-5 text-right border-b border-gray-100 dark:border-gray-800 w-48">Hpp/Lahan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50 dark:divide-gray-800 font-sans">
                            @php $globalIdx = 1; $summariesByLahan = $this->summariesByLahan;@endphp
                            @foreach($allLahans as $lahan)
                            @php $lahanSummaries = $summariesByLahan->get($lahan->id); @endphp

                            @if($lahanSummaries && $lahanSummaries->count() > 0)
                            {{-- Jika Lahan ada isinya, loop setiap kombinasi --}}
                            @foreach($lahanSummaries as $row)
                            <tr class="group hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors">
                                <td class="px-6 py-5 text-center border-b border-gray-50 dark:border-gray-800">
                                    {{ ($this->editStokAction)(['id' => $row->id]) }} {{ ($this->deleteStokAction)(['id' => $row->id]) }}
                                </td>
                                <td class="px-6 py-5 text-center text-gray-300 dark:text-gray-600 font-mono text-xs">{{ $globalIdx++ }}</td>
                                <td class="px-6 py-5">
                                    <div class="flex items-center gap-2.5">
                                        <span class="text-gray-800 dark:text-gray-200 font-black text-base">{{ $lahan->kode_lahan }}</span>
                                    </div>
                                </td>
                                <td class="px-6 py-5">
                                    <div class="flex items-center gap-2">
                                        <span class="font-black text-gray-900 dark:text-gray-100 text-sm tabular-nums leading-none">{{ $row->panjang }}</span>
                                        <span class="text-gray-300 dark:text-gray-400 font-light">—</span>
                                        <span @class([ 'text-sm font-bold uppercase tracking-wide' , 'text-emerald-600 dark:text-emerald-400'=> str_contains(strtolower($row->jenisKayu->nama_kayu), 'sengon'),
                                            'text-amber-600 dark:text-amber-400' => !str_contains(strtolower($row->jenisKayu->nama_kayu), 'sengon'),
                                            ])>
                                            {{ $row->jenisKayu->nama_kayu }}
                                        </span>
                                    </div>
                                </td>
                                <td class="px-6 py-5 text-center">
                                    <div class="inline-block px-3 py-1 bg-gray-100 dark:bg-gray-800 rounded font-black text-gray-700 dark:text-gray-300 tabular-nums">
                                        {{ number_format($row->stok_batang) }}
                                    </div>
                                </td>
                                <td class="px-6 py-5 text-right font-mono font-black text-blue-600 dark:text-blue-400">
                                    {{ number_format($row->stok_kubikasi, 4) }}
                                </td>
                                <td class="px-6 py-5 text-right">
                                    <span class="font-black text-gray-800 dark:text-gray-200 text-sm tabular-nums">
                                        Rp {{ number_format($row->nilai_stok, 0, ',', '.') }}
                                    </span>
                                </td>
                                <td class="px-6 py-5 text-right">
                                    <span class="font-black text-gray-800 dark:text-gray-200 text-sm tabular-nums">
                                        Rp {{ number_format($row->hpp_average, 0, ',', '.') }}
                                    </span>
                                </td>

                            </tr>
                            @endforeach
                            @else
                            {{-- Jika Lahan Kosong --}}
                            <tr class="group hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors">
                                <td class="px-6 py-5 text-center border-b border-gray-50 dark:border-gray-800">
                                    {{ ($this->editStokAction)(['lahan_id' => $lahan->id]) }}
                                </td>
                                <td class="px-6 py-5 text-center text-gray-300 dark:text-gray-600 font-mono text-xs">{{ $globalIdx++ }}</td>
                                <td class="px-6 py-5">
                                    <div class="flex items-center gap-2.5">
                                        <span class="text-gray-800 dark:text-gray-200 font-black text-base">{{ $lahan->kode_lahan }}</span>
                                    </div>
                                </td>
                                <td class="px-6 py-5 font-bold text-gray-500 dark:text-gray-400 uppercase tracking-widest text-[10px]">
                                    Kosong — Tidak Ada Stok Kayu
                                </td>
                                <td class="px-6 py-5 text-center text-red-500 dark:text-red-400 font-black font-mono">0</td>
                                <td class="px-6 py-5 text-right text-red-500 dark:text-red-400 font-black font-mono">0.0000</td>
                                <td class="px-6 py-5 text-center text-red-500 dark:text-red-400 font-black font-mono">0</td>
                                <td class="px-6 py-5 text-right text-red-500 dark:text-red-400 font-black font-mono">0</td>
                            </tr>
                            @endif
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

</x-filament-panels::page>