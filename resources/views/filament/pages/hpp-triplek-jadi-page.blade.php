{{-- resources/views/filament/pages/hpp-triplek-jadi-page.blade.php --}}
<x-filament-panels::page wire:init="loadSummary">
    @php
    $logs = $this->logs;
    $summary = $this->summary; // null selama footer belum dimuat
    $ready = $summary !== null;
    $fmt = fn (int|float|null $value, int $decimals = 0): string => $ready
    ? number_format($value ?? 0, $decimals)
    : '…';
    $balance = $summary['sheets_balance'] ?? 0;
    @endphp

    {{-- Filter bar --}}
    <div class="bg-white dark:bg-gray-800 rounded-sm border border-gray-200 dark:border-gray-700 p-3 mb-5 flex items-center gap-3 flex-wrap">
        <span class="text-[10px] font-black uppercase tracking-wider text-gray-500 dark:text-gray-400">Filter:</span>

        <x-filament::input.wrapper>
            <x-filament::input.select wire:model.live="filterJenisKayu">
                <option value="">Semua Jenis Kayu</option>
                @foreach($this->jenisKayuOptions as $id => $nama)
                <option value="{{ $id }}">{{ $nama }}</option>
                @endforeach
            </x-filament::input.select>
        </x-filament::input.wrapper>

        <x-filament::input.wrapper>
            <x-filament::input.select wire:model.live="filterPanjang">
                <option value="">Semua Panjang</option>
                @foreach($this->lengthOptions as $length)
                <option value="{{ $length }}">{{ (float) $length }} cm</option>
                @endforeach
            </x-filament::input.select>
        </x-filament::input.wrapper>

        <x-filament::input.wrapper>
            <x-filament::input.select wire:model.live="filterTebal">
                <option value="">Semua Tebal</option>
                @foreach($this->thicknessOptions as $thickness)
                <option value="{{ $thickness }}">{{ (float) $thickness }} mm</option>
                @endforeach
            </x-filament::input.select>
        </x-filament::input.wrapper>

        <x-filament::input.wrapper class="w-44">
            <x-filament::input
                type="text"
                wire:model.live.debounce.400ms="filterKw"
                placeholder="Cari kayu/ukuran/KW" />
        </x-filament::input.wrapper>

        {{-- Filter Rentang Tanggal --}}
        <div class="flex items-center gap-1">
            <x-filament::input.wrapper>
                <x-filament::input type="date" wire:model.live="filterTanggalMulai" title="Tanggal Mulai" />
            </x-filament::input.wrapper>
            <span class="text-xs text-gray-400 font-bold">-</span>
            <x-filament::input.wrapper>
                <x-filament::input type="date" wire:model.live="filterTanggalSelesai" title="Tanggal Selesai" />
            </x-filament::input.wrapper>
        </div>

        @if($this->hasActiveFilters)
        <x-filament::button
            color="gray"
            size="sm"
            icon="heroicon-m-x-mark"
            wire:click="resetFilters">
            Reset Filter
        </x-filament::button>
        @endif

        <span class="ml-auto text-[10px] font-black uppercase tracking-widest text-gray-400">
            {{ $fmt($summary['entry_count'] ?? null) }} entri log
        </span>
    </div>

    {{-- Wrapper tabel + indikator loading --}}
    <div
        wire:loading.class="opacity-60 pointer-events-none"
        wire:target="filterJenisKayu,filterPanjang,filterTebal,filterKw,filterTanggalMulai,filterTanggalSelesai,resetFilters,gotoPage,nextPage,previousPage,setPage"
        class="bg-white dark:bg-gray-800 rounded-sm border border-gray-200 dark:border-gray-700 overflow-hidden shadow-sm transition-opacity">

        {{-- Summary bar --}}
        <div class="px-4 py-2.5 border-b border-gray-100 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 flex items-center gap-3 flex-wrap">
            <span class="inline-flex items-center gap-1 bg-green-100 dark:bg-green-900/40 text-green-700 dark:text-green-400 text-[10px] font-black px-2.5 py-1 rounded-sm uppercase tracking-tighter">
                ↑ {{ $fmt($summary['sheets_in'] ?? null) }} lbr masuk
            </span>
            <span class="inline-flex items-center gap-1 bg-red-100 dark:bg-red-900/40 text-red-700 dark:text-red-400 text-[10px] font-black px-2.5 py-1 rounded-sm uppercase tracking-tighter">
                ↓ {{ $fmt($summary['sheets_out'] ?? null) }} lbr keluar
            </span>
            <span class="inline-flex items-center gap-1 bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 text-[10px] font-black px-2.5 py-1 rounded-sm uppercase tracking-tighter">
                = {{ $fmt($summary['sheets_balance'] ?? null) }} saldo
            </span>
            @if($ready && $summary['has_logs'])
            <span class="ml-auto inline-flex items-center gap-1 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-700 text-amber-700 dark:text-amber-300 text-[10px] font-black px-3 py-1 rounded-sm uppercase tracking-tight">
                HPP terakhir: Rp {{ number_format($summary['latest_hpp_average'] ?? 0, 0, ',', '.') }}/m³
            </span>
            @endif
        </div>

        {{-- Tabel log (10 kolom) --}}
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-gray-50 dark:bg-gray-900 text-[10px] font-black uppercase tracking-widest text-gray-500 dark:text-gray-400 border-b border-gray-100 dark:border-gray-700">
                        <th class="px-4 py-3 text-left whitespace-nowrap">Tanggal</th>
                        <th class="px-4 py-3 text-left whitespace-nowrap">Jenis Kayu</th>
                        <th class="px-4 py-3 text-right whitespace-nowrap">Ukuran</th>
                        <th class="px-4 py-3 text-center whitespace-nowrap">Kw/Grade</th>
                        <th class="px-4 py-3 text-left whitespace-nowrap">Tipe</th>
                        <th class="px-4 py-3 text-right border-l border-gray-100 dark:border-gray-700 whitespace-nowrap">
                            Qty<div class="text-[10px] font-medium normal-case text-gray-400 tracking-normal">lembar</div>
                        </th>
                        <th class="px-4 py-3 text-right border-l border-gray-100 dark:border-gray-700 whitespace-nowrap">
                            Stok Lembar<div class="text-[10px] font-medium normal-case text-gray-500 tracking-normal">Sebelum → Sesudah</div>
                        </th>
                        <th class="px-4 py-3 text-right border-l border-gray-100 dark:border-gray-700 whitespace-nowrap">
                            Kubikasi<div class="text-[10px] font-medium normal-case text-gray-500 tracking-normal">masuk/keluar</div>
                        </th>
                        <th class="px-4 py-3 text-right border-l border-gray-100 dark:border-gray-700 whitespace-nowrap">
                            Stok Kubikasi<div class="text-[10px] font-medium normal-case text-gray-500 tracking-normal">Sebelum → Sesudah</div>
                        </th>
                        <th class="px-4 py-3 text-left">Keterangan</th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                    @forelse($logs as $log)
                    @php $isM = $log->tipe_transaksi === 'masuk'; @endphp
                    <tr wire:key="log-{{ $log->id }}" @class([ 'transition' , 'hover:bg-green-50/30 dark:hover:bg-green-900/10'=> $isM,
                        'hover:bg-red-50/30 dark:hover:bg-red-900/10' => ! $isM,
                        ])>
                        <td class="px-4 py-3 font-mono text-xs text-gray-500 dark:text-gray-400 whitespace-nowrap">
                            {{ $log->tanggal->format('d/m/Y') }}
                        </td>

                        <td class="px-4 py-3 font-black text-gray-900 dark:text-white whitespace-nowrap">
                            {{ $log->jenisKayu?->nama_kayu ?? '-' }}
                        </td>

                        <td class="px-4 py-3 text-right font-mono text-xs text-gray-500 dark:text-gray-400 whitespace-nowrap tabular-nums">
                            {{ (float) $log->panjang }}×{{ (float) $log->lebar }}×{{ (float) $log->tebal }}
                        </td>

                        <td class="px-4 py-3 text-center whitespace-nowrap">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-sm text-[9px] font-black uppercase tracking-tight bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300">
                                {{ $log->kw_grade ?? '-' }}
                            </span>
                        </td>

                        <td class="px-4 py-3 whitespace-nowrap">
                            <span @class([ 'inline-flex items-center px-2 py-0.5 rounded-sm text-[9px] font-black uppercase tracking-tight' , 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400'=> $isM,
                                'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400' => ! $isM,
                                ])>
                                {{ $isM ? '↑ Masuk' : '↓ Keluar' }}
                            </span>
                        </td>

                        <td @class([ 'px-4 py-3 text-right font-black text-sm border-l border-gray-50 dark:border-gray-800 whitespace-nowrap tabular-nums' , 'text-green-600 dark:text-green-400'=> $isM,
                            'text-red-600 dark:text-red-400' => ! $isM,
                            ])>
                            {{ $isM ? '+' : '-' }}{{ number_format($log->total_lembar ?? 0) }}
                        </td>

                        <td class="px-4 py-3 border-l border-gray-50 dark:border-gray-800 whitespace-nowrap">
                            <div class="flex items-center justify-end gap-1.5 font-mono text-xs tabular-nums">
                                <span class="text-gray-400 dark:text-gray-500">{{ number_format($log->stok_lembar_before ?? 0) }}</span>
                                <span class="text-gray-300 dark:text-gray-700 text-[10px]">→</span>
                                <span @class([ 'font-black' , 'text-green-600 dark:text-green-400'=> $isM,
                                    'text-red-600 dark:text-red-400' => ! $isM,
                                    ])>{{ number_format($log->stok_lembar_after ?? 0) }}</span>
                            </div>
                        </td>

                        <td class="px-4 py-3 border-l border-gray-50 dark:border-gray-800 whitespace-nowrap tabular-nums">
                            <div class="flex flex-col items-end">
                                <span @class([ 'font-semibold text-xs' , 'text-blue-600 dark:text-blue-400'=> $isM,
                                    'text-orange-600 dark:text-orange-400' => ! $isM,
                                    ])>{{ number_format($log->total_kubikasi ?? 0, 4) }}</span>
                                <div class="text-[9px] text-gray-400 uppercase tracking-tighter">m³</div>
                            </div>
                        </td>

                        <td class="px-4 py-3 border-l border-gray-50 dark:border-gray-800 whitespace-nowrap tabular-nums">
                            <div class="flex items-center justify-end gap-1.5 font-mono text-xs">
                                <span class="text-gray-400 dark:text-gray-500">{{ number_format($log->stok_kubikasi_before ?? 0, 4) }}</span>
                                <span class="text-gray-300 dark:text-gray-700 text-[10px]">→</span>
                                <span @class([ 'font-black' , 'text-blue-600 dark:text-blue-400'=> $isM,
                                    'text-orange-600 dark:text-orange-400' => ! $isM,
                                    ])>{{ number_format($log->stok_kubikasi_after ?? 0, 4) }}</span>
                            </div>
                        </td>

                        <td class="px-4 py-3 text-[11px] font-black uppercase text-gray-700 dark:text-gray-300 whitespace-nowrap">
                            {{ $log->keterangan ?? '—' }}
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="10" class="px-4 py-12 text-center text-sm text-gray-400 dark:text-gray-500">
                            Belum ada log transaksi triplek jadi
                        </td>
                    </tr>
                    @endforelse
                </tbody>

                @if(! $ready || $summary['has_logs'])
                <tfoot>
                    <tr class="text-[10px] font-black border-t-2 bg-gray-50 dark:bg-gray-900/60 border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-300 uppercase tracking-widest">
                        <td colspan="5" class="px-4 py-3 text-gray-500">Saldo Akhir</td>
                        <td @class([ 'px-4 py-3 text-right tabular-nums' , 'text-green-700 dark:text-green-400'=> $balance >= 0,
                            'text-red-600 dark:text-red-400' => $balance < 0,
                                ])>{{ $fmt($summary['sheets_balance'] ?? null) }} lbr</td>
                        <td class="px-4 py-3 text-right tabular-nums">{{ $fmt($summary['latest_stock_sheets'] ?? null) }} lbr</td>
                        <td class="px-4 py-3 text-right tabular-nums text-blue-600 dark:text-blue-400">
                            {{ $fmt($ready ? max(0, $summary['volume_balance']) : null, 4) }} m³
                        </td>
                        <td class="px-4 py-3 text-right tabular-nums text-blue-600 dark:text-blue-400">
                            {{ $fmt($summary['latest_stock_volume'] ?? null, 4) }} m³
                        </td>
                        <td class="px-4 py-3"></td>
                    </tr>
                </tfoot>
                @endif
            </table>
        </div>

        @if($logs->hasPages())
        <div class="px-4 py-3 border-t border-gray-100 dark:border-gray-700">
            <x-filament::pagination :paginator="$logs" />
        </div>
        @endif
    </div>
</x-filament-panels::page>