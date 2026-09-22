<x-filament-panels::page>
    <div class="space-y-6">

        {{-- Header info tanggal --}}
        <div
            class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 px-6 py-4">
            <div class="flex items-center justify-between flex-wrap gap-2">
                <div>
                    <h2 class="text-base font-semibold text-gray-950 dark:text-white">Rekap Stok Veneer</h2>
                    <p class="text-sm text-gray-950 dark:text-white">Per tanggal
                        {{ \Carbon\Carbon::now()->translatedFormat('d F Y') }}</p>
                </div>
                <span class="inline-flex items-center gap-1.5 text-sm font-medium text-gray-950 dark:text-white">
                    <x-heroicon-o-calendar-days class="h-4 w-4" />
                    {{ count($allStocks) }} ukuran ditampilkan
                </span>
            </div>
        </div>

        {{-- Filter bar --}}
        <div
            class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 px-6 py-4">
            <div class="flex flex-wrap items-end gap-4">

                {{-- Search --}}
                <div class="flex-1 min-w-[160px]">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Cari Ukuran</label>
                    <div class="relative">
                        <x-heroicon-o-magnifying-glass
                            class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-800 dark:text-gray-200 pointer-events-none" />
                        <input wire:model.live.debounce.300ms="search" type="text" placeholder="122, 3.7, Sengon…"
                            class="fi-input block w-full rounded-lg border-0 py-1.5 pl-9 pr-3 text-sm text-gray-950 dark:text-white shadow-sm ring-1 ring-inset ring-gray-300 dark:ring-gray-600 bg-white dark:bg-gray-900 placeholder:text-gray-400 dark:placeholder:text-gray-500 focus:ring-2 focus:ring-inset focus:ring-primary-600 dark:focus:ring-primary-500" />
                    </div>
                </div>

                {{-- Jenis Kayu --}}
                <div class="min-w-[140px]">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Jenis Kayu</label>
                    <select wire:model.live="filterKayu"
                        class="fi-select block w-full rounded-lg border-0 py-1.5 px-3 text-sm text-gray-950 dark:text-white shadow-sm ring-1 ring-inset ring-gray-300 dark:ring-gray-600 bg-white dark:bg-gray-900 focus:ring-2 focus:ring-inset focus:ring-primary-600 dark:focus:ring-primary-500">
                        <option value="">Semua Kayu</option>
                        @foreach ($kayuOptions as $opt)
                            <option value="{{ $opt }}">{{ $opt }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- KW --}}
                <div class="min-w-[110px]">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Grade / KW</label>
                    <select wire:model.live="filterKw"
                        class="fi-select block w-full rounded-lg border-0 py-1.5 px-3 text-sm text-gray-950 dark:text-white shadow-sm ring-1 ring-inset ring-gray-300 dark:ring-gray-600 bg-white dark:bg-gray-900 focus:ring-2 focus:ring-inset focus:ring-primary-600 dark:focus:ring-primary-500">
                        <option value="">Semua KW</option>
                        @foreach ($allKws as $kwOpt)
                            <option value="{{ $kwOpt }}">KW {{ $kwOpt }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Urutkan --}}
                <div class="min-w-[120px]">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Urutkan</label>
                    <select wire:model.live="sortBy"
                        class="fi-select block w-full rounded-lg border-0 py-1.5 px-3 text-sm text-gray-950 dark:text-white shadow-sm ring-1 ring-inset ring-gray-300 dark:ring-gray-600 bg-white dark:bg-gray-900 focus:ring-2 focus:ring-inset focus:ring-primary-600 dark:focus:ring-primary-500">
                        <option value="ukuran">Ukuran</option>
                        <option value="jenis_kayu">Jenis Kayu</option>
                    </select>
                </div>

                @if ($search !== '' || $filterKayu !== '' || $filterKw !== '' || $sortBy !== 'ukuran')
                    <div class="flex items-end">
                        <button wire:click="$set('search', ''); $set('filterKayu', ''); $set('filterKw', ''); $set('sortBy', 'ukuran')"
                            class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-white/5 shadow-sm ring-1 ring-inset ring-gray-300 dark:ring-gray-600 hover:bg-gray-50 dark:hover:bg-white/10 transition">
                            <x-heroicon-o-x-mark class="h-4 w-4" />
                            Reset
                        </button>
                    </div>
                @endif
            </div>
        </div>

        {{-- ════════════════════════════════════════════════════════ --}}
        {{-- STOCK GROUPS                                            --}}
        {{-- ════════════════════════════════════════════════════════ --}}
        <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
            @php 
                $currentKayu = null; 
                $currentUkuran = null;
            @endphp
            @forelse($allStocks as $stockGroup)
                @if ($sortBy === 'jenis_kayu' && $currentKayu !== $stockGroup['jenis_kayu'])
                    @php $currentKayu = $stockGroup['jenis_kayu']; @endphp
                    <div class="col-span-1 xl:col-span-2 border-b-2 border-primary-500/30 dark:border-primary-500/20 pb-2 mt-4 first:mt-0 flex items-center gap-2">
                        <x-heroicon-o-tag class="h-5 w-5 text-primary-600 dark:text-primary-400" />
                        <h2 class="text-xl font-bold text-gray-900 dark:text-white uppercase tracking-wider">{{ $currentKayu }}</h2>
                    </div>
                @elseif ($sortBy === 'ukuran')
                    @php 
                        $ukuranLabel = $stockGroup['panjang'] . ' × ' . $stockGroup['lebar'] . ' × ' . $stockGroup['tebal'] . ' mm';
                    @endphp
                    @if ($currentUkuran !== $ukuranLabel)
                        @php $currentUkuran = $ukuranLabel; @endphp
                        <div class="col-span-1 xl:col-span-2 border-b-2 border-primary-500/30 dark:border-primary-500/20 pb-2 mt-4 first:mt-0 flex items-center gap-2">
                            <x-heroicon-o-arrows-pointing-out class="h-5 w-5 text-primary-600 dark:text-primary-400" />
                            <h2 class="text-xl font-bold text-gray-900 dark:text-white uppercase tracking-wider">{{ $currentUkuran }}</h2>
                        </div>
                    @endif
                @endif
                @php
                    // Tentukan KW yang akan ditampilkan
                    if ($filterKw !== '') {
                        $kw = is_numeric($filterKw) ? (int) $filterKw : strtoupper($filterKw);
                        $tableKws = [$kw];
                    } else {
                        $tableKws = [];
                        foreach ($allKws as $k) {
                            $total = 0;
                            foreach (
                                [
                                    'localBasah',
                                    'localKering',
                                    'localJadi',
                                    'externalBasah',
                                    'externalKering',
                                    'externalJadi',
                                ]
                                as $cat
                            ) {
                                $total += $stockGroup[$cat][$k] ?? 0;
                            }
                            if ($total != 0) {
                                $tableKws[] = $k;
                            }
                        }
                    }

                    $totLocalBasah = 0;
                    $totLocalKering = 0;
                    $totLocalJadi = 0;
                    $totExtBasah = 0;
                    $totExtKering = 0;
                    $totExtJadi = 0;
                    $totAll = 0;
                    $tot23 = 0;

                    // Pre-hitung semua baris supaya bisa dipakai di mobile & desktop
                    $rows = [];
                    foreach ($tableKws as $kw) {
                        $localBasah = $stockGroup['localBasah'][$kw] ?? 0;
                        $localKering = $stockGroup['localKering'][$kw] ?? 0;
                        $localJadi = $stockGroup['localJadi'][$kw] ?? 0;
                        $extBasah = $stockGroup['externalBasah'][$kw] ?? 0;
                        $extKering = $stockGroup['externalKering'][$kw] ?? 0;
                        $extJadi = $stockGroup['externalJadi'][$kw] ?? 0;
                        $rowTotal = $localBasah + $localKering + $localJadi + $extBasah + $extKering + $extJadi;

                        $totLocalBasah += $localBasah;
                        $totLocalKering += $localKering;
                        $totLocalJadi += $localJadi;
                        $totExtBasah += $extBasah;
                        $totExtKering += $extKering;
                        $totExtJadi += $extJadi;
                        $totAll += $rowTotal;
                        if (in_array($kw, [2, 3])) {
                            $tot23 += $rowTotal;
                        }

                        $rows[] = compact(
                            'kw',
                            'localBasah',
                            'localKering',
                            'localJadi',
                            'extBasah',
                            'extKering',
                            'extJadi',
                            'rowTotal',
                        );
                    }

                    $fmt = fn($v) => $v > 0 ? number_format($v, 0, ',', '.') : '—';
                @endphp

                <div
                    class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 overflow-hidden self-start">

                    {{-- Section header --}}
                    <div
                        class="fi-section-header flex items-center gap-3 px-4 py-3 border-b border-gray-100 dark:border-white/10">
                        <x-heroicon-o-cube class="h-5 w-5 text-gray-800 dark:text-gray-200 shrink-0" />
                        <div>
                            <span
                                class="text-xs font-medium text-gray-800 dark:text-gray-200">{{ $stockGroup['jenis_kayu'] }}</span>
                            <h3 class="text-sm font-semibold text-gray-950 dark:text-white leading-tight">
                                {{ $stockGroup['panjang'] }} &times; {{ $stockGroup['lebar'] }} &times;
                                {{ $stockGroup['tebal'] }} mm
                            </h3>
                        </div>
                    </div>

                    {{-- ──────────────────────────────────────────────────── --}}
                    {{-- DESKTOP TABLE (md ke atas)                          --}}
                    {{-- ──────────────────────────────────────────────────── --}}
                    <div class="hidden md:block overflow-x-auto">
                        <table class="w-full text-sm whitespace-nowrap">
                            <thead>
                                <tr class="border-b border-gray-200 dark:border-white/10">
                                    <th rowspan="2"
                                        class="px-3 py-2.5 text-center text-xs font-semibold text-gray-950 dark:text-white bg-primary-100 dark:bg-primary-900/20 border-r border-gray-200 dark:border-white/10 align-middle">
                                        KW</th>
                                    <th colspan="3"
                                        class="px-3 py-2 text-center text-xs font-semibold text-gray-950 dark:text-white bg-primary-100 dark:bg-primary-900/20 border-r border-gray-200 dark:border-white/10">
                                        {{ $externalLabel }}</th>
                                    <th colspan="3"
                                        class="px-3 py-2 text-center text-xs font-semibold text-primary-600 dark:text-primary-400 bg-primary-50 dark:bg-primary-950/30 border-r border-gray-200 dark:border-white/10">
                                        {{ $localLabel }}</th>
                                    <th rowspan="2"
                                        class="px-3 py-2.5 text-center text-xs font-semibold text-gray-950 dark:text-white bg-primary-100 dark:bg-primary-900/20 border-r border-gray-200 dark:border-white/10 align-middle">
                                        Total</th>
                                    <th rowspan="2"
                                        class="px-3 py-2.5 text-center text-xs font-semibold text-gray-950 dark:text-white bg-primary-100 dark:bg-primary-900/20 align-middle">
                                        Krat</th>
                                </tr>
                                <tr
                                    class="border-b border-gray-200 dark:border-white/10 text-xs text-gray-950 dark:text-white">
                                    <th
                                        class="px-3 py-2 text-center font-medium bg-primary-100 dark:bg-primary-900/20 border-r border-gray-200 dark:border-white/10">
                                        Basah</th>
                                    <th
                                        class="px-3 py-2 text-center font-medium bg-primary-100 dark:bg-primary-900/20 border-r border-gray-200 dark:border-white/10">
                                        Kering</th>
                                    <th
                                        class="px-3 py-2 text-center font-medium bg-primary-100 dark:bg-primary-900/20 border-r border-gray-200 dark:border-white/10">
                                        Jadi</th>
                                    <th
                                        class="px-3 py-2 text-center font-medium bg-primary-50 dark:bg-primary-950/30 border-r border-gray-200 dark:border-white/10">
                                        Basah</th>
                                    <th
                                        class="px-3 py-2 text-center font-medium bg-primary-50 dark:bg-primary-950/30 border-r border-gray-200 dark:border-white/10">
                                        Kering</th>
                                    <th
                                        class="px-3 py-2 text-center font-medium bg-primary-50 dark:bg-primary-950/30 border-r border-gray-200 dark:border-white/10">
                                        Jadi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                                @foreach ($rows as $row)
                                    <tr class="hover:bg-gray-50 dark:hover:bg-white/5 transition-colors">
                                        <td
                                            class="px-3 py-2.5 text-center text-sm font-semibold text-gray-700 dark:text-gray-300 border-r border-gray-100 dark:border-white/10">
                                            {{ $row['kw'] }}</td>
                                        <td
                                            class="px-3 py-2.5 text-right text-sm tabular-nums text-gray-950 dark:text-white border-r border-gray-100 dark:border-white/10">
                                            {{ $fmt($row['extBasah']) }}</td>
                                        <td
                                            class="px-3 py-2.5 text-right text-sm tabular-nums text-gray-950 dark:text-white border-r border-gray-100 dark:border-white/10">
                                            {{ $fmt($row['extKering']) }}</td>
                                        <td
                                            class="px-3 py-2.5 text-right text-sm tabular-nums text-gray-950 dark:text-white border-r border-gray-100 dark:border-white/10">
                                            {{ $fmt($row['extJadi']) }}</td>
                                        <td
                                            class="px-3 py-2.5 text-right text-sm tabular-nums text-gray-950 dark:text-white border-r border-gray-100 dark:border-white/10">
                                            {{ $fmt($row['localBasah']) }}</td>
                                        <td
                                            class="px-3 py-2.5 text-right text-sm tabular-nums text-gray-950 dark:text-white border-r border-gray-100 dark:border-white/10">
                                            {{ $fmt($row['localKering']) }}</td>
                                        <td
                                            class="px-3 py-2.5 text-right text-sm tabular-nums text-gray-950 dark:text-white border-r border-gray-100 dark:border-white/10">
                                            {{ $fmt($row['localJadi']) }}</td>
                                        <td
                                            class="px-3 py-2.5 text-right text-sm font-semibold tabular-nums border-r border-gray-100 dark:border-white/10 {{ $row['rowTotal'] > 0 ? 'text-gray-950 dark:text-white' : 'text-red-600 dark:text-red-400' }}">
                                            {{ $fmt($row['rowTotal']) }}
                                        </td>
                                        <td
                                            class="px-3 py-2.5 text-right text-sm tabular-nums text-gray-950 dark:text-white">
                                            —</td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot class="border-t-2 border-gray-200 dark:border-white/10">
                                <tr
                                    class="bg-primary-100 dark:bg-primary-900/20 text-xs font-semibold text-gray-950 dark:text-white">
                                    <td
                                        class="px-3 py-2.5 text-left text-gray-800 dark:text-gray-200 uppercase tracking-wide border-r border-gray-200 dark:border-white/10">
                                        Jumlah</td>
                                    <td
                                        class="px-3 py-2.5 text-right tabular-nums border-r border-gray-200 dark:border-white/10">
                                        {{ $fmt($totExtBasah) }}</td>
                                    <td
                                        class="px-3 py-2.5 text-right tabular-nums border-r border-gray-200 dark:border-white/10">
                                        {{ $fmt($totExtKering) }}</td>
                                    <td
                                        class="px-3 py-2.5 text-right tabular-nums border-r border-gray-200 dark:border-white/10">
                                        {{ $fmt($totExtJadi) }}</td>
                                    <td
                                        class="px-3 py-2.5 text-right tabular-nums border-r border-gray-200 dark:border-white/10">
                                        {{ $fmt($totLocalBasah) }}</td>
                                    <td
                                        class="px-3 py-2.5 text-right tabular-nums border-r border-gray-200 dark:border-white/10">
                                        {{ $fmt($totLocalKering) }}</td>
                                    <td
                                        class="px-3 py-2.5 text-right tabular-nums border-r border-gray-200 dark:border-white/10">
                                        {{ $fmt($totLocalJadi) }}</td>
                                    <td
                                        class="px-3 py-2.5 text-right tabular-nums font-bold text-gray-950 dark:text-white border-r border-gray-200 dark:border-white/10">
                                        {{ number_format($totAll, 0, ',', '.') }}</td>
                                    <td class="px-3 py-2.5"></td>
                                </tr>
                                @if (($filterKw === '' || in_array($filterKw, ['2', '3'])) && $tot23 > 0)
                                    <tr
                                        class="border-t border-gray-200 dark:border-white/10 bg-amber-50 dark:bg-amber-950/20 text-xs font-semibold">
                                        <td colspan="7"
                                            class="px-3 py-2.5 text-left text-gray-700 dark:text-gray-300 border-r border-gray-200 dark:border-white/10">
                                            Total KW 2 + KW 3</td>
                                        <td
                                            class="px-3 py-2.5 text-right tabular-nums font-bold text-gray-950 dark:text-white border-r border-gray-200 dark:border-white/10">
                                            {{ number_format($tot23, 0, ',', '.') }}</td>
                                        {{-- LOGIKA FUSO (dinonaktifkan sementara): 1 fuso = 3600 lembar --}}
                                        {{-- <td class="px-3 py-2.5 text-right tabular-nums text-gray-950 dark:text-white">{{ number_format(ceil($tot23 / 3600), 0, ',', '.') }} fuso</td> --}}
                                        <td class="px-3 py-2.5"></td>
                                    </tr>
                                @endif
                            </tfoot>
                        </table>
                    </div>


                    {{-- ──────────────────────────────────────────────────── --}}
                    {{-- MOBILE CARDS (di bawah md)                          --}}
                    {{-- ──────────────────────────────────────────────────── --}}
                    <div class="md:hidden divide-y divide-gray-100 dark:divide-white/5">
                        @foreach ($rows as $row)
                            <div class="px-4 py-3 space-y-2">

                                {{-- Badge KW --}}
                                <div class="flex items-center justify-between">
                                    <span
                                        class="inline-flex items-center rounded-md bg-gray-100 dark:bg-white/10 px-2.5 py-0.5 text-xs font-semibold text-gray-700 dark:text-gray-200">
                                        KW {{ $row['kw'] }}
                                    </span>
                                    <span
                                        class="text-sm font-bold tabular-nums {{ $row['rowTotal'] > 0 ? 'text-gray-950 dark:text-white' : 'text-gray-300 dark:text-gray-600' }}">
                                        Total: {{ $fmt($row['rowTotal']) }}
                                    </span>
                                </div>

                                {{-- Mini table: 2 baris (External & Local) × 3 kolom --}}
                                <table class="w-full text-xs whitespace-nowrap">
                                    <thead>
                                        <tr class="text-gray-800 dark:text-gray-200">
                                            <th class="py-1 text-left font-medium"></th>
                                            <th class="py-1 text-right font-medium">Basah</th>
                                            <th class="py-1 text-right font-medium">Kering</th>
                                            <th class="py-1 text-right font-medium">Jadi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {{-- External (partner) --}}
                                        <tr>
                                            <td class="py-1 font-semibold text-gray-950 dark:text-white">
                                                {{ $externalLabel }}</td>
                                            <td class="py-1 text-right tabular-nums text-gray-950 dark:text-white">
                                                {{ $fmt($row['extBasah']) }}</td>
                                            <td class="py-1 text-right tabular-nums text-gray-950 dark:text-white">
                                                {{ $fmt($row['extKering']) }}</td>
                                            <td class="py-1 text-right tabular-nums text-gray-950 dark:text-white">
                                                {{ $fmt($row['extJadi']) }}</td>
                                        </tr>
                                        {{-- Local (web ini) --}}
                                        <tr>
                                            <td class="py-1 font-semibold text-primary-600 dark:text-primary-400">
                                                {{ $localLabel }}</td>
                                            <td class="py-1 text-right tabular-nums text-gray-950 dark:text-white">
                                                {{ $fmt($row['localBasah']) }}</td>
                                            <td class="py-1 text-right tabular-nums text-gray-950 dark:text-white">
                                                {{ $fmt($row['localKering']) }}</td>
                                            <td class="py-1 text-right tabular-nums text-gray-950 dark:text-white">
                                                {{ $fmt($row['localJadi']) }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        @endforeach

                        {{-- Footer mobile: Jumlah & KW 2+3 --}}
                        <div class="bg-primary-100 dark:bg-primary-900/20 px-4 py-3 space-y-2">
                            <p class="text-xs font-semibold text-gray-800 dark:text-gray-200 uppercase tracking-wide">
                                Jumlah</p>
                            <table class="w-full text-xs whitespace-nowrap">
                                <thead>
                                    <tr class="text-gray-800 dark:text-gray-200">
                                        <th class="py-1 text-left font-medium"></th>
                                        <th class="py-1 text-right font-medium">Basah</th>
                                        <th class="py-1 text-right font-medium">Kering</th>
                                        <th class="py-1 text-right font-medium">Jadi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td class="py-1 font-semibold text-gray-950 dark:text-white">
                                            {{ $externalLabel }}</td>
                                        <td class="py-1 text-right tabular-nums text-gray-950 dark:text-white">
                                            {{ $fmt($totExtBasah) }}</td>
                                        <td class="py-1 text-right tabular-nums text-gray-950 dark:text-white">
                                            {{ $fmt($totExtKering) }}</td>
                                        <td class="py-1 text-right tabular-nums text-gray-950 dark:text-white">
                                            {{ $fmt($totExtJadi) }}</td>
                                    </tr>
                                    <tr>
                                        <td class="py-1 font-semibold text-primary-600 dark:text-primary-400">
                                            {{ $localLabel }}</td>
                                        <td class="py-1 text-right tabular-nums text-gray-950 dark:text-white">
                                            {{ $fmt($totLocalBasah) }}</td>
                                        <td class="py-1 text-right tabular-nums text-gray-950 dark:text-white">
                                            {{ $fmt($totLocalKering) }}</td>
                                        <td class="py-1 text-right tabular-nums text-gray-950 dark:text-white">
                                            {{ $fmt($totLocalJadi) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                            <div
                                class="flex items-center justify-between pt-1 border-t border-gray-200 dark:border-white/10">
                                <span class="text-xs font-semibold text-gray-950 dark:text-white">Total</span>
                                <span
                                    class="text-sm font-bold tabular-nums text-gray-950 dark:text-white">{{ number_format($totAll, 0, ',', '.') }}</span>
                            </div>
                        </div>

                        @if (($filterKw === '' || in_array($filterKw, ['2', '3'])) && $tot23 > 0)
                            <div class="bg-amber-50 dark:bg-amber-950/20 px-4 py-3 flex items-center justify-between">
                                <div>
                                    <p class="text-xs font-semibold text-gray-700 dark:text-gray-300">Total KW 2 + KW 3</p>
                                    {{-- LOGIKA FUSO (dinonaktifkan sementara): 1 fuso = 3600 lembar --}}
                                    {{-- <p class="text-xs text-gray-950 dark:text-white">{{ number_format(ceil($tot23 / 3600), 0, ',', '.') }} fuso</p> --}}
                                </div>
                                <span
                                    class="text-sm font-bold tabular-nums text-gray-950 dark:text-white">{{ number_format($tot23, 0, ',', '.') }}</span>
                            </div>
                        @endif
                    </div>


                </div>{{-- end stock group --}}
            @empty
                <div class="col-span-1 xl:col-span-2 fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 flex flex-col items-center justify-center py-20">
                    <x-heroicon-o-archive-box-x-mark class="h-12 w-12 text-red-600 dark:text-red-400 mb-4" />
                    <p class="text-sm font-semibold text-gray-950 dark:text-white">Tidak ada data ditemukan</p>
                    <p class="mt-1 text-xs text-gray-800 dark:text-gray-200">Coba ubah kata kunci atau reset filter.</p>
                </div>
            @endforelse
        </div>
    </div>
</x-filament-panels::page>

