<x-filament-panels::page>
    {{-- Bagian Filter Tanggal --}}
    <div class="p-4 bg-white dark:bg-zinc-900 rounded-lg shadow border border-zinc-200 dark:border-zinc-700">
        {{ $this->form }}
    </div>

    {{-- Loading Indicator --}}
    @if($isLoading)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-white bg-opacity-75 dark:bg-zinc-900 dark:bg-opacity-75">
        <div class="flex items-center space-x-3 text-zinc-500">
            <x-filament::loading-indicator class="w-8 h-8" />
            <span class="text-lg font-medium animate-pulse">Menghitung kalkulasi ongkos...</span>
        </div>
    </div>
    @endif

    <div class="mt-6 space-y-8">
        @php
        $groupedLaporan = collect($laporanOngkos)->groupBy('kategori_mesin');
        @endphp

        @forelse($groupedLaporan as $kategori => $items)
        <div class="bg-white dark:bg-zinc-950 rounded-sm shadow-xl border border-zinc-200 dark:border-zinc-800 overflow-hidden">

            {{-- Header Tabel (Kategori Mesin) --}}
            <div class="bg-zinc-100 dark:bg-zinc-900 p-4 border-b border-zinc-300 dark:border-zinc-800 flex justify-between items-center">
                <h2 class="text-sm font-black uppercase tracking-widest text-zinc-800 dark:text-zinc-200">
                    {{ $kategori }}
                </h2>
            </div>

            <div class="w-full overflow-x-auto">
                <table class="w-full text-[10px] border-collapse border border-zinc-300 dark:border-zinc-800">
                    <thead>
                        <tr class="bg-zinc-800 dark:bg-zinc-200 text-white dark:text-zinc-900 font-black uppercase text-center tracking-tighter">
                            <th class="p-2 border border-zinc-400 dark:border-zinc-600">Tanggal</th>
                            <th class="p-2 border border-zinc-400 dark:border-zinc-600 w-8">P</th>
                            <th class="p-2 border border-zinc-400 dark:border-zinc-600 w-8">L</th>
                            <th class="p-2 border border-zinc-400 dark:border-zinc-600 w-8">T</th>
                            <th class="p-2 border border-zinc-400 dark:border-zinc-600">Jenis</th>
                            <th class="p-2 border border-zinc-400 dark:border-zinc-600">KW1</th>
                            <th class="p-2 border border-zinc-400 dark:border-zinc-600">KW2</th>
                            <th class="p-2 border border-zinc-400 dark:border-zinc-600">KW3</th>
                            <th class="p-2 border border-zinc-400 dark:border-zinc-600">KW4</th>
                            <th class="p-2 border border-zinc-400 dark:border-zinc-600">KW5</th>
                            <th class="p-2 border border-zinc-400 dark:border-zinc-600">Banyak</th>
                            <th class="p-2 border border-zinc-400 dark:border-zinc-600">m3</th>
                            <th class="p-2 border border-zinc-400 dark:border-zinc-600">Total Pekerja</th>
                            <th class="p-2 border border-zinc-400 dark:border-zinc-600">Harga</th>
                            <th class="p-2 border border-zinc-400 dark:border-zinc-600">Total Solasi</th>
                            <th class="p-2 border border-zinc-400 dark:border-zinc-600">Harga Solasi</th>
                            <th class="p-2 border border-zinc-400 dark:border-zinc-600">Solasi/m3</th>
                            <th class="p-2 border border-zinc-400 dark:border-zinc-600">Solasi/lb</th>
                            <th class="p-2 border border-zinc-400 dark:border-zinc-600">Ongkos Per m3</th>
                            <th class="p-2 border border-zinc-400 dark:border-zinc-600">Ongkos Mesin</th>
                            <th class="p-2 border border-zinc-400 dark:border-zinc-600">Total Per M3+Mesin</th>
                            <th class="p-2 border border-zinc-400 dark:border-zinc-600">Ongkos Per Lbr</th>
                            <th class="p-2 border border-zinc-400 dark:border-zinc-600">Keterangan</th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                        @foreach($items as $row)
                        <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-900/50 transition duration-75 text-center align-middle font-medium text-zinc-900 dark:text-zinc-200">
                            <td class="p-2 border border-zinc-300 dark:border-zinc-800 bg-zinc-50 dark:bg-zinc-900 font-bold">{{ $row['tanggal'] }}</td>
                            <td class="p-2 border border-zinc-300 dark:border-zinc-800">{{ $row['p'] }}</td>
                            <td class="p-2 border border-zinc-300 dark:border-zinc-800">{{ $row['l'] }}</td>
                            <td class="p-2 border border-zinc-300 dark:border-zinc-800">{{ $row['t'] }}</td>
                            <td class="p-2 border border-zinc-300 dark:border-zinc-800 uppercase font-bold text-orange-600 dark:text-orange-400">{{ $row['jenis'] }}</td>

                            <td class="p-2 border border-zinc-300 dark:border-zinc-800">{{ number_format($row['kw1'],0) }}</td>
                            <td class="p-2 border border-zinc-300 dark:border-zinc-800">{{ number_format($row['kw2'],0) }}</td>
                            <td class="p-2 border border-zinc-300 dark:border-zinc-800">{{ number_format($row['kw3'],0) }}</td>
                            <td class="p-2 border border-zinc-300 dark:border-zinc-800">{{ number_format($row['kw4'],0) }}</td>
                            <td class="p-2 border border-zinc-400 dark:border-zinc-600">{{ number_format($row['kw5'],0) }}</td>

                            <td class="p-2 border border-zinc-400 dark:border-zinc-600">{{ number_format($row['byk'],0) }}</td>
                            <td class="p-2 border border-zinc-400 dark:border-zinc-600">{{ number_format($row['m3'], 4) }}</td>
                            <td class="p-2 border border-zinc-300 dark:border-zinc-800 font-bold">{{ $row['ttl_pkj'] }}</td>

                            <td class="p-2 border border-zinc-300 dark:border-zinc-800 text-right font-bold text-green-600 dark:text-green-500">{{ number_format($row['harga'], 0, ',', '.') }}</td>
                            <td class="p-2 border border-zinc-300 dark:border-zinc-800 text-right">{{ number_format($row['total_solasi'], 0, ',', '.') }}</td>
                            <td class="p-2 border border-zinc-300 dark:border-zinc-800 text-right">{{ number_format($row['harga_solasi'], 0, ',', '.') }}</td>

                            <td class="p-2 border border-zinc-300 dark:border-zinc-800 text-right font-bold">{{ number_format($row['solasi_m3'], 0, ',', '.') }}</td>
                            <td class="p-2 border border-zinc-300 dark:border-zinc-800 text-right font-bold">{{ number_format($row['solasi_lbr'], 0, ',', '.') }}</td>

                            <td class="p-2 border border-zinc-300 dark:border-zinc-800 text-right font-black">{{ number_format($row['ongkos_per_m3'], 0, ',', '.') }}</td>
                            <td class="p-2 border border-zinc-300 dark:border-zinc-800 text-right">{{ number_format($row['ongkos_mesin'], 0, ',', '.') }}</td>

                            <td class="p-2 border border-zinc-400 dark:border-zinc-600 text-right font-bold">{{ number_format($row['ongkos_m3_mesin'], 0, ',', '.') }}</td>
                            <td class="p-2 border border-zinc-400 dark:border-zinc-600 text-right font-bold">{{ number_format($row['ongkos_per_lb'], 0, ',', '.') }}</td>

                            <td class="p-2 border border-zinc-300 dark:border-zinc-800 text-left text-[9px] max-w-[150px] truncate text-red-600 dark:text-red-400" title="{{ $row['ket'] }}">{{ $row['ket'] }}</td>
                        </tr>
                        @endforeach
                    </tbody>

                    {{-- Footer Summary --}}
                    <tfoot class="bg-zinc-100 dark:bg-zinc-900 font-black text-zinc-900 dark:text-white uppercase tracking-tighter border-t-2 border-zinc-800 dark:border-zinc-600">
                        <tr>
                            <td colspan="18" class="p-2 text-right border border-zinc-400 dark:border-zinc-600">Grand Total</td>
                            <td class="p-2 text-right border border-zinc-400 dark:border-zinc-600 text-orange-600 dark:text-orange-400">
                                {{ number_format($items->sum('ongkos_per_m3'), 0, ',', '.') }}
                            </td>
                            <td class="p-2 border border-zinc-400 dark:border-zinc-600"></td>
                            <td class="p-2 text-right border border-zinc-400 dark:border-zinc-600 text-orange-600 dark:text-orange-400">
                                {{ number_format($items->sum('ongkos_m3_mesin'), 0, ',', '.') }}
                            </td>
                            <td class="p-2 text-right border border-zinc-400 dark:border-zinc-600 text-orange-600 dark:text-orange-400">
                                {{ number_format($items->sum('ongkos_per_lb'), 0, ',', '.') }}
                            </td>
                            <td class="p-2 border border-zinc-400 dark:border-zinc-600"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
        @empty
        <div class="p-20 text-center bg-zinc-50 dark:bg-zinc-900 rounded-2xl border-2 border-dashed border-zinc-200 dark:border-zinc-800">
            <x-heroicon-o-document-magnifying-glass class="w-16 h-16 mx-auto mb-4 text-zinc-300 dark:text-zinc-600" />
            <p class="text-sm text-zinc-400 font-black uppercase tracking-widest">Data tidak ditemukan.</p>
        </div>
        @endforelse
    </div>
</x-filament-panels::page>