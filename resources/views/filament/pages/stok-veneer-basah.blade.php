{{-- resources/views/filament/pages/stok-veneer-basah.blade.php --}}
<x-filament-panels::page>

    @php
        $summaries = $this->summaries;
        $grouped   = $this->groupedSummaries;
    @endphp

    {{-- Filter bar --}}
    <div class="bg-white dark:bg-gray-800 rounded-sm border border-gray-200 dark:border-gray-700 p-3 mb-5 flex items-center gap-3 flex-wrap">
        <span class="text-[10px] font-black uppercase tracking-wider text-gray-500 dark:text-gray-400">Filter:</span>

        <select wire:model.live="filterJenisKayu"
            class="text-xs bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-600 text-gray-700 dark:text-gray-300 rounded-sm px-3 py-1.5 outline-none focus:border-primary-500">
            <option value="">Semua Jenis Kayu</option>
            @foreach(\App\Models\JenisKayu::orderBy('nama_kayu')->pluck('nama_kayu', 'id') as $id => $nama)
                <option value="{{ $id }}">{{ $nama }}</option>
            @endforeach
        </select>

        <select wire:model.live="filterTebal"
            class="text-xs bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-600 text-gray-700 dark:text-gray-300 rounded-sm px-3 py-1.5 outline-none focus:border-primary-500">
            <option value="">Semua Tebal</option>
            @foreach($this->tebalList as $t)
                <option value="{{ $t }}">{{ $t }} mm</option>
            @endforeach
        </select>

        <span class="ml-auto text-[10px] font-black uppercase tracking-widest text-gray-400">
            {{ $summaries->count() }} kombinasi · {{ number_format($this->totalLembar) }} lbr · Rp {{ number_format($this->totalNilaiStok, 0, ',', '.') }}
        </span>
    </div>

    <div class="flex flex-col gap-8">

        {{-- SECTION 1: RINGKASAN PER TEBAL (implisite F/B vs Core) --}}
        <div class="space-y-6">
            @forelse($grouped as $tebal => $rows)
            <div class="space-y-3">
                <div class="flex items-center gap-3">
                    <span class="bg-gray-800 dark:bg-gray-100 text-white dark:text-gray-900 text-[10px] font-black px-4 py-1.5 rounded uppercase tracking-widest shadow-sm">
                        Tebal {{ $tebal }} mm
                    </span>
                    @php
                        $labelJenis = $tebal <= 1 ? 'F/B (Face/Back)' : 'Core';
                    @endphp
                    <span class="text-[10px] font-black uppercase tracking-widest text-gray-400">{{ $labelJenis }}</span>
                    <div class="h-px flex-1 bg-gray-100 dark:bg-gray-900"></div>
                    <span class="text-[10px] font-black text-gray-500 dark:text-gray-400 tabular-nums">
                        {{ number_format($rows->sum('stok_lembar')) }} lbr ·
                        {{ number_format($rows->sum('stok_kubikasi'), 4) }} m³
                    </span>
                </div>

                <div class="bg-white dark:bg-gray-900 rounded border border-gray-200 dark:border-gray-800 shadow-sm overflow-hidden">
                    <table class="w-full text-sm text-left border-separate border-spacing-0">
                        <thead>
                            <tr class="text-gray-400 dark:text-gray-400 uppercase text-[9px] tracking-widest font-black bg-gray-50/50 dark:bg-gray-800/50">
                                <th class="px-6 py-3 text-center border-b border-gray-100 dark:border-gray-800 w-12">No</th>
                                <th class="px-6 py-3 border-b border-gray-100 dark:border-gray-800">Jenis Kayu</th>
                                <th class="px-6 py-3 border-b border-gray-100 dark:border-gray-800">Ukuran (p×l×t)</th>
                                <th class="px-6 py-3 text-center border-b border-gray-100 dark:border-gray-800">Stok Lembar</th>
                                <th class="px-6 py-3 text-right border-b border-gray-100 dark:border-gray-800">Kubikasi (m³)</th>
                                <th class="px-6 py-3 text-right border-b border-gray-100 dark:border-gray-800 bg-blue-50/30 dark:bg-blue-900/5">
                                    Komponen HPP/m³
                                    <div class="text-[9px] font-medium normal-case tracking-normal text-gray-500">kayu · pekerja · mesin · bahan</div>
                                </th>
                                <th class="px-6 py-3 text-right border-b border-gray-100 dark:border-gray-800 bg-amber-50/50 dark:bg-amber-900/10">
                                    HPP Average
                                    <div class="text-[9px] font-medium normal-case tracking-normal text-amber-500">per m³</div>
                                </th>
                                <th class="px-6 py-3 text-right border-b border-gray-100 dark:border-gray-800">Nilai Stok</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50 dark:divide-gray-800">
                            @foreach($rows as $row)
                            <tr class="group hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors">
                                <td class="px-6 py-4 text-center text-gray-300 dark:text-gray-600 font-mono text-xs">{{ $loop->iteration }}</td>

                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-2">
                                        <div @class(['w-2 h-2 rounded-sm',
                                            'bg-emerald-500' => str_contains(strtolower($row->jenisKayu?->nama_kayu ?? ''), 'sengon'),
                                            'bg-amber-500'   => !str_contains(strtolower($row->jenisKayu?->nama_kayu ?? ''), 'sengon'),
                                        ])></div>
                                        <span class="font-bold text-gray-700 dark:text-gray-300 uppercase tracking-tight">
                                            {{ $row->jenisKayu?->nama_kayu ?? '-' }}
                                        </span>
                                    </div>
                                </td>

                                <td class="px-6 py-4 font-mono text-xs text-gray-500 dark:text-gray-400 tabular-nums">
                                    {{ $row->panjang }}×{{ $row->lebar }}×{{ $row->tebal }}
                                </td>

                                <td class="px-6 py-4 text-center">
                                    <span class="font-black text-gray-700 dark:text-gray-300 tabular-nums text-lg">
                                        {{ number_format($row->stok_lembar) }}
                                    </span>
                                    <div class="text-[9px] text-gray-400 uppercase tracking-tight">lembar</div>
                                </td>

                                <td class="px-6 py-4 text-right font-mono font-black text-blue-600 dark:text-blue-400 tabular-nums">
                                    {{ number_format($row->stok_kubikasi, 4) }}
                                    <span class="text-xs text-gray-400 font-normal">m³</span>
                                </td>

                                {{-- Komponen HPP --}}
                                <td class="px-6 py-4 text-right bg-blue-50/10 dark:bg-blue-900/5">
                                    <div class="flex flex-col items-end gap-0.5 text-[10px] tabular-nums">
                                        <span class="text-emerald-600 dark:text-emerald-400 font-semibold">
                                            K: Rp {{ number_format($row->hpp_kayu_last ?? 0, 0, ',', '.') }}
                                        </span>
                                        <span class="text-blue-600 dark:text-blue-400 font-semibold">
                                            P: Rp {{ number_format($row->hpp_pekerja_last ?? 0, 0, ',', '.') }}
                                        </span>
                                        <span class="text-purple-600 dark:text-purple-400 font-semibold">
                                            M: Rp {{ number_format($row->hpp_mesin_last ?? 0, 0, ',', '.') }}
                                        </span>
                                        <span class="text-orange-600 dark:text-orange-400 font-semibold">
                                            B: Rp {{ number_format($row->hpp_bahan_penolong_last ?? 0, 0, ',', '.') }}
                                        </span>
                                    </div>
                                </td>

                                {{-- HPP Average --}}
                                <td class="px-6 py-4 text-right bg-amber-50/20 dark:bg-amber-900/5">
                                    <span class="font-black text-amber-700 dark:text-amber-400 tabular-nums text-base">
                                        Rp {{ number_format($row->hpp_average ?? 0, 0, ',', '.') }}
                                    </span>
                                    <div class="text-[9px] text-gray-400 uppercase tracking-tight">/m³</div>
                                </td>

                                <td class="px-6 py-4 text-right">
                                    <span class="font-black text-gray-800 dark:text-gray-200 tabular-nums">
                                        Rp {{ number_format($row->nilai_stok ?? 0, 0, ',', '.') }}
                                    </span>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="text-[10px] font-black border-t bg-gray-50 dark:bg-gray-900/60 border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-300 uppercase tracking-widest">
                                <td colspan="3" class="px-6 py-3 text-gray-500">Subtotal tebal {{ $tebal }} mm</td>
                                <td class="px-6 py-3 text-center tabular-nums text-gray-700 dark:text-gray-300">
                                    {{ number_format($rows->sum('stok_lembar')) }} lbr
                                </td>
                                <td class="px-6 py-3 text-right tabular-nums text-blue-600 dark:text-blue-400">
                                    {{ number_format($rows->sum('stok_kubikasi'), 4) }} m³
                                </td>
                                <td class="px-6 py-3 bg-blue-50/10 dark:bg-blue-900/5"></td>
                                <td class="px-6 py-3 bg-amber-50/20 dark:bg-amber-900/5"></td>
                                <td class="px-6 py-3 text-right tabular-nums text-gray-700 dark:text-gray-300">
                                    Rp {{ number_format($rows->sum('nilai_stok'), 0, ',', '.') }}
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
            @empty
            <div class="py-12 text-center text-gray-400 dark:text-gray-600 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded">
                Tidak ada stok veneer basah tersedia
            </div>
            @endforelse
        </div>

        {{-- SECTION 2: TOTAL KESELURUHAN --}}
        @if($summaries->count())
        <div class="bg-white dark:bg-gray-900 rounded border border-gray-200 dark:border-gray-800 shadow-sm overflow-hidden">
            <div class="px-6 py-4 bg-gray-50 dark:bg-gray-800 border-b border-gray-100 dark:border-gray-700">
                <h3 class="text-[10px] font-black uppercase tracking-widest text-gray-500">Total Keseluruhan</h3>
            </div>
            <div class="grid grid-cols-3 divide-x divide-gray-100 dark:divide-gray-800">
                <div class="px-6 py-5">
                    <div class="text-[10px] font-black uppercase tracking-widest text-gray-400 mb-1">Total Lembar</div>
                    <div class="text-2xl font-black text-gray-800 dark:text-gray-200 tabular-nums">
                        {{ number_format($this->totalLembar) }}
                    </div>
                    <div class="text-xs text-gray-400 mt-0.5">lembar veneer basah</div>
                </div>
                <div class="px-6 py-5">
                    <div class="text-[10px] font-black uppercase tracking-widest text-gray-400 mb-1">Total Kubikasi</div>
                    <div class="text-2xl font-black text-blue-600 dark:text-blue-400 tabular-nums">
                        {{ number_format($summaries->sum('stok_kubikasi'), 4) }}
                    </div>
                    <div class="text-xs text-gray-400 mt-0.5">m³</div>
                </div>
                <div class="px-6 py-5">
                    <div class="text-[10px] font-black uppercase tracking-widest text-gray-400 mb-1">Total Nilai Stok</div>
                    <div class="text-2xl font-black text-emerald-600 dark:text-emerald-400 tabular-nums">
                        Rp {{ number_format($this->totalNilaiStok, 0, ',', '.') }}
                    </div>
                    <div class="text-xs text-gray-400 mt-0.5">nilai persediaan</div>
                </div>
            </div>
        </div>
        @endif

    </div>

</x-filament-panels::page>