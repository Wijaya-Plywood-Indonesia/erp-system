{{-- resources/views/filament/pages/hpp-average-page.blade.php --}}
<x-filament-panels::page>

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

        <span class="ml-auto text-[10px] font-black uppercase tracking-widest text-gray-400">{{ $this->logs->count() }} entri log</span>
    </div>

    {{-- Tabel log --}}
    <div class="bg-white dark:bg-gray-800 rounded-sm border border-gray-200 dark:border-gray-700 overflow-hidden shadow-sm">

        {{-- Summary bar --}}
        @php
        $logs = $this->logs;
        $totalMasuk = $logs->where('tipe_transaksi', 'masuk')->sum('total_batang');
        $totalKeluar = $logs->where('tipe_transaksi', 'keluar')->sum('total_batang');
        $saldoBtg = $totalMasuk - $totalKeluar;
        $lastLog = $logs->last();
        @endphp

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

            {{-- HPP Summary Color - Amber --}}
            @if($lastLog)
            <span class="ml-auto inline-flex items-center gap-1 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-700 text-amber-700 dark:text-amber-300 text-[10px] font-black px-3 py-1 rounded-sm uppercase tracking-tight">
                HPP terakhir: Rp {{ number_format($lastLog->hpp_average, 0, ',', '.') }}/m³
            </span>
            @endif
        </div>

        {{-- Tabel --}}
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-gray-50 dark:bg-gray-900 text-[10px] font-black uppercase tracking-widest text-gray-500 dark:text-gray-400 border-b border-gray-100 dark:border-gray-700">
                        <th class="px-4 py-3 text-left whitespace-nowrap">Tanggal</th>
                        <th class="px-4 py-3 text-left whitespace-nowrap">Jenis Kayu</th>
                        <th class="px-4 py-3 text-right whitespace-nowrap">Ukuran</th>
                        <th class="px-4 py-3 text-left whitespace-nowrap">Tipe</th>
                        <th class="px-4 py-3 text-left">Keterangan</th>

                        <th class="px-4 py-3 text-right border-l border-gray-100 dark:border-gray-700 whitespace-nowrap">
                            Qty<div class="text-[10px] font-medium normal-case text-gray-400 tracking-normal">batang</div>
                        </th>

                        <th class="px-4 py-3 text-right border-l border-gray-100 dark:border-gray-700 bg-blue-50/30 dark:bg-blue-900/5 whitespace-nowrap">
                            Stok Batang<div class="text-[10px] font-medium normal-case text-gray-500 tracking-normal">Sebelum → Sesudah</div>
                        </th>

                        {{-- Tambahan Kolom Kubikasi TRX --}}
                        <th class="px-4 py-3 text-right border-l border-gray-100 dark:border-gray-700 whitespace-nowrap">
                            Kubikasi<div class="text-[10px] font-medium normal-case text-gray-500 tracking-normal">jumlah masuk/keluar</div>
                        </th>

                        <th class="px-4 py-3 text-right border-l border-gray-100 dark:border-gray-700 whitespace-nowrap">
                            Stok Kubikasi<div class="text-[10px] font-medium normal-case text-gray-500 tracking-normal">Sebelum → Sesudah</div>
                        </th>

                        <th class="px-4 py-3 text-right border-l border-gray-100 dark:border-gray-700 whitespace-nowrap">
                            Total Poin<div class="text-[10px] font-medium normal-case text-gray-500 tracking-normal">Sebelum → Sesudah</div>
                        </th>

                        {{-- HPP Column Color - Amber --}}
                        <th class="px-4 py-3 text-right border-l border-gray-100 dark:border-gray-700 bg-amber-50/50 dark:bg-amber-900/10 whitespace-nowrap">
                            HPP Average<div class="text-[10px] font-medium normal-case text-gray-500 tracking-normal text-amber-500">per m³</div>
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                    @forelse($logs as $log)
                    @php $isM = $log->tipe_transaksi === 'masuk'; @endphp
                    <tr @class(['transition', 'hover:bg-green-50/30 dark:hover:bg-green-900/10'=> $isM,
                        'hover:bg-red-50/30 dark:hover:bg-red-900/10' => !$isM])>

                        <td class="px-4 py-3 font-mono text-xs text-gray-500 dark:text-gray-400 whitespace-nowrap">
                            {{ $log->tanggal->format('d/m/Y') }}
                        </td>

                        <td class="px-4 py-3 font-black text-gray-900 dark:text-white whitespace-nowrap">
                            {{ $log->jenisKayu?->nama_kayu ?? '-' }}
                        </td>

                        <td class="px-4 py-3 text-right font-black text-xs text-gray-500 dark:text-gray-400 whitespace-nowrap tabular-nums">
                            {{ $log->panjang }} cm
                        </td>

                        <td class="px-4 py-3 whitespace-nowrap">
                            <span @class(['inline-flex items-center px-2 py-0.5 rounded-sm text-[9px] font-black uppercase tracking-tight', 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400'=> $isM,
                                'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400' => !$isM])>
                                {{ $isM ? '↑ Masuk' : '↓ Keluar' }}
                            </span>
                        </td>

                        {{-- Keterangan mengambil data SERI melalui relasi morphTo 'referensi' --}}
                        <td class="px-4 py-3 text-[11px] font-black uppercase text-gray-700 dark:text-gray-300 max-w-[200px] truncate">
                            @if($log->referensi instanceof \App\Models\NotaKayu && $log->referensi->kayuMasuk?->seri)
                            SERI: {{ $log->referensi->kayuMasuk->seri }}
                            @else
                            {{ $log->keterangan ?? '—' }}
                            @endif
                        </td>

                        <td @class(['px-4 py-3 text-right font-black text-sm border-l border-gray-50 dark:border-gray-800 whitespace-nowrap tabular-nums', 'text-green-600 dark:text-green-400'=> $isM,
                            'text-red-600 dark:text-red-400' => !$isM])>
                            {{ $isM ? '+' : '-' }}{{ number_format($log->total_batang) }}
                        </td>

                        <td class="px-4 py-3 border-l border-gray-50 dark:border-gray-800 bg-blue-50/10 dark:bg-blue-900/5 whitespace-nowrap">
                            <div class="flex items-center justify-end gap-1.5 font-mono text-xs tabular-nums">
                                <span class="text-gray-400 dark:text-gray-500 font-medium">{{ number_format($log->stok_batang_before) }}</span>
                                <span class="text-gray-300 dark:text-gray-700 text-[10px]">→</span>
                                <span @class(['font-black', 'text-green-600 dark:text-green-400'=> $isM,
                                    'text-red-600 dark:text-red-400' => !$isM])>
                                    {{ number_format($log->stok_batang_after) }}
                                </span>
                            </div>
                        </td>

                        {{-- Isi Kolom Kubikasi TRX --}}
                        <td class="px-4 py-3 border-l border-gray-50 dark:border-gray-800 whitespace-nowrap tabular-nums">
                            <div class="flex flex-col items-end">
                                <span @class(['font-semibold', 'text-blue-600 dark:text-blue-400'=> $isM, 'text-orange-600 dark:text-orange-400' => !$isM])>
                                    {{ number_format($log->total_kubikasi, 4) }}
                                </span>
                                <div class="text-[9px] text-gray-400 uppercase tracking-tighter">{{ $isM ? 'masuk' : 'keluar' }}</div>
                            </div>
                        </td>

                        <td class="px-4 py-3 border-l border-gray-50 dark:border-gray-800 whitespace-nowrap tabular-nums">
                            <div class="flex items-center justify-end gap-1.5 font-mono text-xs">
                                <span class="text-gray-400 dark:text-gray-500 font-medium">{{ number_format($log->stok_kubikasi_before, 4) }}</span>
                                <span class="text-gray-300 dark:text-gray-700 text-[10px]">→</span>
                                <span @class(['font-black', 'text-blue-600 dark:text-blue-400'=> $isM,
                                    'text-orange-600 dark:text-orange-400' => !$isM])>
                                    {{ number_format($log->stok_kubikasi_after, 4) }}
                                </span>
                            </div>
                        </td>

                        <td class="px-4 py-3 border-l border-gray-50 dark:border-gray-800 whitespace-nowrap tabular-nums">
                            <div class="flex items-center justify-end gap-1.5 font-mono text-xs text-right">
                                <span class="text-gray-400 dark:text-gray-500 font-medium tracking-tighter">{{ number_format($log->nilai_stok_before, 0, ',', '.') }}</span>
                                <span class="text-gray-300 dark:text-gray-700 text-[10px]">→</span>
                                <span @class(['font-black tracking-tight', 'text-green-600 dark:text-green-400'=> $isM,
                                    'text-red-600 dark:text-red-400' => !$isM])>
                                    {{ number_format($log->nilai_stok_after, 0, ',', '.') }}
                                </span>
                            </div>
                        </td>

                        {{-- HPP Average Cell - Amber --}}
                        <td class="px-4 py-3 text-right border-l border-gray-50 dark:border-gray-800 bg-amber-50/20 dark:bg-amber-900/5 whitespace-nowrap">
                            <span class="font-black text-xs text-amber-700 dark:text-amber-400 tabular-nums leading-none">
                                {{ number_format($log->hpp_average, 0, ',', '.') }}
                            </span>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="11" class="px-4 py-12 text-center text-sm text-gray-400 dark:text-gray-500">
                            Belum ada log transaksi
                        </td>
                    </tr>
                    @endforelse
                </tbody>

                @if($logs->count())
                @php
                $m3Saldo = $logs->where('tipe_transaksi','masuk')->sum('total_kubikasi')
                - $logs->where('tipe_transaksi','keluar')->sum('total_kubikasi');
                $poinSaldo = $logs->where('tipe_transaksi','masuk')->sum('nilai_stok')
                - $logs->where('tipe_transaksi','keluar')->sum('nilai_stok');
                @endphp
                <tfoot>
                    <tr class="text-[10px] font-black border-t-2 bg-gray-50 dark:bg-gray-900/60 border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-300 uppercase tracking-widest">
                        <td colspan="5" class="px-4 py-3 text-gray-500">Saldo Akhir</td>
                        <td @class(['px-4 py-3 text-right tabular-nums', 'text-green-700 dark:text-green-400'=> $saldoBtg >= 0,
                            'text-red-600 dark:text-red-400' => $saldoBtg < 0])>
                                {{ number_format($saldoBtg) }} btg
                        </td>
                        <td class="px-4 py-3 text-right tabular-nums bg-blue-50/30 dark:bg-blue-900/10">
                            {{ $lastLog ? number_format($lastLog->stok_batang_after) : '—' }} btg
                        </td>
                        {{-- Footer untuk TRX --}}
                        <td class="px-4 py-3 text-right text-gray-400 font-mono tracking-tighter">
                            —
                        </td>
                        <td class="px-4 py-3 text-right tabular-nums text-blue-600 dark:text-blue-400">
                            {{ number_format(max(0, $m3Saldo), 4) }} m³
                        </td>
                        <td class="px-4 py-3 text-right tabular-nums">
                            Rp {{ number_format(max(0, $poinSaldo), 0, ',', '.') }}
                        </td>
                        {{-- Footer HPP Color - Amber --}}
                        <td class="px-4 py-3 text-right tabular-nums bg-amber-50/50 dark:bg-amber-900/10 text-amber-700 dark:text-amber-400 font-black">
                            {{ $lastLog ? number_format($lastLog->hpp_average, 0, ',', '.').' /m³' : '—' }}
                        </td>
                    </tr>
                </tfoot>
                @endif
            </table>
        </div>
    </div>

</x-filament-panels::page>