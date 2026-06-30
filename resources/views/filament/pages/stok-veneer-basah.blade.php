{{-- resources/views/filament/pages/stok-veneer-basah.blade.php --}}
<x-filament-panels::page>

    @php
        $summaries = $this->summaries;
        $grouped   = $this->groupedSummaries; // grouped per tebal
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

        <select wire:model.live="filterKw"
            class="text-xs bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-600 text-gray-700 dark:text-gray-300 rounded-sm px-3 py-1.5 outline-none focus:border-primary-500">
            <option value="">Semua KW</option>
            @foreach($this->kwList as $kw)
                <option value="{{ $kw }}">KW {{ $kw }}</option>
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
                        Tebal {{ (float)$tebal }} mm
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
                                <th class="px-6 py-3 text-center border-b border-gray-100 dark:border-gray-800">KW</th>
                                <th class="px-6 py-3 text-center border-b border-gray-100 dark:border-gray-800">Stok Lembar</th>
                                <th class="px-6 py-3 text-right border-b border-gray-100 dark:border-gray-800">Kubikasi (m³)</th>
                                <th class="px-6 py-3 text-right border-b border-gray-100 dark:border-gray-800 bg-amber-50/50 dark:bg-amber-900/10">
                                    HPP Average
                                    <div class="text-[9px] font-medium normal-case tracking-normal text-amber-500">Sebelum → Sekarang</div>
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
                                    {{ (float)$row->panjang }}×{{ (float)$row->lebar }}×{{ (float)$row->tebal }}
                                </td>

                                <td class="px-6 py-4 text-center">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-sm text-[9px] font-black uppercase tracking-tight bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300">
                                        {{ $row->kw ?? '-' }}
                                    </span>
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

                                {{-- HPP Average Sebelum → Sekarang --}}
                                @php
                                    $hppSekarang  = (float) ($row->hpp_average ?? 0);
                                    $lastLog      = $row->lastLog;
                                    $hppSebelum   = $lastLog ? (float) ($lastLog->stok_kubikasi_before > 0
                                        ? ($lastLog->nilai_stok_before / $lastLog->stok_kubikasi_before)
                                        : 0)
                                        : 0;
                                @endphp
                                <td class="px-6 py-4 text-right bg-amber-50/20 dark:bg-amber-900/5">
                                    @if($hppSebelum > 0)
                                        <div class="flex items-center justify-end gap-1.5 font-mono text-xs tabular-nums mb-0.5">
                                            <span class="text-gray-400 dark:text-gray-500">Rp {{ number_format($hppSebelum, 0, ',', '.') }}</span>
                                            <span class="text-gray-300 dark:text-gray-700 text-[10px]">→</span>
                                        </div>
                                    @endif
                                    <span class="font-black text-amber-700 dark:text-amber-400 tabular-nums text-base">
                                        Rp {{ number_format($hppSekarang, 0, ',', '.') }}
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