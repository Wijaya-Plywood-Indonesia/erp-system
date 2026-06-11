<x-filament-panels::page>
    <!-- HEADER DENGAN FORM -->
    <div class="p-4 bg-white dark:bg-zinc-900 rounded-lg shadow">
        {{ $this->form }}
    </div>

    <!-- Loading Indicator -->
    @if($isLoading)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-white bg-opacity-75 dark:bg-zinc-900 dark:bg-opacity-75">
        <div class="flex items-center space-x-3">
            <x-filament::loading-indicator class="w-8 h-8 text-primary-600" />
            <span class="text-lg font-medium text-zinc-700 dark:text-zinc-300">Memuat data...</span>
        </div>
    </div>
    @endif

    <!-- Preview Table Section -->
    @if(!$isLoading && !empty($previewRows))
        <div class="bg-white dark:bg-zinc-900 rounded-sm shadow border border-zinc-200 dark:border-zinc-700 overflow-hidden mt-6">
            <!-- Jurnal Header Block -->
            <div class="bg-zinc-800 p-4 text-white flex flex-col md:flex-row md:justify-between md:items-center gap-2">
                <div>
                    <h2 class="text-sm font-bold uppercase tracking-wider font-mono">
                        No. Jurnal: ROT/{{ Carbon\Carbon::parse($tanggal)->format('Ymd') }}/KAYU_KELUAR
                    </h2>
                </div>
                <span class="text-xs text-zinc-400 font-mono self-start md:self-auto">
                    Tanggal Laporan: {{ Carbon\Carbon::parse($tanggal)->format('d-m-Y') }}
                </span>
            </div>

            <div class="p-4">
                <div class="w-full overflow-x-auto">
                    <table class="w-full text-xs md:text-sm border-collapse border border-zinc-300 dark:border-zinc-600">
                        <thead>
                            <tr class="bg-zinc-100 dark:bg-zinc-800 text-zinc-800 dark:text-zinc-300 border-b border-zinc-300 dark:border-zinc-600 font-semibold text-center">
                                <th class="p-2 border border-zinc-300 dark:border-zinc-600 text-left">Nama Akun</th>
                                <th class="p-2 border border-zinc-300 dark:border-zinc-600 text-center w-28">tgl</th>
                                <th class="p-2 border border-zinc-300 dark:border-zinc-600 text-center w-20">jurnal</th>
                                <th class="p-2 border border-zinc-300 dark:border-zinc-600 text-center w-28">No Akun</th>
                                <th class="p-2 border border-zinc-300 dark:border-zinc-600 text-center w-16">No</th>
                                <th class="p-2 border border-zinc-300 dark:border-zinc-600 text-center w-16">mm</th>
                                <th class="p-2 border border-zinc-300 dark:border-zinc-600 text-left w-32">Nama</th>
                                <th class="p-2 border border-zinc-300 dark:border-zinc-600 text-left">Keterangan</th>
                                <th class="p-2 border border-zinc-300 dark:border-zinc-600 text-center w-16">map</th>
                                <th class="p-2 border border-zinc-300 dark:border-zinc-600 text-center w-20">Hit KBK</th>
                                <th class="p-2 border border-zinc-300 dark:border-zinc-600 text-right w-28">Banyak</th>
                                <th class="p-2 border border-zinc-300 dark:border-zinc-600 text-right w-36">M3</th>
                                <th class="p-2 border border-zinc-300 dark:border-zinc-600 text-right w-32">Harga</th>
                                <th class="p-2 border border-zinc-300 dark:border-zinc-600 text-right w-36">Total</th>
                            </tr>
                        </thead>

                        <tbody>
                            @foreach($previewRows as $i => $row)
                            <tr class="{{ $i % 2 === 1 ? 'bg-zinc-50 dark:bg-zinc-800/50' : 'bg-white dark:bg-zinc-900' }} border-b border-zinc-200 dark:border-zinc-800">
                                <td class="p-2 border border-zinc-300 dark:border-zinc-700 text-zinc-900 dark:text-zinc-100 font-medium">
                                    {{ $row['nama_akun'] }}
                                </td>
                                <td class="p-2 border border-zinc-300 dark:border-zinc-700 text-center text-zinc-700 dark:text-zinc-300 font-mono">
                                    {{ $row['tgl'] }}
                                </td>
                                <td class="p-2 border border-zinc-300 dark:border-zinc-700 text-center text-zinc-700 dark:text-zinc-300 font-mono">
                                    {{ $row['jurnal'] }}
                                </td>
                                <td class="p-2 border border-zinc-300 dark:border-zinc-700 text-center text-zinc-800 dark:text-zinc-200 font-semibold font-mono">
                                    {{ $row['no_akun'] }}
                                </td>
                                <td class="p-2 border border-zinc-300 dark:border-zinc-700 text-center text-zinc-700 dark:text-zinc-300 font-mono">
                                    {{ $row['no'] }}
                                </td>
                                <td class="p-2 border border-zinc-300 dark:border-zinc-700 text-center text-zinc-700 dark:text-zinc-300 font-mono">
                                    {{ $row['mm'] }}
                                </td>
                                <td class="p-2 border border-zinc-300 dark:border-zinc-700 text-zinc-700 dark:text-zinc-300">
                                    {{ $row['nama'] }}
                                </td>
                                <td class="p-2 border border-zinc-300 dark:border-zinc-700 text-zinc-900 dark:text-zinc-100 font-medium">
                                    {{ $row['keterangan'] }}
                                </td>
                                <td class="p-2 border border-zinc-300 dark:border-zinc-700 text-center text-zinc-700 dark:text-zinc-300 font-semibold font-mono">
                                    {{ $row['map'] }}
                                </td>
                                <td class="p-2 border border-zinc-300 dark:border-zinc-700 text-center text-zinc-700 dark:text-zinc-300 font-mono font-bold uppercase">
                                    {{ $row['hit_kbk'] ?? '' }}
                                </td>
                                <td class="p-2 border border-zinc-300 dark:border-zinc-700 text-right text-zinc-800 dark:text-zinc-200 font-mono">
                                    {{ $row['banyak'] ? number_format($row['banyak'], 0, ',', '.') : '' }}
                                </td>
                                <td class="p-2 border border-zinc-300 dark:border-zinc-700 text-right text-zinc-800 dark:text-zinc-200 font-mono">
                                    {{ $row['m3'] ? number_format($row['m3'], 4, ',', '.') : '' }}
                                </td>
                                <td class="p-2 border border-zinc-300 dark:border-zinc-700 text-right text-zinc-700 dark:text-zinc-300 font-mono">
                                    {{ $row['harga'] ? number_format($row['harga'], 0, ',', '.') : '-' }}
                                </td>
                                <td class="p-2 border border-zinc-300 dark:border-zinc-700 text-right font-bold text-zinc-900 dark:text-zinc-100 font-mono text-emerald-600 dark:text-emerald-400">
                                    {{ $row['total'] ? number_format($row['total'], 0, ',', '.') : '-' }}
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @elseif(!$isLoading)
        <div class="text-center p-12 text-zinc-500 dark:text-zinc-400 mt-6 bg-white dark:bg-zinc-900 rounded shadow border border-zinc-200 dark:border-zinc-700">
            <p class="text-lg">Tidak ada data transaksi kayu keluar (stok 0) untuk tanggal ini.</p>
        </div>
    @endif
</x-filament-panels::page>
