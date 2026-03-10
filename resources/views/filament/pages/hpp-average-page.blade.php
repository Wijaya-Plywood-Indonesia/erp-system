{{-- resources/views/filament/pages/hpp-average-page.blade.php --}}
<x-filament-panels::page>

    <style>
        .tab-panel {
            transition: opacity 0.15s ease;
        }
    </style>

    {{-- ── Page Tabs: Stok / Log ── --}}
    <div class="border-b border-gray-200 dark:border-gray-700 mb-6">
        <nav class="flex gap-0 -mb-px">
            <button wire:click="$set('activeTab', 'stok')"
                @class(['px-5 py-3 text-sm font-medium border-b-2 transition', 'border-primary-500 text-primary-600 dark:text-primary-400'=> $activeTab === 'stok',
                'border-transparent text-gray-500 hover:text-gray-700 dark:hover:text-gray-300' => $activeTab !== 'stok'])>
                Stok per Lahan
            </button>
            <button wire:click="$set('activeTab', 'log')"
                @class(['px-5 py-3 text-sm font-medium border-b-2 transition', 'border-primary-500 text-primary-600 dark:text-primary-400'=> $activeTab === 'log',
                'border-transparent text-gray-500 hover:text-gray-700 dark:hover:text-gray-300' => $activeTab !== 'log'])>
                Log Transaksi HPP
            </button>
        </nav>
    </div>

    <div wire:loading.class.remove="opacity-100"
        wire:loading.class="opacity-50 pointer-events-none"
        wire:target="$set('activeTab', 'stok'), $set('activeTab', 'log')"
        class="opacity-100 transition-opacity duration-150">

        {{-- ════════ TAB: STOK ════════ --}}
        @if($activeTab === 'stok')

        @php
        $rows = $this->stokSummaries;
        $totalBtg = $rows->sum('stok_batang');
        $totalM3 = $rows->sum('stok_kubikasi');
        $totalVal = $rows->sum('nilai_stok');
        @endphp

        <div class="grid grid-cols-3 gap-4 mb-6">
            @foreach([
            ['label' => 'Stok Batang', 'value' => number_format($totalBtg)],
            ['label' => 'Total Kubikasi', 'value' => number_format($totalM3, 4).' m³'],
            ['label' => 'Nilai Persediaan', 'value' => 'Rp '.number_format($totalVal, 0, ',', '.')],
            ] as $stat)
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
                <div class="text-xs text-gray-500 dark:text-gray-400 mb-1">{{ $stat['label'] }}</div>
                <div class="text-lg font-bold text-gray-900 dark:text-white">{{ $stat['value'] }}</div>
            </div>
            @endforeach
        </div>

        <div class="grid grid-cols-[260px_1fr] gap-4 items-start">

            {{-- Lahan panel --}}
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden sticky top-20">
                <div class="px-4 py-3 border-b border-gray-100 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 flex items-center gap-2">
                    <span class="text-sm font-bold text-gray-900 dark:text-white flex-1">Pilih Lahan</span>
                    <span class="text-xs font-semibold text-gray-600 dark:text-gray-300 font-mono bg-gray-200 dark:bg-gray-700 px-1.5 py-0.5 rounded">{{ $this->lahans->count() }}</span>
                </div>
                <div class="p-2 border-b border-gray-100 dark:border-gray-700">
                    <div class="relative flex items-center">
                        <input wire:model.live.debounce.300ms="lahanSearch" type="text"
                            placeholder="Cari kode / nama lahan..."
                            class="w-full text-xs bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg pl-3 pr-7 py-2 outline-none focus:border-primary-500 text-gray-800 dark:text-gray-200 placeholder-gray-400 dark:placeholder-gray-600">
                        @if($lahanSearch)
                        <button wire:click="$set('lahanSearch', '')"
                            class="absolute right-2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 transition" title="Hapus pencarian">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10 8.586L5.707 4.293a1 1 0 00-1.414 1.414L8.586 10l-4.293 4.293a1 1 0 001.414 1.414L10 11.414l4.293 4.293a1 1 0 001.414-1.414L11.414 10l4.293-4.293a1 1 0 00-1.414-1.414L10 8.586z" clip-rule="evenodd" />
                            </svg>
                        </button>
                        @endif
                    </div>
                </div>
                <div class="max-h-[500px] overflow-y-auto divide-y divide-gray-50 dark:divide-gray-700">
                    @forelse($this->lahans as $lahan)
                    @php $s = $this->stokPerLahan[$lahan->id] ?? null; @endphp
                    <button wire:click="selectLahan({{ $lahan->id }})"
                        @class(['w-full flex items-center gap-2 px-3 py-2.5 text-left transition relative', 'bg-primary-50 dark:bg-primary-900/20'=> $activeLahanId === $lahan->id,
                        'hover:bg-gray-50 dark:hover:bg-gray-700' => $activeLahanId !== $lahan->id])>
                        @if($activeLahanId === $lahan->id)
                        <span class="absolute left-0 top-0 bottom-0 w-0.5 bg-primary-500 rounded-r"></span>
                        @endif
                        <span @class(['font-mono text-[10px] px-1.5 py-0.5 rounded min-w-[44px] text-center font-semibold', 'bg-primary-100 text-primary-700 dark:bg-primary-900 dark:text-primary-300'=> $activeLahanId === $lahan->id,
                            'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300' => $activeLahanId !== $lahan->id])>{{ $lahan->kode_lahan }}</span>
                        <div class="flex-1 min-w-0">
                            <div @class(['text-xs font-semibold truncate', 'text-primary-700 dark:text-primary-300'=> $activeLahanId === $lahan->id,
                                'text-gray-800 dark:text-gray-200' => $activeLahanId !== $lahan->id])>{{ $lahan->nama_lahan }}</div>
                            @if($s)
                            <div class="text-[10px] mt-0.5 font-medium text-gray-500 dark:text-gray-400">
                                <span class="text-gray-700 dark:text-gray-300 font-semibold">{{ number_format($s['btg']) }}</span> btg
                                <span class="mx-1 text-gray-300 dark:text-gray-600">·</span>
                                {{ $s['jenis']->join(', ') }}
                            </div>
                            @else
                            <div class="text-[10px] mt-0.5 font-medium text-amber-500 dark:text-amber-400">kosong</div>
                            @endif
                        </div>
                    </button>
                    @empty
                    <div class="p-4 text-center text-sm font-medium text-gray-500 dark:text-gray-400">Tidak ditemukan</div>
                    @endforelse
                </div>
            </div>

            {{-- Stok right panel --}}
            <div class="flex flex-col gap-4">

                @if($this->activeLahan)
                <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4 flex items-center gap-4">
                    <div>
                        <div class="font-bold text-gray-900 dark:text-white">{{ $this->activeLahan->nama_lahan }}</div>
                        <div class="text-xs text-gray-400 dark:text-gray-500 font-mono mt-0.5">{{ $this->activeLahan->kode_lahan }}</div>
                    </div>
                    <div class="ml-auto flex gap-6">
                        @foreach([[$totalBtg,'total batang'],[$this->jenisList->count(),'jenis kayu'],[$rows->count(),'kombinasi']] as [$val,$lbl])
                        <div class="text-right">
                            <div class="font-bold font-mono text-gray-900 dark:text-white">{{ $val }}</div>
                            <div class="text-[10px] text-gray-400 dark:text-gray-500">{{ $lbl }}</div>
                        </div>
                        @endforeach
                    </div>
                </div>
                @endif

                {{-- Jenis kayu pills --}}
                <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
                    <div class="px-4 py-2.5 border-b border-gray-100 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 text-xs font-semibold text-gray-700 dark:text-gray-300">
                        Filter Jenis Kayu
                    </div>
                    <div class="flex flex-wrap gap-2 p-3">
                        <button wire:click="selectJenis('')"
                            @class(['px-3 py-1.5 rounded-full text-xs font-medium border transition', 'bg-primary-500 border-primary-500 text-white'=> $activeJenis === '',
                            'bg-white dark:bg-gray-800 border-gray-200 dark:border-gray-600 text-gray-600 dark:text-gray-400 hover:border-green-300 dark:hover:border-green-600' => $activeJenis !== ''])>
                            Semua <span class="ml-1 font-mono text-[10px] opacity-75">{{ $rows->count() }}</span>
                        </button>
                        @foreach($this->jenisList as $jenis)
                        @php $cnt = $rows->filter(fn($r) => $r->jenisKayu?->nama_kayu === $jenis)->count(); @endphp
                        <button wire:click="selectJenis('{{ $jenis }}')"
                            @class(['px-3 py-1.5 rounded-full text-xs font-medium border transition', 'bg-primary-500 border-primary-500 text-white'=> $activeJenis === $jenis,
                            'bg-white dark:bg-gray-800 border-gray-200 dark:border-gray-600 text-gray-600 dark:text-gray-400 hover:border-green-300 dark:hover:border-green-600' => $activeJenis !== $jenis])>
                            {{ $jenis }} <span class="ml-1 font-mono text-[10px] opacity-75">{{ $cnt }}</span>
                        </button>
                        @endforeach
                    </div>
                </div>

                {{-- Stok table — tanpa kolom HPP Average --}}
                <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
                    <div class="px-4 py-3 border-b border-gray-100 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 flex items-center gap-2">
                        <span class="text-sm font-semibold text-gray-800 dark:text-gray-200 flex-1">
                            Stok {{ $activeJenis ?: 'Semua Jenis' }} — {{ $this->activeLahan?->nama_lahan }}
                        </span>
                        <span class="text-xs font-mono bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400 px-2 py-0.5 rounded">{{ $rows->count() }} kombinasi</span>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="bg-gray-50 dark:bg-gray-900">
                                    <th class="px-4 py-2.5 text-left text-[10.5px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Jenis Kayu</th>
                                    <th class="px-4 py-2.5 text-left text-[10.5px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Grade</th>
                                    <th class="px-4 py-2.5 text-right text-[10.5px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Panjang</th>
                                    <th class="px-4 py-2.5 text-right text-[10.5px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Stok Batang</th>
                                    <th class="px-4 py-2.5 text-right text-[10.5px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Kubikasi (m³)</th>
                                    <th class="px-4 py-2.5 text-right text-[10.5px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Nilai Stok (Poin)</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50 dark:divide-gray-700">
                                @forelse($rows as $row)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition">
                                    <td class="px-4 py-2.5 font-semibold text-gray-900 dark:text-white">{{ $row->jenisKayu?->nama_kayu ?? '-' }}</td>
                                    <td class="px-4 py-2.5">
                                        <span @class(['inline-flex px-2 py-0.5 rounded text-xs font-semibold', 'bg-green-50 text-green-700 dark:bg-green-900/30 dark:text-green-400'=> $row->grade === 'A',
                                            'bg-amber-50 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400' => $row->grade === 'B',
                                            'bg-blue-50 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400' => $row->grade === 'C'])>Grade {{ $row->grade }}</span>
                                    </td>
                                    <td class="px-4 py-2.5 text-right font-mono text-gray-700 dark:text-gray-300">{{ $row->panjang }} cm</td>
                                    <td class="px-4 py-2.5 text-right">
                                        <span class="font-bold text-gray-900 dark:text-white">{{ number_format($row->stok_batang) }}</span>
                                        <span class="text-xs text-gray-400 dark:text-gray-500 ml-1">btg</span>
                                    </td>
                                    <td class="px-4 py-2.5 text-right font-mono text-gray-700 dark:text-gray-300">{{ number_format($row->stok_kubikasi, 4) }}</td>
                                    {{-- Nilai stok = total poin (kubikasi × harga × 1000) --}}
                                    <td class="px-4 py-2.5 text-right font-mono font-semibold text-green-600 dark:text-green-400">
                                        Rp {{ number_format($row->nilai_stok, 0, ',', '.') }}
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="6" class="px-4 py-10 text-center text-sm text-gray-400 dark:text-gray-500">Lahan ini belum memiliki stok</td>
                                </tr>
                                @endforelse

                                @if($rows->count())
                                <tr class="bg-green-50 dark:bg-green-900/20 border-t-2 border-green-400 dark:border-green-600">
                                    <td colspan="3" class="px-4 py-2.5 text-xs font-bold text-green-800 dark:text-green-300 font-mono uppercase tracking-wide">Grand Total</td>
                                    <td class="px-4 py-2.5 text-right font-bold font-mono text-green-800 dark:text-green-300">{{ number_format($totalBtg) }} btg</td>
                                    <td class="px-4 py-2.5 text-right font-bold font-mono text-green-800 dark:text-green-300">{{ number_format($totalM3, 4) }}</td>
                                    <td class="px-4 py-2.5 text-right font-bold font-mono text-green-800 dark:text-green-300">Rp {{ number_format($totalVal, 0, ',', '.') }}</td>
                                </tr>
                                @endif
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>{{-- /stok right --}}
        </div>

        @endif {{-- /stok tab --}}

        {{-- ════════ TAB: LOG HPP ════════ --}}
        @if($activeTab === 'log')

        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-3 mb-4 flex items-center gap-3 flex-wrap">
            <span class="text-sm font-medium text-gray-600 dark:text-gray-400">Filter:</span>
            <select wire:model.live="filterGrade"
                class="text-sm bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-600 text-gray-700 dark:text-gray-300 rounded-lg px-3 py-1.5 outline-none focus:border-primary-500">
                <option value="">Semua Grade</option>
                <option value="A">Grade A</option>
                <option value="B">Grade B</option>
                <option value="C">Grade C</option>
            </select>
            <select wire:model.live="filterPanjang"
                class="text-sm bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-600 text-gray-700 dark:text-gray-300 rounded-lg px-3 py-1.5 outline-none focus:border-primary-500">
                <option value="">Semua Panjang</option>
                @foreach(\App\Models\HppAverageLog::distinct()->pluck('panjang')->sort() as $p)
                <option value="{{ $p }}">{{ $p }} cm</option>
                @endforeach
            </select>
            <span class="text-xs text-gray-400 dark:text-gray-500 ml-auto font-mono">{{ $this->logs->count() }} entri</span>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">

            <div class="flex border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 px-4">
                @foreach(['semua' => 'Semua', 'masuk' => 'Masuk', 'keluar' => 'Keluar'] as $val => $label)
                <button wire:click="$set('logTab', '{{ $val }}')"
                    @class(['px-4 py-2.5 text-sm font-medium border-b-2 -mb-px transition flex items-center gap-2', 'border-primary-500 text-primary-600 dark:text-primary-400'=> $logTab === $val,
                    'border-transparent text-gray-500 hover:text-gray-700 dark:hover:text-gray-300' => $logTab !== $val])>
                    {{ $label }}
                    <span @class(['text-[10px] font-mono px-1.5 py-0.5 rounded-full', 'bg-primary-100 text-primary-700 dark:bg-primary-900 dark:text-primary-300'=> $logTab === $val,
                        'bg-gray-200 text-gray-500 dark:bg-gray-700 dark:text-gray-400' => $logTab !== $val])>{{ $this->logCounts[$val] }}</span>
                </button>
                @endforeach
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-gray-50 dark:bg-gray-900">
                            <th class="px-4 py-2.5 text-left text-[10.5px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Tanggal</th>
                            <th class="px-4 py-2.5 text-left text-[10.5px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Jenis Kayu</th>
                            <th class="px-4 py-2.5 text-left text-[10.5px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Grade</th>
                            <th class="px-4 py-2.5 text-right text-[10.5px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Panjang</th>
                            <th class="px-4 py-2.5 text-left text-[10.5px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Tipe</th>
                            <th class="px-4 py-2.5 text-left text-[10.5px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Keterangan</th>
                            <th class="px-4 py-2.5 text-right text-[10.5px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Batang</th>
                            <th class="px-4 py-2.5 text-right text-[10.5px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">M³</th>
                            <th class="px-4 py-2.5 text-right text-[10.5px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Poin Trx</th>
                            <th class="px-4 py-2.5 text-right text-[10.5px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Total Poin Stok</th>
                            <th class="px-4 py-2.5 text-right text-[10.5px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">HPP Average</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50 dark:divide-gray-700">
                        @forelse($this->logs as $log)
                        @php $isM = $log->tipe_transaksi === 'masuk'; @endphp
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition">
                            <td class="px-4 py-2.5 font-mono text-xs text-gray-500 dark:text-gray-400">{{ $log->tanggal->format('d/m/Y') }}</td>
                            <td class="px-4 py-2.5 font-semibold text-gray-900 dark:text-white">{{ $log->jenisKayu?->nama_kayu ?? '-' }}</td>
                            <td class="px-4 py-2.5">
                                <span @class(['inline-flex px-2 py-0.5 rounded text-xs font-semibold', 'bg-green-50 text-green-700 dark:bg-green-900/30 dark:text-green-400'=> $log->grade === 'A',
                                    'bg-amber-50 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400' => $log->grade === 'B',
                                    'bg-blue-50 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400' => $log->grade === 'C'])>Grade {{ $log->grade }}</span>
                            </td>
                            <td class="px-4 py-2.5 text-right font-mono text-gray-600 dark:text-gray-400">{{ $log->panjang }} cm</td>
                            <td class="px-4 py-2.5">
                                <span @class(['inline-flex px-2 py-0.5 rounded text-xs font-semibold', 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400'=> $isM,
                                    'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400' => !$isM])>{{ $isM ? 'Masuk' : 'Keluar' }}</span>
                            </td>
                            <td class="px-4 py-2.5 text-xs text-gray-500 dark:text-gray-400 max-w-[180px] truncate">{{ $log->keterangan }}</td>
                            <td class="px-4 py-2.5 text-right font-mono text-gray-700 dark:text-gray-300">{{ number_format($log->total_batang) }}</td>
                            <td class="px-4 py-2.5 text-right font-mono text-gray-700 dark:text-gray-300">{{ number_format($log->total_kubikasi, 4) }}</td>
                            {{-- Poin transaksi = nilai_stok di log (kub × harga × 1000) --}}
                            <td class="px-4 py-2.5 text-right font-mono font-semibold {{ $isM ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                                Rp {{ number_format($log->nilai_stok, 0, ',', '.') }}
                            </td>
                            {{-- Total poin stok setelah transaksi --}}
                            <td class="px-4 py-2.5 text-right font-mono text-gray-600 dark:text-gray-400">
                                Rp {{ number_format($log->nilai_stok_after, 0, ',', '.') }}
                            </td>
                            <td class="px-4 py-2.5 text-right">
                                <span class="inline-flex items-baseline gap-1 bg-violet-50 dark:bg-violet-900/20 border border-violet-200 dark:border-violet-700 text-violet-700 dark:text-violet-300 font-mono text-xs px-2 py-1 rounded-md">
                                    Rp {{ number_format($log->hpp_average, 0, ',', '.') }}
                                    <span class="text-[9px] opacity-60">/m³</span>
                                </span>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="11" class="px-4 py-10 text-center text-sm text-gray-400 dark:text-gray-500">Tidak ada log sesuai filter</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @endif {{-- /log tab --}}

    </div>{{-- /seamless loading wrapper --}}

</x-filament-panels::page>