<x-filament-panels::page>
    <div class="w-full relative">

        @push('styles')
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">

        <style>
            .lb-wrap,
            .lb-wrap * {
                font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif !important;
            }

            /* Konfeti animation */
            @keyframes confettiFall {
                0% {
                    transform: translateY(-10vh) rotate(0deg);
                    opacity: 1;
                }

                100% {
                    transform: translateY(110vh) rotate(720deg);
                    opacity: 0;
                }
            }

            .confetti-piece {
                animation: confettiFall linear forwards;
            }

            /* Slide-Over Drawer */
            #lb-drawer {
                transform: translateX(100%);
                transition: transform 0.35s cubic-bezier(0.32, 0.72, 0, 1);
            }

            #lb-drawer.lb-drawer--open {
                transform: translateX(0);
            }

            #lb-overlay {
                opacity: 0;
                transition: opacity 0.3s ease;
                pointer-events: none;
            }

            #lb-overlay.lb-overlay--visible {
                opacity: 1;
                pointer-events: auto;
            }

            .lb-row {
                transition: filter 0.15s ease, background-color 0.15s ease;
            }

            .lb-row:hover .lb-chevron {
                transform: translateX(4px);
            }

            .lb-chevron {
                transition: transform 0.2s ease;
            }

            .medal-svg {
                filter: drop-shadow(0 1px 2px rgba(0, 0, 0, .18));
            }

            #lb-drawer-body::-webkit-scrollbar {
                width: 4px;
            }

            #lb-drawer-body::-webkit-scrollbar-thumb {
                background: #e2e8f0;
                border-radius: 4px;
            }

            .lb-invoice-row {
                cursor: pointer;
                transition: background-color 0.12s ease;
            }

            .lb-invoice-row:hover .lb-invoice-nota {
                color: #58cc02;
            }

            .lb-invoice-icon {
                opacity: 0;
                transition: opacity 0.15s ease;
            }

            .lb-invoice-row:hover .lb-invoice-icon {
                opacity: 1;
            }

            /* Date Range Picker Trigger */
            #lb-date-trigger {
                display: inline-flex;
                align-items: center;
                gap: 8px;
                padding: 10px 18px;
                border-radius: 12px;
                border: 1.5px solid #e2e8f0;
                background: #fff;
                color: #4b4b4b;
                font-size: 14px;
                font-weight: 700;
                cursor: pointer;
                transition: border-color 0.15s, background 0.15s;
                white-space: nowrap;
                user-select: none;
            }

            .dark #lb-date-trigger {
                background: #27272a;
                border-color: #3f3f46;
                color: #e4e4e7;
            }

            #lb-date-trigger:hover {
                border-color: #58cc02;
            }

            #lb-date-trigger.lb-active {
                border-color: #58cc02;
                background: #f0fde4;
                color: #3a8a01;
            }

            .dark #lb-date-trigger.lb-active {
                background: #1a3809;
                color: #7ae629;
                border-color: #4a7a10;
            }

            /* Reset button */
            #lb-date-reset {
                display: none;
                align-items: center;
                gap: 5px;
                padding: 8px 14px;
                border-radius: 12px;
                border: 1.5px solid #fca5a5;
                background: #fef2f2;
                color: #dc2626;
                font-size: 13px;
                font-weight: 800;
                cursor: pointer;
                transition: background 0.15s;
                white-space: nowrap;
            }

            .dark #lb-date-reset {
                background: #450a0a;
                border-color: #991b1b;
                color: #fca5a5;
            }

            #lb-date-reset:hover {
                background: #fee2e2;
            }

            .dark #lb-date-reset:hover {
                background: #7f1d1d;
            }

            #lb-date-reset.lb-visible {
                display: inline-flex;
            }

            /* Popover */
            #lb-date-popover {
                display: none;
                position: absolute;
                top: calc(100% + 10px);
                left: 0;
                z-index: 999;
                background: #fff;
                border: 1.5px solid #e2e8f0;
                border-radius: 16px;
                box-shadow: 0 8px 32px rgba(0, 0, 0, 0.12);
                padding: 20px;
                width: 340px;
            }

            .dark #lb-date-popover {
                background: #18181b;
                border-color: #3f3f46;
                box-shadow: 0 8px 32px rgba(0, 0, 0, 0.5);
            }

            #lb-date-popover.lb-open {
                display: block;
            }

            /* Calendar grid */
            .lb-cal-header {
                display: flex;
                align-items: center;
                justify-content: space-between;
                margin-bottom: 14px;
            }

            .lb-cal-nav {
                background: none;
                border: none;
                cursor: pointer;
                color: #94a3b8;
                display: flex;
                align-items: center;
                justify-content: center;
                width: 32px;
                height: 32px;
                border-radius: 6px;
            }

            .lb-cal-nav:hover {
                background: #f1f5f9;
                color: #4b4b4b;
            }

            .dark .lb-cal-nav:hover {
                background: #3f3f46;
                color: #e4e4e7;
            }

            .lb-cal-title {
                font-size: 14px;
                font-weight: 800;
                color: #4b4b4b;
            }

            .dark .lb-cal-title {
                color: #e4e4e7;
            }

            .lb-cal-grid {
                display: grid;
                grid-template-columns: repeat(7, 1fr);
                gap: 3px;
                margin-bottom: 4px;
            }

            .lb-cal-dow {
                text-align: center;
                font-size: 11px;
                font-weight: 800;
                color: #94a3b8;
                padding: 4px 0 8px;
                text-transform: uppercase;
            }

            .lb-cal-day {
                position: relative;
                text-align: center;
                font-size: 13px;
                font-weight: 700;
                color: #4b4b4b;
                padding: 6px 0;
                border-radius: 6px;
                cursor: pointer;
                line-height: 1;
            }

            .dark .lb-cal-day {
                color: #d4d4d8;
            }

            .lb-cal-day:hover:not(.lb-empty):not(.lb-disabled) {
                background: #f0fde4;
                color: #3a8a01;
            }

            .dark .lb-cal-day:hover:not(.lb-empty):not(.lb-disabled) {
                background: #1a3809;
                color: #7ae629;
            }

            .lb-cal-day.lb-selected-start,
            .lb-cal-day.lb-selected-end {
                background: #58cc02 !important;
                color: #fff !important;
            }

            .lb-cal-day.lb-in-range {
                background: #f0fde4;
                color: #3a8a01;
                border-radius: 0;
            }

            .dark .lb-cal-day.lb-in-range {
                background: #1a3809;
                color: #7ae629;
            }

            .lb-presets {
                display: flex;
                flex-wrap: wrap;
                gap: 8px;
                margin-bottom: 16px;
                padding-bottom: 14px;
                border-bottom: 1.5px solid #f1f5f9;
            }

            .dark .lb-presets {
                border-color: #3f3f46;
            }

            .lb-preset-btn {
                padding: 6px 12px;
                border-radius: 8px;
                border: 1.5px solid #e2e8f0;
                background: #f8fafc;
                color: #64748b;
                font-size: 12px;
                font-weight: 800;
                cursor: pointer;
            }

            .dark .lb-preset-btn {
                background: #27272a;
                border-color: #3f3f46;
                color: #a1a1aa;
            }

            .lb-preset-btn:hover,
            .lb-preset-btn.lb-preset-active {
                border-color: #58cc02;
                background: #f0fde4;
                color: #3a8a01;
            }

            .dark .lb-preset-btn:hover,
            .dark .lb-preset-btn.lb-preset-active {
                background: #1a3809;
                border-color: #4a7a10;
                color: #7ae629;
            }

            .lb-pop-footer {
                display: flex;
                align-items: center;
                justify-content: space-between;
                margin-top: 14px;
                padding-top: 12px;
                border-top: 1.5px solid #f1f5f9;
                gap: 10px;
            }

            .dark .lb-pop-footer {
                border-color: #3f3f46;
            }

            .lb-pop-range-label {
                font-size: 12px;
                font-weight: 700;
                color: #94a3b8;
                flex: 1;
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
            }

            .lb-pop-apply {
                padding: 10px 18px;
                border-radius: 10px;
                border: none;
                background: #58cc02;
                color: #fff;
                font-size: 13px;
                font-weight: 700;
                cursor: pointer;
            }

            .lb-pop-apply:hover:not(:disabled) {
                background: #46a302;
            }

            .lb-pop-apply:disabled {
                background: #e5e7eb;
                color: #9ca3af;
                cursor: not-allowed;
            }
        </style>
        @endpush

        {{-- Konfeti Container --}}
        <div id="lb-confetti" class="fixed inset-0 overflow-hidden pointer-events-none z-[60]" aria-hidden="true"></div>

        <div class="lb-wrap max-w-4xl mx-auto px-2 py-4 relative">

            {{-- Header --}}
            <div class="flex flex-col items-center mb-6 mt-2 text-center">
                <div class="mb-3">
                    <img src="https://api.dicebear.com/10.x/identicon/svg?seed=Luna" alt="Maskot" class="w-20 h-20 object-contain drop-shadow-md select-none" />
                </div>
                <h1 class="text-3xl font-black text-[#4b4b4b] dark:text-white mb-1">
                    Leaderboard Supplier Kayu
                </h1>
                <p class="text-[#a5a5a5] dark:text-zinc-400 font-semibold text-sm">
                    Peringkat aktivitas transaksi mitra supplier kayu berdasarkan nota kayu masuk
                </p>
            </div>

            {{-- Metric Selector Tabs --}}
            <div class="flex items-center justify-center mb-6">
                <div class="bg-slate-200/80 dark:bg-zinc-800/80 p-1.5 rounded-2xl flex items-center gap-1.5 border border-slate-300/50 dark:border-zinc-700/50 flex-wrap justify-center shadow-inner">
                    <button wire:click="setSortBy('total_pembelian')"
                        class="px-4 py-2.5 rounded-xl text-xs md:text-sm font-extrabold transition-all flex items-center gap-2 {{ $sortBy === 'total_pembelian' ? 'bg-[#58cc02] text-white shadow-md' : 'text-slate-600 dark:text-zinc-300 hover:text-slate-900 dark:hover:text-white' }}">
                        Total Nilai (Rp)
                    </button>
                    <button wire:click="setSortBy('nota_dicetak')"
                        class="px-4 py-2.5 rounded-xl text-xs md:text-sm font-extrabold transition-all flex items-center gap-2 {{ $sortBy === 'nota_dicetak' ? 'bg-[#58cc02] text-white shadow-md' : 'text-slate-600 dark:text-zinc-300 hover:text-slate-900 dark:hover:text-white' }}">
                        Jumlah Transaksi (Nota)
                    </button>
                    <button wire:click="setSortBy('total_kubikasi')"
                        class="px-4 py-2.5 rounded-xl text-xs md:text-sm font-extrabold transition-all flex items-center gap-2 {{ $sortBy === 'total_kubikasi' ? 'bg-[#58cc02] text-white shadow-md' : 'text-slate-600 dark:text-zinc-300 hover:text-slate-900 dark:hover:text-white' }}">
                        Total Kubikasi (m³)
                    </button>
                </div>
            </div>

            {{-- Filter Bar --}}
            <div class="flex items-center gap-3 mb-6 max-w-2xl mx-auto flex-wrap">
                {{-- Search --}}
                <div class="relative flex-1 min-w-[180px]">
                    <svg class="absolute left-4 top-3.5 w-5 h-5 text-slate-300 dark:text-zinc-500 pointer-events-none" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <circle cx="11" cy="11" r="8" />
                        <path d="m21 21-4.35-4.35" />
                    </svg>
                    <input type="text" wire:model.live.debounce.300ms="search" placeholder="Cari mitra supplier..."
                        class="w-full bg-slate-50 dark:bg-zinc-800/50 border-2 border-slate-100 dark:border-zinc-700/50 text-slate-700 dark:text-zinc-100 font-bold px-12 py-2.5 rounded-2xl focus:outline-none focus:border-[#58cc02] focus:bg-white dark:focus:bg-zinc-800 transition-all placeholder:text-slate-400 placeholder:font-semibold" />
                    @if ($search)
                    <button wire:click="$set('search', '')" class="absolute right-4 top-3 text-slate-400 hover:text-slate-600 dark:text-zinc-500 dark:hover:text-zinc-300">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path d="M18 6 6 18M6 6l12 12" />
                        </svg>
                    </button>
                    @endif
                </div>

                {{-- Date Range Trigger --}}
                <div class="relative" id="lb-date-wrapper">
                    <div class="flex items-center gap-2">
                        <button id="lb-date-trigger" onclick="lbTogglePicker(event)" aria-haspopup="true" aria-expanded="false">
                            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                <rect x="3" y="4" width="18" height="18" rx="3" />
                                <path d="M16 2v4M8 2v4M3 10h18" />
                            </svg>
                            <span id="lb-trigger-label">Semua Periode</span>
                            <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                <path d="m6 9 6 6 6-6" />
                            </svg>
                        </button>
                        <button id="lb-date-reset" onclick="lbResetFilter()" title="Hapus filter tanggal">
                            <svg width="11" height="11" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                <path d="M18 6 6 18M6 6l12 12" />
                            </svg>
                            Reset
                        </button>
                    </div>

                    {{-- Popover --}}
                    <div id="lb-date-popover" role="dialog" aria-label="Pilih rentang tanggal">
                        <div class="lb-presets">
                            <button class="lb-preset-btn" data-preset="today" onclick="lbApplyPreset('today')">Hari ini</button>
                            <button class="lb-preset-btn" data-preset="yesterday" onclick="lbApplyPreset('yesterday')">Kemarin</button>
                            <button class="lb-preset-btn" data-preset="this_week" onclick="lbApplyPreset('this_week')">Minggu ini</button>
                            <button class="lb-preset-btn" data-preset="this_month" onclick="lbApplyPreset('this_month')">Bulan ini</button>
                            <button class="lb-preset-btn" data-preset="last_month" onclick="lbApplyPreset('last_month')">Bulan lalu</button>
                            <button class="lb-preset-btn" data-preset="this_year" onclick="lbApplyPreset('this_year')">Tahun ini</button>
                        </div>

                        <div class="lb-cal-header">
                            <button class="lb-cal-nav" onclick="lbNavMonth(-1)"><svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                    <path d="m15 18-6-6 6-6" />
                                </svg></button>
                            <span class="lb-cal-title" id="lb-cal-title">—</span>
                            <button class="lb-cal-nav" onclick="lbNavMonth(1)"><svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                    <path d="m9 18 6-6-6-6" />
                                </svg></button>
                        </div>

                        <div class="lb-cal-grid" id="lb-cal-grid"></div>

                        <div class="lb-pop-footer">
                            <span class="lb-pop-range-label" id="lb-range-label">Pilih tanggal mulai</span>
                            <button class="lb-pop-apply" id="lb-apply-btn" onclick="lbCommitFilter()" disabled>Terapkan</button>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Leaderboard Table --}}
            <div class="bg-white dark:bg-zinc-900 rounded-3xl p-4 md:p-6 border-2 border-slate-200/80 dark:border-zinc-800 shadow-xl">
                @if ($leaderboard->isEmpty())
                <div class="text-center py-16 text-slate-400 dark:text-zinc-500 font-bold text-lg">
                    Tidak ada supplier ditemukan.
                </div>
                @else
                <div class="w-full overflow-x-auto">
                    <table class="w-full text-left border-separate border-spacing-y-2.5 min-w-[620px]">
                        <thead>
                            <tr class="text-slate-400 dark:text-zinc-500 font-extrabold text-[11px] uppercase tracking-wider">
                                <th class="pb-2 px-3 text-center w-16">Rank</th>
                                <th class="pb-2 px-3">Mitra Supplier</th>
                                <th class="pb-2 px-3 text-center w-28">Total Transaksi</th>
                                <th class="pb-2 px-3 text-center w-32">Total Kubikasi</th>
                                <th class="pb-2 px-4 text-right">Total Nilai Pembelian</th>
                                <th class="pb-2 px-2 w-8"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($leaderboard as $supplier)
                            @php
                            $rank = $supplier->rank;
                            $isTop3 = $rank <= 3;
                                $medalBg=match($rank) { 1=> '#facc15', 2 => '#e2e8f0', 3 => '#f59e0b', default => null };
                                $medalText = match($rank) { 1 => '#fff', 2 => '#64748b', 3 => '#fff', default => null };
                                @endphp
                                <tr class="lb-row group cursor-pointer bg-white dark:bg-zinc-800/50 hover:bg-slate-50 dark:hover:bg-zinc-800 transition-all"
                                    onclick="lbOpenDrawer({{ $supplier->supplier_id }}, {{ json_encode($supplier->supplier_name) }})">

                                    {{-- Rank / Medal --}}
                                    <td class="py-3 px-3 rounded-l-2xl border-y border-l border-slate-100 dark:border-zinc-800">
                                        @if ($isTop3)
                                        <div class="relative w-8 h-10 flex flex-col items-center justify-center -mt-1 mx-auto">
                                            <svg viewBox="0 0 24 32" class="absolute inset-0 w-full h-full medal-svg">
                                                <path d="M0 6 C0 2.7 2.7 0 6 0 L18 0 C21.3 0 24 2.7 24 6 L24 24 L12 30 L0 24 Z" fill="{{ $medalBg }}" />
                                            </svg>
                                            <span class="relative z-10 font-black text-sm" style="color: {{ $medalText }}">{{ $rank }}</span>
                                        </div>
                                        @else
                                        <div class="w-full font-black text-[#58cc02] dark:text-[#7ae629] text-center text-lg">{{ $rank }}</div>
                                        @endif
                                    </td>

                                    {{-- Supplier Name --}}
                                    <td class="py-3 px-3 border-y border-slate-100 dark:border-zinc-800">
                                        <div class="flex items-center gap-3">
                                            <div class="relative flex-shrink-0">
                                                <div class="w-11 h-11 rounded-full overflow-hidden border-2 border-slate-200 dark:border-zinc-700 bg-slate-100 dark:bg-zinc-700">
                                                    <img src="https://api.dicebear.com/9.x/lorelei/svg?seed={{ urlencode($supplier->supplier_name) }}&backgroundColor=transparent"
                                                        alt="{{ $supplier->supplier_name }}" class="w-full h-full object-cover" />
                                                </div>
                                                <div class="absolute bottom-0 right-0 w-3 h-3 bg-[#58cc02] border-2 border-white dark:border-zinc-800 rounded-full"></div>
                                            </div>
                                            <span class="font-extrabold text-sm md:text-base text-[#4b4b4b] dark:text-zinc-100">
                                                {{ $supplier->supplier_name }}
                                            </span>
                                        </div>
                                    </td>

                                    {{-- Nota Count --}}
                                    <td class="py-3 px-3 text-center border-y border-slate-100 dark:border-zinc-800">
                                        <span class="text-xs font-black {{ $sortBy === 'nota_dicetak' ? 'text-[#58cc02]' : 'text-slate-500 dark:text-zinc-400' }}">
                                            {{ $supplier->nota_dicetak }}x Nota
                                        </span>
                                    </td>

                                    {{-- Volume --}}
                                    <td class="py-3 px-3 text-center border-y border-slate-100 dark:border-zinc-800">
                                        <span class="inline-flex items-center gap-1 text-xs font-black px-2.5 py-1 rounded-full {{ $sortBy === 'total_kubikasi' ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300' : 'text-slate-500 dark:text-zinc-400' }}">
                                            {{ number_format($supplier->total_kubikasi, 4, ',', '.') }} m³
                                        </span>
                                    </td>

                                    {{-- Total Pembelian --}}
                                    <td class="py-3 px-4 text-right border-y border-slate-100 dark:border-zinc-800">
                                        <span class="font-black text-sm md:text-base {{ $sortBy === 'total_pembelian' ? 'text-[#58cc02]' : 'text-slate-600 dark:text-zinc-300' }}">
                                            Rp {{ number_format($supplier->total_pembelian, 0, ',', '.') }}
                                        </span>
                                    </td>

                                    {{-- Chevron --}}
                                    <td class="py-3 px-2 rounded-r-2xl border-y border-r border-slate-100 dark:border-zinc-800">
                                        <svg class="lb-chevron w-5 h-5 ml-auto text-slate-300 dark:text-zinc-600 group-hover:text-[#58cc02]" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                            <path d="m9 18 6-6-6-6" />
                                        </svg>
                                    </td>
                                </tr>
                                @endforeach
                        </tbody>
                    </table>
                </div>

                @if ($hasMore || $showAll)
                <div class="mt-4 pt-4 border-t border-slate-100 dark:border-zinc-800 text-center">
                    <button wire:click="toggleShowAll"
                        class="inline-flex items-center gap-2 px-6 py-2.5 rounded-xl font-extrabold text-xs bg-slate-100 dark:bg-zinc-800 text-slate-600 dark:text-zinc-300 hover:bg-slate-200 dark:hover:bg-zinc-700 transition-all">
                        @if ($showAll)
                        <span>Sembunyikan (Tampilkan 20 Teratas)</span>
                        @else
                        <span>Tampilkan Semua ({{ $totalCount }} Supplier)</span>
                        @endif
                    </button>
                </div>
                @endif
                @endif
            </div>

        </div>

        {{-- Overlay --}}
        <div id="lb-overlay" class="fixed inset-0 z-40 bg-slate-900/30 dark:bg-black/60 backdrop-blur-sm" onclick="lbCloseDrawer()" aria-hidden="true"></div>

        {{-- Slide-Over Drawer --}}
        <div id="lb-drawer"
            class="fixed inset-y-0 right-0 z-50 w-screen max-w-2xl flex flex-col bg-white dark:bg-zinc-900 shadow-2xl"
            role="dialog" aria-modal="true">

            <div id="lb-drawer-body" class="flex-1 overflow-y-auto p-6 sm:p-8">

                <div class="flex items-start justify-between pb-6 border-b-2 border-slate-100 dark:border-zinc-800 mb-6">
                    <div class="flex items-center gap-4">
                        <div class="w-16 h-16 rounded-full bg-slate-100 dark:bg-zinc-800 border-2 border-slate-200 dark:border-zinc-700 overflow-hidden flex-shrink-0">
                            <img id="lb-drawer-avatar" src="" alt="avatar" class="w-full h-full object-cover" />
                        </div>
                        <div>
                            <h3 id="lb-drawer-name" class="text-2xl font-black text-slate-800 dark:text-white leading-tight mb-1">—</h3>
                            <span class="inline-flex items-center gap-1 text-xs font-bold text-[#58cc02] dark:text-[#7ae629] bg-[#ddf4c5] dark:bg-[#1f3f0e] px-2.5 py-0.5 rounded-md">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                    <path d="M9 12l2 2 4-4m6 2a9 9 0 1 1-18 0 9 9 0 0 1 18 0z" />
                                </svg>
                                Mitra Terverifikasi
                            </span>
                        </div>
                    </div>
                    <button onclick="lbCloseDrawer()" class="p-2 rounded-full bg-slate-100 dark:bg-zinc-800 hover:bg-slate-200 dark:hover:bg-zinc-700 text-slate-500 dark:text-zinc-400">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path d="M18 6 6 18M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div class="grid grid-cols-3 gap-3 mb-8">
                    <div class="bg-slate-50 dark:bg-zinc-800/50 border-2 border-slate-100 dark:border-zinc-700/50 p-3.5 rounded-2xl text-center">
                        <span class="block text-[11px] text-slate-400 dark:text-zinc-500 font-extrabold tracking-wider uppercase mb-1">Total Belanja</span>
                        <span id="lb-drawer-total" class="text-base sm:text-lg font-black text-slate-800 dark:text-zinc-100">—</span>
                    </div>
                    <div class="bg-slate-50 dark:bg-zinc-800/50 border-2 border-slate-100 dark:border-zinc-700/50 p-3.5 rounded-2xl text-center">
                        <span class="block text-[11px] text-slate-400 dark:text-zinc-500 font-extrabold tracking-wider uppercase mb-1">Nota Tercetak</span>
                        <span id="lb-drawer-nota" class="text-base sm:text-lg font-black text-slate-800 dark:text-zinc-100">—</span>
                    </div>
                    <div class="bg-emerald-50 dark:bg-emerald-950/40 border-2 border-emerald-200 dark:border-emerald-900/60 p-3.5 rounded-2xl text-center">
                        <span class="block text-[11px] text-emerald-700 dark:text-emerald-400 font-extrabold tracking-wider uppercase mb-1">Total Kubikasi</span>
                        <span id="lb-drawer-kubikasi" class="text-base sm:text-lg font-black text-emerald-600 dark:text-emerald-400">—</span>
                    </div>
                </div>

                <div class="flex items-center justify-between mb-4">
                    <h4 class="text-xs font-extrabold text-slate-700 dark:text-zinc-200 uppercase tracking-wider flex items-center gap-2">
                        <svg class="w-4 h-4 text-slate-400 dark:text-zinc-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                            <polyline points="14 2 14 8 20 8" />
                        </svg>
                        Riwayat Nota Pembelian
                    </h4>
                    <span id="lb-drawer-invoice-count" class="text-xs font-bold text-slate-400 dark:text-zinc-500"></span>
                </div>

                <div class="rounded-2xl border-2 border-slate-100 dark:border-zinc-800 overflow-hidden">
                    <table class="w-full text-left">
                        <thead>
                            <tr class="bg-slate-50 dark:bg-zinc-800/80 text-slate-400 dark:text-zinc-500 font-extrabold text-xs uppercase tracking-wider border-b-2 border-slate-100 dark:border-zinc-800">
                                <th class="py-3 px-4">Tanggal</th>
                                <th class="py-3 px-4">No. Nota</th>
                                <th class="py-3 px-3 text-center">Volume</th>
                                <th class="py-3 px-4 text-right">Nominal</th>
                            </tr>
                        </thead>
                        <tbody id="lb-drawer-invoices" class="divide-y-2 divide-slate-100 dark:divide-zinc-800 text-sm">
                            <tr>
                                <td colspan="4" class="py-8 text-center text-slate-400 font-bold">Memuat data…</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div id="lb-drawer-more-wrapper" class="mt-4 text-center hidden">
                    <button onclick="lbToggleShowAllInvoices()" id="lb-drawer-more-btn"
                        class="px-5 py-2 rounded-xl text-xs font-extrabold bg-slate-100 dark:bg-zinc-800 text-slate-600 dark:text-zinc-300 hover:bg-slate-200 dark:hover:bg-zinc-700 transition-all">
                        Lihat Semua Nota
                    </button>
                </div>

            </div>
        </div>

        {{-- JavaScript --}}
    </div>
</x-filament-panels::page>

@push('scripts')
<script>
    (function() {
        /* Konfeti */
        (function spawnConfetti() {
            const container = document.getElementById('lb-confetti');
            if (!container) return;
            const colors = ['#58cc02', '#ffc800', '#ce82ff', '#ff4b4b', '#1cb0f6'];
            for (let i = 0; i < 35; i++) {
                const el = document.createElement('div');
                el.className = 'confetti-piece';
                Object.assign(el.style, {
                    position: 'absolute',
                    top: '-20px',
                    left: Math.random() * 100 + '%',
                    width: '10px',
                    height: '20px',
                    borderRadius: '3px',
                    backgroundColor: colors[Math.floor(Math.random() * colors.length)],
                    animationDuration: (Math.random() * 2 + 1.5) + 's',
                    animationDelay: (Math.random() * 0.5) + 's',
                    opacity: 0,
                });
                container.appendChild(el);
            }
            setTimeout(() => {
                container.style.transition = 'opacity 1s';
                container.style.opacity = '0';
            }, 2000);
            setTimeout(() => {
                container.remove();
            }, 3000);
        })();

        /* Helpers */
        function formatRupiah(number) {
            return new Intl.NumberFormat('id-ID', {
                style: 'currency',
                currency: 'IDR',
                minimumFractionDigits: 0
            }).format(number);
        }

        function formatDate(dateStr) {
            if (!dateStr || dateStr === '-') return '-';
            return new Date(dateStr).toLocaleDateString('id-ID', {
                year: 'numeric',
                month: 'short',
                day: 'numeric'
            });
        }

        function fmtYMD(d) {
            return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
        }

        function fmtLabel(d) {
            return d.toLocaleDateString('id-ID', {
                day: 'numeric',
                month: 'short',
                year: 'numeric'
            });
        }

        function statusLabel(status) {
            const map = {
                hutang: {
                    label: 'Hutang',
                    color: '#ef4444'
                },
                cicilan: {
                    label: 'Cicilan',
                    color: '#f59e0b'
                },
                lunas: {
                    label: 'Lunas',
                    color: '#58cc02'
                },
            };
            const s = map[status] || {
                label: status,
                color: '#94a3b8'
            };
            return `<span style="color:${s.color};font-weight:800;font-size:11px;">${s.label}</span>`;
        }

        /* Date Range Picker State */
        let pickerOpen = false;
        let calYear = new Date().getFullYear();
        let calMonth = new Date().getMonth();
        let selectStart = null;
        let selectEnd = null;
        let selectHover = null;
        let appliedDari = '';
        let appliedSampai = '';

        const DOW_LABELS = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'];
        const MONTHS_ID = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

        function presetRanges() {
            const now = new Date();
            const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
            const yd = new Date(today);
            yd.setDate(yd.getDate() - 1);
            const wStart = new Date(today);
            const dayIndex = today.getDay(); // 0 = Minggu, 1 = Senin, ... 6 = Sabtu
            const offsetToMonday = dayIndex === 0 ? 6 : dayIndex - 1;
            wStart.setDate(today.getDate() - offsetToMonday);
            const mStart = new Date(today.getFullYear(), today.getMonth(), 1);
            const mEnd = new Date(today.getFullYear(), today.getMonth() + 1, 0);
            const lmS = new Date(today.getFullYear(), today.getMonth() - 1, 1);
            const lmE = new Date(today.getFullYear(), today.getMonth(), 0);
            const yStart = new Date(today.getFullYear(), 0, 1);
            const yEnd = new Date(today.getFullYear(), 11, 31);
            return {
                today: {
                    dari: today,
                    sampai: today
                },
                yesterday: {
                    dari: yd,
                    sampai: yd
                },
                this_week: {
                    dari: wStart,
                    sampai: today
                },
                this_month: {
                    dari: mStart,
                    sampai: mEnd
                },
                last_month: {
                    dari: lmS,
                    sampai: lmE
                },
                this_year: {
                    dari: yStart,
                    sampai: yEnd
                },
            };
        }

        function lbRenderCalendar() {
            const titleEl = document.getElementById('lb-cal-title');
            const gridEl = document.getElementById('lb-cal-grid');
            titleEl.textContent = MONTHS_ID[calMonth] + ' ' + calYear;

            const dow = DOW_LABELS.map(d => `<div class="lb-cal-dow">${d}</div>`).join('');
            const firstDay = new Date(calYear, calMonth, 1).getDay();
            const daysInMonth = new Date(calYear, calMonth + 1, 0).getDate();
            const today = new Date();
            today.setHours(0, 0, 0, 0);

            let cells = dow;
            for (let i = 0; i < firstDay; i++) cells += `<div class="lb-cal-day lb-empty"></div>`;

            for (let d = 1; d <= daysInMonth; d++) {
                const thisDate = new Date(calYear, calMonth, d);
                const isToday = thisDate.getTime() === today.getTime();

                let rangeStart = selectStart;
                let rangeEnd = selectEnd || selectHover;
                if (rangeStart && rangeEnd && rangeStart > rangeEnd) {
                    [rangeStart, rangeEnd] = [rangeEnd, rangeStart];
                }

                let cls = 'lb-cal-day';
                if (isToday) cls += ' lb-today';
                if (rangeStart && thisDate.getTime() === rangeStart.getTime()) cls += ' lb-selected-start';
                if (rangeEnd && thisDate.getTime() === rangeEnd.getTime()) cls += ' lb-selected-end';
                if (rangeStart && rangeEnd && thisDate > rangeStart && thisDate < rangeEnd) cls += ' lb-in-range';

                cells += `<div class="${cls}" onclick="lbPickDay('${fmtYMD(thisDate)}')" onmouseenter="lbHoverDay('${fmtYMD(thisDate)}')">${d}</div>`;
            }

            gridEl.innerHTML = cells;
            lbUpdateRangeLabel();
            document.getElementById('lb-apply-btn').disabled = !(selectStart && selectEnd);
        }

        function lbUpdateRangeLabel() {
            const el = document.getElementById('lb-range-label');
            if (!selectStart) {
                el.textContent = 'Pilih tanggal mulai';
                return;
            }
            if (!selectEnd) {
                el.textContent = fmtLabel(selectStart) + ' → ...';
                return;
            }
            let s = selectStart,
                e = selectEnd;
            if (s > e)[s, e] = [e, s];
            el.textContent = fmtLabel(s) + ' → ' + fmtLabel(e);
        }

        window.lbTogglePicker = function(e) {
            e.stopPropagation();
            pickerOpen = !pickerOpen;
            const pop = document.getElementById('lb-date-popover');
            pop.classList.toggle('lb-open', pickerOpen);
            if (pickerOpen) lbRenderCalendar();
        };

        document.addEventListener('click', function(e) {
            const wrapper = document.getElementById('lb-date-wrapper');
            if (wrapper && !wrapper.contains(e.target)) {
                pickerOpen = false;
                document.getElementById('lb-date-popover').classList.remove('lb-open');
            }
        });

        window.lbNavMonth = function(dir) {
            calMonth += dir;
            if (calMonth < 0) {
                calMonth = 11;
                calYear--;
            }
            if (calMonth > 11) {
                calMonth = 0;
                calYear++;
            }
            lbRenderCalendar();
        };

        window.lbPickDay = function(ymd) {
            // Mencegah timezone shift dengan menambahkan jam lokal T00:00:00
            const d = new Date(ymd + 'T00:00:00');
            if (!selectStart || (selectStart && selectEnd)) {
                selectStart = d;
                selectEnd = null;
                document.querySelectorAll('.lb-preset-btn').forEach(b => b.classList.remove('lb-preset-active'));
            } else {
                if (d < selectStart) {
                    selectEnd = selectStart;
                    selectStart = d;
                } else {
                    selectEnd = d;
                }
            }
            lbRenderCalendar();
        };

        window.lbHoverDay = function(ymd) {
            if (selectStart && !selectEnd) {
                selectHover = new Date(ymd + 'T00:00:00');
                lbRenderCalendar();
            }
        };

        window.lbApplyPreset = function(preset) {
            const ranges = presetRanges();
            if (!ranges[preset]) return;
            selectStart = ranges[preset].dari;
            selectEnd = ranges[preset].sampai;
            selectHover = null;
            calYear = selectStart.getFullYear();
            calMonth = selectStart.getMonth();
            document.querySelectorAll('.lb-preset-btn').forEach(b => b.classList.remove('lb-preset-active'));
            const btn = document.querySelector(`.lb-preset-btn[data-preset="${preset}"]`);
            if (btn) btn.classList.add('lb-preset-active');
            lbRenderCalendar();
        };

        function getLivewireComponent() {
            if (typeof Livewire === 'undefined') return null;
            return Livewire.find(@js($this->getId()));
        }

        let lbFilterBusy = false;

        /* Mengirimkan filter tanggal secara atomik ke Livewire */
        window.lbCommitFilter = function() {
            if (!selectStart || !selectEnd || lbFilterBusy) return;
            let s = selectStart,
                e = selectEnd;
            if (s > e)[s, e] = [e, s];
            appliedDari = fmtYMD(s);
            appliedSampai = fmtYMD(e);

            const label = fmtLabel(s) === fmtLabel(e) ? fmtLabel(s) : fmtLabel(s) + ' – ' + fmtLabel(e);
            document.getElementById('lb-trigger-label').textContent = label;
            document.getElementById('lb-date-trigger').classList.add('lb-active');
            document.getElementById('lb-date-reset').classList.add('lb-visible');

            pickerOpen = false;
            document.getElementById('lb-date-popover').classList.remove('lb-open');

            const lw = getLivewireComponent();
            if (!lw) return;

            const applyBtn = document.getElementById('lb-apply-btn');
            const originalLabel = applyBtn.textContent;
            lbFilterBusy = true;
            applyBtn.disabled = true;
            applyBtn.textContent = 'Menerapkan...';

            // Panggilan method Livewire v3 lewat proxy mengembalikan Promise,
            // jadi kita bisa tahu persis kapan request selesai (sukses/gagal).
            Promise.resolve(lw.applyDateFilter(appliedDari, appliedSampai))
                .finally(function() {
                    lbFilterBusy = false;
                    applyBtn.disabled = false;
                    applyBtn.textContent = originalLabel;
                });
        };

        window.lbResetFilter = function() {
            if (lbFilterBusy) return;
            appliedDari = '';
            appliedSampai = '';
            selectStart = null;
            selectEnd = null;
            selectHover = null;
            document.getElementById('lb-trigger-label').textContent = 'Semua Periode';
            document.getElementById('lb-date-trigger').classList.remove('lb-active');
            document.getElementById('lb-date-reset').classList.remove('lb-visible');
            document.querySelectorAll('.lb-preset-btn').forEach(b => b.classList.remove('lb-preset-active'));

            const lw = getLivewireComponent();
            if (!lw) return;

            const resetBtn = document.getElementById('lb-date-reset');
            lbFilterBusy = true;
            resetBtn.style.opacity = '0.5';
            resetBtn.style.pointerEvents = 'none';

            Promise.resolve(lw.applyDateFilter('', ''))
                .finally(function() {
                    lbFilterBusy = false;
                    resetBtn.style.opacity = '';
                    resetBtn.style.pointerEvents = '';
                });
        };

        /* Drawer Fetching via Native Livewire Async Promise */
        const drawer = document.getElementById('lb-drawer');
        const overlay = document.getElementById('lb-overlay');
        let lbActiveSupplierId = null;

        function lbFetchInvoices(supplierId) {
            document.getElementById('lb-drawer-invoices').innerHTML =
                '<tr><td colspan="4" class="py-8 text-center text-slate-400 dark:text-zinc-500 font-bold">Memuat data…</td></tr>';

            fetch(`/internal/supplier-detail/${supplierId}`, {
                    headers: {
                        'Accept': 'application/json'
                    }
                })
                .then(function(res) {
                    if (!res.ok) throw new Error('HTTP ' + res.status);
                    return res.json();
                })
                .then(function(detail) {
                    document.getElementById('lb-drawer-total').textContent = formatRupiah(detail.total_pembelian);
                    document.getElementById('lb-drawer-nota').textContent = detail.nota_dicetak + 'x';
                    document.getElementById('lb-drawer-kubikasi').textContent = detail.total_kubikasi ? Number(detail.total_kubikasi).toFixed(4) + ' m³' : '0 m³';

                    const tbody = document.getElementById('lb-drawer-invoices');
                    if (!detail.invoices || detail.invoices.length === 0) {
                        tbody.innerHTML = '<tr><td colspan="4" class="py-8 text-center text-slate-400 font-bold">Tidak ada nota pada periode ini.</td></tr>';
                        return;
                    }

                    tbody.innerHTML = detail.invoices.map(function(inv) {
                        return `
                <tr class="lb-invoice-row hover:bg-slate-50 dark:hover:bg-zinc-800/40">
                    <td class="py-3.5 px-3 font-bold text-slate-500 dark:text-zinc-400 text-xs whitespace-nowrap">
                        ${formatDate(inv.tanggal)}
                    </td>
                    <td class="py-3.5 px-3">
                        <span class="lb-invoice-nota font-extrabold text-xs text-[#4b4b4b] dark:text-zinc-200">
                            ${inv.nomor_nota || '-'}
                        </span>
                    </td>
                    <td class="py-3.5 px-2 text-center font-black text-xs text-emerald-600 dark:text-emerald-400">
                        ${inv.kubikasi ? Number(inv.kubikasi).toFixed(4) + ' m³' : '-'}
                    </td>
                    <td class="py-3.5 px-3 text-right">
                        <div class="font-extrabold text-xs text-[#58cc02] dark:text-[#7ae629]">${formatRupiah(inv.grand_total)}</div>
                        <div class="mt-0.5">${statusLabel(inv.status)}</div>
                    </td>
                </tr>
                `;
                    }).join('');
                })
                .catch(function() {
                    document.getElementById('lb-drawer-invoices').innerHTML =
                        '<tr><td colspan="4" class="py-8 text-center text-red-400 font-bold">Gagal memuat data detail supplier.</td></tr>';
                });
        }

        window.lbOpenDrawer = function(supplierId, supplierName) {
            lbActiveSupplierId = supplierId;
            document.getElementById('lb-drawer-name').textContent = supplierName;
            document.getElementById('lb-drawer-avatar').src =
                `https://api.dicebear.com/9.x/lorelei/svg?seed=${encodeURIComponent(supplierName)}&backgroundColor=transparent`;
            document.getElementById('lb-drawer-total').textContent = '—';
            document.getElementById('lb-drawer-nota').textContent = '—';
            document.getElementById('lb-drawer-kubikasi').textContent = '—';

            drawer.classList.add('lb-drawer--open');
            overlay.classList.add('lb-overlay--visible');
            document.body.style.overflow = 'hidden';

            lbFetchInvoices(supplierId);
        };

        window.lbCloseDrawer = function() {
            drawer.classList.remove('lb-drawer--open');
            overlay.classList.remove('lb-overlay--visible');
            document.body.style.overflow = '';
        };

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                if (pickerOpen) {
                    pickerOpen = false;
                    document.getElementById('lb-date-popover').classList.remove('lb-open');
                } else {
                    lbCloseDrawer();
                }
            }
        });
    })();
</script>
@endpush