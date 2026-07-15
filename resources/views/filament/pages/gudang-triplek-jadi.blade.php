{{-- resources/views/filament/pages/gudang-triplek-jadi.blade.php --}}
<x-filament-panels::page>

    {{-- DINONAKTIFKAN: Detail Stok (ubah false -> true bila dibutuhkan lagi) --}}
    @if(false)
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-3">
        <span class="text-[10px] font-black uppercase tracking-widest text-gray-500 dark:text-gray-400">Detail Stok Triplek Jadi</span>

        <div class="relative w-full sm:w-72">
            <input
                type="text"
                wire:model.live.debounce.300ms="searchQuery"
                placeholder="Cari kayu/ukuran/grade..."
                class="w-full text-xs bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-300 rounded-sm pl-8 pr-3 py-2 outline-none focus:border-primary-500"
            />
            <svg class="w-3.5 h-3.5 absolute left-2.5 top-1/2 -translate-y-1/2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z" />
            </svg>
        </div>
    </div>

    <div class="bg-white dark:bg-gray-900 rounded border border-gray-200 dark:border-gray-800 shadow-sm overflow-hidden">
        <div class="divide-y divide-gray-50 dark:divide-gray-800 max-h-[240px] overflow-y-auto">
            @forelse($this->stokList as $row)
            <div class="flex items-center gap-2 sm:gap-3 px-3 sm:px-5 py-3 hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors">
                <span class="font-mono text-[11px] sm:text-xs text-gray-500 dark:text-gray-400 tabular-nums whitespace-nowrap shrink-0">
                    {{ ($row->panjang + 0) }}×{{ ($row->lebar + 0) }}×{{ ($row->tebal + 0) }}
                </span>
                <span class="inline-flex items-center justify-center px-1.5 sm:px-2 py-0.5 rounded-sm text-[9px] font-black uppercase bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400 whitespace-nowrap shrink-0">
                    {{ $row->kw_grade ?? '-' }}
                </span>
                <span class="text-xs font-bold text-gray-700 dark:text-gray-300 uppercase truncate min-w-0 flex-1">
                    {{ $row->jenisKayu?->nama_kayu ?? '-' }}
                </span>
                <span class="font-mono text-[10px] text-gray-400 dark:text-gray-500 tabular-nums whitespace-nowrap shrink-0 hidden sm:block">
                    {{ number_format((float) $row->stok_kubikasi, 4) }} m³
                </span>
                <span class="text-right font-black text-sm text-amber-500 dark:text-amber-400 whitespace-nowrap tabular-nums shrink-0 ml-auto">
                    {{ number_format($row->stok_lembar) }} <span class="text-[10px] font-semibold text-gray-400">Lbr</span>
                </span>
            </div>
            @empty
            <div class="px-5 py-8 text-center text-xs text-gray-400 dark:text-gray-600">
                Belum ada stok Triplek Jadi
            </div>
            @endforelse
        </div>
    </div>
    @endif

    {{-- ══════════════════════════════════════════════════════════════════════
         BARANG KELUAR (satu-satunya tampilan halaman ini)
         Tujuan: Produksi Nyusup / Gudang Satu
    ═══════════════════════════════════════════════════════════════════════ --}}
    <div>
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-3">
            <div class="flex flex-col sm:flex-row sm:items-center gap-3">
                <span class="text-[10px] font-black uppercase tracking-widest text-gray-500 dark:text-gray-400">Mutasi Keluar Triplek Jadi</span>
                <button
                    type="button"
                    wire:click="$set('showFormKeluarModal', true)"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-sm text-[11px] font-bold text-white bg-amber-600 hover:bg-amber-500 transition-colors w-fit">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4" />
                    </svg>
                    <span>Catat Barang Keluar</span>
                </button>
            </div>

            <div class="relative w-full sm:w-64">
                <input
                    type="text"
                    wire:model.live.debounce.300ms="keluarSearchQuery"
                    placeholder="Cari riwayat keluar..."
                    class="w-full text-xs bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-300 rounded-sm pl-8 pr-3 py-2 outline-none focus:border-primary-500"
                />
                <svg class="w-3.5 h-3.5 absolute left-2.5 top-1/2 -translate-y-1/2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z" />
                </svg>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-900 rounded border border-gray-200 dark:border-gray-800 shadow-sm overflow-hidden">
            <div class="divide-y divide-gray-100 dark:divide-gray-800 max-h-[560px] overflow-y-auto">
                @forelse($this->riwayatKeluarFiltered as $rk)
                    <div wire:key="rk-{{ $rk->id }}" class="px-4 sm:px-5 py-4 hover:bg-gray-50 dark:hover:bg-gray-800/40 transition-colors">
                        <div class="flex items-start justify-between gap-3 flex-wrap">
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span class="font-mono text-[11px] sm:text-xs text-gray-500 dark:text-gray-400 tabular-nums whitespace-nowrap">
                                        {{ ($rk->panjang + 0) }}×{{ ($rk->lebar + 0) }}×{{ ($rk->tebal + 0) }}
                                    </span>
                                    <span class="inline-flex items-center px-1.5 sm:px-2 py-0.5 rounded-sm text-[9px] font-black uppercase bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400 whitespace-nowrap">
                                        {{ $rk->kw_grade ?? '-' }}
                                    </span>
                                    <span class="text-xs font-bold text-gray-700 dark:text-gray-300 uppercase truncate">
                                        {{ $rk->jenisKayu?->nama_kayu ?? '-' }}
                                    </span>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-sm text-[9px] font-black uppercase bg-sky-100 text-sky-700 dark:bg-sky-900/30 dark:text-sky-400 whitespace-nowrap">
                                        {{ $rk->tujuan }}
                                    </span>
                                </div>
                                <div class="mt-1 text-[11px] text-gray-400 dark:text-gray-500 truncate">
                                    {{ $rk->created_at->translatedFormat('d M Y H:i') }}
                                    <span class="text-gray-300 dark:text-gray-600">·</span>
                                    Oleh: {{ $rk->operator?->name ?? '-' }}
                                    @if($rk->keterangan)
                                        <span class="text-gray-300 dark:text-gray-600">·</span> {{ $rk->keterangan }}
                                    @endif
                                </div>
                                @if($rk->palets->isNotEmpty())
                                    <div class="mt-2 flex flex-wrap gap-1">
                                        @foreach($rk->palets as $p)
                                            <span class="text-[10px] font-mono px-1.5 py-0.5 rounded-sm bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400 border border-gray-200 dark:border-gray-700">
                                                P{{ $p->nomor_palet }}: <strong class="text-gray-800 dark:text-gray-200">{{ number_format($p->jumlah_lembar) }}</strong>
                                            </span>
                                        @endforeach
                                    </div>
                                @endif
                            </div>

                            <div class="shrink-0 text-right">
                                <div class="font-black text-sm text-red-500 dark:text-red-400 whitespace-nowrap tabular-nums">
                                    -{{ number_format($rk->stok_lembar) }} <span class="text-[10px] font-semibold text-gray-400">Lbr</span>
                                </div>
                                <div class="text-[10px] text-gray-400 dark:text-gray-500 whitespace-nowrap tabular-nums">
                                    {{ number_format((float) $rk->stok_kubikasi, 4) }} m³ · {{ $rk->jumlah_palet }} plt
                                </div>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="px-5 py-8 text-center text-xs text-gray-400 dark:text-gray-600">
                        Belum ada riwayat pengeluaran
                    </div>
                @endforelse
            </div>
        </div>
    </div>

    {{-- ═══ MODAL FORM BARANG KELUAR ═══ --}}
    @if($showFormKeluarModal)
    <div class="fixed inset-0 z-[100] flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" wire:click="$set('showFormKeluarModal', false)"></div>

        <div class="relative w-full max-w-lg bg-white dark:bg-gray-900 rounded-lg border border-gray-200 dark:border-gray-800 shadow-2xl max-h-[90vh] overflow-y-auto">
            <div class="px-5 py-4 border-b border-gray-100 dark:border-gray-800 flex items-center gap-2">
                <span class="inline-block w-1.5 h-4 bg-amber-500 rounded-sm"></span>
                <span class="text-sm font-black uppercase tracking-wider text-amber-600 dark:text-amber-400">Form Barang Keluar</span>
            </div>

            <form wire:submit.prevent="prosesKeluar" class="p-5 space-y-4 text-xs">

                {{-- 1. PILIH BARANG (search dropdown dari stok) --}}
                <div class="space-y-1.5 relative" x-data="{
                    isDropdownOpen: false,
                    searchTerm: '',
                    selectedStokId: @entangle('selectedStokId'),
                    options: [
                        @foreach($this->stokList as $s)
                        {
                            id: '{{ $s->id }}',
                            nama: '{{ $s->jenisKayu?->nama_kayu }} ({{ $s->kw_grade }}) - Sisa: {{ number_format((int) $s->stok_lembar) }} lbr',
                            no: '{{ ($s->panjang + 0) }}x{{ ($s->lebar + 0) }}x{{ ($s->tebal + 0) }}'
                        },
                        @endforeach
                    ],
                    get filteredOptions() {
                        if (this.searchTerm === '') return this.options;
                        return this.options.filter(o =>
                            o.nama.toLowerCase().includes(this.searchTerm.toLowerCase()) ||
                            o.no.toLowerCase().includes(this.searchTerm.toLowerCase())
                        );
                    },
                    selectItem(item) {
                        this.selectedStokId = item.id;
                        this.searchTerm = item.no + ' | ' + item.nama;
                        this.isDropdownOpen = false;
                    },
                    clearItem() {
                        this.selectedStokId = null;
                        this.searchTerm = '';
                    },
                    init() {
                        let found = this.options.find(o => o.id == this.selectedStokId);
                        if (found) this.searchTerm = found.no + ' | ' + found.nama;
                    }
                }" @click.away="isDropdownOpen = false">
                    <label class="text-[11px] font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider block">Pilih Barang</label>
                    <div class="relative flex items-center">
                        <input type="text" x-model="searchTerm" @focus="isDropdownOpen = true" placeholder="Ketik ukuran, grade, atau jenis kayu..."
                            class="w-full px-3 py-2 bg-white dark:bg-gray-950 border border-gray-300 dark:border-gray-700 rounded-sm font-bold text-gray-900 dark:text-gray-100 outline-none pr-10 focus:border-amber-500 text-sm placeholder:text-gray-400 dark:placeholder:text-gray-600 placeholder:font-normal placeholder:text-xs">
                        <button type="button" x-show="searchTerm.length > 0 || selectedStokId" @click="clearItem()"
                            class="absolute right-3 text-gray-400 dark:text-gray-500 hover:text-red-500 transition-colors">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                    <div x-show="isDropdownOpen" x-cloak class="absolute z-50 w-full mt-1 bg-white dark:bg-gray-950 border border-gray-200 dark:border-gray-800 rounded-sm shadow-2xl max-h-48 overflow-y-auto p-1 divide-y divide-gray-100 dark:divide-gray-900/60">
                        <template x-for="item in filteredOptions" :key="item.id">
                            <button type="button" @click="selectItem(item)" class="w-full text-left px-3 py-2 hover:bg-amber-500 hover:text-gray-950 flex flex-col transition-colors group">
                                <span class="text-[11px] text-gray-500 dark:text-gray-400 group-hover:text-gray-900 font-medium" x-text="item.nama"></span>
                                <span class="font-bold text-gray-800 dark:text-gray-200 group-hover:text-gray-950 text-xs" x-text="item.no"></span>
                            </button>
                        </template>
                        <div x-show="filteredOptions.length === 0" class="text-gray-400 dark:text-gray-600 p-3 text-center italic text-[11px]">Barang tidak ditemukan</div>
                    </div>
                </div>

                {{-- 2A. JUMLAH PALET --}}
                <div class="space-y-1.5">
                    <label class="text-[11px] font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider block">Jumlah Palet</label>
                    <input
                        type="text" inputmode="numeric"
                        wire:model.live="jumlahPalet"
                        required
                        class="w-full text-sm p-2 bg-white dark:bg-gray-950 border border-gray-300 dark:border-gray-700 rounded-sm text-gray-900 dark:text-gray-100 focus:border-amber-500 focus:outline-none font-bold" />
                </div>

                {{-- 2B. ISI PER PALET (dinamis) --}}
                <div class="space-y-1.5">
                    <label class="text-[11px] font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider block">Isi per palet</label>
                    <div class="grid grid-cols-2 gap-2.5 max-h-[160px] overflow-y-auto border border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-950/30 p-3 rounded-sm">
                        @for($i = 0; $i < max(1, (int) $jumlahPalet); $i++)
                            <div class="space-y-1">
                                <div class="flex items-center justify-between">
                                    <span class="text-[10px] text-gray-400 dark:text-gray-500 font-bold uppercase block">Palet #{{ $i + 1 }}</span>
                                    @if(count($paletQuantities) > 1)
                                        <button type="button" wire:click="hapusPalet({{ $i }})" class="text-gray-300 dark:text-gray-600 hover:text-red-500 transition-colors">
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12" />
                                            </svg>
                                        </button>
                                    @endif
                                </div>
                                <div class="relative">
                                    <input
                                        type="text" inputmode="numeric"
                                        wire:model="paletQuantities.{{ $i }}"
                                        placeholder="Kuantitas"
                                        required
                                        class="w-full text-xs p-2 border border-gray-300 dark:border-gray-700 rounded-sm bg-white dark:bg-gray-950 text-gray-900 dark:text-gray-100 focus:border-amber-500 focus:outline-none" />
                                    <span class="absolute right-2.5 top-2 text-[11px] text-gray-400 dark:text-gray-500">lbr</span>
                                </div>
                            </div>
                        @endfor
                    </div>
                </div>

                {{-- LIVE TOTAL --}}
                @php
                    $totalLbr = array_sum(array_map('intval', $paletQuantities));
                @endphp
                @if($totalLbr > 0)
                <div class="p-3 border border-dashed border-amber-500/30 bg-amber-500/5 rounded-sm flex items-center justify-between">
                    <div>
                        <p class="text-[10px] text-gray-500 dark:text-gray-400 uppercase tracking-widest font-black">Total Lembar</p>
                        <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-0.5">
                            {{ max(1, (int) $jumlahPalet) }} palet ({{ implode(' + ', array_map(fn($v) => $v ?: 0, $paletQuantities)) }})
                        </p>
                    </div>
                    <span class="px-3 py-1 bg-amber-500 text-gray-950 font-black text-sm rounded-sm">
                        {{ number_format($totalLbr) }} Lbr
                    </span>
                </div>
                @endif

                {{-- 3. TUJUAN KELUAR: Produksi Nyusup / Gudang Satu --}}
                <div class="space-y-1.5">
                    <label class="text-[11px] font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider block">Tujuan Keluar</label>
                    <select
                        wire:model="tujuanKeluar"
                        required
                        class="w-full text-sm p-2 border border-gray-300 dark:border-gray-700 rounded-sm bg-white dark:bg-gray-950 text-gray-900 dark:text-gray-100 focus:border-amber-500 focus:outline-none font-bold">
                        @foreach(\App\Filament\Pages\GudangTriplekJadi::TUJUAN_OPTIONS as $opt)
                            <option value="{{ $opt }}">{{ $opt }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- 4. KETERANGAN --}}
                <div class="space-y-1.5">
                    <label class="text-[11px] font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider block">Keterangan</label>
                    <textarea
                        wire:model="keteranganKeluar"
                        placeholder="Tulis PO, atau nama mandor lapangan..."
                        rows="2"
                        class="w-full text-sm p-2.5 border border-gray-300 dark:border-gray-700 rounded-sm bg-white dark:bg-gray-950 text-gray-900 dark:text-gray-100 focus:border-amber-500 focus:outline-none"></textarea>
                </div>

                {{-- AKSI --}}
                <div class="pt-2 flex justify-end gap-2">
                    <button
                        type="button"
                        wire:click="$set('showFormKeluarModal', false)"
                        class="px-3.5 py-1.5 rounded-sm text-[11px] font-bold text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors">
                        Batal
                    </button>
                    <button
                        type="submit"
                        wire:loading.attr="disabled"
                        wire:target="prosesKeluar"
                        class="inline-flex items-center gap-1.5 px-4 py-1.5 rounded-sm text-[11px] font-bold text-gray-950 bg-amber-500 hover:bg-amber-400 disabled:opacity-50 transition-colors">
                        <span wire:loading.remove wire:target="prosesKeluar">Proses Barang Keluar</span>
                        <span wire:loading wire:target="prosesKeluar">Memproses…</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
    @endif

</x-filament-panels::page>