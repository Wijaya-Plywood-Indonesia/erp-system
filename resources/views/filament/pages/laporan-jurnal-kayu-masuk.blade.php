<x-filament-panels::page>
    <!-- Filter Form -->
    <div class="p-4 bg-white dark:bg-zinc-900 rounded-lg shadow border border-zinc-200 dark:border-zinc-800">
        {{ $this->form }}
    </div>

    <!-- Loading Indicator -->
    <div wire:loading wire:target="loadData" class="w-full text-center py-12">
        <div class="flex items-center justify-center space-x-3">
            <x-filament::loading-indicator class="w-8 h-8 text-primary-600" />
            <span class="text-lg font-medium text-zinc-700 dark:text-zinc-300">Memuat data...</span>
        </div>
    </div>

    <!-- Preview Tables -->
    <div wire:loading.remove>
        @if(!empty($jurnalTables))
            <div class="space-y-8">
                @foreach($jurnalTables as $table)
                    <div class="bg-white dark:bg-zinc-900 rounded-lg shadow-md border border-zinc-200 dark:border-zinc-800 overflow-hidden">
                        <!-- Card Header Block -->
                        @php
                            $tglClean = \Carbon\Carbon::parse($table['tgl_kayu_masuk'])->format('Ymd');
                            $noJurnal = "MASUK/" . $tglClean . "/" . $table['no_nota'];
                        @endphp
                        <div class="bg-zinc-850 dark:bg-zinc-950 p-4 text-white flex flex-col sm:flex-row sm:justify-between sm:items-center gap-4" style="background-color: #27272a;">
                            <div>
                                <h2 class="text-sm font-bold uppercase tracking-wider font-mono">
                                    No. Jurnal: {{ $noJurnal }}
                                </h2>
                                <div class="text-xs text-zinc-400 mt-1 font-mono">
                                    Seri: <span class="font-semibold text-white">{{ $table['seri'] }}</span> &nbsp;|&nbsp; 
                                    Tanggal: <span class="font-semibold text-white">{{ $table['tgl_kayu_masuk'] }}</span>
                                </div>
                            </div>
                            <div class="text-xs text-zinc-300 font-mono sm:text-right">
                                <div>Supplier: <span class="font-semibold text-white font-sans">{{ $table['nama_supplier'] }}</span></div>
                                <div>Nopol: <span class="font-semibold text-white">{{ $table['nopol_kendaraan'] }}</span> &nbsp;|&nbsp; Legal: <span class="font-semibold text-white">{{ $table['dokumen_legal'] }}</span></div>
                            </div>
                        </div>

                        <!-- Card Body (Ledger Table) -->
                        <div class="p-4">
                            <div class="w-full overflow-x-auto">
                                <table class="w-full text-xs md:text-sm border-collapse border border-zinc-300 dark:border-zinc-700">
                                    <thead>
                                        <tr class="bg-zinc-100 dark:bg-zinc-800 text-zinc-800 dark:text-zinc-300 border-b border-zinc-300 dark:border-zinc-700 font-semibold">
                                            <th class="p-2 border border-zinc-300 dark:border-zinc-700 text-left">Nama Akun</th>
                                            <th class="p-2 border border-zinc-300 dark:border-zinc-700 text-center w-28">tgl</th>
                                            <th class="p-2 border border-zinc-300 dark:border-zinc-700 text-center w-16">jur</th>
                                            <th class="p-2 border border-zinc-300 dark:border-zinc-700 text-center w-28">No Akun</th>
                                            <th class="p-2 border border-zinc-300 dark:border-zinc-700 text-center w-16">No</th>
                                            <th class="p-2 border border-zinc-300 dark:border-zinc-700 text-left w-32">Nama Supplier</th>
                                            <th class="p-2 border border-zinc-300 dark:border-zinc-700 text-center w-16">Lahan</th>
                                            <th class="p-2 border border-zinc-300 dark:border-zinc-700 text-center w-16">m</th>
                                            <th class="p-2 border border-zinc-300 dark:border-zinc-700 text-center w-20">Hit KBK</th>
                                            <th class="p-2 border border-zinc-300 dark:border-zinc-700 text-right w-24">Banyak</th>
                                            <th class="p-2 border border-zinc-300 dark:border-zinc-700 text-right w-28">M3</th>
                                            <th class="p-2 border border-zinc-300 dark:border-zinc-700 text-right w-32">Harga</th>
                                            <th class="p-2 border border-zinc-300 dark:border-zinc-700 text-right w-36">Total</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($table['rows'] as $row)
                                            <tr class="border-b border-zinc-200 dark:border-zinc-800 hover:bg-zinc-50 dark:hover:bg-zinc-800/40">
                                                <td class="p-2 border border-zinc-300 dark:border-zinc-700 font-medium text-zinc-900 dark:text-zinc-100 font-sans">
                                                    {{ $row['nama_akun'] }}
                                                </td>
                                                <td class="p-2 border border-zinc-300 dark:border-zinc-700 text-center font-mono">
                                                    {{ $row['tgl'] }}
                                                </td>
                                                <td class="p-2 border border-zinc-300 dark:border-zinc-700 text-center font-mono">
                                                    {{ $row['jur'] }}
                                                </td>
                                                <td class="p-2 border border-zinc-300 dark:border-zinc-700 text-center font-semibold font-mono text-zinc-850 dark:text-zinc-200">
                                                    {{ $row['no_akun'] }}
                                                </td>
                                                <td class="p-2 border border-zinc-300 dark:border-zinc-700 text-center font-mono">
                                                    {{ $row['no'] }}
                                                </td>
                                                <td class="p-2 border border-zinc-300 dark:border-zinc-700 font-sans">
                                                    {{ $row['nama_supplier'] }}
                                                </td>
                                                <td class="p-2 border border-zinc-300 dark:border-zinc-700 text-center font-semibold font-mono">
                                                    {{ $row['lahan'] }}
                                                </td>
                                                <td class="p-2 border border-zinc-300 dark:border-zinc-700 text-center font-mono uppercase font-bold
                                                    {{ $row['m'] === 'd' ? 'text-primary-600 dark:text-primary-400' : 'text-amber-600 dark:text-amber-500' }}">
                                                    {{ $row['m'] }}
                                                </td>
                                                <td class="p-2 border border-zinc-300 dark:border-zinc-700 text-center font-mono font-bold uppercase">
                                                    {{ $row['hit_kbk'] ?? '' }}
                                                </td>
                                                <td class="p-2 border border-zinc-300 dark:border-zinc-700 text-right font-mono">
                                                    {{ $row['banyak'] !== null ? number_format($row['banyak'], 0, ',', '.') : '' }}
                                                </td>
                                                <td class="p-2 border border-zinc-300 dark:border-zinc-700 text-right font-mono">
                                                    {{ $row['m3'] !== null ? number_format($row['m3'], 4, ',', '.') : '' }}
                                                </td>
                                                <td class="p-2 border border-zinc-300 dark:border-zinc-700 text-right font-semibold font-mono text-emerald-600 dark:text-emerald-400">
                                                    {{ $row['harga'] !== null ? number_format($row['harga'], 0, ',', '.') : '-' }}
                                                </td>
                                                <td class="p-2 border border-zinc-300 dark:border-zinc-700 text-right font-bold font-mono text-emerald-700 dark:text-emerald-300">
                                                    {{ $row['total'] !== null ? number_format($row['total'], 0, ',', '.') : '-' }}
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="text-center p-12 text-zinc-500 dark:text-zinc-400 bg-white dark:bg-zinc-900 rounded-lg shadow border border-zinc-200 dark:border-zinc-800 mt-6">
                <p class="text-lg">Tidak ada data transaksi kayu masuk untuk tanggal ini.</p>
            </div>
        @endif
    </div>
</x-filament-panels::page>
