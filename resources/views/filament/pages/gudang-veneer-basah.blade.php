<x-filament-panels::page>

    {{-- ══════════════════════════════════════════════════════════════
         PANEL ATAS — SERAH TERIMA DARI ROTARY
    ═══════════════════════════════════════════════════════════════ --}}
    @php
        $serahTerima = $this->serahTerima;
        $riwayat = $this->riwayatSerahTerima;
    @endphp

    <section class="space-y-3">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-zinc-200 dark:border-zinc-800 pb-2">
            <h2 class="text-xs font-black uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                Serah Terima dari Rotary
            </h2>

            <div class="flex border-b border-zinc-200 dark:border-zinc-800">
                <button type="button" wire:click="$set('serahTerimaTab', 'aktif')"
                    class="px-4 py-2 text-[10px] font-black uppercase tracking-widest border-b-2 -mb-px transition-all {{ $serahTerimaTab === 'aktif' ? 'border-amber-500 text-amber-600 dark:text-amber-400' : 'border-transparent text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-200' }}">
                    Aktif
                    <span class="ml-1 inline-flex items-center justify-center min-w-[1.1rem] px-1 rounded-full text-[9px] font-bold {{ $serahTerimaTab === 'aktif' ? 'bg-amber-500 text-white' : 'bg-zinc-200 dark:bg-zinc-700 text-zinc-500 dark:text-zinc-400' }}">
                        {{ $serahTerima->count() }}
                    </span>
                </button>
                <button type="button" wire:click="$set('serahTerimaTab', 'history')"
                    class="px-4 py-2 text-[10px] font-black uppercase tracking-widest border-b-2 -mb-px transition-all {{ $serahTerimaTab === 'history' ? 'border-amber-500 text-amber-600 dark:text-amber-400' : 'border-transparent text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-200' }}">
                    History
                </button>
            </div>
        </div>

        <div class="bg-white dark:bg-zinc-950 border border-zinc-200 dark:border-zinc-800 shadow-sm overflow-hidden">
            <div class="divide-y divide-zinc-100 dark:divide-zinc-800 max-h-[400px] overflow-y-auto">

                @if ($serahTerimaTab === 'aktif')
                    @forelse($serahTerima as $st)
                        @php
                            $palet = $st->detailHasilPalet;
                            $ukuran = $palet?->ukuran;
                            $kayu = $palet?->penggunaanLahan?->jenisKayu?->nama_kayu ?? '-';
                        @endphp
                        <div wire:key="basah-st-{{ $st->id }}"
                            class="px-3 sm:px-5 py-3 hover:bg-zinc-50 dark:hover:bg-zinc-900/40 transition-colors space-y-1.5">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="inline-flex items-center px-1.5 sm:px-2 py-0.5 rounded-sm text-[9px] font-black uppercase whitespace-nowrap shrink-0 bg-sky-100 text-sky-700 dark:bg-sky-900/30 dark:text-sky-400">
                                    Rotary
                                </span>
                                <span class="text-[10px] font-mono text-zinc-500 dark:text-zinc-400 whitespace-nowrap shrink-0">
                                    {{ $this->formatKodePalet($st) }}
                                </span>
                            </div>
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="text-base sm:text-sm font-black text-zinc-900 dark:text-white uppercase">
                                    {{ $kayu }}
                                </span>
                                <span class="font-mono text-sm sm:text-xs font-bold text-zinc-600 dark:text-zinc-300 tabular-nums whitespace-nowrap">
                                    {{ $ukuran?->panjang + 0 }}×{{ $ukuran?->lebar + 0 }}×{{ $ukuran?->tebal + 0 }}
                                    <span class="text-[10px] text-zinc-400">mm</span>
                                </span>
                                <span class="inline-flex items-center px-2 py-0.5 rounded-sm text-sm sm:text-xs font-black tabular-nums whitespace-nowrap bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400">
                                    {{ number_format($this->formatJumlahLembar($st)) }} Lbr
                                </span>
                            </div>
                            <div class="flex items-center justify-between gap-2 flex-wrap sm:flex-nowrap">
                                <div class="text-[10px] text-zinc-400 whitespace-nowrap">
                                    Diserahkan: {{ $st->diserahkan_oleh }}
                                </div>
                                <button type="button" wire:click="terimaRotary({{ $st->id }})"
                                    wire:confirm="Terima palet ini ke stok Gudang Veneer Basah?"
                                    wire:loading.attr="disabled" wire:target="terimaRotary({{ $st->id }})"
                                    class="shrink-0 inline-flex items-center gap-1.5 px-3 py-1.5 rounded-sm text-[11px] font-bold text-white bg-emerald-600 hover:bg-emerald-500 disabled:opacity-50 transition-colors">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                                    </svg>
                                    <span>Terima</span>
                                </button>
                            </div>
                        </div>
                    @empty
                        <div class="px-5 py-8 text-center text-xs text-zinc-400 dark:text-zinc-600">
                            Tidak ada palet dari Rotary yang menunggu diterima
                        </div>
                    @endforelse
                @else
                    @forelse($riwayat as $st)
                        @php
                            $palet = $st->detailHasilPalet;
                            $ukuran = $palet?->ukuran;
                            $kayu = $palet?->penggunaanLahan?->jenisKayu?->nama_kayu ?? '-';
                        @endphp
                        <div wire:key="basah-hist-{{ $st->id }}" class="px-3 sm:px-5 py-3 opacity-80 space-y-1">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="text-[10px] font-mono text-zinc-500 dark:text-zinc-400 whitespace-nowrap shrink-0">
                                    {{ $this->formatKodePalet($st) }}
                                </span>
                                <span class="font-mono text-[11px] sm:text-xs text-zinc-500 dark:text-zinc-400 tabular-nums whitespace-nowrap shrink-0">
                                    {{ $ukuran?->panjang + 0 }}×{{ $ukuran?->lebar + 0 }}×{{ $ukuran?->tebal + 0 }}
                                </span>
                                <span class="inline-flex items-center px-2 py-0.5 rounded-sm text-[9px] font-black uppercase bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400 whitespace-nowrap shrink-0">
                                    {{ $st->status }}
                                </span>
                                <span class="inline-flex items-center px-1.5 py-0.5 rounded-sm text-[10px] font-black tabular-nums whitespace-nowrap bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400 shrink-0">
                                    {{ number_format($this->formatJumlahLembar($st)) }} Lbr
                                </span>
                            </div>
                            <div class="flex items-center justify-between gap-2 flex-wrap">
                                <span class="text-xs font-bold text-zinc-700 dark:text-zinc-300 uppercase truncate min-w-0">
                                    {{ $kayu }}
                                </span>
                                <span class="text-[10px] text-zinc-400 whitespace-nowrap">
                                    {{ $st->diterima_oleh }} · {{ $st->created_at?->format('d/m/Y H:i') }}
                                </span>
                            </div>
                        </div>
                    @empty
                        <div class="px-5 py-8 text-center text-xs text-zinc-400 dark:text-zinc-600">
                            Belum ada riwayat penerimaan
                        </div>
                    @endforelse
                @endif
            </div>
        </div>
    </section>

    {{-- ══════════════════════════════════════════════════════════════
         MUTASI KELUAR VENEER BASAH
    ═══════════════════════════════════════════════════════════════ --}}
    <section class="space-y-3 mt-6">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <h2 class="text-xs font-black uppercase tracking-wider text-zinc-500 dark:text-zinc-400 flex items-center gap-3">
                Mutasi Keluar Veneer Basah
                <button type="button" wire:click="openFormKeluar"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-sm text-[11px] font-bold text-white bg-amber-500 hover:bg-amber-400 transition-colors">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4" />
                    </svg>
                    Catat Barang Keluar
                </button>
            </h2>

            <input type="text" wire:model.live.debounce.400ms="keluarSearchQuery" placeholder="Cari riwayat keluar..."
                class="text-xs rounded-sm border-zinc-300 dark:border-zinc-700 dark:bg-zinc-900 w-56" />
        </div>

        <div class="bg-white dark:bg-zinc-950 border border-zinc-200 dark:border-zinc-800 shadow-sm overflow-hidden">
            <div class="divide-y divide-zinc-100 dark:divide-zinc-800 max-h-[500px] overflow-y-auto">
                @forelse ($this->riwayatKeluar as $mutasi)
                    <div wire:key="mutasi-group-{{ $mutasi->id }}" class="px-3 sm:px-5 py-3 space-y-2">
                        <div class="flex items-center justify-between gap-3 flex-wrap">
                            <div class="text-[10px] text-zinc-400">
                                {{ $mutasi->created_at?->format('d M Y H:i') }} · Oleh: {{ $mutasi->dibuat_oleh }}
                                <span class="inline-flex px-1.5 py-0.5 rounded-sm text-[9px] font-black uppercase {{ $mutasi->tujuan === 'dryer' ? 'bg-sky-100 text-sky-700 dark:bg-sky-900/30 dark:text-sky-400' : 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400' }}">
                                    {{ $mutasi->tujuan === 'dryer' ? 'Press Dryer' : 'Kedi' }}
                                </span>
                                @if ($mutasi->keterangan)
                                    · {{ $mutasi->keterangan }}
                                @endif
                            </div>
                            @if ($mutasi->bisa_diedit)
                                <button type="button" wire:click="editKeluar({{ $mutasi->id }})"
                                    class="shrink-0 inline-flex items-center gap-1 text-[10px] font-bold text-amber-600 dark:text-amber-400 hover:underline">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                    </svg>
                                    Edit
                                </button>
                            @else
                                <span class="text-[9px] text-zinc-300 dark:text-zinc-700 font-bold uppercase" title="Sebagian/semua palet sudah diterima atau ditolak">Terkunci</span>
                            @endif
                        </div>

                        <div class="space-y-1">
                            @foreach ($mutasi->details as $detail)
                                @php $st = $detail->serahTerima; @endphp
                                <div wire:key="mutasi-{{ $detail->id }}" class="flex items-center justify-between gap-3 flex-wrap bg-zinc-50 dark:bg-zinc-900/40 rounded-sm px-2.5 py-1.5">
                                    <div class="flex items-center gap-2 flex-wrap min-w-0">
                                        <span class="inline-flex px-1.5 py-0.5 rounded-sm text-[9px] font-black uppercase bg-zinc-200 text-zinc-700 dark:bg-zinc-700 dark:text-zinc-300 shrink-0">
                                            P{{ $detail->no_palet ?? '-' }}
                                        </span>
                                        <span class="font-mono text-xs text-zinc-600 dark:text-zinc-300">
                                            {{ $detail->ukuran?->panjang + 0 }}×{{ $detail->ukuran?->lebar + 0 }}×{{ $detail->ukuran?->tebal + 0 }} mm
                                        </span>
                                        <span class="inline-flex px-1.5 py-0.5 rounded-sm text-[9px] font-black bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-400">
                                            KW {{ $detail->kw }}
                                        </span>
                                        <span class="text-xs font-bold uppercase text-zinc-700 dark:text-zinc-300">
                                            {{ $detail->jenisKayu?->nama_kayu }}
                                        </span>
                                    </div>
                                    <div class="text-right shrink-0">
                                        <div class="font-black text-sm text-rose-500 dark:text-rose-400 tabular-nums">
                                            -{{ number_format($detail->qty_lembar) }} <span class="text-[10px] font-semibold text-zinc-400">Lbr</span>
                                        </div>
                                        <div class="text-[10px] text-zinc-400">{{ number_format($detail->m3, 4) }} m³</div>
                                        <span class="inline-flex px-1.5 py-0.5 rounded-sm text-[9px] font-black uppercase mt-1
                                            {{ $st?->status === 'Diterima' ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400'
                                               : ($st?->status === 'Ditolak' ? 'bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-400'
                                               : 'bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-400') }}">
                                            {{ $st?->status ?? 'Menunggu' }}
                                        </span>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @empty
                    <div class="px-5 py-8 text-center text-xs text-zinc-400 dark:text-zinc-600">
                        Belum ada mutasi keluar
                    </div>
                @endforelse
            </div>
        </div>
    </section>

    {{-- ══════════════════════════════════════════════════════════════
         MODAL: FORM BARANG KELUAR (pola sama persis dengan Gudang Veneer Kering)
    ═══════════════════════════════════════════════════════════════ --}}
    @if ($showFormKeluarModal)
    <div class="fixed inset-0 z-[100] flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" wire:click="cancelFormKeluar"></div>

        <div class="relative w-full max-w-lg bg-white dark:bg-gray-900 rounded-lg border border-gray-200 dark:border-gray-800 shadow-2xl max-h-[90vh] overflow-y-auto">
            <div class="px-5 py-4 border-b border-gray-100 dark:border-gray-800 flex items-center gap-2">
                <span class="inline-block w-1.5 h-4 bg-amber-500 rounded-sm"></span>
                <span class="text-sm font-black uppercase tracking-wider text-amber-600 dark:text-amber-400">Form Barang Keluar</span>
            </div>

            <form wire:submit.prevent="prosesKeluar" class="p-5 space-y-4 text-xs">

                {{-- PILIH VENEER — 100% client-side (Alpine), tanpa round-trip server --}}
                <div class="space-y-1.5 relative" x-data="{
                        isDropdownOpen: false,
                        searchTerm: '',
                        selectedSummaryId: @entangle('selectedSummaryId'),
                        options: [
                            @foreach ($this->veneerStokAll as $s)
                        {
                            id: '{{ $s->id }}',
                            nama: '{{ $s->jenisKayu?->nama_kayu }} (KW {{ $s->kw }}) - Sisa: {{ number_format((float) $s->stok_lembar) }} lbr',
                            no: '{{ number_format((float) $s->panjang, 2) }}x{{ number_format((float) $s->lebar, 2) }}x{{ number_format((float) $s->tebal, 2) }}'
                        }, @endforeach
                        ],
                        get filteredOptions() {
                            if (this.searchTerm === '') return this.options;
                            return this.options.filter(o =>
                                o.nama.toLowerCase().includes(this.searchTerm.toLowerCase()) ||
                                o.no.toLowerCase().includes(this.searchTerm.toLowerCase())
                            );
                        },
                        selectVeneer(item) {
                            this.selectedSummaryId = item.id;
                            this.searchTerm = item.no + ' | ' + item.nama;
                            this.isDropdownOpen = false;
                        },
                        clearVeneer() {
                            this.selectedSummaryId = null;
                            this.searchTerm = '';
                        },
                        init() {
                            let found = this.options.find(o => o.id == this.selectedSummaryId);
                            if (found) this.searchTerm = found.no + ' | ' + found.nama;
                        }
                    }" @click.away="isDropdownOpen = false">
                    <label class="text-[11px] font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider block">Pilih Veneer</label>
                    <div class="relative flex items-center">
                        <input type="text" x-model="searchTerm" @focus="isDropdownOpen = true"
                            placeholder="Ketik dimensi ukuran atau KW..."
                            autocomplete="off"
                            class="w-full px-3 py-2 bg-white dark:bg-gray-950 border border-gray-300 dark:border-gray-700 rounded-sm font-bold text-gray-900 dark:text-gray-100 outline-none pr-10 focus:border-amber-500 text-sm placeholder:text-gray-400 dark:placeholder:text-gray-600 placeholder:font-normal placeholder:text-xs">
                        <button type="button" x-show="searchTerm.length > 0 || selectedSummaryId"
                            @click="clearVeneer()"
                            class="absolute right-3 text-gray-400 dark:text-gray-500 hover:text-red-500 transition-colors">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                    <div x-show="isDropdownOpen" x-cloak
                        class="absolute z-50 w-full mt-1 bg-white dark:bg-gray-950 border border-gray-200 dark:border-gray-800 rounded-sm shadow-2xl max-h-48 overflow-y-auto p-1 divide-y divide-gray-100 dark:divide-gray-900/60">
                        <template x-for="item in filteredOptions" :key="item.id">
                            <button type="button" @click="selectVeneer(item)"
                                class="w-full text-left px-3 py-2 hover:bg-amber-500 hover:text-gray-950 flex flex-col transition-colors group">
                                <span class="text-[11px] text-gray-500 dark:text-gray-400 group-hover:text-gray-900 font-medium" x-text="item.nama"></span>
                                <span class="font-bold text-gray-800 dark:text-gray-200 group-hover:text-gray-950 text-xs" x-text="item.no"></span>
                            </button>
                        </template>
                        <div x-show="filteredOptions.length === 0"
                            class="text-gray-400 dark:text-gray-600 p-3 text-center italic text-[11px]">Veneer tidak ditemukan</div>
                    </div>
                </div>

                {{-- JUMLAH PALET --}}
                <div class="space-y-1.5">
                    <label class="text-[11px] font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider block">Jumlah Palet</label>
                    <input type="text" inputmode="numeric" wire:model.live="jumlahPalet" required
                        class="w-full text-sm p-2 bg-white dark:bg-gray-950 border border-gray-300 dark:border-gray-700 rounded-sm text-gray-900 dark:text-gray-100 focus:border-amber-500 focus:outline-none font-bold" />
                </div>

                {{-- ISI PER PALET --}}
                <div class="space-y-1.5">
                    <label class="text-[11px] font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider block">Isi per palet</label>
                    <div class="grid grid-cols-2 gap-2.5 max-h-[160px] overflow-y-auto border border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-950/30 p-3 rounded-sm">
                        @foreach ($paletQuantities as $index => $qty)
                            <div wire:key="palet-{{ $index }}" class="space-y-1">
                                <div class="flex items-center justify-between">
                                    <span class="text-[10px] text-gray-400 dark:text-gray-500 font-bold uppercase block">Palet #{{ $index + 1 }}</span>
                                    @if (count($paletQuantities) > 1)
                                        <button type="button" wire:click="hapusPalet({{ $index }})"
                                            class="text-gray-300 dark:text-gray-600 hover:text-red-500 transition-colors">
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12" />
                                            </svg>
                                        </button>
                                    @endif
                                </div>
                                <div class="relative">
                                    <input type="text" inputmode="numeric" wire:model="paletQuantities.{{ $index }}"
                                        placeholder="Kuantitas" required
                                        class="w-full text-xs p-2 border border-gray-300 dark:border-gray-700 rounded-sm bg-white dark:bg-gray-950 text-gray-900 dark:text-gray-100 focus:border-amber-500 focus:outline-none" />
                                    <span class="absolute right-2.5 top-2 text-[11px] text-gray-400 dark:text-gray-500">lbr</span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                {{-- TUJUAN KELUAR --}}
                <div class="space-y-1.5">
                    <label class="text-[11px] font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider block">Tujuan Keluar</label>
                    <select wire:model="tujuanKeluar"
                        class="w-full text-sm p-2 bg-white dark:bg-gray-950 border border-gray-300 dark:border-gray-700 rounded-sm text-gray-900 dark:text-gray-100 focus:border-amber-500 focus:outline-none font-bold">
                        <option value="dryer">Press Dryer</option>
                        <option value="kedi">Kedi</option>
                    </select>
                </div>

                {{-- KETERANGAN --}}
                <div class="space-y-1.5">
                    <label class="text-[11px] font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider block">Keterangan</label>
                    <textarea wire:model="keteranganKeluar" rows="2" placeholder="Tulis PO, atau nama mandor lapangan..."
                        class="w-full text-sm p-2 bg-white dark:bg-gray-950 border border-gray-300 dark:border-gray-700 rounded-sm text-gray-900 dark:text-gray-100 focus:border-amber-500 focus:outline-none placeholder:text-gray-400 dark:placeholder:text-gray-600"></textarea>
                </div>

                <div class="pt-2 flex items-center justify-end gap-3 border-t border-gray-100 dark:border-gray-800 -mx-5 px-5 pb-1">
                    <button type="button" wire:click="cancelFormKeluar" class="text-xs font-bold text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200">
                        Batal
                    </button>
                    <button type="submit" wire:loading.attr="disabled" wire:target="prosesKeluar"
                        class="px-4 py-2 rounded-sm text-xs font-bold text-white bg-amber-500 hover:bg-amber-400 disabled:opacity-50">
                        Proses Barang Keluar
                    </button>
                </div>
            </form>
        </div>
    </div>
    @endif

    {{-- ══════════════════════════════════════════════════════════════
         MODAL: EDIT RINCIAN PALET (selama belum ada palet yang diterima/ditolak)
    ═══════════════════════════════════════════════════════════════ --}}
    @if ($showEditKeluarModal)
    <div class="fixed inset-0 z-[100] flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" wire:click="cancelEditKeluar"></div>

        <div class="relative w-full max-w-lg bg-white dark:bg-gray-900 rounded-lg border border-gray-200 dark:border-gray-800 shadow-2xl max-h-[90vh] overflow-y-auto">
            <div class="px-5 py-4 border-b border-gray-100 dark:border-gray-800 flex items-center gap-2">
                <span class="inline-block w-1.5 h-4 bg-amber-500 rounded-sm"></span>
                <span class="text-sm font-black uppercase tracking-wider text-amber-600 dark:text-amber-400">Edit Rincian Palet</span>
            </div>

            <form wire:submit.prevent="updateKeluar" class="p-5 space-y-4 text-xs">

                {{-- JUMLAH PALET --}}
                <div class="space-y-1.5">
                    <label class="text-[11px] font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider block">Jumlah Palet</label>
                    <input type="text" inputmode="numeric" wire:model.live="editJumlahPalet" required
                        class="w-full text-sm p-2 bg-white dark:bg-gray-950 border border-gray-300 dark:border-gray-700 rounded-sm text-gray-900 dark:text-gray-100 focus:border-amber-500 focus:outline-none font-bold" />
                </div>

                {{-- ISI PER PALET --}}
                <div class="space-y-1.5">
                    <label class="text-[11px] font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider block">Isi per palet</label>
                    <div class="grid grid-cols-2 gap-2.5 max-h-[160px] overflow-y-auto border border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-950/30 p-3 rounded-sm">
                        @foreach ($editPaletQuantities as $index => $qty)
                            <div wire:key="edit-palet-{{ $index }}" class="space-y-1">
                                <div class="flex items-center justify-between">
                                    <span class="text-[10px] text-gray-400 dark:text-gray-500 font-bold uppercase block">Palet #{{ $index + 1 }}</span>
                                    @if (count($editPaletQuantities) > 1)
                                        <button type="button" wire:click="hapusEditPalet({{ $index }})"
                                            class="text-gray-300 dark:text-gray-600 hover:text-red-500 transition-colors">
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12" />
                                            </svg>
                                        </button>
                                    @endif
                                </div>
                                <div class="relative">
                                    <input type="text" inputmode="numeric" wire:model="editPaletQuantities.{{ $index }}"
                                        placeholder="Kuantitas" required
                                        class="w-full text-xs p-2 border border-gray-300 dark:border-gray-700 rounded-sm bg-white dark:bg-gray-950 text-gray-900 dark:text-gray-100 focus:border-amber-500 focus:outline-none" />
                                    <span class="absolute right-2.5 top-2 text-[11px] text-gray-400 dark:text-gray-500">lbr</span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="pt-2 flex items-center justify-end gap-3 border-t border-gray-100 dark:border-gray-800 -mx-5 px-5 pb-1">
                    <button type="button" wire:click="cancelEditKeluar" class="text-xs font-bold text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200">
                        Batal
                    </button>
                    <button type="submit" wire:loading.attr="disabled" wire:target="updateKeluar"
                        class="px-4 py-2 rounded-sm text-xs font-bold text-white bg-amber-500 hover:bg-amber-400 disabled:opacity-50">
                        Simpan Perubahan
                    </button>
                </div>
            </form>
        </div>
    </div>
    @endif
</x-filament-panels::page>