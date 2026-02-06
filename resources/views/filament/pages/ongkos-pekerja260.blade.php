<x-filament-panels::page>
    <div class="p-4 bg-white dark:bg-zinc-900 rounded-lg shadow border border-zinc-200 dark:border-zinc-700">
        {{ $this->form }}
    </div>

    @if($isLoading)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-white bg-opacity-75 dark:bg-zinc-900 dark:bg-opacity-75">
        <div class="flex items-center space-x-3 text-zinc-500">
            <x-filament::loading-indicator class="w-8 h-8" />
            <span class="text-lg font-medium animate-pulse">Menghitung kalkulasi...</span>
        </div>
    </div>
    @endif

    <div class="mt-6 space-y-8">
        @php $groupedLaporan = collect($laporanOngkos)->groupBy('kategori_mesin'); @endphp

        @forelse($groupedLaporan as $kategori => $items)
        <div class="bg-white dark:bg-zinc-950 rounded-sm shadow-xl border border-zinc-200 dark:border-zinc-800 overflow-hidden">
            <div class="bg-zinc-100 dark:bg-zinc-900 p-4 border-b border-zinc-500 dark:border-zinc-800 flex justify-between">
                <h2 class="text-sm font-black uppercase tracking-widest text-zinc-800 dark:text-zinc-200">{{ $kategori }}</h2>
            </div>

            <div class="w-full overflow-x-auto">
                <table class="w-full text-[10px] border-collapse border border-zinc-500 dark:border-zinc-800">
                    <thead>
                        <tr class="bg-zinc-800 dark:bg-zinc-200 text-white dark:text-zinc-900 font-black uppercase text-center">
                            <th class="p-2 border border-zinc-400">Tanggal</th>
                            <th class="p-2 border border-zinc-400">P</th>
                            <th class="p-2 border border-zinc-400">L</th>
                            <th class="p-2 border border-zinc-400">T</th>
                            <th class="p-2 border border-zinc-400">Jenis</th>
                            <th class="p-2 border border-zinc-400">KW1</th>
                            <th class="p-2 border border-zinc-400">KW2</th>
                            <th class="p-2 border border-zinc-400">KW3</th>
                            <th class="p-2 border border-zinc-400">KW4</th>
                            <th class="p-2 border border-zinc-400">KW5</th>
                            <th class="p-2 border border-zinc-400">Banyak</th>
                            <th class="p-2 border border-zinc-400">m3</th>
                            <th class="p-2 border border-zinc-400">Total Pekerja</th>
                            <th class="p-2 border border-zinc-400">Harga</th>
                            <th class="p-2 border border-zinc-400">Total Solasi</th>
                            <th class="p-2 border border-zinc-400">Harga Solasi</th>
                            <th class="p-2 border border-zinc-400">Solasi/m3</th>
                            <th class="p-2 border border-zinc-400">Solasi/lb</th>
                            <th class="p-2 border border-zinc-400">Ongkos/m3</th>
                            <th class="p-2 border border-zinc-400">Mesin</th>
                            <th class="p-2 border border-zinc-400">Ongkos m3+Mesin</th>
                            <th class="p-2 border border-zinc-400">Ongkos/Lb</th>
                            <th class="p-2 border border-zinc-400">Ket</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                        @php $dateGroup = collect($items)->groupBy('tanggal'); @endphp
                        @foreach($dateGroup as $tanggal => $rows)
                        @php
                        $rowCount = count($rows);
                        $totalM3Tgl = $rows->sum('m3');
                        $totalBykTgl = $rows->sum('byk');
                        $first = $rows->first();
                        @endphp
                        @foreach($rows as $index => $row)
                        <tr class="text-center align-middle font-medium text-zinc-900 dark:text-zinc-200">
                            @if($index === 0)
                            <td class="p-2 border border-zinc-500 bg-zinc-50 dark:bg-zinc-900 font-bold" rowspan="{{ $rowCount }}">{{ $tanggal }}</td>
                            @endif
                            <td class="p-2 border border-zinc-500">{{ $row['p'] }}</td>
                            <td class="p-2 border border-zinc-500">{{ $row['l'] }}</td>
                            <td class="p-2 border border-zinc-500">{{ $row['t'] }}</td>
                            <td class="p-2 border border-zinc-500 uppercase font-bold text-orange-600">{{ $row['jenis'] }}</td>
                            <td class="p-2 border border-zinc-500">{{ $row['kw1'] }}</td>
                            <td class="p-2 border border-zinc-500">{{ $row['kw2'] }}</td>
                            <td class="p-2 border border-zinc-500">{{ $row['kw3'] }}</td>
                            <td class="p-2 border border-zinc-500">{{ $row['kw4'] }}</td>
                            <td class="p-2 border border-zinc-500">{{ $row['kw5'] }}</td>
                            <td class="p-2 border border-zinc-500">{{ $row['byk'] }}</td>
                            <td class="p-2 border border-zinc-500">{{ number_format($row['m3'], 4) }}</td>

                            @if($index === 0)
                            <td class="p-2 border border-zinc-500 font-bold" rowspan="{{ $rowCount }}">{{ $first['ttl_pkj'] }}</td>
                            <td class="p-2 border border-zinc-500 text-right font-bold text-green-600" rowspan="{{ $rowCount }}">{{ number_format($first['harga'], 0, ',', '.') }}</td>
                            <td class="p-2 border border-zinc-500" rowspan="{{ $rowCount }}">{{ number_format($rows->sum('total_solasi'), 0) }}</td>
                            <td class="p-2 border border-zinc-500" rowspan="{{ $rowCount }}">{{ number_format($rows->sum('harga_solasi'), 0) }}</td>
                            <td class="p-2 border border-zinc-500" rowspan="{{ $rowCount }}">{{ $totalM3Tgl > 0 ? number_format($rows->sum('harga_solasi') / $totalM3Tgl, 0) : 0 }}</td>
                            <td class="p-2 border border-zinc-500" rowspan="{{ $rowCount }}">{{ $totalBykTgl > 0 ? number_format($rows->sum('harga_solasi') / $totalBykTgl, 0) : 0 }}</td>
                            <td class="p-2 border border-zinc-500 font-black" rowspan="{{ $rowCount }}">{{ $totalM3Tgl > 0 ? number_format($first['harga'] / $totalM3Tgl, 0, ',', '.') : 0 }}</td>
                            <td class="p-2 border border-zinc-500 text-right" rowspan="{{ $rowCount }}">{{ number_format($first['ongkos_mesin'], 0, ',', '.') }}</td>
                            <td class="p-2 border border-zinc-500 text-right font-bold text-orange-600" rowspan="{{ $rowCount }}">
                                @php $totalBiaya = $first['harga'] + $first['ongkos_mesin'] + $rows->sum('harga_solasi'); @endphp
                                {{ $totalM3Tgl > 0 ? number_format($totalBiaya / $totalM3Tgl, 0, ',', '.') : 0 }}
                            </td>
                            <td class="p-2 border border-zinc-500 text-right font-bold text-orange-600" rowspan="{{ $rowCount }}">
                                {{ $totalBykTgl > 0 ? number_format(($first['harga'] + $first['ongkos_mesin']) / $totalBykTgl, 0, ',', '.') : 0 }}
                            </td>
                            <td class="p-2 border border-zinc-500 text-[9px] text-red-500" rowspan="{{ $rowCount }}">{{ $first['ket'] }}</td>
                            @endif
                        </tr>
                        @endforeach
                        @endforeach
                    </tbody>
                    <tfoot class="bg-zinc-100 dark:bg-zinc-900 font-black text-zinc-900 dark:text-white uppercase border-t border-zinc-500 text-center">
                        <tr>
                            <td colspan="10" class="p-2 text-right">Grand Total</td>
                            <td class="p-2">{{ number_format($items->sum('byk'), 0) }}</td>
                            <td class="p-2">{{ number_format($items->sum('m3'), 4) }}</td>
                            <td class="p-2">{{ number_format($items->sum('ttl_pkj'), 0) }}</td>
                            <td colspan="5"></td>
                            <td class="p-2 text-right text-orange-600">{{ number_format($items->sum('ongkos_per_m3'), 0, ',', '.') }}</td>
                            <td></td>
                            <td class="p-2 text-right text-orange-600">{{ number_format($items->sum('ongkos_m3_mesin'), 0, ',', '.') }}</td>
                            <td class="p-2 text-right text-orange-600">{{ number_format($items->sum('ongkos_per_lb'), 0, ',', '.') }}</td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
        @empty
        <div class="p-20 text-center bg-zinc-50 dark:bg-zinc-900 border-2 border-dashed border-zinc-200 rounded-2xl">
            <p class="text-sm text-zinc-400 font-black uppercase">Data tidak ditemukan.</p>
        </div>
        @endforelse
    </div>
</x-filament-panels::page>