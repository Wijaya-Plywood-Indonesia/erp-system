{{-- resources/views/filament/pages/gudang-veneer-kering.blade.php --}}
<x-filament-panels::page>

    @php
        $faceback = $this->faceback;
        $core     = $this->core;
    @endphp

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

    {{-- ══════════════════════════════════════════════════════════════════════
         SERAH TERIMA VENEER KERING
         Antrian: VeneerMutasi status 'divalidasi' + punya id_nota_bm + ada
         detail veneer kering. State "sudah diterima" ditentukan dari ledger
         gudang_veneer_kering (relasi details.gudangKering), bukan kolom mutasi.
         Belum diterima (terbaru dulu) di atas; sudah diterima turun ke bawah
         dan tombolnya hilang.
    ═══════════════════════════════════════════════════════════════════════ --}}
    @php
        $serahTerima = $this->serahTerima;
        $menunggu    = $serahTerima->filter(fn ($vm) => ! $vm->sudahDiterima())->count();
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
                        $sudah  = $vm->sudahDiterima();
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
                                        'dimensi' => number_format((float) $d->ukuran?->panjang, 2) . '×' . number_format((float) $d->ukuran?->lebar, 2) . '×' . number_format((float) $d->ukuran?->tebal, 2) . ' mm',
                                        'kw'      => $d->kw ?? '-',
                                        'kayu'    => $d->jenisKayu?->nama_kayu ?? '-',
                                        'qty'     => number_format((float) $d->qty),
                                    ])->values();
                                @endphp
                                <button
                                    type="button"
                                    x-on:click="target = {
                                        id: {{ $vm->id }},
                                        noNota: @js($vm->no_nota ?? '—'),
                                        tanggal: @js(optional($vm->tanggal)->translatedFormat('d M Y')),
                                        keterangan: @js($vm->keterangan),
                                        totalLbr: @js(number_format($vm->details->sum('qty'))),
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
                                    <div class="text-[11px] font-semibold text-gray-600 dark:text-gray-300 whitespace-nowrap">
                                        {{ $ledger?->penerima?->name ?? '—' }}
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
                {{-- Backdrop --}}
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

                {{-- Card --}}
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
                    {{-- Header --}}
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

                    {{-- Body --}}
                    <div class="px-5 py-4 overflow-y-auto">
                        <p class="text-xs text-gray-500 dark:text-gray-400 mb-3">
                            Barang berikut akan masuk ke <span class="font-bold text-gray-700 dark:text-gray-300">Gudang Veneer Kering</span> dan menambah stok resmi. Pastikan rinciannya sudah sesuai fisik barang sebelum konfirmasi.
                        </p>

                        <div class="rounded-sm border border-gray-100 dark:border-gray-800 divide-y divide-gray-100 dark:divide-gray-800 overflow-hidden">
                            <template x-for="(item, idx) in (target?.items ?? [])" :key="idx">
                                <div class="px-3 py-2 flex flex-wrap items-center justify-between gap-2 bg-gray-50 dark:bg-gray-800/40">
                                    <div class="min-w-0">
                                        <div class="flex items-center gap-2 flex-wrap">
                                            <span class="font-mono text-[11px] text-gray-500 dark:text-gray-400" x-text="item.dimensi"></span>
                                            <span class="inline-flex items-center px-1.5 py-0.5 rounded-sm text-[9px] font-black uppercase bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400" x-text="'KW ' + item.kw"></span>
                                        </div>
                                        <div class="text-xs font-bold text-gray-700 dark:text-gray-300 uppercase truncate" x-text="item.kayu"></div>
                                    </div>
                                    <span class="text-sm font-black text-amber-500 dark:text-amber-400 whitespace-nowrap" x-text="item.qty + ' Lbr'"></span>
                                </div>
                            </template>
                        </div>

                        <div class="mt-3 flex items-center justify-between px-1">
                            <span class="text-[10px] font-black uppercase tracking-widest text-gray-400">Total</span>
                            <span class="text-sm font-black text-gray-800 dark:text-gray-200" x-text="(target?.totalLbr ?? '0') + ' Lembar'"></span>
                        </div>

                        <template x-if="target?.keterangan">
                            <div class="mt-3 px-3 py-2 rounded-sm bg-amber-50 dark:bg-amber-900/10 border border-amber-100 dark:border-amber-900/30 text-[11px] text-amber-700 dark:text-amber-400">
                                <span class="font-bold">Catatan:</span> <span x-text="target?.keterangan"></span>
                            </div>
                        </template>
                    </div>

                    {{-- Footer --}}
                    <div class="px-5 py-3 bg-gray-50 dark:bg-gray-800/60 border-t border-gray-100 dark:border-gray-800 flex items-center justify-end gap-2 shrink-0">
                        <button
                            type="button"
                            x-on:click="confirmOpen = false"
                            class="px-3.5 py-1.5 rounded-sm text-[11px] font-bold text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                            Batal
                        </button>
                        <button
                            type="button"
                            x-on:click="$wire.terima(target.id); confirmOpen = false"
                            class="inline-flex items-center gap-1.5 px-4 py-1.5 rounded-sm text-[11px] font-bold text-white bg-emerald-600 hover:bg-emerald-500 transition-colors">
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

</x-filament-panels::page>