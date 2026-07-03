{{-- resources/views/filament/pages/gudang-veneer-kering.blade.php --}}
<x-filament-panels::page>

    @php
        $faceback = $this->faceback;
        $core     = $this->core;
    @endphp

    <div class="flex items-center justify-between mb-3">
        <span class="text-[10px] font-black uppercase tracking-widest text-gray-500 dark:text-gray-400">Detail Stok</span>

        <div class="relative w-72">
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
                <div class="grid grid-cols-[128px_52px_1fr_auto] items-center gap-3 px-5 py-3 hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors">
                    <span class="font-mono text-xs text-gray-500 dark:text-gray-400 tabular-nums whitespace-nowrap">
                        {{ number_format((float) $row->ukuran?->panjang, 2) }}×{{ number_format((float) $row->ukuran?->lebar, 2) }}×{{ number_format((float) $row->ukuran?->tebal, 2) }}
                        <span class="text-[10px] text-gray-400">mm</span>
                    </span>
                    <span class="inline-flex items-center justify-center px-2 py-0.5 rounded-sm text-[9px] font-black uppercase bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400 whitespace-nowrap w-fit">
                        KW {{ $row->kw ?? '-' }}
                    </span>
                    <span class="text-xs font-bold text-gray-700 dark:text-gray-300 uppercase truncate">
                        {{ $row->jenisKayu?->nama_kayu ?? '-' }}
                    </span>
                    <span class="text-right font-black text-sm text-amber-500 dark:text-amber-400 whitespace-nowrap tabular-nums">
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
                <div class="grid grid-cols-[128px_52px_1fr_auto] items-center gap-3 px-5 py-3 hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors">
                    <span class="font-mono text-xs text-gray-500 dark:text-gray-400 tabular-nums whitespace-nowrap">
                        {{ number_format((float) $row->ukuran?->panjang, 2) }}×{{ number_format((float) $row->ukuran?->lebar, 2) }}×{{ number_format((float) $row->ukuran?->tebal, 2) }}
                        <span class="text-[10px] text-gray-400">mm</span>
                    </span>
                    <span class="inline-flex items-center justify-center px-2 py-0.5 rounded-sm text-[9px] font-black uppercase bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400 whitespace-nowrap w-fit">
                        KW {{ $row->kw ?? '-' }}
                    </span>
                    <span class="text-xs font-bold text-gray-700 dark:text-gray-300 uppercase truncate">
                        {{ $row->jenisKayu?->nama_kayu ?? '-' }}
                    </span>
                    <span class="text-right font-black text-sm text-amber-500 dark:text-amber-400 whitespace-nowrap tabular-nums">
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

    <div class="mt-8">
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
                         class="px-5 py-4 transition-colors {{ $sudah ? 'opacity-60' : 'hover:bg-gray-50 dark:hover:bg-gray-800/40' }}">

                        {{-- Header baris --}}
                        <div class="flex items-start justify-between gap-4">
                            <div class="min-w-0">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span class="font-mono text-xs font-bold text-gray-700 dark:text-gray-200 whitespace-nowrap">
                                        {{ $vm->no_nota ?? '—' }}
                                    </span>
                                    @if($sudah)
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-sm text-[9px] font-black uppercase bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400">
                                            Diterima
                                        </span>
                                    @else
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-sm text-[9px] font-black uppercase bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400">
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
                                <button
                                    type="button"
                                    wire:click="terima({{ $vm->id }})"
                                    wire:confirm="Terima barang dari nota {{ $vm->no_nota }} ke Gudang Veneer Kering?"
                                    wire:target="terima({{ $vm->id }})"
                                    wire:loading.attr="disabled"
                                    class="shrink-0 inline-flex items-center gap-1.5 px-3 py-1.5 rounded-sm text-[11px] font-bold text-white bg-emerald-600 hover:bg-emerald-500 disabled:opacity-50 disabled:cursor-not-allowed transition-colors">
                                    <svg wire:loading.remove wire:target="terima({{ $vm->id }})" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                                    </svg>
                                    <svg wire:loading wire:target="terima({{ $vm->id }})" class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                                    </svg>
                                    <span wire:loading.remove wire:target="terima({{ $vm->id }})">Terima</span>
                                    <span wire:loading wire:target="terima({{ $vm->id }})">Memproses…</span>
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
                                    <div class="grid grid-cols-[128px_52px_140px_1fr_auto] items-center gap-3">
                                        <span class="font-mono text-xs text-gray-500 dark:text-gray-400 tabular-nums whitespace-nowrap">
                                            {{ number_format((float) $d->ukuran?->panjang, 2) }}×{{ number_format((float) $d->ukuran?->lebar, 2) }}×{{ number_format((float) $d->ukuran?->tebal, 2) }}
                                            <span class="text-[10px] text-gray-400">mm</span>
                                        </span>
                                        <span class="inline-flex items-center justify-center px-2 py-0.5 rounded-sm text-[9px] font-black uppercase bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400 whitespace-nowrap w-fit">
                                            KW {{ $d->kw ?? '-' }}
                                        </span>
                                        <span class="text-xs font-bold text-gray-700 dark:text-gray-300 uppercase truncate">
                                            {{ $d->jenisKayu?->nama_kayu ?? '-' }}
                                        </span>
                                        <span class="text-xs font-medium text-gray-600 dark:text-gray-300 truncate">
                                            {{ $d->keterangan_nota ?? '—' }}
                                        </span>
                                        <span class="text-right font-black text-sm text-amber-500 dark:text-amber-400 whitespace-nowrap tabular-nums">
                                            {{ number_format((float) $d->qty) }} <span class="text-[10px] font-semibold text-gray-400">Lbr</span>
                                        </span>
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
    </div>

</x-filament-panels::page>