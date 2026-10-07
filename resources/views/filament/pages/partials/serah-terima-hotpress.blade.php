    {{-- ══════════════════════════════════════════════════════════════════════
         SERAH TERIMA KE GUDANG TRIPLEK MENTAH — dari Hotpress & dari Graji
    ═══════════════════════════════════════════════════════════════════════ --}}
    @php
        $menungguTerima = $this->menungguTerima;  // dari Hotpress
        $menungguGraji  = $this->menungguGraji;   // dari Graji Triplek
        $riwayatTerima  = $this->riwayatTerima;

        $totalAktif = $menungguTerima->count() + $menungguGraji->count();
    @endphp

    <section class="space-y-3 mb-6">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-zinc-200 dark:border-zinc-800 pb-2">
            <h2 class="text-xs font-black uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                Serah Terima Masuk
            </h2>

            <div class="flex border-b border-zinc-200 dark:border-zinc-800">
                <button type="button" wire:click="$set('serahTerimaTab', 'aktif')"
                    class="px-4 py-2 text-[10px] font-black uppercase tracking-widest border-b-2 -mb-px transition-all {{ $serahTerimaTab === 'aktif' ? 'border-amber-500 text-amber-600 dark:text-amber-400' : 'border-transparent text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-200' }}">
                    Aktif
                    <span class="ml-1 inline-flex items-center justify-center min-w-[1.1rem] px-1 rounded-full text-[9px] font-bold {{ $serahTerimaTab === 'aktif' ? 'bg-amber-500 text-white' : 'bg-zinc-200 dark:bg-zinc-700 text-zinc-500 dark:text-zinc-400' }}">
                        {{ $totalAktif }}
                    </span>
                </button>
                <button type="button" wire:click="$set('serahTerimaTab', 'history')"
                    class="px-4 py-2 text-[10px] font-black uppercase tracking-widest border-b-2 -mb-px transition-all {{ $serahTerimaTab === 'history' ? 'border-amber-500 text-amber-600 dark:text-amber-400' : 'border-transparent text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-200' }}">
                    History
                </button>
            </div>
        </div>

        <div class="bg-white dark:bg-zinc-950 border border-zinc-200 dark:border-zinc-800 shadow-sm overflow-hidden">
            <div class="divide-y divide-zinc-100 dark:divide-zinc-800 max-h-[500px] overflow-y-auto">

                @if ($serahTerimaTab === 'aktif')

                    {{-- ── Pending dari Hotpress ── --}}
                    @foreach ($menungguTerima as $st)
                        @php
                            $bsj    = $st->triplekHasilHp?->barangSetengahJadi;
                            $ukuran = $bsj?->ukuran;
                            $kayu   = $bsj?->jenisBarang?->nama_jenis_barang ?? '-';
                            $kw     = $bsj?->grade?->nama_grade ?? '-';
                            $qty    = (float) ($st->triplekHasilHp?->isi ?? 0);
                            $palet  = $st->triplekHasilHp?->no_palet ?? '-';
                        @endphp
                        <div wire:key="hp-aktif-{{ $st->id }}"
                            class="px-3 sm:px-5 py-3 hover:bg-zinc-50 dark:hover:bg-zinc-900/40 transition-colors">
                            <div class="flex items-center gap-2 sm:gap-3 flex-wrap">
                                <span class="inline-flex items-center px-1.5 sm:px-2 py-0.5 rounded-sm text-[9px] font-black uppercase whitespace-nowrap shrink-0 bg-sky-100 text-sky-700 dark:bg-sky-900/30 dark:text-sky-400">
                                    Hotpress
                                </span>
                                <span class="text-[10px] font-mono text-zinc-500 dark:text-zinc-400 whitespace-nowrap shrink-0">
                                    Palet {{ $palet }}
                                </span>
                                <span class="font-mono text-[11px] sm:text-xs text-zinc-500 dark:text-zinc-400 tabular-nums whitespace-nowrap shrink-0">
                                    {{ $ukuran?->panjang + 0 }}×{{ $ukuran?->lebar + 0 }}×{{ $ukuran?->tebal + 0 }}
                                    <span class="text-[10px] text-zinc-400">mm</span>
                                </span>
                                <span class="inline-flex items-center justify-center px-1.5 sm:px-2 py-0.5 rounded-sm text-[9px] font-black uppercase bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-400 whitespace-nowrap shrink-0">
                                    KW {{ $kw }}
                                </span>
                                <span class="text-xs font-bold text-zinc-700 dark:text-zinc-300 uppercase truncate min-w-0 flex-1">
                                    {{ $kayu }}
                                </span>
                                <span class="text-right font-black text-sm text-amber-500 dark:text-amber-400 whitespace-nowrap tabular-nums shrink-0">
                                    {{ number_format($qty) }} <span class="text-[10px] font-semibold text-zinc-400">Lbr</span>
                                </span>
                                <button type="button" wire:click="terimaKeGudang({{ $st->id }})"
                                    wire:confirm="Terima barang dari Hotpress ke gudang? Stok akan bertambah."
                                    wire:loading.attr="disabled" wire:target="terimaKeGudang({{ $st->id }})"
                                    class="shrink-0 inline-flex items-center gap-1.5 px-3 py-1.5 rounded-sm text-[11px] font-bold text-white bg-emerald-600 hover:bg-emerald-500 disabled:opacity-50 transition-colors">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                                    </svg>
                                    <span>Terima</span>
                                </button>
                            </div>
                            <div class="mt-1 text-[11px] text-zinc-400 dark:text-zinc-500 truncate">
                                Diserahkan oleh:
                                <span class="font-semibold text-zinc-600 dark:text-zinc-300">{{ $st->diserahkan_oleh ?? '-' }}</span>
                                <span class="text-zinc-300 dark:text-zinc-600">·</span>
                                {{ optional($st->created_at)->translatedFormat('d M Y H:i') }}
                            </div>
                        </div>
                    @endforeach

                    {{-- ── Pending dari Graji Triplek ── --}}
                    @foreach ($menungguGraji as $st)
                        @php
                            $hasil  = $st->hasilGrajiTriplek;
                            $bsj    = $hasil?->barangSetengahJadiHp;
                            $ukuran = $bsj?->ukuran;
                            $kayu   = $bsj?->jenisBarang?->nama_jenis_barang ?? '-';
                            $kw     = $bsj?->grade?->nama_grade ?? '-';
                            $qty    = (float) ($hasil?->isi ?? 0);
                            $palet  = $hasil?->no_palet ?? '-';
                        @endphp
                        <div wire:key="graji-aktif-{{ $st->id }}"
                            class="px-3 sm:px-5 py-3 hover:bg-zinc-50 dark:hover:bg-zinc-900/40 transition-colors">
                            <div class="flex items-center gap-2 sm:gap-3 flex-wrap">
                                <span class="inline-flex items-center px-1.5 sm:px-2 py-0.5 rounded-sm text-[9px] font-black uppercase whitespace-nowrap shrink-0 bg-violet-100 text-violet-700 dark:bg-violet-900/30 dark:text-violet-400">
                                    Graji
                                </span>
                                <span class="text-[10px] font-mono text-zinc-500 dark:text-zinc-400 whitespace-nowrap shrink-0">
                                    Palet {{ $palet }}
                                </span>
                                <span class="font-mono text-[11px] sm:text-xs text-zinc-500 dark:text-zinc-400 tabular-nums whitespace-nowrap shrink-0">
                                    {{ $ukuran?->panjang + 0 }}×{{ $ukuran?->lebar + 0 }}×{{ $ukuran?->tebal + 0 }}
                                    <span class="text-[10px] text-zinc-400">mm</span>
                                </span>
                                <span class="inline-flex items-center justify-center px-1.5 sm:px-2 py-0.5 rounded-sm text-[9px] font-black uppercase bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-400 whitespace-nowrap shrink-0">
                                    KW {{ $kw }}
                                </span>
                                <span class="text-xs font-bold text-zinc-700 dark:text-zinc-300 uppercase truncate min-w-0 flex-1">
                                    {{ $kayu }}
                                </span>
                                <span class="text-right font-black text-sm text-amber-500 dark:text-amber-400 whitespace-nowrap tabular-nums shrink-0">
                                    {{ number_format($qty) }} <span class="text-[10px] font-semibold text-zinc-400">Lbr</span>
                                </span>
                                <button type="button" wire:click="terimaGrajiKeGudang({{ $st->id }})"
                                    wire:confirm="Terima hasil Graji ke gudang? Stok akan bertambah."
                                    wire:loading.attr="disabled" wire:target="terimaGrajiKeGudang({{ $st->id }})"
                                    class="shrink-0 inline-flex items-center gap-1.5 px-3 py-1.5 rounded-sm text-[11px] font-bold text-white bg-emerald-600 hover:bg-emerald-500 disabled:opacity-50 transition-colors">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                                    </svg>
                                    <span>Terima</span>
                                </button>
                            </div>
                            <div class="mt-1 text-[11px] text-zinc-400 dark:text-zinc-500 truncate">
                                Diserahkan oleh:
                                <span class="font-semibold text-zinc-600 dark:text-zinc-300">{{ $st->diserahkan_oleh ?? '-' }}</span>
                                <span class="text-zinc-300 dark:text-zinc-600">·</span>
                                {{ optional($st->created_at)->translatedFormat('d M Y H:i') }}
                            </div>
                        </div>
                    @endforeach

                    @if ($totalAktif === 0)
                        <div class="px-5 py-8 text-center text-xs text-zinc-400 dark:text-zinc-600">
                            Tidak ada barang yang menunggu diterima
                        </div>
                    @endif

                @else

                    {{-- ── History (Hotpress & Graji yang sudah diterima) ── --}}
                    @forelse ($riwayatTerima as $st)
                        @php
                            $dariGraji = $st->id_hasil_graji_triplek !== null;

                            if ($dariGraji) {
                                $hasil  = $st->hasilGrajiTriplek;
                                $bsj    = $hasil?->barangSetengahJadiHp;
                                $palet  = $hasil?->no_palet ?? '-';
                            } else {
                                $bsj    = $st->triplekHasilHp?->barangSetengahJadi;
                                $palet  = $st->triplekHasilHp?->no_palet ?? '-';
                            }

                            $ukuran = $bsj?->ukuran;
                            $kayu   = $dariGraji
                                ? ($bsj?->jenisBarang?->nama_jenis_barang ?? '-')
                                : ($bsj?->jenisBarang?->nama_jenis_barang ?? '-');
                            $kw     = $bsj?->grade?->nama_grade ?? '-';
                            $qty    = $dariGraji
                                ? (float) ($hasil?->isi ?? 0)
                                : (float) ($st->triplekHasilHp?->isi ?? 0);
                        @endphp
                        <div wire:key="hist-{{ $st->id }}"
                            class="px-3 sm:px-5 py-3 hover:bg-zinc-50 dark:hover:bg-zinc-900/40 transition-colors">
                            <div class="flex items-center gap-2 sm:gap-3 flex-wrap">
                                @if ($dariGraji)
                                    <span class="inline-flex items-center px-1.5 sm:px-2 py-0.5 rounded-sm text-[9px] font-black uppercase whitespace-nowrap shrink-0 bg-violet-100 text-violet-700 dark:bg-violet-900/30 dark:text-violet-400">
                                        Graji
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-1.5 sm:px-2 py-0.5 rounded-sm text-[9px] font-black uppercase whitespace-nowrap shrink-0 bg-sky-100 text-sky-700 dark:bg-sky-900/30 dark:text-sky-400">
                                        Hotpress
                                    </span>
                                @endif
                                <span class="text-[10px] font-mono text-zinc-500 dark:text-zinc-400 whitespace-nowrap shrink-0">
                                    Palet {{ $palet }}
                                </span>
                                <span class="font-mono text-[11px] sm:text-xs text-zinc-500 dark:text-zinc-400 tabular-nums whitespace-nowrap shrink-0">
                                    {{ $ukuran?->panjang + 0 }}×{{ $ukuran?->lebar + 0 }}×{{ $ukuran?->tebal + 0 }}
                                    <span class="text-[10px] text-zinc-400">mm</span>
                                </span>
                                <span class="inline-flex items-center justify-center px-1.5 sm:px-2 py-0.5 rounded-sm text-[9px] font-black uppercase bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-400 whitespace-nowrap shrink-0">
                                    KW {{ $kw }}
                                </span>
                                <span class="text-xs font-bold text-zinc-700 dark:text-zinc-300 uppercase truncate min-w-0 flex-1">
                                    {{ $kayu }}
                                </span>
                                <span class="text-right font-black text-sm text-emerald-500 dark:text-emerald-400 whitespace-nowrap tabular-nums shrink-0">
                                    +{{ number_format($qty) }} <span class="text-[10px] font-semibold text-zinc-400">Lbr</span>
                                </span>
                            </div>
                            <div class="mt-1 text-[11px] text-zinc-400 dark:text-zinc-500 truncate">
                                Diterima oleh:
                                <span class="font-semibold text-zinc-600 dark:text-zinc-300">{{ $st->diterima_gudang_oleh ?? trim(explode(' - ', $st->diterima_oleh ?? '-')[0]) }}</span>
                                <span class="text-zinc-300 dark:text-zinc-600">·</span>
                                {{ optional($st->diterima_gudang_at)->translatedFormat('d M Y H:i') }}
                            </div>
                        </div>
                    @empty
                        <div class="px-5 py-8 text-center text-xs text-zinc-400 dark:text-zinc-600">
                            Belum ada riwayat serah terima masuk
                        </div>
                    @endforelse

                @endif

            </div>
        </div>
    </section>
