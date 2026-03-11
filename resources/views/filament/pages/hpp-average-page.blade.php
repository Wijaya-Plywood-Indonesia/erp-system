{{-- resources/views/filament/pages/hpp-average-page.blade.php --}}
<x-filament-panels::page>

    {{-- Filter bar --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-3 mb-5 flex items-center gap-3 flex-wrap">
        <span class="text-sm font-medium text-gray-500 dark:text-gray-400">Filter:</span>

        <select wire:model.live="filterJenisKayu"
            class="text-sm bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-600 text-gray-700 dark:text-gray-300 rounded-lg px-3 py-1.5 outline-none focus:border-primary-500">
            <option value="">Semua Jenis Kayu</option>
            @foreach(\App\Models\JenisKayu::orderBy('nama_kayu')->pluck('nama_kayu', 'id') as $id => $nama)
            <option value="{{ $id }}">{{ $nama }}</option>
            @endforeach
        </select>

        <select wire:model.live="filterPanjang"
            class="text-sm bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-600 text-gray-700 dark:text-gray-300 rounded-lg px-3 py-1.5 outline-none focus:border-primary-500">
            <option value="">Semua Ukuran</option>
            @foreach(\App\Models\HppAverageLog::whereNull('grade')->distinct()->orderBy('panjang')->pluck('panjang') as $p)
            <option value="{{ $p }}">{{ $p }} cm</option>
            @endforeach
        </select>

        <span class="ml-auto text-xs font-mono text-gray-400">{{ $this->logs->count() }} entri</span>
    </div>

    {{-- Tabel log --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">

        {{-- Summary bar --}}
        @php
        $logs = $this->logs;
        $totalMasuk = $logs->where('tipe_transaksi', 'masuk')->sum('total_batang');
        $totalKeluar = $logs->where('tipe_transaksi', 'keluar')->sum('total_batang');
        $saldoBtg = $totalMasuk - $totalKeluar;
        $lastLog = $logs->last();
        @endphp

        <div class="px-4 py-2.5 border-b border-gray-100 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 flex items-center gap-3 flex-wrap">
            <span class="inline-flex items-center gap-1 bg-green-100 dark:bg-green-900/40 text-green-700 dark:text-green-400 text-xs font-mono px-2.5 py-1 rounded-full font-semibold">
                ↑ {{ number_format($totalMasuk) }} masuk
            </span>
            <span class="inline-flex items-center gap-1 bg-red-100 dark:bg-red-900/40 text-red-700 dark:text-red-400 text-xs font-mono px-2.5 py-1 rounded-full font-semibold">
                ↓ {{ number_format($totalKeluar) }} keluar
            </span>
            <span class="inline-flex items-center gap-1 bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 text-xs font-mono px-2.5 py-1 rounded-full font-semibold">
                = {{ number_format($saldoBtg) }} saldo
            </span>
            @if($lastLog)
            <span class="ml-auto inline-flex items-center gap-1 bg-violet-50 dark:bg-violet-900/20 border border-violet-200 dark:border-violet-700 text-violet-700 dark:text-violet-300 text-xs font-mono px-2.5 py-1 rounded-full">
                HPP terakhir: Rp {{ number_format($lastLog->hpp_average, 0, ',', '.') }}/m³
            </span>
            @endif
        </div>

        {{-- Tabel --}}
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-gray-50 dark:bg-gray-900 text-[10.5px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        <th class="px-4 py-2.5 text-left whitespace-nowrap">Tanggal</th>
                        <th class="px-4 py-2.5 text-left whitespace-nowrap">Jenis Kayu</th>
                        <th class="px-4 py-2.5 text-right whitespace-nowrap">Ukuran</th>
                        <th class="px-4 py-2.5 text-left whitespace-nowrap">Tipe</th>
                        <th class="px-4 py-2.5 text-left">Keterangan</th>

                        {{-- Kuantitas --}}
                        <th class="px-4 py-2.5 text-right border-l border-gray-200 dark:border-gray-700 whitespace-nowrap">
                            Qty<div class="text-[9px] font-normal normal-case text-gray-400">batang</div>
                        </th>

                        {{-- Stok before → after --}}
                        <th class="px-4 py-2.5 text-right border-l border-gray-200 dark:border-gray-700 bg-blue-50/50 dark:bg-blue-900/10 whitespace-nowrap">
                            Stok Batang<div class="text-[9px] font-normal normal-case text-gray-400">Sebelum → Sesudah</div>
                        </th>

                        {{-- Kubikasi before → after --}}
                        <th class="px-4 py-2.5 text-right border-l border-gray-200 dark:border-gray-700 whitespace-nowrap">
                            Kubikasi (m³)<div class="text-[9px] font-normal normal-case text-gray-400">Sebelum → Sesudah</div>
                        </th>

                        {{-- Poin/Nilai before → after --}}
                        <th class="px-4 py-2.5 text-right border-l border-gray-200 dark:border-gray-700 whitespace-nowrap">
                            Total Poin<div class="text-[9px] font-normal normal-case text-gray-400">Sebelum → Sesudah</div>
                        </th>

                        {{-- HPP --}}
                        <th class="px-4 py-2.5 text-right border-l border-gray-200 dark:border-gray-700 bg-violet-50/50 dark:bg-violet-900/10 whitespace-nowrap">
                            HPP Average<div class="text-[9px] font-normal normal-case text-gray-400">per m³</div>
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                    @forelse($logs as $log)
                    @php $isM = $log->tipe_transaksi === 'masuk'; @endphp
                    <tr @class(['transition', 'hover:bg-green-50/30 dark:hover:bg-green-900/10'=> $isM,
                        'hover:bg-red-50/30 dark:hover:bg-red-900/10' => !$isM])>

                        {{-- Tanggal --}}
                        <td class="px-4 py-3 font-mono text-xs text-gray-500 dark:text-gray-400 whitespace-nowrap">
                            {{ $log->tanggal->format('d/m/Y') }}
                        </td>

                        {{-- Jenis Kayu --}}
                        <td class="px-4 py-3 font-semibold text-gray-900 dark:text-white whitespace-nowrap">
                            {{ $log->jenisKayu?->nama_kayu ?? '-' }}
                        </td>

                        {{-- Ukuran --}}
                        <td class="px-4 py-3 text-right font-mono text-xs text-gray-500 dark:text-gray-400 whitespace-nowrap">
                            {{ $log->panjang }} cm
                        </td>

                        {{-- Tipe --}}
                        <td class="px-4 py-3 whitespace-nowrap">
                            <span @class(['inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold', 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400'=> $isM,
                                'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400' => !$isM])>
                                {{ $isM ? '↑ Masuk' : '↓ Keluar' }}
                            </span>
                        </td>

                        {{-- Keterangan --}}
                        <td class="px-4 py-3 text-xs text-gray-500 dark:text-gray-400 max-w-[180px] truncate">
                            {{ $log->keterangan }}
                        </td>

                        {{-- Qty batang --}}
                        <td @class(['px-4 py-3 text-right font-mono font-bold text-sm border-l border-gray-100 dark:border-gray-700 whitespace-nowrap', 'text-green-600 dark:text-green-400'=> $isM,
                            'text-red-600 dark:text-red-400' => !$isM])>
                            {{ $isM ? '+' : '-' }}{{ number_format($log->total_batang) }}
                        </td>

                        {{-- Stok batang before → after --}}
                        <td class="px-4 py-3 border-l border-gray-100 dark:border-gray-700 bg-blue-50/20 dark:bg-blue-900/5 whitespace-nowrap">
                            <div class="flex items-center justify-end gap-1.5 font-mono text-xs">
                                <span class="text-gray-400 dark:text-gray-500">{{ number_format($log->stok_batang_before) }}</span>
                                <span class="text-gray-300 dark:text-gray-600">→</span>
                                <span @class(['font-bold', 'text-green-600 dark:text-green-400'=> $isM,
                                    'text-red-600 dark:text-red-400' => !$isM])>
                                    {{ number_format($log->stok_batang_after) }}
                                </span>
                                <span class="text-[9px] text-gray-400">btg</span>
                            </div>
                        </td>

                        {{-- Kubikasi before → after --}}
                        <td class="px-4 py-3 border-l border-gray-100 dark:border-gray-700 whitespace-nowrap">
                            <div class="flex items-center justify-end gap-1.5 font-mono text-xs">
                                <span class="text-gray-400 dark:text-gray-500">{{ number_format($log->stok_kubikasi_before, 4) }}</span>
                                <span class="text-gray-300 dark:text-gray-600">→</span>
                                <span @class(['font-semibold', 'text-blue-600 dark:text-blue-400'=> $isM,
                                    'text-orange-600 dark:text-orange-400' => !$isM])>
                                    {{ number_format($log->stok_kubikasi_after, 4) }}
                                </span>
                                <span class="text-[9px] text-gray-400">m³</span>
                            </div>
                        </td>

                        {{-- Poin/Nilai before → after --}}
                        <td class="px-4 py-3 border-l border-gray-100 dark:border-gray-700 whitespace-nowrap">
                            <div class="flex items-center justify-end gap-1.5 font-mono text-xs">
                                <span class="text-gray-400 dark:text-gray-500">{{ number_format($log->nilai_stok_before, 0, ',', '.') }}</span>
                                <span class="text-gray-300 dark:text-gray-600">→</span>
                                <span @class(['font-semibold', 'text-green-600 dark:text-green-400'=> $isM,
                                    'text-red-600 dark:text-red-400' => !$isM])>
                                    {{ number_format($log->nilai_stok_after, 0, ',', '.') }}
                                </span>
                            </div>
                        </td>

                        {{-- HPP Average --}}
                        <td class="px-4 py-3 text-right border-l border-gray-100 dark:border-gray-700 bg-violet-50/20 dark:bg-violet-900/5 whitespace-nowrap">
                            <span class="font-mono text-xs font-semibold text-violet-700 dark:text-violet-300">
                                {{ number_format($log->hpp_average, 0, ',', '.') }}
                            </span>
                            <span class="text-[9px] text-gray-400">/m³</span>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="10" class="px-4 py-12 text-center text-sm text-gray-400 dark:text-gray-500">
                            Belum ada log transaksi
                        </td>
                    </tr>
                    @endforelse
                </tbody>

                {{-- Footer --}}
                @if($logs->count())
                @php
                $m3Saldo = $logs->where('tipe_transaksi','masuk')->sum('total_kubikasi')
                - $logs->where('tipe_transaksi','keluar')->sum('total_kubikasi');
                $poinSaldo = $logs->where('tipe_transaksi','masuk')->sum('nilai_stok')
                - $logs->where('tipe_transaksi','keluar')->sum('nilai_stok');
                @endphp
                <tfoot>
                    <tr class="text-xs font-bold border-t-2 bg-gray-50 dark:bg-gray-900/60 border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300">
                        <td colspan="5" class="px-4 py-2.5 uppercase tracking-wide text-gray-500">Saldo Akhir</td>
                        <td @class(['px-4 py-2.5 text-right font-mono', 'text-green-700 dark:text-green-400'=> $saldoBtg >= 0,
                            'text-red-600 dark:text-red-400' => $saldoBtg < 0])>
                                {{ number_format($saldoBtg) }} btg
                        </td>
                        <td class="px-4 py-2.5 text-right font-mono bg-blue-50/50 dark:bg-blue-900/10">
                            {{ $lastLog ? number_format($lastLog->stok_batang_after) : '—' }} btg
                        </td>
                        <td class="px-4 py-2.5 text-right font-mono text-blue-600 dark:text-blue-400">
                            {{ number_format(max(0, $m3Saldo), 4) }} m³
                        </td>
                        <td class="px-4 py-2.5 text-right font-mono">
                            {{ number_format(max(0, $poinSaldo), 0, ',', '.') }}
                        </td>
                        <td class="px-4 py-2.5 text-right font-mono bg-violet-50/50 dark:bg-violet-900/10 text-violet-700 dark:text-violet-300">
                            {{ $lastLog ? number_format($lastLog->hpp_average, 0, ',', '.').' /m³' : '—' }}
                        </td>
                    </tr>
                </tfoot>
                @endif
            </table>
        </div>
    </div>

</x-filament-panels::page>