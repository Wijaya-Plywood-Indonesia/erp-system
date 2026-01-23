<x-filament-panels::page>
    <div
        class="p-4 bg-white dark:bg-zinc-900 rounded-lg shadow border border-zinc-200 dark:border-zinc-700">
        {{ $this->form }}
    </div>

    @if($isLoading)
    <div
        class="fixed inset-0 z-50 flex items-center justify-center bg-white bg-opacity-75 dark:bg-zinc-900 dark:bg-opacity-75">
        <div class="flex items-center space-x-3">
            <x-filament::loading-indicator class="w-8 h-8 text-primary-600" />
            <span class="text-lg font-medium text-zinc-700 dark:text-zinc-300">Sedang memproses data gabungan...</span>
        </div>
    </div>
    @endif

    <div class="mt-6">
        <div
            class="bg-white dark:bg-zinc-900 rounded-sm shadow-lg border border-zinc-200 dark:border-zinc-700 overflow-hidden">
            <div
                class="bg-zinc-800 p-4 text-white flex justify-between items-center">
                <h2
                    class="text-lg font-bold text-center uppercase tracking-wider">
                    LAPORAN HARIAN
                </h2>
                <div
                    class="text-xs font-mono bg-zinc-700 px-2 py-1 rounded border border-zinc-600">
                    {{ count($laporanGabungan) }} DATA PEGAWAI
                </div>
            </div>

            <div class="p-0">
                <div class="w-full overflow-x-auto">
                    <div class="min-w-[800px]">
                        <table
                            class="w-full text-sm border-collapse border border-zinc-300 dark:border-zinc-600">
                            <thead>
                                <tr
                                    class="bg-zinc-700 text-white text-xs uppercase tracking-wider">
                                    <th
                                        class="p-3 text-center border-r border-zinc-600 w-16">
                                        Kodep
                                    </th>
                                    <th
                                        class="p-3 text-left border-r border-zinc-600">
                                        Nama Pegawai
                                    </th>
                                    <th
                                        class="p-3 text-center border-r border-zinc-600 w-20">
                                        Masuk
                                    </th>
                                    <th
                                        class="p-3 text-center border-r border-zinc-600 w-20">
                                        Pulang
                                    </th>
                                    <th
                                        class="p-3 text-left border-r border-zinc-600">
                                        Hasil / Divisi
                                    </th>
                                    <th
                                        class="p-3 text-center border-r border-zinc-600 w-16">
                                        Ijin
                                    </th>
                                    <th
                                        class="p-3 text-right border-r border-zinc-600 w-36">
                                        Potongan Target
                                    </th>
                                    <th class="p-3 text-left">Keterangan</th>
                                </tr>
                            </thead>

                            <tbody>
                                @forelse($laporanGabungan as $index => $row)
                                <tr
                                    class="{{
                                        $index % 2 === 0
                                            ? 'bg-white dark:bg-zinc-900'
                                            : 'bg-zinc-50 dark:bg-zinc-800/50'
                                    }} border-t border-zinc-300 dark:border-zinc-700 hover:bg-zinc-100 dark:hover:bg-zinc-700 transition duration-75">
                                    <td
                                        class="p-2 text-center text-xs font-mono border-r border-zinc-300 dark:border-zinc-700 text-zinc-600 dark:text-zinc-400">
                                        {{ $row["kodep"] }}
                                    </td>

                                    <td
                                        class="p-2 text-left text-xs font-semibold border-r border-zinc-300 dark:border-zinc-700 text-zinc-900 dark:text-zinc-100">
                                        {{ $row["nama"] }}
                                    </td>

                                    <td
                                        class="p-2 text-center text-xs border-r border-zinc-300 dark:border-zinc-700 font-mono text-zinc-700 dark:text-zinc-300">
                                        {{ $row["masuk"] }}
                                    </td>

                                    <td
                                        class="p-2 text-center text-xs border-r border-zinc-300 dark:border-zinc-700 font-mono text-zinc-700 dark:text-zinc-300">
                                        {{ $row["pulang"] }}
                                    </td>

                                    <td class="p-2 text-left text-xs font-medium border-r border-zinc-300 dark:border-zinc-700 whitespace-nowrap">
                                        @if(str_contains($row['hasil'], 'ROTARY'))
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-300">
                                            ROTARY
                                        </span>
                                        @elseif(str_contains($row['hasil'], 'DRYER'))
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300">
                                            DRYER
                                        </span>
                                        @elseif(str_contains($row['hasil'], 'REPAIR'))
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300">
                                            REPAIR
                                        </span>
                                        @elseif(str_contains($row['hasil'], 'SANDING JOINT'))
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold bg-teal-100 text-blue-800 dark:bg-teal-900 dark:text-teal-300">
                                            SANDING JOIN
                                        </span>
                                        @elseif(str_contains($row['hasil'], 'JOINT'))
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold bg-cyan-100 text-cyan-800 dark:bg-cyan-900 dark:text-cyan-300">
                                            JOIN
                                        </span>
                                        @elseif(str_contains($row['hasil'], 'STIK'))
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold bg-pink-100 text-pink-800 dark:bg-pink-900 dark:text-pink-300">
                                            STIK
                                        </span>
                                        @elseif(str_contains($row['hasil'], 'KEDI') || str_contains($row['hasil'], 'PUTTY'))
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-300">
                                            KEDI
                                        </span>
                                        @elseif(str_contains($row['hasil'], 'POT AFALAN'))
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold bg-rose-100 text-rose-800 dark:bg-rose-900 dark:text-rose-300">
                                            POT AFALAN
                                        </span>
                                        @else
                                        <span class="text-zinc-700 dark:text-zinc-300 font-bold">{{ $row["hasil"] }}</span>
                                        @endif
                                    </td>

                                    <td
                                        class="p-2 text-center text-xs font-bold border-r border-zinc-300 dark:border-zinc-700 text-yellow-600 dark:text-yellow-400">
                                        {{ $row["ijin"] }}
                                    </td>

                                    <td
                                        class="p-2 text-right text-xs font-mono border-r border-zinc-300 dark:border-zinc-700">
                                        @if($row['potongan_targ'] > 0)
                                        <span
                                            class="font-bold text-red-600 dark:text-red-400">
                                            Rp
                                            {{
                                                number_format(
                                                    $row["potongan_targ"],
                                                    0,
                                                    ",",
                                                    "."
                                                )
                                            }}
                                        </span>
                                        @else
                                        <span
                                            class="text-zinc-400 dark:text-zinc-600 font-light">-</span>
                                        @endif
                                    </td>

                                    <td
                                        class="p-2 text-left text-xs italic text-zinc-600 dark:text-zinc-400">
                                        {{ $row["keterangan"] }}
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td
                                        colspan="8"
                                        class="p-12 text-center text-zinc-500 dark:text-zinc-400">
                                        <div
                                            class="flex flex-col items-center justify-center">
                                            <x-heroicon-o-document-magnifying-glass
                                                class="w-12 h-12 mb-2 opacity-50" />
                                            <p class="text-lg">
                                                Tidak ada data pegawai untuk
                                                tanggal ini.
                                            </p>
                                        </div>
                                    </td>
                                </tr>
                                @endforelse
                            </tbody>

                            @if(!empty($laporanGabungan))
                            <tfoot
                                class="bg-zinc-100 dark:bg-zinc-800 border-t-2 border-zinc-300 dark:border-zinc-600">
                                <tr>
                                    <td
                                        colspan="8"
                                        class="p-3 text-center text-xs text-zinc-600 dark:text-zinc-400 space-x-4">
                                        <span class="font-medium">Total Pekerja:</span>
                                        <strong
                                            class="text-zinc-900 dark:text-white text-sm">{{
                                                count($laporanGabungan)
                                            }}</strong>

                                        <span class="text-zinc-300">|</span>

                                        <span class="font-medium">Total Potongan:</span>
                                        <strong
                                            class="text-red-600 dark:text-red-400 text-sm font-mono">
                                            Rp
                                            {{
                                                number_format(
                                                    array_sum(
                                                        array_column(
                                                            $laporanGabungan,
                                                            "potongan_targ"
                                                        )
                                                    ),
                                                    0,
                                                    ",",
                                                    "."
                                                )
                                            }}
                                        </strong>
                                    </td>
                                </tr>
                            </tfoot>
                            @endif
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>