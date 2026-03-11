{{-- resources/views/filament/pages/stok-kayu-page.blade.php --}}
<x-filament-panels::page>

    @php
    $summaries = $this->summaries;
    $grouped = $this->groupedSummaries;
    $lahanPerKombinasi = $this->lahanPerKombinasi;

    $totalBtg = $summaries->sum('stok_batang');
    $totalM3 = $summaries->sum('stok_kubikasi');
    // $totalNilai = $summaries->sum('nilai_stok'); {{-- nonaktif --}}
    @endphp

    <div class="flex flex-col gap-5">

        {{-- ── Stats ── --}}
        <div class="grid grid-cols-3 gap-3">

            <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-4">
                <div class="text-[10.5px] uppercase tracking-widest text-gray-400 dark:text-gray-500 mb-1">Total Kayu Tersedia</div>
                <div class="text-2xl font-extrabold text-green-500">
                    {{ number_format($totalBtg) }}
                    <span class="text-sm font-normal text-gray-400">batang</span>
                </div>
                <div class="text-[11px] text-gray-400 mt-1">
                    {{ $this->activeLahan ? $this->activeLahan->nama_lahan : 'Dari semua lahan aktif' }}
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-4">
                <div class="text-[10.5px] uppercase tracking-widest text-gray-400 dark:text-gray-500 mb-1">Total Kubikasi</div>
                <div class="text-2xl font-extrabold text-blue-500">
                    {{ number_format($totalM3, 4) }}
                    <span class="text-sm font-normal text-gray-400">m³</span>
                </div>
                <div class="text-[11px] text-gray-400 mt-1">Semua ukuran & grade</div>
            </div>

            <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-4">
                <div class="text-[10.5px] uppercase tracking-widest text-gray-400 dark:text-gray-500 mb-1">Jenis Kayu</div>
                <div class="text-2xl font-extrabold text-amber-500">
                    {{ $this->jenisList->count() }}
                    <span class="text-sm font-normal text-gray-400">jenis</span>
                </div>
                <div class="text-[11px] text-gray-400 mt-1 truncate">
                    {{ $this->jenisList->join(', ') ?: '—' }}
                </div>
            </div>

            {{-- DINONAKTIFKAN: Nilai Persediaan
            <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-4 opacity-40">
                <div class="text-[10.5px] uppercase tracking-widest text-gray-400 mb-1">Nilai Persediaan</div>
                <div class="text-2xl font-extrabold text-violet-500">
                    Rp {{ number_format($totalNilai, 0, ',', '.') }}
        </div>
        <div class="text-[11px] text-gray-400 mt-1">stok_kubikasi × hpp_average</div>
    </div>
    --}}

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

            {{-- Search --}}
            <div class="p-2 border-b border-gray-100 dark:border-gray-700">
                <div class="relative">
                    <input wire:model.live.debounce.300ms="lahanSearch" type="text"
                        placeholder="Cari kode / nama..."
                        class="w-full text-xs bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg pl-3 pr-7 py-2 outline-none focus:border-primary-500 text-gray-800 dark:text-gray-200 placeholder-gray-400">
                    @if($lahanSearch)
                    <button wire:click="$set('lahanSearch', '')"
                        class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                        <x-heroicon-m-x-mark class="w-3.5 h-3.5" />
                    </button>
                    @endif
                </div>
            </div>

            <div class="max-h-[520px] overflow-y-auto">

                {{-- Item: Semua Lahan (global) --}}
                <button wire:click="selectLahan(null)"
                    @class(['w-full flex items-center gap-2.5 px-3 py-2.5 text-left transition relative border-b border-gray-50 dark:border-gray-700', 'bg-primary-50 dark:bg-primary-900/20 border-l-2 border-l-primary-500'=> $activeLahanId === null,
                    'hover:bg-gray-50 dark:hover:bg-gray-700/50' => $activeLahanId !== null])>
                    <span @class(['flex items-center justify-center text-xs w-8 h-6 rounded font-bold', 'bg-primary-100 text-primary-600 dark:bg-primary-900 dark:text-primary-300'=> $activeLahanId === null,
                        'bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400' => $activeLahanId !== null])>
                        🌐
                    </span>
                    <div class="flex-1 min-w-0">
                        <div @class(['text-xs font-bold truncate', 'text-primary-700 dark:text-primary-300'=> $activeLahanId === null,
                            'text-gray-700 dark:text-gray-200' => $activeLahanId !== null])>
                            Semua Lahan
                        </div>
                        <div class="text-[10px] text-gray-400 mt-0.5">
                            {{ $this->summaries->sum('stok_batang') }} btg total
                        </div>
                    </div>
                </button>

                {{-- Daftar lahan --}}
                @forelse($this->lahans as $lahan)
                @php $s = $this->stokPerLahan[$lahan->id] ?? null; @endphp
                <button wire:click="selectLahan({{ $lahan->id }})"
                    @class(['w-full flex items-center gap-2.5 px-3 py-2.5 text-left transition relative border-b border-gray-50 dark:border-gray-700', 'bg-primary-50 dark:bg-primary-900/20 border-l-2 border-l-primary-500'=> $activeLahanId === $lahan->id,
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
                        <div class="text-[10px] text-amber-500 mt-0.5 font-medium">Stok kosong</div>
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
                    <div class="text-[11px] text-gray-400 mt-0.5">Menampilkan stok gabungan dari semua lahan aktif</div>
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

            {{-- ── Filter bar compact ── --}}
            <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl px-4 py-2.5 flex items-center gap-3 flex-wrap">

                {{-- Filter Ukuran --}}
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

                {{-- Filter Grade --}}
                <span class="text-[11px] font-semibold text-gray-400">Grade:</span>
                <div class="flex gap-1.5">
                    <button wire:click="$set('filterGrade', '')"
                        @class(['px-2.5 py-1 rounded-full text-[11.5px] font-medium border transition', 'bg-primary-500 border-primary-500 text-white'=> $filterGrade === '',
                        'border-gray-200 dark:border-gray-600 text-gray-500 dark:text-gray-400 hover:border-primary-400' => $filterGrade !== ''])>
                        Semua
                    </button>
                    <button wire:click="$set('filterGrade', 'A')"
                        @class(['px-2.5 py-1 rounded-full text-[11.5px] font-bold border transition', 'bg-green-600 border-green-600 text-white'=> $filterGrade === 'A',
                        'border-gray-200 dark:border-gray-600 text-gray-500 dark:text-gray-400 hover:border-green-400' => $filterGrade !== 'A'])>
                        A
                    </button>
                    <button wire:click="$set('filterGrade', 'B')"
                        @class(['px-2.5 py-1 rounded-full text-[11.5px] font-bold border transition', 'bg-amber-500 border-amber-500 text-white'=> $filterGrade === 'B',
                        'border-gray-200 dark:border-gray-600 text-gray-500 dark:text-gray-400 hover:border-amber-400' => $filterGrade !== 'B'])>
                        B
                    </button>
                </div>

                <div class="w-px h-5 bg-gray-200 dark:bg-gray-700"></div>

                {{-- Filter Jenis --}}
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
                    {{ $summaries->count() }} jenis · {{ number_format($totalBtg) }} batang
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
                        <strong class="text-gray-600 dark:text-gray-300">{{ $rows->count() }} jenis kayu</strong>
                        &nbsp;·&nbsp;
                        <strong class="text-gray-600 dark:text-gray-300">{{ number_format($pBtg) }} batang</strong>
                        &nbsp;·&nbsp;
                        {{ number_format($pM3, 4) }} m³
                    </span>
                </div>

                {{-- Cards grid --}}
                <div class="grid gap-3" style="grid-template-columns: repeat(auto-fill, minmax(200px, 1fr))">
                    @foreach($rows as $row)
                    @php
                    $isA = $row->grade === 'A';
                    $key = $row->id_jenis_kayu . '_' . $row->grade . '_' . $row->panjang;
                    $lahans = $lahanPerKombinasi[$key] ?? collect();
                    @endphp
                    <div @class(['relative overflow-hidden rounded-xl border transition hover:-translate-y-px', 'bg-white dark:bg-gray-800 border-green-200 dark:border-green-800/50 hover:border-green-300 dark:hover:border-green-700'=> $isA,
                        'bg-white dark:bg-gray-800 border-amber-200 dark:border-amber-800/50 hover:border-amber-300 dark:hover:border-amber-700' => !$isA])>

                        {{-- Colour strip top --}}
                        <div @class(['h-0.5', 'bg-gradient-to-r from-green-400 to-green-600'=> $isA,
                            'bg-gradient-to-r from-amber-400 to-amber-600' => !$isA])></div>

                        <div class="p-4">

                            {{-- Top row --}}
                            <div class="flex items-center justify-between mb-3">
                                <span class="text-sm font-bold text-gray-900 dark:text-white">
                                    {{ $row->jenisKayu?->nama_kayu ?? '—' }}
                                </span>
                                <span @class(['text-[10px] font-bold px-2 py-0.5 rounded border', 'bg-green-50 text-green-700 border-green-300 dark:bg-green-900/30 dark:text-green-400 dark:border-green-700'=> $isA,
                                    'bg-amber-50 text-amber-700 border-amber-300 dark:bg-amber-900/30 dark:text-amber-400 dark:border-amber-700' => !$isA])>
                                    Grade {{ $row->grade }}
                                </span>
                            </div>

                            {{-- Big number --}}
                            <div @class(['text-3xl font-extrabold leading-none tabular-nums', 'text-green-500'=> $isA,
                                'text-amber-500' => !$isA])>
                                {{ number_format($row->stok_batang) }}
                            </div>
                            <div class="text-[10px] text-gray-400 mt-1 mb-3">batang tersedia</div>

                            <div class="h-px bg-gray-100 dark:bg-gray-700 mb-3"></div>

                            {{-- Metrics --}}
                            <div class="flex flex-col gap-1.5">
                                <div class="flex justify-between items-center">
                                    <span class="text-[10.5px] text-gray-400">Kubikasi</span>
                                    <span class="text-[11px] font-mono font-semibold text-blue-500 dark:text-blue-400">{{ number_format($row->stok_kubikasi, 4) }} m³</span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-[10.5px] text-gray-400">Nilai Stok</span>
                                    <span class="text-[11px] font-mono font-semibold text-green-600 dark:text-green-400">Rp {{ number_format($row->nilai_stok, 0, ',', '.') }}</span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-[10.5px] text-gray-400">Harga Rata-rata</span>
                                    <span class="text-[11px] font-mono font-semibold text-violet-500 dark:text-violet-400">{{ number_format($row->hpp_average, 0, ',', '.') }}/m³</span>
                                </div>
                            </div>

                            {{-- Lahan dots: hanya tampil saat mode global (activeLahanId null) --}}
                            @if(!$activeLahanId && $lahans->count())
                            <div class="flex items-center gap-1.5 mt-3 flex-wrap">
                                <span class="text-[9px] text-gray-400">Ada di:</span>
                                @foreach($lahans as $kode)
                                <span class="text-[9px] font-mono bg-blue-50 dark:bg-blue-900/30 border border-blue-200 dark:border-blue-700 text-blue-600 dark:text-blue-400 px-1.5 py-0.5 rounded">{{ $kode }}</span>
                                @endforeach
                            </div>
                            @endif

                        </div>
                    </div>
                    @endforeach
                </div>

                {{-- Subtotal strip --}}
                <div class="grid grid-cols-3 gap-0 bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden">
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
    </div>{{-- /flex --}}

</x-filament-panels::page>