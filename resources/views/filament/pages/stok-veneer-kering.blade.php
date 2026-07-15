{{-- resources/views/filament/pages/stok-veneer-kering.blade.php --}}
<x-filament-panels::page>
    @php
    $latestStok = $this->latestStok;
    $grouped = $this->groupedStok;
    @endphp

    {{-- Filter bar + toggle kolom --}}
    <div class="bg-white dark:bg-gray-800 rounded-sm border border-gray-200 dark:border-gray-700 p-3 mb-5 flex items-center gap-3 flex-wrap shadow-sm">
        <span class="text-[10px] font-black uppercase tracking-wider text-gray-500 dark:text-gray-400">Filter:</span>

        <select wire:model.live="filterJenisKayu"
            class="text-xs bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-600 text-gray-700 dark:text-gray-300 rounded-sm px-3 py-1.5 outline-none focus:ring-1 focus:ring-primary-500">
            <option value="">Semua Jenis Kayu</option>
            @foreach(\App\Models\JenisKayu::orderBy('nama_kayu')->pluck('nama_kayu', 'id') as $id => $nama)
            <option value="{{ $id }}">{{ $nama }}</option>
            @endforeach
        </select>

        <select wire:model.live="filterCoreType"
            class="text-xs bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-600 text-gray-700 dark:text-gray-300 rounded-sm px-3 py-1.5 outline-none focus:ring-1 focus:ring-primary-500">
            <option value="">Semua Tipe Core</option>
            <option value="long">Long Core</option>
            <option value="short">Short Core</option>
        </select>

        <div class="h-4 w-px bg-gray-200 dark:bg-gray-700"></div>

        <button type="button" wire:click="$toggle('showM3')"
            @class([ 'text-[9px] font-bold uppercase tracking-tight px-2 py-1 rounded-sm border transition' , 'bg-blue-50 border-blue-300 text-blue-700 dark:bg-blue-900/30 dark:border-blue-700 dark:text-blue-400'=> $showM3,
            'bg-transparent border-gray-200 dark:border-gray-700 text-gray-400 dark:text-gray-500' => !$showM3,
            ])>
            m³
        </button>

        <button type="button" wire:click="$toggle('showHppAverage')"
            @class([ 'text-[9px] font-bold uppercase tracking-tight px-2 py-1 rounded-sm border transition' , 'bg-amber-50 border-amber-300 text-amber-700 dark:bg-amber-900/30 dark:border-amber-700 dark:text-amber-400'=> $showHppAverage,
            'bg-transparent border-gray-200 dark:border-gray-700 text-gray-400 dark:text-gray-500' => !$showHppAverage,
            ])>
            HPP
        </button>

        <button type="button" wire:click="$toggle('showNilaiStok')"
            @class([ 'text-[9px] font-bold uppercase tracking-tight px-2 py-1 rounded-sm border transition' , 'bg-emerald-50 border-emerald-300 text-emerald-700 dark:bg-emerald-900/30 dark:border-emerald-700 dark:text-emerald-400'=> $showNilaiStok,
            'bg-transparent border-gray-200 dark:border-gray-700 text-gray-400 dark:text-gray-500' => !$showNilaiStok,
            ])>
            Rp
        </button>

        <div class="ml-auto flex items-center gap-4">
            <span class="text-[10px] font-black uppercase tracking-widest text-gray-400">
                {{ $latestStok->count() }} Kombinasi
                @if($showM3)
                · {{ number_format($this->totalM3, 4) }} m³
                @endif
            </span>
            @if($showNilaiStok)
            <span class="bg-primary-50 text-primary-700 dark:bg-primary-950 dark:text-primary-400 px-3 py-1 rounded font-black text-xs border border-primary-100">
                TOTAL: RP {{ number_format($this->totalNilaiStok, 0, ',', '.') }}
            </span>
            @endif
        </div>
    </div>

    <div class="flex flex-col gap-8">
        @forelse($grouped as $tebal => $rows)
        <div class="space-y-3">
            <div class="flex items-center gap-3">
                <span class="bg-gray-800 dark:bg-gray-100 text-white dark:text-gray-900 text-[10px] font-black px-4 py-1.5 rounded uppercase tracking-widest shadow-sm">
                    Tebal {{ (float)$tebal }} mm
                </span>
                @php $labelJenis = $tebal <= 1 ? 'F/B (Face/Back)' : 'Core' ; @endphp
                    <span class="text-[10px] font-black uppercase tracking-widest text-gray-400">{{ $labelJenis }}</span>
                    <div class="h-px flex-1 bg-gray-100 dark:bg-gray-800"></div>
                    @if($showM3)
                    <div class="flex gap-4">
                        <span class="text-[10px] font-black text-blue-600 tabular-nums uppercase tracking-tighter">
                            Vol: {{ number_format($rows->sum('stok_m3_sesudah'), 4) }} m³
                        </span>
                    </div>
                    @endif
            </div>

            <div class="bg-white dark:bg-gray-900 rounded border border-gray-200 dark:border-gray-800 shadow-sm overflow-hidden">
                <table class="w-full text-sm text-left border-separate border-spacing-0">
                    <thead>
                        <tr class="text-gray-400 uppercase text-[9px] tracking-widest font-black bg-gray-50/50 dark:bg-gray-800/50">
                            <th class="px-6 py-3 border-b border-gray-100 dark:border-gray-800 w-12 text-center">No</th>
                            <th class="px-6 py-3 border-b border-gray-100 dark:border-gray-800">Jenis Kayu</th>
                            <th class="px-6 py-3 text-center border-b border-gray-100 dark:border-gray-800">Tipe Core</th>
                            <th class="px-6 py-3 border-b border-gray-100 dark:border-gray-800">Ukuran (p×l×t)</th>
                            <th class="px-6 py-3 text-center border-b border-gray-100 dark:border-gray-800">KW</th>
                            <th class="px-6 py-3 text-center border-b border-gray-100 dark:border-gray-800">Jumlah Lembar</th>
                            @if($showM3)
                            <th class="px-6 py-3 text-right border-b border-gray-100 dark:border-gray-800">M3 Stok</th>
                            @endif
                            @if($showHppAverage)
                            <th class="px-6 py-3 text-right border-b border-gray-100 dark:border-gray-800 bg-amber-50/30 dark:bg-amber-900/10">HPP Average</th>
                            @endif
                            @if($showNilaiStok)
                            <th class="px-6 py-3 text-right border-b border-gray-100 dark:border-gray-800">Nilai Stok</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50 dark:divide-gray-800">
                        @foreach($rows as $row)
                        <tr class="group hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors">
                            <td class="px-6 py-4 text-center text-gray-300 dark:text-gray-600 font-mono text-xs">{{ $loop->iteration }}</td>
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-2">
                                    <div @class(['w-2 h-2 rounded-sm', 'bg-emerald-500'=> str_contains(strtolower($row->jenisKayu?->nama_kayu ?? ''), 'sengon'), 'bg-amber-500' => !str_contains(strtolower($row->jenisKayu?->nama_kayu ?? ''), 'sengon')])></div>
                                    <span class="font-bold text-gray-700 dark:text-gray-300 uppercase tracking-tight">{{ $row->jenisKayu?->nama_kayu ?? '-' }}</span>
                                </div>
                            </td>
                            @php
                            $coreType = $this->getCoreTypeLabel($row);
                            @endphp
                            <td class="px-6 py-4 text-center">
                                @if($coreType)
                                <span @class([ 'inline-flex items-center px-2 py-0.5 rounded-sm text-[9px] font-black uppercase tracking-tight' , 'bg-teal-50 text-teal-700 dark:bg-teal-900/30 dark:text-teal-400'=> $coreType === 'Long Core',
                                    'bg-amber-50 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400' => $coreType === 'Short Core',
                                    ])>
                                    {{ $coreType }}
                                </span>
                                @else
                                <span class="text-gray-300 dark:text-gray-600 text-xs">-</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 font-mono text-xs text-gray-500 dark:text-gray-400 tabular-nums">
                                {{ (float)($row->ukuran->panjang ?? 0) }}×{{ (float)($row->ukuran->lebar ?? 0) }}×{{ (float)($row->ukuran->tebal ?? 0) }}
                            </td>
                            <td class="px-6 py-4 text-center">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-sm text-[9px] font-black uppercase tracking-tight bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300">{{ $row->kw ?? '-' }}</span>
                            </td>
                            <td class="px-6 py-4 text-center font-black text-gray-700 dark:text-gray-300 tabular-nums">
                                {{ number_format($row->total_lembar, 0) }}
                            </td>
                            @if($showM3)
                            <td class="px-6 py-4 text-right font-mono font-black text-blue-600 dark:text-blue-400 tabular-nums">
                                {{ number_format($row->stok_m3_sesudah, 4) }} <span class="text-[9px] font-normal uppercase">m³</span>
                            </td>
                            @endif
                            @if($showHppAverage)
                            <td class="px-6 py-4 text-right bg-amber-50/20 dark:bg-amber-900/5">
                                <span class="font-black text-amber-700 dark:text-amber-400 tabular-nums text-base">Rp {{ number_format($row->hpp_average ?? 0, 0, ',', '.') }}</span>
                            </td>
                            @endif
                            @if($showNilaiStok)
                            <td class="px-6 py-4 text-right font-black text-gray-800 dark:text-gray-200 tabular-nums">
                                Rp {{ number_format($row->nilai_stok_sesudah ?? 0, 0, ',', '.') }}
                            </td>
                            @endif
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @empty
        <div class="py-20 text-center text-gray-400 bg-white dark:bg-gray-900 border border-dashed border-gray-200 dark:border-gray-800 rounded-lg">
            <span class="font-black uppercase tracking-widest">Stok Tidak Tersedia</span>
        </div>
        @endforelse
    </div>
</x-filament-panels::page>