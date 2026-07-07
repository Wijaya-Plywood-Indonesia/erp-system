{{-- resources/views/filament/pages/gudang-veneer-kering.blade.php --}}
<x-filament-panels::page>

    @php
        $faceback = $this->faceback;
        $core     = $this->core;
    @endphp

    {{-- DINONAKTIFKAN: tab navigation (serah terima dihapus dari alur) --}}
    @if(false)
    {{-- ═══ TAB NAVIGATION ═══ --}}
    <div class="flex border-b border-gray-200 dark:border-gray-800 mb-4">
        <button
            type="button"
            wire:click="$set('activeTab', 'masuk')"
            class="flex-1 sm:flex-initial px-6 py-3 text-xs font-black uppercase tracking-widest border-b-2 -mb-px transition-all flex items-center justify-center gap-2 {{ $activeTab === 'masuk' ? 'border-amber-500 text-amber-600 dark:text-amber-400 bg-amber-500/5' : 'border-transparent text-gray-400 hover:text-gray-600 dark:hover:text-gray-200' }}">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 14l-7 7m0 0l-7-7m7 7V3" />
            </svg>
            <span>Serah Terima</span>
        </button>
        <button
            type="button"
            wire:click="$set('activeTab', 'keluar')"
            class="flex-1 sm:flex-initial px-6 py-3 text-xs font-black uppercase tracking-widest border-b-2 -mb-px transition-all flex items-center justify-center gap-2 {{ $activeTab === 'keluar' ? 'border-amber-500 text-amber-600 dark:text-amber-400 bg-amber-500/5' : 'border-transparent text-gray-400 hover:text-gray-600 dark:hover:text-gray-200' }}">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 10l7-7m0 0l7 7m-7-7v18" />
            </svg>
            <span>Veneer Keluar</span>
        </button>
    </div>
    @endif


    {{-- DINONAKTIFKAN: detail stok --}}
    @if(false)
    {{-- ═══ DETAIL STOK (tampil di kedua tab) ═══ --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-3">
        <span class="text-[10px] font-black uppercase tracking-widest text-gray-500 dark:text-gray-400">Detail Stok</span>

        <div class="relative w-full sm:w-72">
            <input
                type="text"
                wire:model.live.debounce.300ms="search"
                placeholder="Cari saldo ukuran/KW..."
                class="w-full text-xs bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-300 rounded-sm pl-8 pr-3 py-2 outline-none focus:border-primary-500"
            />
            <svg class="w-3.5 h-3.5 absolute left-2.5 top-1/2 -translate-y-1/2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z" />
            </svg>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">

        {{-- KATEGORI: FACE/BACK --}}
        <div class="bg-white dark:bg-gray-900 rounded border border-gray-200 dark:border-gray-800 shadow-sm overflow-hidden flex flex-col">
            <div class="px-5 py-3 border-b border-gray-100 dark:border-gray-800 flex items-center justify-between shrink-0">
                <span class="flex items-center gap-2 text-[11px] font-black uppercase tracking-widest text-amber-600 dark:text-amber-400">
                    <span class="w-1.5 h-1.5 rounded-sm bg-amber-500"></span>
                    Kategori: Face/Back
                </span>
                <span class="text-[10px] font-semibold text-gray-400 dark:text-gray-500">
                    {{ $faceback->count() }} Item terdaftar
                </span>
            </div>

            <div class="divide-y divide-gray-50 dark:divide-gray-800 max-h-[240px] overflow-y-auto">
                @forelse($faceback as $row)
                <div class="flex items-center gap-2 sm:gap-3 px-3 sm:px-5 py-3 hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors">
                    <span class="font-mono text-[11px] sm:text-xs text-gray-500 dark:text-gray-400 tabular-nums whitespace-nowrap shrink-0">
                        {{ number_format((float) $row->ukuran?->panjang, 2) }}×{{ number_format((float) $row->ukuran?->lebar, 2) }}×{{ number_format((float) $row->ukuran?->tebal, 2) }}
                        <span class="text-[10px] text-gray-400">mm</span>
                    </span>
                    <span class="inline-flex items-center justify-center px-1.5 sm:px-2 py-0.5 rounded-sm text-[9px] font-black uppercase bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400 whitespace-nowrap shrink-0">
                        KW {{ $row->kw ?? '-' }}
                    </span>
                    <span class="text-xs font-bold text-gray-700 dark:text-gray-300 uppercase truncate min-w-0 flex-1">
                        {{ $row->jenisKayu?->nama_kayu ?? '-' }}
                    </span>
                    <span class="text-right font-black text-sm text-amber-500 dark:text-amber-400 whitespace-nowrap tabular-nums shrink-0 ml-auto">
                        {{ number_format($row->total_lembar) }} <span class="text-[10px] font-semibold text-gray-400">Lbr</span>
                    </span>
                </div>
                @empty
                <div class="px-5 py-8 text-center text-xs text-gray-400 dark:text-gray-600">
                    Tidak ada stok Face/Back
                </div>
                @endforelse
            </div>
        </div>

        {{-- KATEGORI: CORE --}}
        <div class="bg-white dark:bg-gray-900 rounded border border-gray-200 dark:border-gray-800 shadow-sm overflow-hidden flex flex-col">
            <div class="px-5 py-3 border-b border-gray-100 dark:border-gray-800 flex items-center justify-between shrink-0">
                <span class="flex items-center gap-2 text-[11px] font-black uppercase tracking-widest text-amber-600 dark:text-amber-400">
                    <span class="w-1.5 h-1.5 rounded-sm bg-amber-500"></span>
                    Kategori: Core
                </span>
                <span class="text-[10px] font-semibold text-gray-400 dark:text-gray-500">
                    {{ $core->count() }} Item terdaftar
                </span>
            </div>

            <div class="divide-y divide-gray-50 dark:divide-gray-800 max-h-[240px] overflow-y-auto">
                @forelse($core as $row)
                <div class="flex items-center gap-2 sm:gap-3 px-3 sm:px-5 py-3 hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors">
                    <span class="font-mono text-[11px] sm:text-xs text-gray-500 dark:text-gray-400 tabular-nums whitespace-nowrap shrink-0">
                        {{ number_format((float) $row->ukuran?->panjang, 2) }}×{{ number_format((float) $row->ukuran?->lebar, 2) }}×{{ number_format((float) $row->ukuran?->tebal, 2) }}
                        <span class="text-[10px] text-gray-400">mm</span>
                    </span>
                    <span class="inline-flex items-center justify-center px-1.5 sm:px-2 py-0.5 rounded-sm text-[9px] font-black uppercase bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400 whitespace-nowrap shrink-0">
                        KW {{ $row->kw ?? '-' }}
                    </span>
                    <span class="text-xs font-bold text-gray-700 dark:text-gray-300 uppercase truncate min-w-0 flex-1">
                        {{ $row->jenisKayu?->nama_kayu ?? '-' }}
                    </span>
                    <span class="text-right font-black text-sm text-amber-500 dark:text-amber-400 whitespace-nowrap tabular-nums shrink-0 ml-auto">
                        {{ number_format($row->total_lembar) }} <span class="text-[10px] font-semibold text-gray-400">Lbr</span>
                    </span>
                </div>
                @empty
                <div class="px-5 py-8 text-center text-xs text-gray-400 dark:text-gray-600">
                    Tidak ada stok Core
                </div>
                @endforelse
            </div>
        </div>

    </div>

    @endif

    {{-- DINONAKTIFKAN: serah terima veneer kering --}}
    @if(false)
    {{-- ══════════════════════════════════════════════════════════════════════
         TAB 1: SERAH TERIMA VENEER KERING
    ═══════════════════════════════════════════════════════════════════════ --}}
    @php
        $serahTerima = $this->serahTerima;
        $menunggu    = $serahTerima->filter(fn ($vm) => ! $vm->details->every(fn ($d) => $d->stokVeneerKering !== null))->count();
    @endphp

    <div class="mt-8" x-data="{ confirmOpen: false, target: null }">
        <div class="flex items-center justify-between mb-3">
            <span class="text-[10px] font-black uppercase tracking-widest text-gray-500 dark:text-gray-400">Serah Terima Veneer Kering</span>
            <span class="text-[10px] font-semibold text-gray-400 dark:text-gray-500">
                {{ $menunggu }} menunggu diterima
            </span>
        </div>

        <div class="bg-white dark:bg-gray-900 rounded border border-gray-200 dark:border-gray-800 shadow-sm overflow-hidden">
            <div class="divide-y divide-gray-100 dark:divide-gray-800 max-h-[560px] overflow-y-auto">
                @forelse($serahTerima as $vm)
                    @php
                        $sudah  = $vm->details->every(fn ($d) => $d->stokVeneerKering !== null);
                        $ledger = $sudah ? $vm->penerimaanKering() : null;
                    @endphp

                    <div wire:key="vm-{{ $vm->id }}"
                         class="px-4 sm:px-5 py-4 transition-colors {{ $sudah ? 'opacity-60' : 'hover:bg-gray-50 dark:hover:bg-gray-800/40' }}">

                        {{-- Header baris --}}
                        <div class="flex items-start justify-between gap-3 flex-wrap">
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span class="font-mono text-xs font-bold text-gray-700 dark:text-gray-200 whitespace-nowrap">
                                        {{ $vm->no_nota ?? '—' }}
                                    </span>
                                    @if($sudah)
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-sm text-[9px] font-black uppercase bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400 whitespace-nowrap">
                                            Diterima
                                        </span>
                                    @else
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-sm text-[9px] font-black uppercase bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400 whitespace-nowrap">
                                            Menunggu
                                        </span>
                                    @endif
                                </div>
                                <div class="mt-1 text-[11px] text-gray-400 dark:text-gray-500 truncate">
                                    {{ optional($vm->tanggal)->translatedFormat('d M Y') }}
                                    @if($vm->keterangan)
                                        <span class="text-gray-300 dark:text-gray-600">·</span> {{ $vm->keterangan }}
                                    @endif
                                </div>
                            </div>

                            @unless($sudah)
                                @php
                                    $itemsJson = $vm->details->map(fn ($d) => [
                                        'id'      => $d->id,
                                        'dimensi' => number_format((float) $d->ukuran?->panjang, 2) . '×' . number_format((float) $d->ukuran?->lebar, 2) . '×' . number_format((float) $d->ukuran?->tebal, 2) . ' mm',
                                        'kw'      => $d->kw ?? '-',
                                        'kayu'    => $d->jenisKayu?->nama_kayu ?? '-',
                                        'qty'     => (float) $d->qty,
                                        'qtyFmt'  => number_format((float) $d->qty),
                                        'sudah'   => $d->stokVeneerKering !== null,
                                        'checked' => $d->stokVeneerKering === null, // default: centang yang belum diterima
                                    ])->values();
                                @endphp
                                <button
                                    type="button"
                                    x-on:click="target = {
                                        id: {{ $vm->id }},
                                        noNota: @js($vm->no_nota ?? '—'),
                                        tanggal: @js(optional($vm->tanggal)->translatedFormat('d M Y')),
                                        keterangan: @js($vm->keterangan),
                                        items: @js($itemsJson)
                                    }; confirmOpen = true"
                                    class="shrink-0 inline-flex items-center gap-1.5 px-3 py-1.5 rounded-sm text-[11px] font-bold text-white bg-emerald-600 hover:bg-emerald-500 transition-colors">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                                    </svg>
                                    <span>Terima</span>
                                </button>
                            @else
                                <div class="shrink-0 text-right">
                                    <div class="text-[11px] font-semibold text-emerald-600 dark:text-emerald-400 whitespace-nowrap">
                                        Diterima
                                    </div>
                                    <div class="text-[10px] text-gray-400 dark:text-gray-500 whitespace-nowrap">
                                        {{ optional($ledger?->created_at)->translatedFormat('d M Y H:i') }}
                                    </div>
                                </div>
                            @endunless
                        </div>

                        {{-- Detail item veneer kering di nota ini --}}
                        <div class="mt-3 rounded-sm bg-gray-50 dark:bg-gray-800/40 border border-gray-100 dark:border-gray-800 divide-y divide-gray-100 dark:divide-gray-800">
                            @foreach($vm->details as $d)
                                <div class="px-3 py-2">
                                    <div class="flex items-center gap-2 sm:gap-3">
                                        <span class="font-mono text-[11px] sm:text-xs text-gray-500 dark:text-gray-400 tabular-nums whitespace-nowrap shrink-0">
                                            {{ number_format((float) $d->ukuran?->panjang, 2) }}×{{ number_format((float) $d->ukuran?->lebar, 2) }}×{{ number_format((float) $d->ukuran?->tebal, 2) }}
                                            <span class="text-[10px] text-gray-400">mm</span>
                                        </span>
                                        <span class="inline-flex items-center justify-center px-1.5 sm:px-2 py-0.5 rounded-sm text-[9px] font-black uppercase bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400 whitespace-nowrap shrink-0">
                                            KW {{ $d->kw ?? '-' }}
                                        </span>
                                        @if($d->stokVeneerKering !== null)
                                            <span class="inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded-sm text-[9px] font-black uppercase bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400 whitespace-nowrap shrink-0">
                                                ✓ Diterima
                                            </span>
                                        @endif
                                        <span class="text-xs font-bold text-gray-700 dark:text-gray-300 uppercase truncate min-w-0 flex-1 hidden sm:block">
                                            {{ $d->jenisKayu?->nama_kayu ?? '-' }}
                                        </span>
                                        <span class="text-xs font-medium text-gray-600 dark:text-gray-300 truncate min-w-0 flex-1 hidden sm:block">
                                            {{ $d->keterangan_nota ?? '—' }}
                                        </span>
                                        <span class="text-right font-black text-sm text-amber-500 dark:text-amber-400 whitespace-nowrap tabular-nums shrink-0 ml-auto">
                                            {{ number_format((float) $d->qty) }} <span class="text-[10px] font-semibold text-gray-400">Lbr</span>
                                        </span>
                                    </div>
                                    <div class="mt-1 flex items-center gap-2 sm:hidden">
                                        <span class="text-xs font-bold text-gray-700 dark:text-gray-300 uppercase truncate">
                                            {{ $d->jenisKayu?->nama_kayu ?? '-' }}
                                        </span>
                                        @if($d->keterangan_nota)
                                            <span class="text-gray-300 dark:text-gray-600 shrink-0">·</span>
                                            <span class="text-[11px] text-gray-500 dark:text-gray-400 truncate">
                                                {{ $d->keterangan_nota }}
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @empty
                    <div class="px-5 py-8 text-center text-xs text-gray-400 dark:text-gray-600">
                        Belum ada serah terima veneer kering
                    </div>
                @endforelse
            </div>
        </div>

        {{-- ═══ MODAL KONFIRMASI TERIMA ═══ --}}
        <template x-teleport="body">
            <div
                x-show="confirmOpen"
                x-cloak
                class="fixed inset-0 z-[100] flex items-center justify-center p-4"
                style="display: none;"
            >
                <div
                    x-show="confirmOpen"
                    x-transition:enter="transition ease-out duration-200"
                    x-transition:enter-start="opacity-0"
                    x-transition:enter-end="opacity-100"
                    x-transition:leave="transition ease-in duration-150"
                    x-transition:leave-start="opacity-100"
                    x-transition:leave-end="opacity-0"
                    class="absolute inset-0 bg-black/60 backdrop-blur-sm"
                    x-on:click="confirmOpen = false"
                ></div>

                <div
                    x-show="confirmOpen"
                    x-transition:enter="transition ease-out duration-200"
                    x-transition:enter-start="opacity-0 scale-95 translate-y-2"
                    x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                    x-transition:leave="transition ease-in duration-150"
                    x-transition:leave-start="opacity-100 scale-100"
                    x-transition:leave-end="opacity-0 scale-95"
                    class="relative w-full max-w-md bg-white dark:bg-gray-900 rounded-lg border border-gray-200 dark:border-gray-800 shadow-2xl overflow-hidden max-h-[90vh] flex flex-col"
                    x-cloak
                >
                    <div class="px-5 py-4 bg-gradient-to-r from-emerald-600 to-emerald-500 flex items-center gap-3 shrink-0">
                        <div class="w-9 h-9 rounded-full bg-white/20 flex items-center justify-center shrink-0">
                            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0l-6.16-3.422a2 2 0 00-1.94 0L4 13" />
                            </svg>
                        </div>
                        <div class="min-w-0">
                            <div class="text-sm font-black text-white">Konfirmasi Terima Barang</div>
                            <div class="text-[11px] text-emerald-100 truncate" x-text="'Nota ' + (target?.noNota ?? '') + ' · ' + (target?.tanggal ?? '')"></div>
                        </div>
                    </div>

                    <div class="px-5 py-4 overflow-y-auto">
                        <p class="text-xs text-gray-500 dark:text-gray-400 mb-3">
                            Centang barang yang benar-benar diterima. Hanya yang tercentang yang masuk ke <span class="font-bold text-gray-700 dark:text-gray-300">Gudang Veneer Kering</span> dan menambah stok resmi. Sisanya tetap menunggu dan bisa diterima nanti.
                        </p>

                        <div class="rounded-sm border border-gray-100 dark:border-gray-800 divide-y divide-gray-100 dark:divide-gray-800 overflow-hidden">
                            <template x-for="(item, idx) in (target?.items ?? [])" :key="item.id">
                                <label class="px-3 py-2 flex items-center justify-between gap-2 bg-gray-50 dark:bg-gray-800/40"
                                       :class="item.sudah ? 'opacity-50' : 'cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-800/70'">
                                    <div class="flex items-center gap-2.5 min-w-0">
                                        <input type="checkbox"
                                               class="accent-emerald-600 shrink-0"
                                               x-model="item.checked"
                                               :disabled="item.sudah">
                                        <div class="min-w-0">
                                            <div class="flex items-center gap-2 flex-wrap">
                                                <span class="font-mono text-[11px] text-gray-500 dark:text-gray-400" x-text="item.dimensi"></span>
                                                <span class="inline-flex items-center px-1.5 py-0.5 rounded-sm text-[9px] font-black uppercase bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400" x-text="'KW ' + item.kw"></span>
                                                <span x-show="item.sudah" class="inline-flex items-center px-1.5 py-0.5 rounded-sm text-[9px] font-black uppercase bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400">✓ Diterima</span>
                                            </div>
                                            <div class="text-xs font-bold text-gray-700 dark:text-gray-300 uppercase truncate" x-text="item.kayu"></div>
                                        </div>
                                    </div>
                                    <span class="text-sm font-black text-amber-500 dark:text-amber-400 whitespace-nowrap" x-text="item.qtyFmt + ' Lbr'"></span>
                                </label>
                            </template>
                        </div>

                        <div class="mt-3 flex items-center justify-between px-1">
                            <span class="text-[10px] font-black uppercase tracking-widest text-gray-400">
                                Total dipilih (<span x-text="(target?.items ?? []).filter(i => i.checked && !i.sudah).length"></span> barang)
                            </span>
                            <span class="text-sm font-black text-gray-800 dark:text-gray-200"
                                  x-text="(target?.items ?? []).filter(i => i.checked && !i.sudah).reduce((a, i) => a + Number(i.qty), 0).toLocaleString('id-ID') + ' Lembar'"></span>
                        </div>

                        <template x-if="target?.keterangan">
                            <div class="mt-3 px-3 py-2 rounded-sm bg-amber-50 dark:bg-amber-900/10 border border-amber-100 dark:border-amber-900/30 text-[11px] text-amber-700 dark:text-amber-400">
                                <span class="font-bold">Catatan:</span> <span x-text="target?.keterangan"></span>
                            </div>
                        </template>
                    </div>

                    <div class="px-5 py-3 bg-gray-50 dark:bg-gray-800/60 border-t border-gray-100 dark:border-gray-800 flex items-center justify-end gap-2 shrink-0">
                        <button
                            type="button"
                            x-on:click="confirmOpen = false"
                            class="px-3.5 py-1.5 rounded-sm text-[11px] font-bold text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                            Batal
                        </button>
                        <button
                            type="button"
                            :disabled="(target?.items ?? []).filter(i => i.checked && !i.sudah).length === 0"
                            x-on:click="$wire.terima(target.id, (target?.items ?? []).filter(i => i.checked && !i.sudah).map(i => i.id)); confirmOpen = false"
                            class="inline-flex items-center gap-1.5 px-4 py-1.5 rounded-sm text-[11px] font-bold text-white bg-emerald-600 hover:bg-emerald-500 disabled:opacity-50 disabled:cursor-not-allowed transition-colors">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                            </svg>
                            Ya, Terima Barang
                        </button>
                    </div>
                </div>
            </div>
        </template>
    </div>

    @else
    {{-- ══════════════════════════════════════════════════════════════════════
         TAB 2: VENEER KELUAR
    ═══════════════════════════════════════════════════════════════════════ --}}
    <div class="mt-8">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-3">
            <div class="flex flex-col sm:flex-row sm:items-center gap-3">
                <span class="text-[10px] font-black uppercase tracking-widest text-gray-500 dark:text-gray-400">Mutasi Keluar Veneer Kering</span>
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

        {{-- Riwayat keluar --}}
        <div class="bg-white dark:bg-gray-900 rounded border border-gray-200 dark:border-gray-800 shadow-sm overflow-hidden">
            <div class="divide-y divide-gray-100 dark:divide-gray-800 max-h-[560px] overflow-y-auto">
                @forelse($this->riwayatKeluar as $rk)
                    <div wire:key="rk-{{ $rk->id }}" class="px-4 sm:px-5 py-4 hover:bg-gray-50 dark:hover:bg-gray-800/40 transition-colors">
                        <div class="flex items-start justify-between gap-3 flex-wrap">
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span class="font-mono text-[11px] sm:text-xs text-gray-500 dark:text-gray-400 tabular-nums whitespace-nowrap">
                                        {{ number_format((float) $rk->ukuran?->panjang, 2) }}×{{ number_format((float) $rk->ukuran?->lebar, 2) }}×{{ number_format((float) $rk->ukuran?->tebal, 2) }}
                                        <span class="text-[10px] text-gray-400">mm</span>
                                    </span>
                                    <span class="inline-flex items-center px-1.5 sm:px-2 py-0.5 rounded-sm text-[9px] font-black uppercase bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400 whitespace-nowrap">
                                        KW {{ $rk->kw }}
                                    </span>
                                    <span class="text-xs font-bold text-gray-700 dark:text-gray-300 uppercase truncate">
                                        {{ $rk->jenisKayu?->nama_kayu ?? '-' }}
                                    </span>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-sm text-[9px] font-black uppercase bg-sky-100 text-sky-700 dark:bg-sky-900/30 dark:text-sky-400 whitespace-nowrap">
                                        {{ $rk->tujuan_keluar }}
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
                                                P{{ $p->no_palet }}: <strong class="text-gray-800 dark:text-gray-200">{{ number_format((float) $p->qty) }}</strong>
                                            </span>
                                        @endforeach
                                    </div>
                                @endif
                            </div>

                            <div class="shrink-0 text-right">
                                <div class="font-black text-sm text-red-500 dark:text-red-400 whitespace-nowrap tabular-nums">
                                    -{{ number_format((float) $rk->qty) }} <span class="text-[10px] font-semibold text-gray-400">Lbr</span>
                                </div>
                                <div class="text-[10px] text-gray-400 dark:text-gray-500 whitespace-nowrap tabular-nums">
                                    {{ number_format((float) $rk->m3, 4) }} m³ · {{ $rk->jumlah_palet }} plt
                                </div>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="px-5 py-8 text-center text-xs text-gray-400 dark:text-gray-600">
                        Belum ada riwayat pengeluaran veneer kering
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

                {{-- 1. PILIH VENEER (search dropdown dari stok) --}}
                <div class="space-y-1.5 relative" x-data="{
                    isDropdownOpen: false,
                    searchTerm: '',
                    selectedStokId: @entangle('selectedStokId'),
                    options: [
                        @foreach($faceback->concat($core) as $s)
                        {
                            id: '{{ $s->id }}',
                            nama: '{{ $s->jenisKayu?->nama_kayu }} (KW {{ $s->kw }}) - Sisa: {{ number_format((float) $s->total_lembar) }} lbr',
                            no: '{{ number_format((float) $s->ukuran?->panjang, 2) }}x{{ number_format((float) $s->ukuran?->lebar, 2) }}x{{ number_format((float) $s->ukuran?->tebal, 2) }}'
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
                    selectVeneer(item) {
                        this.selectedStokId = item.id;
                        this.searchTerm = item.no + ' | ' + item.nama;
                        this.isDropdownOpen = false;
                    },
                    clearVeneer() {
                        this.selectedStokId = null;
                        this.searchTerm = '';
                    },
                    init() {
                        let found = this.options.find(o => o.id == this.selectedStokId);
                        if (found) this.searchTerm = found.no + ' | ' + found.nama;
                    }
                }" @click.away="isDropdownOpen = false">
                    <label class="text-[11px] font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider block">Pilih Veneer</label>
                    <div class="relative flex items-center">
                        <input type="text" x-model="searchTerm" @focus="isDropdownOpen = true" placeholder="Ketik dimensi ukuran atau KW..."
                            class="w-full px-3 py-2 bg-white dark:bg-gray-950 border border-gray-300 dark:border-gray-700 rounded-sm font-bold text-gray-900 dark:text-gray-100 outline-none pr-10 focus:border-amber-500 text-sm placeholder:text-gray-400 dark:placeholder:text-gray-600 placeholder:font-normal placeholder:text-xs">
                        <button type="button" x-show="searchTerm.length > 0 || selectedStokId" @click="clearVeneer()"
                            class="absolute right-3 text-gray-400 dark:text-gray-500 hover:text-red-500 transition-colors">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                    <div x-show="isDropdownOpen" x-cloak class="absolute z-50 w-full mt-1 bg-white dark:bg-gray-950 border border-gray-200 dark:border-gray-800 rounded-sm shadow-2xl max-h-48 overflow-y-auto p-1 divide-y divide-gray-100 dark:divide-gray-900/60">
                        <template x-for="item in filteredOptions" :key="item.id">
                            <button type="button" @click="selectVeneer(item)" class="w-full text-left px-3 py-2 hover:bg-amber-500 hover:text-gray-950 flex flex-col transition-colors group">
                                <span class="text-[11px] text-gray-500 dark:text-gray-400 group-hover:text-gray-900 font-medium" x-text="item.nama"></span>
                                <span class="font-bold text-gray-800 dark:text-gray-200 group-hover:text-gray-950 text-xs" x-text="item.no"></span>
                            </button>
                        </template>
                        <div x-show="filteredOptions.length === 0" class="text-gray-400 dark:text-gray-600 p-3 text-center italic text-[11px]">Veneer tidak ditemukan</div>
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

                {{-- 3. TUJUAN KELUAR (tetap: Repair) --}}
                <div class="space-y-1.5">
                    <label class="text-[11px] font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider block">Tujuan Keluar</label>
                    <div class="w-full text-sm p-2 border border-gray-300 dark:border-gray-700 rounded-sm bg-gray-50 dark:bg-gray-950/50 text-gray-900 dark:text-gray-100 font-bold">
                        Repair
                    </div>
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
    @endif

</x-filament-panels::page>