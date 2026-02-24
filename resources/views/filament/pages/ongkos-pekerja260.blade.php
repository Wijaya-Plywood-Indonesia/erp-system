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
            <span class="text-lg font-medium animate-pulse">Menghitung kalkulasi...</span>
        </div>
    </div>
    @endif

    <div class="mt-6 space-y-8">
        @php
        $groupedLaporan = collect($laporanOngkos)->groupBy('kategori_mesin');
        @endphp

        @forelse($groupedLaporan as $kategori => $items)
        <div class="bg-white dark:bg-zinc-950 rounded-sm shadow-xl border border-zinc-200 dark:border-zinc-800 overflow-hidden">

            {{-- Header Tabel --}}
            <div class="bg-zinc-100 dark:bg-zinc-900 p-4 border-b border-zinc-500 dark:border-zinc-800 flex justify-between">
                <h2 class="text-sm font-black uppercase tracking-widest text-zinc-800 dark:text-zinc-200">
                    {{ $kategori }}
                </h2>
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
                            {{-- Kolom yang di-merge berdasarkan kriteria User --}}
                            <th class="p-2 border border-zinc-400">Total Pekerja</th>
                            <th class="p-2 border border-zinc-400">Harga Pekerja</th>
                            {{-- Data Solasi per ukuran --}}
                            <th class="p-2 border border-zinc-400">Total Solasi</th>
                            <th class="p-2 border border-zinc-400">Harga Solasi</th>
                            <th class="p-2 border border-zinc-400">Solasi/m3</th>
                            <th class="p-2 border border-zinc-400">Solasi/lb</th>
                            {{-- Ongkos kolektif yang di-merge --}}
                            <th class="p-2 border border-zinc-400">Ongkos per m3</th>
                            <th class="p-2 border border-zinc-400">Ongkos Mesin</th>
                            <th class="p-2 border border-zinc-400">Ongkos m3+Mesin</th>
                            <th class="p-2 border border-zinc-400">Ongkos per Lembar</th>
                            <th class="p-2 border border-zinc-400">Ket</th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                        @php
                        $dateCounts = $items->countBy('tanggal');
                        $displayedDates = [];
                        $displayedMergeCols = [];
                        @endphp

                        @foreach($items as $row)
                        <tr class="text-center align-middle font-medium text-zinc-900 dark:text-zinc-200">

                            {{-- MERGE: Tanggal --}}
                            @if(!in_array($row['tanggal'], $displayedDates))
                            <td class="p-2 border border-zinc-500 bg-zinc-50 dark:bg-zinc-900 font-bold" rowspan="{{ $dateCounts[$row['tanggal']] }}">
                                {{ $row['tanggal'] }}
                            </td>
                            @php $displayedDates[] = $row['tanggal']; @endphp
                            @endif

                            {{-- INDIVIDUAL: Ukuran --}}
                            <td class="p-2 border border-zinc-500">{{ $row['p'] }}</td>
                            <td class="p-2 border border-zinc-500">{{ $row['l'] }}</td>
                            <td class="p-2 border border-zinc-500">{{ $row['t'] }}</td>
                            <td class="p-2 border border-zinc-500 uppercase font-bold text-orange-600">{{ $row['jenis'] }}</td>
                            <td class="p-2 border border-zinc-500">{{ number_format($row['kw1'], 0) }}</td>
                            <td class="p-2 border border-zinc-500">{{ number_format($row['kw2'], 0) }}</td>
                            <td class="p-2 border border-zinc-500">{{ number_format($row['kw3'], 0) }}</td>
                            <td class="p-2 border border-zinc-500">{{ number_format($row['kw4'], 0) }}</td>
                            <td class="p-2 border border-zinc-500">{{ number_format($row['kw5'], 0) }}</td>
                            <td class="p-2 border border-zinc-500 font-bold">{{ number_format($row['byk'], 0) }}</td>
                            <td class="p-2 border border-zinc-500">{{ number_format($row['m3'], 4) }}</td>

                            {{-- MERGE: Total Pekerja & Harga Pekerja --}}
                            @if(!in_array($row['tanggal'], $displayedMergeCols))
                            <td class="p-2 border border-zinc-500 font-bold" rowspan="{{ $dateCounts[$row['tanggal']] }}">
                                {{ $row['ttl_pkj'] }}
                            </td>
                            <td class="p-2 border border-zinc-500 text-right font-bold text-green-600" rowspan="{{ $dateCounts[$row['tanggal']] }}">
                                {{ number_format($row['harga'], 0, ',', '.') }}
                            </td>
                            @endif

                            {{-- INDIVIDUAL: Data Solasi (Sesuai per Baris Ukuran) --}}
                            <td class="p-2 border border-zinc-500">{{ number_format($row['total_solasi'], 0) }}</td>
                            <td class="p-2 border border-zinc-500 text-right">{{ number_format($row['harga_solasi'], 0, ',', '.') }}</td>
                            <td class="p-2 border border-zinc-500 text-right font-bold">{{ number_format($row['solasi_m3'], 0, ',', '.') }}</td>
                            <td class="p-2 border border-zinc-500 text-right font-bold">{{ number_format($row['solasi_lbr'], 0, ',', '.') }}</td>

                            {{-- MERGE: Ongkos-ongkos Kolektif --}}
                            @if(!in_array($row['tanggal'], $displayedMergeCols))
                            <td class="p-2 border border-zinc-500 text-right font-black" rowspan="{{ $dateCounts[$row['tanggal']] }}">
                                {{ number_format($row['ongkos_per_m3'], 0, ',', '.') }}
                            </td>
                            <td class="p-2 border border-zinc-500 text-right font-medium" rowspan="{{ $dateCounts[$row['tanggal']] }}">
                                {{ number_format($row['ongkos_mesin'], 0, ',', '.') }}
                            </td>
                            <td class="p-2 border border-zinc-500 text-right font-bold text-orange-600" rowspan="{{ $dateCounts[$row['tanggal']] }}">
                                {{ number_format($row['ongkos_m3_mesin'], 0, ',', '.') }}
                            </td>
                            <td class="p-2 border border-zinc-500 text-right font-bold text-orange-600" rowspan="{{ $dateCounts[$row['tanggal']] }}">
                                {{ number_format($row['ongkos_per_lb'], 0, ',', '.') }}
                            </td>
                            <td class="p-2 border border-zinc-500 text-[9px] text-red-600 dark:text-red-400 font-black italic" rowspan="{{ $dateCounts[$row['tanggal']] }}">
                                {{ $row['ket'] }}
                            </td>
                            @php $displayedMergeCols[] = $row['tanggal']; @endphp
                            @endif
                        </tr>
                        @endforeach
                    </tbody>

                    {{-- Footer Summary --}}
                    <tfoot class="bg-zinc-100 dark:bg-zinc-900 font-black text-zinc-900 dark:text-white uppercase border-t border-zinc-500 text-center">
                        <tr>
                            <td colspan="10" class="p-2 text-right">Grand Total</td>

                            {{-- Total Banyak (Sum) --}}
                            <td class="p-2 border border-zinc-500 dark:border-zinc-700">
                                {{ number_format($items->sum('byk'), 0) }}
                            </td>

                            {{-- Total M3 (Sum) --}}
                            <td class="p-2 border border-zinc-500 dark:border-zinc-700">
                                {{ number_format($items->sum('m3'), 4) }}
                            </td>

                            {{-- Total Pekerja (Unique per Tanggal) --}}
                            <td class="p-2 border border-zinc-500 dark:border-zinc-700">
                                {{ number_format($items->unique('tanggal')->sum('ttl_pkj'), 0) }}
                            </td>

                            <td colspan="5" class="p-2 border border-zinc-500 dark:border-zinc-700"></td>

                            {{-- Rata-rata Ongkos per M3 (Average dari semua baris) --}}
                            <td class="p-2 border border-zinc-500 dark:border-zinc-700 text-right text-orange-600">
                                {{ number_format($items->avg('ongkos_per_m3'), 0, ',', '.') }}
                            </td>

                            <td class="p-2 border border-zinc-500 dark:border-zinc-700"></td>

                            {{-- Rata-rata Ongkos M3 + Mesin (Average dari semua baris) --}}
                            <td class="p-2 border border-zinc-500 dark:border-zinc-700 text-right text-orange-600">
                                {{ number_format($items->avg('ongkos_m3_mesin'), 0, ',', '.') }}
                            </td>

                            {{-- Rata-rata Ongkos per Lembar (Average dari semua baris) --}}
                            <td class="p-2 border border-zinc-500 dark:border-zinc-700 text-right text-orange-600">
                                {{ number_format($items->avg('ongkos_per_lb'), 0, ',', '.') }}
                            </td>

                            <td class="p-2 border border-zinc-500 dark:border-zinc-700"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
        @empty
        <div class="p-20 text-center bg-zinc-50 dark:bg-zinc-900 border-2 border-dashed border-zinc-200 rounded-2xl">
            <p class="text-sm text-zinc-400 font-black uppercase tracking-widest">Data tidak ditemukan.</p>
        </div>
        @endforelse
    </div>
</x-filament-panels::page>