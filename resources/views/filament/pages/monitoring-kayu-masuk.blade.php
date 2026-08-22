<x-filament-panels::page>
    <style>
        /* Reset border-radius untuk tampilan ala Shadcn UI / Filament Minimalis */
        .fi-main,
        .fi-main *,
        button,
        input,
        select,
        table,
        th,
        td {
            border-radius: 0 !important;
        }

        [x-cloak] {
            display: none !important;
        }

        /* Animasi Transisi Halus & Tipis saat Membuka Preview Accordion */
        .accordion-row-enter {
            animation: subtleFadeDown 0.22s cubic-bezier(0.16, 1, 0.3, 1) forwards;
            transform-origin: top;
        }

        @keyframes subtleFadeDown {
            from {
                opacity: 0;
                transform: translateY(-6px) scaleY(0.98);
            }

            to {
                opacity: 1;
                transform: translateY(0) scaleY(1);
            }
        }

        /* Animasi Transisi Halus Centered Loading Card */
        .loading-card-enter {
            animation: loadingPopIn 0.22s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        }

        @keyframes loadingPopIn {
            from {
                opacity: 0;
                transform: scale(0.92) translateY(6px);
            }

            to {
                opacity: 1;
                transform: scale(1) translateY(0);
            }
        }
    </style>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

    <div
        class="space-y-4 font-sans text-xs"
        x-data="{
            currentDateTime: '',
            showAdvancedFilters: false,
            updateTime() {
                const now = new Date();
                this.currentDateTime = now.toLocaleDateString('id-ID', {
                    weekday: 'short', day: 'numeric', month: 'short', year: 'numeric'
                }) + ' - ' + now.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
            },
            initFlatpickr() {
                flatpickr($refs.dateRangeInput, {
                    mode: 'range',
                    dateFormat: 'Y-m-d',
                    altInput: true,
                    altFormat: 'j M Y',
                    onChange: (selectedDates) => {
                        if (selectedDates.length === 2) {
                            $wire.set('dariTanggal', selectedDates[0].toISOString().split('T')[0]);
                            $wire.set('sampaiTanggal', selectedDates[1].toISOString().split('T')[0]);
                        } else if (selectedDates.length === 0) {
                            $wire.set('dariTanggal', null);
                            $wire.set('sampaiTanggal', null);
                        }
                    }
                });
            }
        }"
        x-init="updateTime(); setInterval(() => updateTime(), 1000); $nextTick(() => initFlatpickr())">

        <!-- NAVBAR TOOLBAR UTAMA -->
        <div class="bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 p-3 shadow-xs space-y-3 font-sans">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex flex-wrap items-center gap-3 flex-1">

                    <!-- 1. Input Pencarian -->
                    <div class="relative min-w-[200px] flex-1 sm:flex-initial">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none text-zinc-400">
                            <x-heroicon-m-magnifying-glass class="w-4 h-4" />
                        </span>
                        <input type="text"
                            wire:model.live.debounce.300ms="search"
                            placeholder="Cari Seri, No Nota, Supplier, Nopol..."
                            class="w-full pl-9 pr-3 py-1.5 text-xs font-sans bg-zinc-50 dark:bg-zinc-800 border border-zinc-300 dark:border-zinc-700 text-zinc-900 dark:text-zinc-100 focus:outline-none focus:border-amber-600 transition">
                    </div>

                    <!-- 2. Rentang Tanggal Flatpickr -->
                    <div class="relative min-w-[180px]" wire:ignore>
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none text-zinc-400">
                            <x-heroicon-m-calendar class="w-4 h-4" />
                        </span>
                        <input type="text"
                            x-ref="dateRangeInput"
                            placeholder="Rentang Tanggal..."
                            class="w-full pl-9 pr-3 py-1.5 text-xs font-sans bg-zinc-50 dark:bg-zinc-800 border border-zinc-300 dark:border-zinc-700 text-zinc-900 dark:text-zinc-100 focus:outline-none focus:border-amber-600 cursor-pointer transition">
                    </div>

                    <!-- 3. FILTER BULAN -->
                    <select wire:model.live="bulan"
                        class="px-3 py-1.5 text-xs font-sans bg-zinc-50 dark:bg-zinc-800 border border-zinc-300 dark:border-zinc-700 text-zinc-900 dark:text-zinc-100 focus:outline-none focus:border-amber-600 font-semibold cursor-pointer">
                        <option value="ALL">Semua Bulan</option>
                        @foreach(['01'=>'Januari','02'=>'Februari','03'=>'Maret','04'=>'April','05'=>'Mei','06'=>'Juni','07'=>'Juli','08'=>'Agustus','09'=>'September','10'=>'Oktober','11'=>'November','12'=>'Desember'] as $num => $nama)
                        <option value="{{ $num }}">{{ $nama }}</option>
                        @endforeach
                    </select>

                    <!-- 4. Tombol Toggle Filter Opsi Lainnya -->
                    <button @click="showAdvancedFilters = !showAdvancedFilters" type="button"
                        :class="showAdvancedFilters || {{ $this->hasActiveAdvancedFilters ? 'true' : 'false' }}
                            ? 'bg-amber-700 text-white border-amber-700 shadow-xs'
                            : 'bg-zinc-50 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300 border-zinc-300 dark:border-zinc-700 hover:bg-zinc-100 dark:hover:bg-zinc-700'"
                        class="px-3 py-1.5 text-xs font-sans font-semibold border flex items-center gap-2 transition">
                        <x-heroicon-m-funnel class="w-3.5 h-3.5" />
                        <span>Filter Opsi</span>
                        @if($this->hasActiveAdvancedFilters)
                        <span class="w-1.5 h-1.5 bg-amber-400"></span>
                        @endif
                    </button>
                </div>

                <div class="text-xs font-sans text-zinc-500 dark:text-zinc-400">
                    Total: <span class="font-bold text-amber-600 dark:text-amber-400">{{ $this->monitoringData->total() }}</span> Data Kayu Masuk
                </div>
            </div>

            <!-- Panel Filter Tambahan (Dropdowns) -->
            <div x-show="showAdvancedFilters" x-cloak x-transition.opacity.duration.200ms
                class="pt-3 border-t border-zinc-200 dark:border-zinc-800 flex flex-wrap items-center gap-3">
                <div class="text-xs font-sans font-bold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider flex items-center gap-1 mr-1">
                    <x-heroicon-m-adjustments-horizontal class="w-3.5 h-3.5" />
                    <span>Filter Tambahan:</span>
                </div>

                <select wire:model.live="tahun"
                    class="px-3 py-1.5 text-xs font-sans bg-zinc-50 dark:bg-zinc-800 border border-zinc-300 dark:border-zinc-700 text-zinc-900 dark:text-zinc-100 focus:outline-none focus:border-amber-600 font-semibold cursor-pointer">
                    @for($y = now()->year; $y >= now()->year - 3; $y--)
                    <option value="{{ $y }}">{{ $y }}</option>
                    @endfor
                </select>

                <select wire:model.live="statusLogistik"
                    class="px-3 py-1.5 text-xs font-sans bg-zinc-50 dark:bg-zinc-800 border border-zinc-300 dark:border-zinc-700 text-zinc-900 dark:text-zinc-100 focus:outline-none focus:border-amber-600 font-semibold cursor-pointer">
                    <option value="ALL">Semua Status Logistik</option>
                    <option value="BELUM_DIAPA_APAIN">Belum Turun / Belum Turus</option>
                    <option value="SELESAI_TURUS">Selesai Turus (Belum Ada Nota)</option>
                    <option value="DICETAK_BELUM_LUNAS">Dicetak (Belum Lunas)</option>
                    <option value="DICETAK_SUDAH_LUNAS">Dicetak (Sudah Lunas)</option>
                </select>

                <select wire:model.live="supplierId"
                    class="px-3 py-1.5 text-xs font-sans bg-zinc-50 dark:bg-zinc-800 border border-zinc-300 dark:border-zinc-700 text-zinc-900 dark:text-zinc-100 focus:outline-none focus:border-amber-600 font-semibold cursor-pointer">
                    <option value="ALL">Semua Supplier</option>
                    @foreach($this->suppliers as $sup)
                    <option value="{{ $sup->id }}">{{ $sup->nama_supplier }}</option>
                    @endforeach
                </select>

                <!-- TOMBOL RESET FILTER -->
                @if($search !== '' || $statusLogistik !== 'ALL' || $supplierId !== 'ALL' || $dariTanggal || $sampaiTanggal || $bulan !== 'ALL')
                <button wire:click="resetFilters" type="button"
                    class="text-xs font-sans text-zinc-700 dark:text-zinc-300 hover:text-zinc-900 dark:hover:text-white font-semibold px-2.5 py-1 flex items-center gap-1.5 border border-zinc-300 dark:border-zinc-700 bg-zinc-100 dark:bg-zinc-800 hover:bg-zinc-200 dark:hover:bg-zinc-700 transition ml-auto">
                    <x-heroicon-m-arrow-path class="w-3.5 h-3.5 text-zinc-500 dark:text-zinc-400" />
                    <span>Reset Filter</span>
                </button>
                @endif
            </div>
        </div>

        <!-- CONTAINER UTAMA TABEL DENGAN SCROLL VERTICAL & HORIZONTAL -->
        <div class="relative bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 shadow-xs font-sans flex flex-col">

            <!-- LOADING OVERLAY -->
            <div wire:loading.delay.default
                wire:target="search, statusLogistik, supplierId, dariTanggal, sampaiTanggal, bulan, tahun, resetFilters, toggleRow"
                class="absolute inset-0 z-40 flex items-center justify-center bg-white/80 dark:bg-zinc-900/80 backdrop-blur-[2px] transition-all">
                <div class="loading-card-enter flex items-center gap-3 bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 px-5 py-3 shadow-xl">
                    <svg class="animate-spin h-6 w-6 text-amber-600 dark:text-amber-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3.5"></circle>
                        <path class="opacity-90" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                    <span class="text-xs font-sans font-bold text-zinc-800 dark:text-zinc-200 tracking-wide">
                        Memuat data...
                    </span>
                </div>
            </div>

            <!-- AREA SCROLLABLE TABEL -->
            <div class="overflow-x-auto overflow-y-auto" style="max-height: calc(100vh - 290px);">
                <table class="w-full text-left text-xs border-collapse font-sans">
                    <!-- STICKY HEADER -->
                    <thead class="sticky top-0 z-20 bg-zinc-100 dark:bg-zinc-800 border-b border-zinc-200 dark:border-zinc-700 text-zinc-600 dark:text-zinc-300 font-bold uppercase tracking-wider shadow-xs">
                        <tr>
                            <th class="py-3 px-3 text-center w-8 bg-zinc-100 dark:bg-zinc-800">#</th>
                            <th class="py-3 px-4 bg-zinc-100 dark:bg-zinc-800">Seri & Tanggal</th>
                            <th class="py-3 px-4 bg-zinc-100 dark:bg-zinc-800">Kendaraan & Supplier</th>
                            @if($showDokumenCol)
                            <th class="py-3 px-4 bg-zinc-100 dark:bg-zinc-800">Dokumen Transport</th>
                            @endif
                            <th class="py-3 px-4 bg-zinc-100 dark:bg-zinc-800">Status Turun Kayu</th>
                            <th class="py-3 px-4 bg-zinc-100 dark:bg-zinc-800">Status Turus Kayu</th>
                            <th class="py-3 px-4 bg-zinc-100 dark:bg-zinc-800">Nota Kayu & Status Pelunasan</th>
                            <th class="py-3 px-4 text-center bg-zinc-100 dark:bg-zinc-800">Cek Nota</th>
                        </tr>
                    </thead>

                    <!-- DATA UTAMA TABLE BODY -->
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800 border-b border-zinc-200 dark:border-zinc-800">
                        @forelse($this->monitoringData as $item)
                        @php
                        $isTurunSelesai = (bool)$item->has_turun;
                        $hasTurus = (bool)$item->has_turus;
                        $nota = $item->notaKayu;
                        $isExpanded = $expandedRow === $item->id;
                        @endphp

                        <!-- BARIS UTAMA -->
                        <tr wire:click="toggleRow({{ $item->id }})" wire:key="row-{{ $item->id }}"
                            class="cursor-pointer select-none hover:bg-amber-50/60 dark:hover:bg-zinc-800/80 transition-colors duration-150 {{ $isExpanded ? 'bg-amber-50/80 dark:bg-amber-950/30 border-l-4 border-l-amber-600' : '' }}">

                            <td class="py-3 px-3 text-center">
                                <span class="text-zinc-400 dark:text-zinc-500 font-bold text-[10px] transition-transform duration-200 inline-block {{ $isExpanded ? 'transform rotate-90 text-amber-600 dark:text-amber-400' : '' }}">
                                    ►
                                </span>
                            </td>

                            <td class="py-3 px-4">
                                <div class="font-bold text-zinc-900 dark:text-white">
                                    <span class="px-2 py-0.5 font-sans bg-zinc-100 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 text-amber-600 dark:text-amber-400 font-semibold">
                                        Seri {{ $item->seri }}
                                    </span>
                                </div>
                                <div class="text-[11px] text-zinc-500 dark:text-zinc-400 mt-1 font-sans flex items-center gap-1">
                                    <x-heroicon-m-calendar class="w-3.5 h-3.5 text-zinc-400" />
                                    <span>{{ $item->tgl_kayu_masuk ? \Carbon\Carbon::parse($item->tgl_kayu_masuk)->translatedFormat('d M Y') : '-' }}</span>
                                </div>
                            </td>

                            <td class="py-3 px-4">
                                <div class="font-sans font-bold text-zinc-900 dark:text-zinc-100">
                                    {{ $item->penggunaanKendaraanSupplier?->jenis_kendaraan ?? '-' }}
                                    - {{ $item->penggunaanKendaraanSupplier?->nopol_kendaraan ?? '-' }}
                                </div>
                                <div class="text-[11px] text-zinc-500 dark:text-zinc-400 mt-0.5">
                                    <span class="font-semibold text-amber-600 dark:text-amber-300">{{ $item->penggunaanSupplier?->nama_supplier ?? '-' }}</span>
                                </div>
                            </td>

                            @if($showDokumenCol)
                            <td class="py-3 px-4">
                                <span class="inline-flex items-center text-[11px] font-semibold px-2 py-0.5 bg-zinc-100 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300 border border-zinc-200 dark:border-zinc-700">
                                    {{ $item->jenis_dokumen_angkut ?? '-' }}
                                </span>
                            </td>
                            @endif

                            <td class="py-3 px-4">
                                @if($isTurunSelesai)
                                <span class="inline-flex items-center gap-1 text-[11px] font-bold text-emerald-800 dark:text-emerald-300 bg-emerald-50 dark:bg-emerald-950/80 border border-emerald-300 dark:border-emerald-800 px-2 py-0.5">
                                    <span class="w-1.5 h-1.5 bg-emerald-500 rounded-full"></span>
                                    Selesai Turun
                                </span>
                                @else
                                <span class="inline-flex items-center gap-1 text-[11px] text-zinc-500 dark:text-zinc-400 bg-zinc-100 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 px-2 py-0.5 font-medium">
                                    <span class="w-1.5 h-1.5 bg-zinc-400 rounded-full"></span>
                                    Belum Turun
                                </span>
                                @endif
                            </td>

                            <td class="py-3 px-4">
                                @if(!$isTurunSelesai)
                                <span class="inline-flex items-center text-[11px] text-zinc-400 dark:text-zinc-500 bg-zinc-100 dark:bg-zinc-800/50 border border-zinc-200 dark:border-zinc-800 px-2 py-0.5 font-normal italic opacity-80" title="Menunggu proses Turun Kayu selesai">
                                    Belum Turus
                                </span>
                                @elseif($hasTurus)
                                <span class="inline-flex items-center gap-1 text-[11px] font-bold text-emerald-800 dark:text-emerald-300 bg-emerald-50 dark:bg-emerald-950/80 border border-emerald-300 dark:border-emerald-800 px-2 py-0.5">
                                    <span class="w-1.5 h-1.5 bg-emerald-500 rounded-full"></span>
                                    Selesai Turus
                                </span>
                                @else
                                <span class="inline-flex items-center gap-1 text-[11px] text-rose-700 dark:text-rose-400 bg-rose-50 dark:bg-rose-950/60 border border-rose-200 dark:border-rose-900 px-2 py-0.5 font-bold">
                                    <span class="w-1.5 h-1.5 bg-rose-500 rounded-full animate-pulse"></span>
                                    Belum Turus
                                </span>
                                @endif
                            </td>

                            <td class="py-3 px-4">
                                @if(!$isTurunSelesai || !$hasTurus)
                                <span class="inline-flex items-center text-[11px] text-zinc-400 dark:text-zinc-500 bg-zinc-100 dark:bg-zinc-800/50 border border-zinc-200 dark:border-zinc-800 px-2 py-0.5 font-normal italic opacity-80" title="Menunggu proses Turus Kayu selesai">
                                    Belum Ada Nota
                                </span>
                                @elseif(!$nota)
                                <span class="inline-flex items-center text-[11px] text-zinc-500 dark:text-zinc-400 bg-zinc-100 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 px-2 py-0.5 font-medium">
                                    Belum Ada Nota
                                </span>
                                @elseif(!$this->isSudahDiperiksa($nota->status))
                                <span class="inline-flex items-center text-[11px] font-bold text-sky-700 dark:text-sky-300 bg-sky-50 dark:bg-sky-950/80 border border-sky-200 dark:border-sky-800 px-2 py-0.5">
                                    Dibuat
                                </span>
                                @elseif($this->isLunas($nota->status_pelunasan))
                                <span class="inline-flex items-center gap-1 text-[11px] font-bold text-emerald-800 dark:text-emerald-300 bg-emerald-50 dark:bg-emerald-950/80 border border-emerald-300 dark:border-emerald-800 px-2 py-0.5">
                                    <span class="w-1.5 h-1.5 bg-emerald-500 rounded-full"></span>
                                    {{ $this->statusPelunasanLabel($nota->status_pelunasan) }}
                                </span>
                                @else
                                <span class="inline-flex items-center text-[11px] font-bold text-amber-800 dark:text-amber-300 bg-amber-50 dark:bg-amber-950/80 border border-amber-300 dark:border-amber-800 px-2 py-0.5">
                                    Dicetak ({{ $this->statusPelunasanLabel($nota->status_pelunasan) }})
                                </span>
                                @endif
                            </td>

                            <td class="py-3 px-4 text-center" @click.stop>
                                @if($nota && $this->isSudahDiperiksa($nota->status))
                                <a href="{{ route('nota-kayu.show', $nota->id) }}" target="_blank"
                                    class="inline-flex items-center gap-1 px-2.5 py-1 text-[11px] font-semibold bg-indigo-50 dark:bg-indigo-950/80 text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800 hover:bg-indigo-100 dark:hover:bg-indigo-900 transition">
                                    <x-heroicon-m-arrow-top-right-on-square class="w-3.5 h-3.5" />
                                    <span>Cek Nota</span>
                                </a>
                                @else
                                <span class="text-zinc-400 dark:text-zinc-600 text-xs italic">-</span>
                                @endif
                            </td>
                        </tr>

                        <!-- SUB-ROW DETAIL ACCORDION -->
                        @if($isExpanded)
                        @php $detail = $detailsCache[$item->id] ?? null; @endphp
                        @if($detail)
                        <tr x-data x-show="true"
                            x-transition:enter="transition ease-out duration-200"
                            x-transition:enter-start="opacity-0 -translate-y-1"
                            x-transition:enter-end="opacity-100 translate-y-0"
                            wire:key="detail-{{ $item->id }}"
                            class="bg-zinc-50 dark:bg-zinc-900/90 border-b-2 border-amber-500/40">
                            <td colspan="{{ $showDokumenCol ? 8 : 7 }}" class="p-4 bg-zinc-50/60 dark:bg-zinc-950/80">
                                <div class="space-y-4">
                                    <div class="flex flex-wrap justify-between items-center border-b border-zinc-200 dark:border-zinc-800 pb-2 gap-2">
                                        <div class="font-bold text-xs uppercase tracking-wider text-amber-700 dark:text-amber-400 flex items-center gap-1.5">
                                            <x-heroicon-m-clipboard-document-list class="w-4 h-4 text-amber-600" />
                                            <span>Preview & Detail Seri {{ $item->seri }}</span>
                                        </div>
                                        <div class="flex items-center gap-2 text-xs">
                                            <span class="px-2.5 py-1 text-[10px] font-bold border uppercase tracking-wider {{ $isTurunSelesai ? 'bg-emerald-50 text-emerald-800 border-emerald-300 dark:bg-emerald-950 dark:text-emerald-300 dark:border-emerald-800' : 'bg-zinc-100 text-zinc-600 border-zinc-300 dark:bg-zinc-800 dark:text-zinc-400 dark:border-zinc-700' }}">
                                                Turun: {{ $isTurunSelesai ? 'Selesai Turun' : 'Belum Turun' }}
                                            </span>
                                            <span class="px-2.5 py-1 text-[10px] font-bold border uppercase tracking-wider {{ $hasTurus ? 'bg-emerald-50 text-emerald-800 border-emerald-300 dark:bg-emerald-950 dark:text-emerald-300 dark:border-emerald-800' : 'bg-rose-50 text-rose-800 border-rose-300 dark:bg-rose-950 dark:text-rose-300 dark:border-rose-800' }}">
                                                Turus: {{ $hasTurus ? 'Selesai' : 'Belum Turus' }}
                                            </span>
                                        </div>
                                    </div>

                                    <div class="grid grid-cols-1 lg:grid-cols-12 gap-4 text-xs">
                                        <div class="lg:col-span-4 flex flex-col gap-4">
                                            <div class="bg-white dark:bg-zinc-900 p-4 border border-zinc-200 dark:border-zinc-800 space-y-2 flex-1 shadow-2xs">
                                                <h5 class="font-bold text-zinc-400 dark:text-zinc-400 uppercase text-[10px] tracking-wider border-b border-zinc-100 dark:border-zinc-800 pb-1">
                                                    Informasi Kendaraan & Supplier
                                                </h5>
                                                <div class="space-y-1.5 pt-1">
                                                    <div><span class="text-zinc-400">Supplier:</span> <span class="font-bold text-zinc-900 dark:text-white">{{ $item->penggunaanSupplier?->nama_supplier ?? '-' }}</span></div>
                                                    <div><span class="text-zinc-400">Kendaraan & Nopol:</span> <span class="font-sans font-bold text-indigo-600 dark:text-indigo-400">{{ $item->penggunaanKendaraanSupplier?->jenis_kendaraan ?? '-' }} - {{ $item->penggunaanKendaraanSupplier?->nopol_kendaraan ?? '-' }}</span></div>
                                                    <div><span class="text-zinc-400">Dokumen Angkut:</span> <span class="font-semibold text-amber-600 dark:text-amber-400">{{ $item->jenis_dokumen_angkut ?? '-' }}</span></div>
                                                </div>
                                            </div>

                                            <div class="bg-white dark:bg-zinc-900 p-4 border border-zinc-200 dark:border-zinc-800 space-y-2 flex-1 shadow-2xs">
                                                <h5 class="font-bold text-zinc-400 dark:text-zinc-400 uppercase text-[10px] tracking-wider border-b border-zinc-100 dark:border-zinc-800 pb-1">
                                                    Status Nota Kayu
                                                </h5>
                                                @if($nota)
                                                <div class="space-y-1.5 pt-1">
                                                    <div><span class="text-zinc-400">Tgl. Nota Dibuat:</span> <span>{{ $item->tgl_kayu_masuk ? \Carbon\Carbon::parse($item->tgl_kayu_masuk)->translatedFormat('d M Y') : '-' }}</span></div>
                                                    <div><span class="text-zinc-400">Penanggung Jawab:</span> <span class="font-semibold text-zinc-900 dark:text-white">{{ $nota->penanggung_jawab ?? '-' }}</span></div>
                                                    <div><span class="text-zinc-400">Penerima:</span> <span class="font-semibold text-zinc-900 dark:text-white">{{ $nota->penerima ?? '-' }}</span></div>
                                                    <div><span class="text-zinc-400">Satpam:</span> <span class="font-semibold text-zinc-900 dark:text-white">{{ $nota->satpam ?? '-' }}</span></div>
                                                </div>
                                                @else
                                                <div class="text-zinc-400 dark:text-zinc-500 italic pt-2">Belum ada nota kayu yang diterbitkan untuk seri ini.</div>
                                                @endif
                                            </div>
                                        </div>

                                        <div class="lg:col-span-8 bg-white dark:bg-zinc-900 p-4 border border-zinc-200 dark:border-zinc-800 space-y-2 shadow-2xs">
                                            <div class="flex justify-between items-center border-b border-zinc-100 dark:border-zinc-800 pb-2">
                                                <h5 class="font-bold text-zinc-400 dark:text-zinc-400 uppercase text-[10px] tracking-wider">
                                                    Perbandingan Detail & Turusan
                                                </h5>
                                                <span class="text-[12px] font-sans text-amber-600 dark:text-amber-400 font-semibold">
                                                    Total Hasil Turus: {{ $detail['total_batang'] }} Btg / {{ number_format($detail['total_volume'], 4) }} m³
                                                </span>
                                            </div>

                                            @php $comparison = $detail['comparison']; @endphp

                                            <div class="max-h-64 overflow-y-auto overflow-x-auto pt-1">
                                                <table class="w-full text-left text-[11px] border-collapse font-sans">
                                                    <thead>
                                                        <tr class="sticky top-0 z-10 bg-zinc-50 dark:bg-zinc-950 text-zinc-400 dark:text-zinc-400 border-b border-zinc-200 dark:border-zinc-800 font-bold uppercase text-[10px]">
                                                            <th class="py-2 px-2 text-center w-8">No</th>
                                                            <th class="py-2 px-3">Jenis Kayu & Panjang</th>
                                                            <th class="py-2 px-2 text-center">Diameter</th>
                                                            <th class="py-2 px-3 text-center">Turusan 1</th>
                                                            <th class="py-2 px-3 text-center">Turusan 2</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800/80">
                                                        @forelse($comparison as $idx => $row)
                                                        <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/40 font-sans">
                                                            <td class="py-2 px-2 text-center text-zinc-500 dark:text-zinc-400">{{ $idx + 1 }}</td>
                                                            <td class="py-2 px-3 font-sans font-semibold text-zinc-800 dark:text-zinc-200">{{ $row['panjang'] }} {{ $row['jenis_kayu'] }}</td>
                                                            <td class="py-2 px-2 text-center text-zinc-600 dark:text-zinc-300">{{ $row['diameter'] }} cm</td>
                                                            <td class="py-2 px-3 text-center text-amber-600 dark:text-amber-400 font-bold">{{ $row['turusan_1'] }} Btg</td>
                                                            <td class="py-2 px-3 text-center text-emerald-600 dark:text-emerald-400 font-bold">{{ $row['turusan_2'] }} Btg</td>
                                                        </tr>
                                                        @empty
                                                        <tr>
                                                            <td colspan="5" class="py-4 text-center text-zinc-400 dark:text-zinc-500 italic">Belum ada data detail kayu atau turusan.</td>
                                                        </tr>
                                                        @endforelse
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        @else
                        <tr>
                            <td colspan="{{ $showDokumenCol ? 8 : 7 }}" class="p-4 text-center text-rose-500 dark:text-rose-400 italic text-xs">
                                Data detail tidak ditemukan (mungkin sudah dihapus).
                            </td>
                        </tr>
                        @endif
                        @endif
                        @empty
                        <tr>
                            <td colspan="{{ $showDokumenCol ? 8 : 7 }}" class="text-center py-12 text-zinc-400 dark:text-zinc-500">
                                <x-heroicon-o-inbox class="w-12 h-12 mx-auto mb-2 text-zinc-300 dark:text-zinc-700" />
                                <p class="text-xs font-semibold">Tidak ada data kayu masuk yang sesuai filter.</p>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- STICKY FOOTER / PAGINATION -->
            <div class="sticky bottom-0 z-20 p-3 border-t border-zinc-200 dark:border-zinc-800 bg-zinc-50/95 dark:bg-zinc-900/95 backdrop-blur-xs shadow-md">
                {{ $this->monitoringData->links() }}
            </div>
        </div>
    </div>
</x-filament-panels::page>