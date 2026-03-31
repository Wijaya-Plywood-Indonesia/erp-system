{{-- resources/views/filament/pages/hpp-average-page.blade.php --}}
<x-filament-panels::page>

    @php
    $logsByLahan = $this->logs->groupBy('id_lahan');
    @endphp

    {{-- Filter bar --}}
    <div class="bg-white dark:bg-gray-800 rounded-sm border border-gray-200 dark:border-gray-700 p-3 mb-5 flex items-center gap-3 flex-wrap">
        <span class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider text-[10px]">Filter:</span>

        <select wire:model.live="filterJenisKayu"
            class="text-xs bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-600 text-gray-700 dark:text-gray-300 rounded-sm px-3 py-1.5 outline-none focus:border-primary-500">
            <option value="">Semua Jenis Kayu</option>
            @foreach(\App\Models\JenisKayu::orderBy('nama_kayu')->pluck('nama_kayu', 'id') as $id => $nama)
            <option value="{{ $id }}">{{ $nama }}</option>
            @endforeach
        </select>

        <select wire:model.live="filterPanjang"
            class="text-xs bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-600 text-gray-700 dark:text-gray-300 rounded-sm px-3 py-1.5 outline-none focus:border-primary-500">
            <option value="">Semua Ukuran</option>
            @foreach(\App\Models\HppAverageLog::whereNull('grade')->distinct()->orderBy('panjang')->pluck('panjang') as $p)
            <option value="{{ $p }}">{{ $p }} cm</option>
            @endforeach
        </select>

        <div x-data="{ 
                open: false, 
                search: '', 
                selected: @entangle('filterLahan').live,
                items: {{ \App\Models\Lahan::orderBy('kode_lahan')->get()->map(fn($l) => ['id' => $l->id, 'label' => $l->kode_lahan . ' - ' . $l->nama_lahan])->toJson() }},
                get filteredItems() {
                    if (this.search.trim() === '') return this.items;
                    return this.items.filter(i => i.label.toLowerCase().includes(this.search.toLowerCase()));
                }
            }" class="relative min-w-[280px]">

            {{-- Button Trigger --}}
            <button @click="open = !open" type="button" class="w-full text-xs bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-600 text-gray-700 dark:text-gray-300 rounded-sm px-3 py-1.5 flex justify-between items-center outline-none focus:ring-1 focus:ring-amber-500 focus:border-amber-500">
                <span class="font-bold" x-text="selected ? items.find(i => i.id == selected)?.label : 'Pilih Lahan / Semua'"></span>
                <svg class="w-3 h-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                </svg>
            </button>

            {{-- Dropdown Menu --}}
            <div x-show="open"
                x-transition:enter="transition ease-out duration-100"
                x-transition:enter-start="opacity-0 scale-95"
                x-transition:enter-end="opacity-100 scale-100"
                @click.away="open = false"
                class="absolute z-50 mt-1 w-full bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 shadow-xl rounded-sm overflow-hidden"
                style="display: none;">

                {{-- Search Input with Clear Button --}}
                <div class="p-2 border-b border-gray-100 dark:border-gray-700 bg-gray-50 dark:bg-gray-900">
                    <div class="relative flex items-center">
                        <input x-model="search" type="text" placeholder="Cari kode atau nama..." class="w-full bg-white dark:bg-gray-800 text-[11px] border border-gray-200 dark:border-gray-600 rounded-sm p-1.5 pr-7 outline-none focus:border-amber-500">

                        {{-- Clear Button (X) --}}
                        <button
                            x-show="search.length > 0"
                            @click="search = ''"
                            type="button"
                            class="absolute right-2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>
                </div>

                {{-- List --}}
                <div class="max-h-60 overflow-y-auto custom-scrollbar font-sans">
                    <div @click="selected = ''; open = false; search = ''" class="px-3 py-2 text-[10px] font-black text-gray-400 uppercase hover:bg-gray-100 dark:hover:bg-gray-700 cursor-pointer border-b border-gray-50 dark:border-gray-800">
                        TAMPILKAN SEMUA LAHAN
                    </div>
                    <template x-for="item in filteredItems" :key="item.id">
                        <div @click="selected = item.id; open = false; search = ''" class="px-3 py-2 text-xs hover:bg-gray-100 dark:hover:bg-gray-700 cursor-pointer border-b border-gray-50 dark:border-gray-800 last:border-0">
                            <span class="font-bold text-gray-800 dark:text-gray-200" x-text="item.label"></span>
                        </div>
                    </template>

                    {{-- No Results --}}
                    <div x-show="filteredItems.length === 0" class="px-3 py-4 text-center text-[10px] text-gray-400 uppercase italic">
                        Tidak ditemukan
                    </div>
                </div>
            </div>
        </div>

        {{-- LOOPING LOG PER LAHAN (Gaya Meja) --}}
        <div class="space-y-12">
            @forelse($logsByLahan as $lahanId => $lahanLogs)
            @php
            $lahan = \App\Models\Lahan::find($lahanId);
            $totalMasuk = $lahanLogs->where('tipe_transaksi', 'masuk')->sum('total_batang');
            $totalKeluar = $lahanLogs->where('tipe_transaksi', 'keluar')->sum('total_batang');
            $saldoBtg = $totalMasuk - $totalKeluar;
            $lastLogLahan = $lahanLogs->last();
            @endphp

            <div class="bg-white dark:bg-gray-800 rounded-sm border border-gray-200 dark:border-gray-700 overflow-hidden shadow-sm">

                {{-- HEADER MEJA (Dark Header) --}}
                <div class="bg-gray-800 dark:bg-gray-950 text-white px-4 py-3 flex items-center justify-start border-b border-gray-700 dark:border-black">
                    <h2 class="text-sm font-black tracking-[0.2em] uppercase">
                        LAHAN {{ $lahan?->kode_lahan ?? 'N/A' }} — {{ $lahan?->nama_lahan ?? 'N/A' }}
                    </h2>
                </div>

                {{-- SUMMARY BAR PER LAHAN --}}
                <div class="px-4 py-2.5 border-b border-gray-100 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 flex items-center gap-3 flex-wrap">
                    <span class="inline-flex items-center gap-1 bg-green-100 dark:bg-green-900/40 text-green-700 dark:text-green-400 text-[10px] font-black px-2.5 py-1 rounded-sm uppercase tracking-tighter">
                        ↑ {{ number_format($totalMasuk) }} masuk
                    </span>
                    <span class="inline-flex items-center gap-1 bg-red-100 dark:bg-red-900/40 text-red-700 dark:text-red-400 text-[10px] font-black px-2.5 py-1 rounded-sm uppercase tracking-tighter">
                        ↓ {{ number_format($totalKeluar) }} keluar
                    </span>
                    <span class="inline-flex items-center gap-1 bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 text-[10px] font-black px-2.5 py-1 rounded-sm uppercase tracking-tighter">
                        {{ number_format($saldoBtg) }} saldo
                    </span>

                    @if($lastLogLahan)
                    <span class="ml-auto inline-flex items-center gap-1 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-700 text-amber-700 dark:text-amber-300 text-[10px] font-black px-3 py-1 rounded-sm uppercase tracking-tight">
                        HPP TERAKHIR: Rp {{ number_format($lastLogLahan->hpp_average, 0, ',', '.') }}/m³
                    </span>
                    @endif
                </div>

                {{-- TABEL LOG PER LAHAN --}}
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="bg-gray-50 dark:bg-gray-900 text-[10px] font-black uppercase tracking-widest text-gray-500 dark:text-gray-400 border-b border-gray-100 dark:border-gray-700">
                                <th class="px-4 py-3 text-left whitespace-nowrap">Tanggal</th>
                                <th class="px-4 py-3 text-left whitespace-nowrap">Jenis Kayu</th>
                                <th class="px-4 py-3 text-right whitespace-nowrap">Ukuran</th>
                                <th class="px-4 py-3 text-left whitespace-nowrap">Tipe</th>
                                <th class="px-4 py-3 text-left">Keterangan</th>

                                <th class="px-4 py-3 text-right border-l border-gray-100 dark:border-gray-700 whitespace-nowrap text-gray-400">Qty</th>

                                <th class="px-4 py-3 text-right border-l border-gray-100 dark:border-gray-700 bg-blue-50/30 dark:bg-blue-900/5 whitespace-nowrap">
                                    Stok Batang<div class="text-[10px] font-medium normal-case text-gray-500 tracking-normal">Sebelum → Sesudah</div>
                                </th>

                                <th class="px-4 py-3 text-right border-l border-gray-100 dark:border-gray-700 whitespace-nowrap text-blue-600">
                                    Kubikasi<div class="text-[10px] font-medium normal-case text-gray-500 tracking-normal text-blue-400">jumlah m³</div>
                                </th>

                                <th class="px-4 py-3 text-right border-l border-gray-100 dark:border-gray-700 whitespace-nowrap">
                                    Stok Kubikasi<div class="text-[10px] font-medium normal-case text-gray-500 tracking-normal">Sebelum → Sesudah</div>
                                </th>

                                <th class="px-4 py-3 text-right border-l border-gray-100 dark:border-gray-700 whitespace-nowrap">
                                    Total Poin<div class="text-[10px] font-medium normal-case text-gray-500 tracking-normal">Sebelum → Sesudah</div>
                                </th>

                                <th class="px-4 py-3 text-right border-l border-gray-100 dark:border-gray-700 bg-amber-50/50 dark:bg-amber-900/10 whitespace-nowrap text-amber-600">
                                    HPP Average<div class="text-[10px] font-medium normal-case text-gray-500 tracking-normal text-amber-500">per m³</div>
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            @foreach($lahanLogs as $log)
                            @php $isM = $log->tipe_transaksi === 'masuk'; @endphp
                            <tr @class(['transition', 'hover:bg-green-50/30 dark:hover:bg-green-900/10'=> $isM, 'hover:bg-red-50/30 dark:hover:bg-red-900/10' => !$isM])>

                                <td class="px-4 py-3 font-mono text-xs text-gray-500 dark:text-gray-400 whitespace-nowrap">
                                    {{ $log->tanggal->format('d/m/Y') }}
                                </td>

                                <td class="px-4 py-3 font-black text-gray-900 dark:text-white whitespace-nowrap uppercase">
                                    {{ $log->jenisKayu?->nama_kayu ?? '-' }}
                                </td>

                                <td class="px-4 py-3 text-right font-black text-xs text-gray-500 dark:text-gray-400 whitespace-nowrap tabular-nums">
                                    {{ $log->panjang }} cm
                                </td>

                                <td class="px-4 py-3 whitespace-nowrap">
                                    <span @class(['inline-flex items-center px-2 py-0.5 rounded-sm text-[9px] font-black uppercase tracking-tight', 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400'=> $isM, 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400' => !$isM])>
                                        {{ $isM ? '↑ Masuk' : '↓ Keluar' }}
                                    </span>
                                </td>

                                <td class="px-4 py-3 text-[11px] font-black uppercase text-gray-700 dark:text-gray-300 truncate max-w-[150px]">
                                    @if($log->referensi instanceof \App\Models\NotaKayu && $log->referensi->kayuMasuk?->seri)
                                    SERI: {{ $log->referensi->kayuMasuk->seri }}
                                    @else
                                    {{ $log->keterangan ?? '—' }}
                                    @endif
                                </td>

                                <td @class(['px-4 py-3 text-right font-black text-sm border-l border-gray-50 dark:border-gray-800 whitespace-nowrap tabular-nums', 'text-green-600 dark:text-green-400'=> $isM, 'text-red-600 dark:text-red-400' => !$isM])>
                                    {{ $isM ? '+' : '-' }}{{ number_format($log->total_batang) }}
                                </td>

                                <td class="px-4 py-3 border-l border-gray-50 dark:border-gray-800 bg-blue-50/10 dark:bg-blue-900/5 whitespace-nowrap">
                                    <div class="flex items-center justify-end gap-1.5 font-mono text-xs tabular-nums">
                                        <span class="text-gray-400 dark:text-gray-500 font-medium">{{ number_format($log->stok_batang_before) }}</span>
                                        <span class="text-gray-300 dark:text-gray-700 text-[10px]">→</span>
                                        <span @class(['font-black', 'text-green-600 dark:text-green-400'=> $isM, 'text-red-600 dark:text-red-400' => !$isM])>
                                            {{ number_format($log->stok_batang_after) }}
                                        </span>
                                    </div>
                                </td>

                                <td class="px-4 py-3 border-l border-gray-50 dark:border-gray-800 whitespace-nowrap tabular-nums text-right">
                                    <div class="flex flex-col items-end">
                                        <span @class(['font-black', 'text-blue-600 dark:text-blue-400'=> $isM, 'text-orange-600 dark:text-orange-400' => !$isM])>
                                            {{ number_format($log->total_kubikasi, 4) }}
                                        </span>
                                        <div class="text-[9px] text-gray-400 uppercase tracking-tighter">{{ $isM ? 'masuk' : 'keluar' }}</div>
                                    </div>
                                </td>

                                <td class="px-4 py-3 border-l border-gray-50 dark:border-gray-800 whitespace-nowrap tabular-nums">
                                    <div class="flex items-center justify-end gap-1.5 font-mono text-xs">
                                        <span class="text-gray-400 dark:text-gray-500 font-medium">{{ number_format($log->stok_kubikasi_before, 4) }}</span>
                                        <span class="text-gray-300 dark:text-gray-700 text-[10px]">→</span>
                                        <span @class(['font-black', 'text-blue-600 dark:text-blue-400'=> $isM, 'text-orange-600 dark:text-orange-400' => !$isM])>
                                            {{ number_format($log->stok_kubikasi_after, 4) }}
                                        </span>
                                    </div>
                                </td>

                                <td class="px-4 py-3 border-l border-gray-50 dark:border-gray-800 whitespace-nowrap tabular-nums">
                                    <div class="flex items-center justify-end gap-1.5 font-mono text-xs text-right">
                                        <span class="text-gray-400 dark:text-gray-500 font-medium tracking-tighter">{{ number_format($log->nilai_stok_before, 0, ',', '.') }}</span>
                                        <span class="text-gray-300 dark:text-gray-700 text-[10px]">→</span>
                                        <span @class(['font-black tracking-tight', 'text-green-600 dark:text-green-400'=> $isM, 'text-red-600 dark:text-red-400' => !$isM])>
                                            {{ number_format($log->nilai_stok_after, 0, ',', '.') }}
                                        </span>
                                    </div>
                                </td>

                                <td class="px-4 py-3 text-right border-l border-gray-50 dark:border-gray-800 bg-amber-50/20 dark:bg-amber-900/5 whitespace-nowrap">
                                    <span class="font-black text-xs text-amber-700 dark:text-amber-400 tabular-nums">
                                        {{ number_format($log->hpp_average, 0, ',', '.') }}
                                    </span>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>

                        {{-- FOOTER PER LAHAN --}}
                        <tfoot>
                            @php
                            $m3Saldo = $lahanLogs->where('tipe_transaksi','masuk')->sum('total_kubikasi') - $lahanLogs->where('tipe_transaksi','keluar')->sum('total_kubikasi');
                            $poinSaldo = $lahanLogs->where('tipe_transaksi','masuk')->sum('nilai_stok') - $lahanLogs->where('tipe_transaksi','keluar')->sum('nilai_stok');
                            @endphp
                            <tr class="text-[10px] font-black border-t-2 bg-gray-50 dark:bg-gray-900/60 border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-300 uppercase tracking-widest">
                                <td colspan="5" class="px-4 py-4 text-gray-500 italic">Saldo Akhir Lahan {{ $lahan?->kode_lahan }}</td>
                                <td class="px-4 py-4 text-right border-l border-gray-100 dark:border-gray-700 font-black">{{ number_format($saldoBtg) }} btg</td>
                                <td class="px-4 py-4 text-right border-l border-gray-100 dark:border-gray-700 bg-blue-50/30 dark:bg-blue-900/10 text-blue-600 dark:text-blue-400 font-black">
                                    {{ number_format($lastLogLahan->stok_batang_after) }} btg
                                </td>
                                <td class="px-4 py-4 border-l border-gray-100 dark:border-gray-700"></td>
                                <td class="px-4 py-4 text-right border-l border-gray-100 dark:border-gray-700 text-blue-600 dark:text-blue-400 font-black">
                                    {{ number_format($lastLogLahan->stok_kubikasi_after, 4) }} m³
                                </td>
                                <td class="px-4 py-4 text-right border-l border-gray-100 dark:border-gray-700 font-black">
                                    Rp {{ number_format($lastLogLahan->nilai_stok_after, 0, ',', '.') }}
                                </td>
                                <td class="px-4 py-4 text-right border-l border-gray-100 dark:border-gray-800 bg-amber-50/50 dark:bg-amber-900/20 text-amber-700 dark:text-amber-400 font-black text-xs">
                                    Rp {{ number_format($lastLogLahan->hpp_average, 0, ',', '.') }}
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
            @empty
            <div class="px-4 py-20 text-center border-2 border-dashed border-gray-200 dark:border-gray-700 rounded-sm bg-gray-50/50">
                <span class="text-xs font-black uppercase tracking-[0.3em] text-gray-400">Belum ada log transaksi untuk lahan ini</span>
            </div>
            @endforelse
        </div>

</x-filament-panels::page>