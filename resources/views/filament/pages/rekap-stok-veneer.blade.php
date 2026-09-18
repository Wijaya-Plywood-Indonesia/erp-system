<x-filament-panels::page>
<div class="space-y-6">

    {{-- Header info tanggal --}}
    <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 px-6 py-4">
        <div class="flex items-center justify-between flex-wrap gap-2">
            <div>
                <h2 class="text-base font-semibold text-gray-950 dark:text-white">Rekap Stok Veneer</h2>
                <p class="text-sm text-gray-500 dark:text-gray-400">Per tanggal {{ \Carbon\Carbon::now()->translatedFormat('d F Y') }}</p>
            </div>
            <span class="inline-flex items-center gap-1.5 text-sm font-medium text-gray-600 dark:text-gray-400">
                <x-heroicon-o-calendar-days class="h-4 w-4"/>
                {{ count($allStocks) }} ukuran ditampilkan
            </span>
        </div>
    </div>

    {{-- Filter bar --}}
    <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 px-6 py-4">
        <div class="flex flex-wrap items-end gap-4">

            {{-- Search --}}
            <div class="flex-1 min-w-[180px]">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Cari Ukuran</label>
                <div class="relative">
                    <x-heroicon-o-magnifying-glass class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400 dark:text-gray-500 pointer-events-none"/>
                    <input
                        wire:model.live.debounce.300ms="search"
                        type="text"
                        placeholder="122, 3.7, Sengon…"
                        class="fi-input block w-full rounded-lg border-0 py-1.5 pl-9 pr-3 text-sm text-gray-950 dark:text-white shadow-sm ring-1 ring-inset ring-gray-300 dark:ring-gray-600 bg-white dark:bg-white/5 placeholder:text-gray-400 dark:placeholder:text-gray-500 focus:ring-2 focus:ring-inset focus:ring-primary-600 dark:focus:ring-primary-500"
                    />
                </div>
            </div>

            {{-- Jenis Kayu --}}
            <div class="min-w-[150px]">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Jenis Kayu</label>
                <select
                    wire:model.live="filterKayu"
                    class="fi-select block w-full rounded-lg border-0 py-1.5 px-3 text-sm text-gray-950 dark:text-white shadow-sm ring-1 ring-inset ring-gray-300 dark:ring-gray-600 bg-white dark:bg-white/5 focus:ring-2 focus:ring-inset focus:ring-primary-600 dark:focus:ring-primary-500"
                >
                    <option value="">Semua Kayu</option>
                    @foreach($kayuOptions as $opt)
                        <option value="{{ $opt }}">{{ $opt }}</option>
                    @endforeach
                </select>
            </div>

            {{-- KW --}}
            <div class="min-w-[120px]">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Grade / KW</label>
                <select
                    wire:model.live="filterKw"
                    class="fi-select block w-full rounded-lg border-0 py-1.5 px-3 text-sm text-gray-950 dark:text-white shadow-sm ring-1 ring-inset ring-gray-300 dark:ring-gray-600 bg-white dark:bg-white/5 focus:ring-2 focus:ring-inset focus:ring-primary-600 dark:focus:ring-primary-500"
                >
                    <option value="">Semua KW</option>
                    <option value="1">KW 1</option>
                    <option value="2">KW 2</option>
                    <option value="3">KW 3</option>
                    <option value="4">KW 4</option>
                    <option value="5">KW 5</option>
                    <option value="AF">AF</option>
                </select>
            </div>

            @if($search !== '' || $filterKayu !== '' || $filterKw !== '')
            <div class="flex items-end">
                <button
                    wire:click="$set('search', ''); $set('filterKayu', ''); $set('filterKw', '')"
                    class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-white/5 shadow-sm ring-1 ring-inset ring-gray-300 dark:ring-gray-600 hover:bg-gray-50 dark:hover:bg-white/10 transition"
                >
                    <x-heroicon-o-x-mark class="h-4 w-4"/>
                    Reset
                </button>
            </div>
            @endif
        </div>
    </div>

    {{-- Tables --}}
    @forelse($allStocks as $stockGroup)
    @php
        $kws = [1, 2, 3, 4, 5, 'AF'];
        if ($filterKw !== '') {
            $kws = [is_numeric($filterKw) ? (int)$filterKw : strtoupper($filterKw)];
        }
        $totWjBasah = 0; $totWjKering = 0; $totWjJadi = 0;
        $totAll = 0; $tot23 = 0;
    @endphp

    <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 overflow-hidden">

        {{-- Section header --}}
        <div class="fi-section-header flex items-center gap-3 px-6 py-4 border-b border-gray-100 dark:border-white/10">
            <x-heroicon-o-cube class="h-5 w-5 text-gray-400 dark:text-gray-500 shrink-0"/>
            <div>
                <span class="text-xs font-medium text-gray-400 dark:text-gray-500">{{ $stockGroup['jenis_kayu'] }}</span>
                <h3 class="text-sm font-semibold text-gray-950 dark:text-white leading-tight">
                    {{ $stockGroup['panjang'] }} &times; {{ $stockGroup['lebar'] }} &times; {{ $stockGroup['tebal'] }} mm
                </h3>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-200 dark:border-white/10">
                        <th rowspan="2"
                            class="w-14 px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 bg-gray-50 dark:bg-white/5 border-r border-gray-200 dark:border-white/10 align-middle">
                            KW
                        </th>
                        <th colspan="3"
                            class="px-4 py-2 text-center text-xs font-semibold text-gray-600 dark:text-gray-300 bg-gray-50 dark:bg-white/5 border-r border-gray-200 dark:border-white/10">
                            Wahana
                        </th>
                        <th colspan="3"
                            class="px-4 py-2 text-center text-xs font-semibold text-primary-600 dark:text-primary-400 bg-primary-50 dark:bg-primary-950/30 border-r border-gray-200 dark:border-white/10">
                            Wijaya
                        </th>
                        <th rowspan="2"
                            class="px-4 py-3 text-right text-xs font-semibold text-gray-500 dark:text-gray-400 bg-gray-50 dark:bg-white/5 border-r border-gray-200 dark:border-white/10 align-middle">
                            Total
                        </th>
                        <th rowspan="2"
                            class="px-4 py-3 text-right text-xs font-semibold text-gray-500 dark:text-gray-400 bg-gray-50 dark:bg-white/5 align-middle">
                            Krat
                        </th>
                    </tr>
                    <tr class="border-b border-gray-200 dark:border-white/10 text-xs text-gray-500 dark:text-gray-400">
                        <th class="px-4 py-2 text-center font-medium bg-gray-50 dark:bg-white/5 border-r border-gray-200 dark:border-white/10">Basah</th>
                        <th class="px-4 py-2 text-center font-medium bg-gray-50 dark:bg-white/5 border-r border-gray-200 dark:border-white/10">Kering</th>
                        <th class="px-4 py-2 text-center font-medium bg-gray-50 dark:bg-white/5 border-r border-gray-200 dark:border-white/10">Jadi</th>
                        <th class="px-4 py-2 text-center font-medium bg-primary-50 dark:bg-primary-950/30 border-r border-gray-200 dark:border-white/10">Basah</th>
                        <th class="px-4 py-2 text-center font-medium bg-primary-50 dark:bg-primary-950/30 border-r border-gray-200 dark:border-white/10">Kering</th>
                        <th class="px-4 py-2 text-center font-medium bg-primary-50 dark:bg-primary-950/30 border-r border-gray-200 dark:border-white/10">Jadi</th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                    @foreach($kws as $kw)
                    @php
                        $wjBasah  = $stockGroup['wijayaBasah'][$kw]  ?? 0;
                        $wjKering = $stockGroup['wijayaKering'][$kw] ?? 0;
                        $wjJadi   = $stockGroup['wijayaJadi'][$kw]   ?? 0;
                        $rowTotal = $wjBasah + $wjKering + $wjJadi;

                        $totWjBasah  += $wjBasah;
                        $totWjKering += $wjKering;
                        $totWjJadi   += $wjJadi;
                        $totAll      += $rowTotal;
                        if (in_array($kw, [2, 3])) $tot23 += $rowTotal;
                    @endphp
                    <tr class="{{ $rowTotal == 0 ? 'opacity-40' : '' }} hover:bg-gray-50 dark:hover:bg-white/5 transition-colors">
                        <td class="px-4 py-3 text-center text-sm font-semibold text-gray-700 dark:text-gray-300 border-r border-gray-100 dark:border-white/10">
                            {{ $kw }}
                        </td>
                        <td class="px-4 py-3 text-right text-sm tabular-nums text-gray-400 dark:text-gray-600 border-r border-gray-100 dark:border-white/10">—</td>
                        <td class="px-4 py-3 text-right text-sm tabular-nums text-gray-400 dark:text-gray-600 border-r border-gray-100 dark:border-white/10">—</td>
                        <td class="px-4 py-3 text-right text-sm tabular-nums text-gray-400 dark:text-gray-600 border-r border-gray-100 dark:border-white/10">—</td>
                        <td class="px-4 py-3 text-right text-sm tabular-nums text-gray-950 dark:text-white border-r border-gray-100 dark:border-white/10">
                            {{ $wjBasah > 0 ? number_format($wjBasah, 0, ',', '.') : '—' }}
                        </td>
                        <td class="px-4 py-3 text-right text-sm tabular-nums text-gray-950 dark:text-white border-r border-gray-100 dark:border-white/10">
                            {{ $wjKering > 0 ? number_format($wjKering, 0, ',', '.') : '—' }}
                        </td>
                        <td class="px-4 py-3 text-right text-sm tabular-nums text-gray-950 dark:text-white border-r border-gray-100 dark:border-white/10">
                            {{ $wjJadi > 0 ? number_format($wjJadi, 0, ',', '.') : '—' }}
                        </td>
                        <td class="px-4 py-3 text-right text-sm font-semibold tabular-nums border-r border-gray-100 dark:border-white/10
                            {{ $rowTotal > 0 ? 'text-gray-950 dark:text-white' : 'text-gray-300 dark:text-gray-700' }}">
                            {{ $rowTotal > 0 ? number_format($rowTotal, 0, ',', '.') : '—' }}
                        </td>
                        <td class="px-4 py-3 text-right text-sm tabular-nums text-gray-400 dark:text-gray-600">—</td>
                    </tr>
                    @endforeach
                </tbody>

                <tfoot class="border-t border-gray-200 dark:border-white/10">
                    <tr class="bg-gray-50 dark:bg-white/5 text-xs font-semibold text-gray-600 dark:text-gray-300">
                        <td class="px-4 py-3 text-left text-gray-400 dark:text-gray-500 uppercase tracking-wide border-r border-gray-200 dark:border-white/10">Jumlah</td>
                        <td class="px-4 py-3 text-right text-gray-400 dark:text-gray-600 border-r border-gray-200 dark:border-white/10">—</td>
                        <td class="px-4 py-3 text-right text-gray-400 dark:text-gray-600 border-r border-gray-200 dark:border-white/10">—</td>
                        <td class="px-4 py-3 text-right text-gray-400 dark:text-gray-600 border-r border-gray-200 dark:border-white/10">—</td>
                        <td class="px-4 py-3 text-right tabular-nums border-r border-gray-200 dark:border-white/10">{{ $totWjBasah  > 0 ? number_format($totWjBasah,  0, ',', '.') : '—' }}</td>
                        <td class="px-4 py-3 text-right tabular-nums border-r border-gray-200 dark:border-white/10">{{ $totWjKering > 0 ? number_format($totWjKering, 0, ',', '.') : '—' }}</td>
                        <td class="px-4 py-3 text-right tabular-nums border-r border-gray-200 dark:border-white/10">{{ $totWjJadi   > 0 ? number_format($totWjJadi,   0, ',', '.') : '—' }}</td>
                        <td class="px-4 py-3 text-right tabular-nums font-bold text-gray-950 dark:text-white border-r border-gray-200 dark:border-white/10">
                            {{ number_format($totAll, 0, ',', '.') }}
                        </td>
                        <td class="px-4 py-3"></td>
                    </tr>

                    @if($filterKw === '' || in_array($filterKw, ['2', '3']))
                    <tr class="border-t border-gray-200 dark:border-white/10 bg-gray-50 dark:bg-white/5 text-xs font-semibold text-gray-600 dark:text-gray-300">
                        <td colspan="7" class="px-4 py-3 text-left border-r border-gray-200 dark:border-white/10">
                            Total KW 2 + KW 3
                        </td>
                        <td class="px-4 py-3 text-right tabular-nums font-bold text-gray-950 dark:text-white border-r border-gray-200 dark:border-white/10">
                            {{ $tot23 > 0 ? number_format($tot23, 0, ',', '.') : '—' }}
                        </td>
                        <td class="px-4 py-3 text-right tabular-nums text-gray-400 dark:text-gray-500">
                            {{ $tot23 > 0 ? number_format(ceil($tot23 / 3600), 0, ',', '.') . ' fuso' : '—' }}
                        </td>
                    </tr>
                    @endif
                </tfoot>
            </table>
        </div>
    </div>

    @empty
    <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 flex flex-col items-center justify-center py-20">
        <x-heroicon-o-archive-box-x-mark class="h-12 w-12 text-gray-300 dark:text-gray-700 mb-4"/>
        <p class="text-sm font-semibold text-gray-500 dark:text-gray-400">Tidak ada data ditemukan</p>
        <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">Coba ubah kata kunci atau reset filter.</p>
    </div>
    @endforelse

</div>
</x-filament-panels::page>
