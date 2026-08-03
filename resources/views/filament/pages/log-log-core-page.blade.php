<x-filament-panels::page>
    @php
    $logsByKombinasi = $this->logsByKombinasi;
    @endphp

    <div class="bg-white dark:bg-gray-800 rounded-sm border border-gray-200 dark:border-gray-700 p-3 mb-8 flex items-center gap-3 flex-wrap shadow-sm">
        <span class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider text-[10px]">Filter:</span>

        <select wire:model.live="filterJenisKayu" class="text-xs bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-600 rounded-sm px-3 py-1.5 outline-none">
            <option value="">Semua Jenis Kayu</option>
            @foreach(\App\Models\JenisKayu::orderBy('nama_kayu')->pluck('nama_kayu', 'id') as $id => $nama)
            <option value="{{ $id }}">{{ $nama }}</option>
            @endforeach
        </select>

        <select wire:model.live="filterPanjang" class="text-xs bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-600 rounded-sm px-3 py-1.5 outline-none">
            <option value="">Semua Ukuran</option>
            @foreach(\App\Models\LogLogCore::distinct()->orderBy('panjang')->pluck('panjang') as $p)
            <option value="{{ $p }}">{{ $p }} cm</option>
            @endforeach
        </select>

        <select wire:model.live="filterTipeTransaksi" class="text-xs bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-600 rounded-sm px-3 py-1.5 outline-none">
            <option value="">Semua Tipe</option>
            <option value="masuk">Masuk</option>
            <option value="keluar">Keluar</option>
        </select>
    </div>

    <div class="space-y-8">
        @forelse($logsByKombinasi as $key => $kombinasiLogs)
        @php
        $firstLog = $kombinasiLogs->first();
        $stok = \App\Models\StokLogCore::where('id_jenis_kayu', $firstLog->id_jenis_kayu)
        ->where('panjang', $firstLog->panjang)
        ->first();
        $saldoQty = $stok?->stok_qty ?? 0;
        $totalMasuk = $kombinasiLogs->where('tipe_transaksi', 'masuk')->sum('qty');
        $totalKeluar = $kombinasiLogs->where('tipe_transaksi', 'keluar')->sum('qty');
        @endphp

        <div class="bg-white dark:bg-gray-800 rounded-sm border border-gray-200 dark:border-gray-700 overflow-hidden shadow-sm">
            <div class="bg-gray-800 dark:bg-gray-950 text-white px-4 py-3 flex items-center justify-start border-b border-gray-700">
                <h2 class="text-xs md:text-sm font-black tracking-[0.2em] uppercase truncate">
                    {{ $firstLog->jenisKayu?->nama_kayu ?? 'N/A' }} — {{ $firstLog->panjang }} cm
                </h2>
            </div>

            <div class="px-4 py-2.5 border-b border-gray-100 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 flex flex-wrap items-center gap-3">
                <span class="inline-flex items-center gap-1.5 bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-400 text-[10px] font-bold px-2 py-0.5 rounded border border-emerald-200/50 uppercase">
                    {{ number_format($totalMasuk) }} Masuk
                </span>
                <span class="inline-flex items-center gap-1.5 bg-rose-50 dark:bg-rose-950/40 text-rose-700 dark:text-rose-400 text-[10px] font-bold px-2 py-0.5 rounded border border-rose-200/50 uppercase">
                    {{ number_format($totalKeluar) }} Keluar
                </span>
                <span class="inline-flex items-center gap-1.5 bg-slate-50 dark:bg-slate-900/40 text-slate-700 dark:text-slate-300 text-[10px] font-bold px-2 py-0.5 rounded border border-slate-200/50 uppercase">
                    {{ number_format($saldoQty) }} Saldo
                </span>
            </div>

            <div class="overflow-x-auto custom-scrollbar">
                <table class="w-full text-sm min-w-[800px] border-collapse">
                    <thead>
                        <tr class="bg-gray-50 dark:bg-gray-900 text-[10px] font-black uppercase tracking-widest text-gray-500 border-b border-gray-100 dark:border-gray-700">
                            <th class="px-4 py-3 text-left whitespace-nowrap">Tanggal</th>
                            <th class="px-4 py-3 text-left whitespace-nowrap">Tipe</th>
                            <th class="px-4 py-3 text-left">Keterangan</th>
                            <th class="px-4 py-3 text-right border-l border-gray-100 dark:border-gray-700 whitespace-nowrap">Qty</th>
                            <th class="px-4 py-3 text-right border-l border-gray-100 dark:border-gray-700 whitespace-nowrap bg-blue-50/30 dark:bg-blue-900/5">
                                Stok<div class="text-[10px] font-medium normal-case text-gray-500">Sebelum → Sesudah</div>
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @foreach($kombinasiLogs as $log)
                        @php $isM = $log->tipe_transaksi === 'masuk'; @endphp
                        <tr @class(['transition', 'hover:bg-green-50/30 dark:hover:bg-green-900/10'=> $isM, 'hover:bg-red-50/30 dark:hover:bg-red-900/10' => !$isM])>
                            <td class="px-4 py-3 font-mono text-xs text-gray-500 whitespace-nowrap uppercase">
                                {{ $log->tanggal->format('d/m/y') }}
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                <span @class([ 'inline-flex items-center gap-1 px-2.5 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider border' , 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950/30'=> $isM,
                                    'bg-rose-50 text-rose-700 border-rose-200 dark:bg-rose-950/30' => !$isM
                                    ])>
                                    {{ $isM ? 'Masuk' : 'Keluar' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-[11px] font-semibold uppercase text-gray-700 dark:text-gray-300 max-w-[300px]">
                                {{ $log->keterangan ?? '—' }}
                            </td>
                            <td @class(['px-4 py-3 text-right font-black text-sm border-l border-gray-50 dark:border-gray-800 whitespace-nowrap tabular-nums', 'text-green-600 dark:text-green-400'=> $isM, 'text-red-600 dark:text-red-400' => !$isM])>
                                {{ $isM ? '+' : '-' }}{{ number_format($log->qty) }}
                            </td>
                            <td class="px-4 py-3 border-l border-gray-50 dark:border-gray-800 bg-blue-50/10 dark:bg-blue-900/5 whitespace-nowrap">
                                <div class="flex items-center justify-end gap-1.5 font-mono text-xs tabular-nums">
                                    <span class="text-gray-400">{{ number_format($log->stok_qty_before) }}</span>
                                    <span class="text-gray-300 text-[10px]">→</span>
                                    <span @class(['font-black', 'text-green-600 dark:text-green-400'=> $isM, 'text-red-600 dark:text-red-400' => !$isM])>
                                        {{ number_format($log->stok_qty_after) }}
                                    </span>
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @empty
        <div class="px-4 py-20 text-center border-2 border-dashed border-gray-200 dark:border-gray-700 rounded-sm bg-gray-50/50">
            <span class="text-xs font-black uppercase tracking-[0.3em] text-gray-400">Belum ada log LogCore</span>
        </div>
        @endforelse
    </div>
</x-filament-panels::page>