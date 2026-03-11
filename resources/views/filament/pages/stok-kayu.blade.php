{{-- resources/views/filament/pages/stok-kayu-page.blade.php --}}
<x-filament-panels::page>

    @php
    $summaries = $this->summaries;
    $grouped = $this->groupedSummaries;
    $lahanPerKombinasi = $this->lahanPerKombinasi;
    $isGlobal = is_null($this->activeLahanId);
    $totalBtg = $summaries->sum('stok_batang');
    $totalM3 = $summaries->sum('stok_kubikasi');
    @endphp

    <div class="flex flex-col gap-5">

        {{-- ── Stats ── --}}
        <div class="grid grid-cols-3 gap-3">
            <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-4">
                <div class="text-[10.5px] uppercase tracking-widest text-gray-400 mb-1">Total Kayu Tersedia</div>
                <div class="text-2xl font-extrabold text-green-500">
                    {{ number_format($totalBtg) }}
                    <span class="text-sm font-normal text-gray-400">batang</span>
                </div>
                <div class="text-[11px] text-gray-400 mt-1">
                    {{ $this->activeLahan?->nama_lahan ?? 'Semua lahan aktif' }}
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-4">
                <div class="text-[10.5px] uppercase tracking-widest text-gray-400 mb-1">Total Kubikasi</div>
                <div class="text-2xl font-extrabold text-blue-500">
                    {{ number_format($totalM3, 4) }}
                    <span class="text-sm font-normal text-gray-400">m³</span>
                </div>
                <div class="text-[11px] text-gray-400 mt-1">Semua ukuran</div>
            </div>

            <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-4">
                <div class="text-[10.5px] uppercase tracking-widest text-gray-400 mb-1">Jenis Kayu</div>
                <div class="text-2xl font-extrabold text-amber-500">
                    {{ $this->jenisList->count() }}
                    <span class="text-sm font-normal text-gray-400">jenis</span>
                </div>
                <div class="text-[11px] text-gray-400 mt-1 truncate">
                    {{ $this->jenisList->join(', ') ?: '—' }}
                </div>
            </div>
        </div>

        {{-- ── Layout: Sidebar + Right ── --}}
        <div class="grid gap-4" style="grid-template-columns: 240px 1fr; align-items: start;">

            {{-- ── SIDEBAR LAHAN ── --}}
            <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl overflow-hidden sticky top-20">

                <div class="px-4 py-3 bg-gray-50 dark:bg-gray-900 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between">
                    <span class="text-sm font-bold text-gray-900 dark:text-white">Pilih Lahan</span>
                    <span class="text-[10px] font-mono bg-gray-200 dark:bg-gray-700 text-gray-500 px-2 py-0.5 rounded-full">
                        {{ $this->lahans->count() }}
                    </span>
                </div>

                <div class="p-2 border-b border-gray-100 dark:border-gray-700">
                    <input wire:model.live.debounce.300ms="lahanSearch" type="text"
                        placeholder="Cari kode / nama..."
                        class="w-full text-xs bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg px-3 py-2 outline-none focus:border-primary-500 text-gray-800 dark:text-gray-200 placeholder-gray-400">
                </div>

                <div class="max-h-[520px] overflow-y-auto">

                    {{-- Semua Lahan --}}
                    <button wire:click="selectLahan(null)"
                        @class(['w-full flex items-center gap-2.5 px-3 py-2.5 text-left transition border-b border-gray-50 dark:border-gray-700', 'bg-primary-50 dark:bg-primary-900/20 border-l-2 border-l-primary-500'=> $isGlobal,
                        'hover:bg-gray-50 dark:hover:bg-gray-700/50' => !$isGlobal])>
                        <span @class(['text-xs w-8 h-6 rounded font-bold flex items-center justify-center', 'bg-primary-100 text-primary-600 dark:bg-primary-900 dark:text-primary-300'=> $isGlobal,
                            'bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400' => !$isGlobal])>
                            🌐
                        </span>
                        <div class="flex-1 min-w-0">
                            <div @class(['text-xs font-bold truncate', 'text-primary-700 dark:text-primary-300'=> $isGlobal,
                                'text-gray-700 dark:text-gray-200' => !$isGlobal])>
                                Semua Lahan
                            </div>
                            <div class="text-[10px] text-gray-400 mt-0.5">
                                {{ number_format($this->summaries->sum('stok_batang')) }} btg total
                            </div>
                        </div>
                    </button>

                    @forelse($this->lahans as $lahan)
                    @php $s = $this->stokPerLahan[$lahan->id] ?? null; @endphp
                    <button wire:click="selectLahan({{ $lahan->id }})"
                        @class(['w-full flex items-center gap-2.5 px-3 py-2.5 text-left transition border-b border-gray-50 dark:border-gray-700', 'bg-primary-50 dark:bg-primary-900/20 border-l-2 border-l-primary-500'=> $activeLahanId === $lahan->id,
                        'hover:bg-gray-50 dark:hover:bg-gray-700/50' => $activeLahanId !== $lahan->id])>
                        <span @class(['font-mono text-[10px] px-1.5 py-0.5 rounded min-w-[36px] text-center font-bold', 'bg-primary-100 text-primary-700 dark:bg-primary-900 dark:text-primary-300'=> $activeLahanId === $lahan->id,
                            'bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400' => $activeLahanId !== $lahan->id])>
                            {{ $lahan->kode_lahan }}
                        </span>
                        <div class="flex-1 min-w-0">
                            <div @class(['text-xs font-semibold truncate', 'text-primary-700 dark:text-primary-300'=> $activeLahanId === $lahan->id,
                                'text-gray-800 dark:text-gray-200' => $activeLahanId !== $lahan->id])>
                                {{ $lahan->nama_lahan }}
                            </div>
                            @if($s)
                            <div class="text-[10px] text-gray-400 mt-0.5">
                                <span class="font-semibold text-gray-600 dark:text-gray-300">{{ number_format($s['btg']) }}</span> btg
                                · {{ $s['jenis']->take(2)->join(', ') }}{{ $s['jenis']->count() > 2 ? '...' : '' }}
                            </div>
                            @else
                            <div class="text-[10px] text-amber-500 mt-0.5">Stok kosong</div>
                            @endif
                        </div>
                    </button>
                    @empty
                    <div class="px-4 py-6 text-center text-xs text-gray-400">Tidak ditemukan</div>
                    @endforelse
                </div>
            </div>

            {{-- ── RIGHT PANEL ── --}}
            <div class="flex flex-col gap-4">

                {{-- Context bar --}}
                <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl px-5 py-3.5 flex items-center gap-4">
                    @if($this->activeLahan)
                    <div class="w-9 h-9 rounded-lg bg-primary-50 dark:bg-primary-900/30 flex items-center justify-center">
                        <x-heroicon-o-map-pin class="w-5 h-5 text-primary-500" />
                    </div>
                    <div>
                        <div class="font-bold text-gray-900 dark:text-white">{{ $this->activeLahan->nama_lahan }}</div>
                        <div class="text-[11px] font-mono text-gray-400 mt-0.5">{{ $this->activeLahan->kode_lahan }}</div>
                    </div>
                    @else
                    <div class="w-9 h-9 rounded-lg bg-indigo-50 dark:bg-indigo-900/30 flex items-center justify-center">
                        <x-heroicon-o-globe-alt class="w-5 h-5 text-indigo-500" />
                    </div>
                    <div>
                        <div class="font-bold text-gray-900 dark:text-white">Semua Lahan</div>
                        <div class="text-[11px] text-gray-400 mt-0.5">Stok gabungan dari semua lahan aktif</div>
                    </div>
                    @endif
                    <div class="ml-auto flex gap-6">
                        <div class="text-right">
                            <div class="text-lg font-extrabold font-mono text-green-500">{{ number_format($totalBtg) }}</div>
                            <div class="text-[10px] text-gray-400">total batang</div>
                        </div>
                        <div class="text-right">
                            <div class="text-lg font-extrabold font-mono text-blue-500">{{ number_format($totalM3, 4) }}</div>
                            <div class="text-[10px] text-gray-400">m³</div>
                        </div>
                    </div>
                </div>

                {{-- Filter bar --}}
                <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl px-4 py-2.5 flex items-center gap-3 flex-wrap">

                    <span class="text-[11px] font-semibold text-gray-400 whitespace-nowrap">Ukuran:</span>
                    <div class="flex gap-1.5 flex-wrap">
                        <button wire:click="$set('filterPanjang', '')"
                            @class(['px-2.5 py-1 rounded-full text-[11.5px] font-medium border transition', 'bg-primary-500 border-primary-500 text-white'=> $filterPanjang === '',
                            'border-gray-200 dark:border-gray-600 text-gray-500 dark:text-gray-400 hover:border-primary-400' => $filterPanjang !== ''])>
                            Semua
                        </button>
                        @foreach($this->panjangList as $p)
                        <button wire:click="$set('filterPanjang', '{{ $p }}')"
                            @class(['px-2.5 py-1 rounded-full text-[11.5px] font-medium border transition', 'bg-primary-500 border-primary-500 text-white'=> $filterPanjang == $p,
                            'border-gray-200 dark:border-gray-600 text-gray-500 dark:text-gray-400 hover:border-primary-400' => $filterPanjang != $p])>
                            {{ $p }} cm
                        </button>
                        @endforeach
                    </div>

                    <div class="w-px h-5 bg-gray-200 dark:bg-gray-700"></div>

                    <span class="text-[11px] font-semibold text-gray-400">Jenis:</span>
                    <div class="flex gap-1.5 flex-wrap">
                        <button wire:click="$set('filterJenis', '')"
                            @class(['px-2.5 py-1 rounded-full text-[11.5px] font-medium border transition', 'bg-primary-500 border-primary-500 text-white'=> $filterJenis === '',
                            'border-gray-200 dark:border-gray-600 text-gray-500 dark:text-gray-400 hover:border-primary-400' => $filterJenis !== ''])>
                            Semua
                        </button>
                        @foreach($this->jenisList as $j)
                        <button wire:click="$set('filterJenis', '{{ $j }}')"
                            @class(['px-2.5 py-1 rounded-full text-[11.5px] font-medium border transition', 'bg-primary-500 border-primary-500 text-white'=> $filterJenis === $j,
                            'border-gray-200 dark:border-gray-600 text-gray-500 dark:text-gray-400 hover:border-primary-400' => $filterJenis !== $j])>
                            {{ $j }}
                        </button>
                        @endforeach
                    </div>

                    <span class="ml-auto text-[11px] font-mono text-gray-400 whitespace-nowrap">
                        {{ $summaries->count() }} item · {{ number_format($totalBtg) }} batang
                    </span>
                </div>

                {{-- ── Cards grouped by panjang ── --}}
                @forelse($grouped as $panjang => $rows)
                @php
                $pBtg = $rows->sum('stok_batang');
                $pM3 = $rows->sum('stok_kubikasi');
                $pLahan = $rows->pluck('id_lahan')->unique()->count();
                @endphp

                <div class="flex flex-col gap-3">

                    {{-- Group header --}}
                    <div class="flex items-center gap-3 pb-2 border-b border-gray-100 dark:border-gray-700">
                        <span class="font-mono text-sm font-extrabold px-3 py-1 rounded-lg bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 text-blue-600 dark:text-blue-300">
                            {{ $panjang }} cm
                        </span>
                        <span class="text-[11px] text-gray-400">
                            <strong class="text-gray-600 dark:text-gray-300">{{ $rows->count() }} jenis</strong>
                            &nbsp;·&nbsp;
                            <strong class="text-gray-600 dark:text-gray-300">{{ number_format($pBtg) }} batang</strong>
                            &nbsp;·&nbsp;
                            {{ number_format($pM3, 4) }} m³
                        </span>
                    </div>

                    {{-- Cards grid --}}
                    <div class="grid gap-3" style="grid-template-columns: repeat(auto-fill, minmax(200px, 1fr))">
                        @foreach($rows as $row)
                        @php $lahans = $lahanPerKombinasi[$row->id_jenis_kayu . '_' . $row->panjang] ?? collect(); @endphp
                        <div class="relative overflow-hidden rounded-xl border bg-white dark:bg-gray-800 border-gray-200 dark:border-gray-700 hover:border-primary-300 dark:hover:border-primary-700 transition hover:-translate-y-px hover:shadow-md">

                            <div class="h-0.5 bg-gradient-to-r from-primary-400 to-blue-500"></div>

                            <div class="p-4">
                                <div class="flex items-start justify-between mb-3">
                                    <span class="text-sm font-bold text-gray-900 dark:text-white leading-tight">
                                        {{ $row->jenisKayu?->nama_kayu ?? '—' }}
                                    </span>
                                    <span class="text-[10px] font-mono text-gray-400 bg-gray-100 dark:bg-gray-700 px-1.5 py-0.5 rounded ml-2 whitespace-nowrap">
                                        {{ $row->panjang }} cm
                                    </span>
                                </div>

                                <div class="text-3xl font-black leading-none text-primary-600 dark:text-primary-400">
                                    {{ number_format($row->stok_batang) }}
                                </div>
                                <div class="text-[10px] text-gray-400 mt-0.5 mb-3">batang tersedia</div>

                                <div class="border-t border-gray-100 dark:border-gray-700 pt-3 space-y-1.5">
                                    <div class="flex justify-between items-center">
                                        <span class="text-[10.5px] text-gray-500 dark:text-gray-400">Kubikasi</span>
                                        <span class="text-[11px] font-semibold font-mono text-blue-600 dark:text-blue-400">
                                            {{ number_format($row->stok_kubikasi, 4) }} m³
                                        </span>
                                    </div>
                                    <div class="flex justify-between items-center">
                                        <span class="text-[10.5px] text-gray-500 dark:text-gray-400">Nilai Stok</span>
                                        <span class="text-[11px] font-semibold font-mono text-green-600 dark:text-green-400">
                                            Rp {{ number_format($row->nilai_stok, 0, ',', '.') }}
                                        </span>
                                    </div>
                                    <div class="flex justify-between items-center">
                                        <span class="text-[10.5px] text-gray-500 dark:text-gray-400">HPP Rata-rata</span>
                                        <span class="text-[11px] font-semibold font-mono text-violet-600 dark:text-violet-400">
                                            {{ number_format($row->hpp_average, 0, ',', '.') }}/m³
                                        </span>
                                    </div>
                                </div>

                                {{-- Lahan dots: hanya mode global --}}
                                @if($isGlobal && $lahans->count())
                                <div class="flex items-center gap-1 flex-wrap mt-3 pt-2.5 border-t border-gray-100 dark:border-gray-700">
                                    <span class="text-[9px] text-gray-400">Ada di:</span>
                                    @foreach($lahans as $kode)
                                    <span class="text-[9px] font-mono bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 text-blue-600 dark:text-blue-400 px-1.5 py-0.5 rounded">
                                        {{ $kode }}
                                    </span>
                                    @endforeach
                                </div>
                                @endif
                            </div>
                        </div>
                        @endforeach
                    </div>

                    {{-- Subtotal strip --}}
                    <div class="grid grid-cols-3 bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden">
                        <div class="px-4 py-2.5 border-r border-gray-200 dark:border-gray-700">
                            <div class="text-[9.5px] uppercase tracking-wider text-gray-400 mb-0.5">Total {{ $panjang }} cm</div>
                            <div class="text-sm font-bold font-mono text-green-500">{{ number_format($pBtg) }} batang</div>
                        </div>
                        <div class="px-4 py-2.5 border-r border-gray-200 dark:border-gray-700">
                            <div class="text-[9.5px] uppercase tracking-wider text-gray-400 mb-0.5">Kubikasi</div>
                            <div class="text-sm font-bold font-mono text-blue-500">{{ number_format($pM3, 4) }} m³</div>
                        </div>
                        <div class="px-4 py-2.5">
                            <div class="text-[9.5px] uppercase tracking-wider text-gray-400 mb-0.5">
                                {{ $activeLahanId ? 'Jenis Kayu' : 'Tersebar di' }}
                            </div>
                            <div class="text-sm font-bold font-mono text-amber-500">
                                {{ $activeLahanId ? $rows->count().' jenis' : $pLahan.' lahan' }}
                            </div>
                        </div>
                    </div>

                </div>
                @empty
                <div class="py-16 text-center text-sm text-gray-400 dark:text-gray-500 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl">
                    Tidak ada stok yang cocok dengan filter yang dipilih
                </div>
                @endforelse

            </div>{{-- /right --}}
        </div>{{-- /grid --}}
    </div>

</x-filament-panels::page>