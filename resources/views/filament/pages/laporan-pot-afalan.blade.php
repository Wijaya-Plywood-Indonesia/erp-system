<x-filament-panels::page>
    {{-- Form Filter Tanggal --}}
    <div class="p-4 bg-white dark:bg-zinc-900 rounded-lg shadow border border-zinc-200 dark:border-zinc-800">
        {{ $this->form }}
    </div>

    {{-- Loading Indicator --}}
    @if ($isLoading)
        <div
            class="fixed inset-0 z-50 flex items-center justify-center bg-white bg-opacity-75 dark:bg-zinc-900 dark:bg-opacity-75">
            <div class="flex items-center space-x-3">
                <x-filament::loading-indicator class="w-8 h-8 text-primary-600" />
                <span class="text-lg font-medium text-zinc-700 dark:text-zinc-300">Memuat data potong afalan...</span>
            </div>
        </div>
    @endif

    @php
        // $dataProduksi berbentuk array PER MEJA:
        // ['nomor_meja', 'tanggal', 'detail_produksi' => [...], 'rekap_pekerja' => [...]]
        $tables = collect($dataProduksi ?? [])->values();
    @endphp

    <div class="space-y-12 mt-6">
        @forelse ($tables as $table)
            @php
                $detailProduksi = $table['detail_produksi'] ?? [];
                $rekapPekerja = $table['rekap_pekerja'] ?? [];
                $totalPekerja = count($rekapPekerja);
                $totalPotongan = collect($rekapPekerja)->sum('pot_target');
            @endphp

            <div
                class="bg-white dark:bg-zinc-900 rounded-sm shadow-lg border border-zinc-200 dark:border-zinc-700 overflow-hidden">
                {{-- Header Blok Meja/Area --}}
                <div class="bg-zinc-800 p-4 text-white flex justify-between items-center">
                    <h2 class="text-lg font-bold uppercase">
                        {{ $table['nomor_meja'] ?? '-' }}
                    </h2>
                    <span class="text-xs bg-zinc-700 px-2 py-1 rounded">
                        {{ $table['tanggal'] ?? '-' }}
                    </span>
                </div>

                <div class="p-4 space-y-8">

                    {{-- ===== BAGIAN A: DETAIL PRODUKSI PER UKURAN ===== --}}
                    <div class="w-full overflow-x-auto">
                        <div class="min-w-[800px]">
                            <table class="w-full text-sm border-collapse border border-zinc-300 dark:border-zinc-600">
                                <thead>
                                    <tr>
                                        <th colspan="6"
                                            class="p-4 text-xl font-bold text-center bg-zinc-700 text-white uppercase tracking-wider">
                                            Detail Produksi
                                        </th>
                                    </tr>
                                    <tr
                                        class="bg-zinc-200 dark:bg-zinc-700 text-zinc-800 dark:text-zinc-300 border-t border-zinc-300 dark:border-zinc-600">
                                        <th class="p-2 text-left text-xs font-semibold uppercase">Ukuran</th>
                                        <th class="p-2 text-left text-xs font-semibold uppercase">Jenis Kayu</th>
                                        <th class="p-2 text-center text-xs font-semibold w-20 uppercase">Grade/KW</th>
                                        <th class="p-2 text-right text-xs font-semibold w-32 uppercase">Target (Adj.)
                                        </th>
                                        <th class="p-2 text-right text-xs font-semibold w-32 uppercase">Hasil</th>
                                        <th class="p-2 text-right text-xs font-semibold w-24 uppercase">Selisih</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($detailProduksi as $i => $prod)
                                        @php
                                            $isMencapaiTarget = ($prod['hasil'] ?? 0) >= ($prod['target'] ?? 0);
                                            $warnaStatus = $isMencapaiTarget
                                                ? 'text-green-500 font-bold'
                                                : 'text-red-500 font-bold';
                                            $selisih = $prod['selisih'] ?? 0;
                                            $tanda = $selisih >= 0 ? '+' : '';
                                            $isNotFound = ($prod['kode_ukuran'] ?? null) === 'POT-AFALAN-NOT-FOUND';
                                        @endphp
                                        <tr
                                            class="{{ $i % 2 === 1 ? 'bg-zinc-50 dark:bg-zinc-800/50' : 'bg-white dark:bg-zinc-900' }} border-t border-zinc-300 dark:border-zinc-700 hover:bg-zinc-100 dark:hover:bg-zinc-800/80 transition-colors">
                                            <td
                                                class="p-2 text-left text-xs border-r border-zinc-300 dark:border-zinc-700 font-medium">
                                                {{ $prod['ukuran'] ?? '-' }}
                                                @if ($isNotFound)
                                                    <span class="text-red-400 font-semibold">(Target Tidak
                                                        Ditemukan)</span>
                                                @endif
                                            </td>
                                            <td
                                                class="p-2 text-left text-xs border-r border-zinc-300 dark:border-zinc-700 uppercase">
                                                {{ $prod['jenis_kayu'] ?? '-' }}
                                            </td>
                                            <td
                                                class="p-2 text-center text-xs border-r border-zinc-300 dark:border-zinc-700">
                                                <span
                                                    class="bg-primary-600 text-white px-2 py-0.5 rounded font-bold uppercase">
                                                    KW {{ $prod['kw'] ?? '-' }}
                                                </span>
                                            </td>
                                            <td
                                                class="p-2 text-right text-xs border-r border-zinc-300 dark:border-zinc-700 font-mono">
                                                {{ number_format($prod['target'] ?? 0) }}
                                            </td>
                                            <td
                                                class="p-2 text-right text-xs border-r border-zinc-300 dark:border-zinc-700 font-mono {{ $warnaStatus }}">
                                                {{ number_format($prod['hasil'] ?? 0) }}
                                            </td>
                                            <td class="p-2 text-right text-xs font-mono {{ $warnaStatus }}">
                                                {{ $tanda }}{{ number_format(abs($selisih)) }}
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="6"
                                                class="p-4 text-center text-zinc-500 dark:text-zinc-400 text-sm italic">
                                                Tidak ada detail produksi untuk meja ini.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                    {{-- ===== BAGIAN B: REKAP PENCAPAIAN & POTONGAN HARIAN ===== --}}
                    <div class="w-full overflow-x-auto">
                        <div class="min-w-[800px]">
                            <table class="w-full text-sm border-collapse border border-zinc-300 dark:border-zinc-600">
                                <thead>
                                    <tr>
                                        <th colspan="7"
                                            class="p-4 text-xl font-bold text-center bg-zinc-700 text-white uppercase tracking-wider">
                                            Data Pekerja
                                        </th>
                                    </tr>
                                    <tr
                                        class="bg-zinc-200 dark:bg-zinc-700 text-zinc-800 dark:text-zinc-300 border-t border-zinc-300 dark:border-zinc-600">
                                        <th class="p-2 text-center text-xs font-semibold w-16 uppercase">ID</th>
                                        <th class="p-2 text-left text-xs font-semibold w-40 uppercase">Nama</th>
                                        <th class="p-2 text-center text-xs font-semibold w-20 uppercase">Masuk</th>
                                        <th class="p-2 text-center text-xs font-semibold w-20 uppercase">Pulang</th>
                                        <th class="p-2 text-center text-xs font-semibold w-16 uppercase">Ijin</th>
                                        <th class="p-2 text-right text-xs font-semibold w-36 uppercase">Potongan Target
                                        </th>
                                        <th class="p-2 text-left text-xs font-semibold uppercase">Keterangan</th>
                                    </tr>
                                </thead>

                                <tbody>
                                    @forelse ($rekapPekerja as $i => $p)
                                        @php
                                            $potTarget = (int) ($p['pot_target'] ?? 0);
                                        @endphp
                                        <tr
                                            class="{{ $i % 2 === 1 ? 'bg-zinc-50 dark:bg-zinc-800/50' : 'bg-white dark:bg-zinc-900' }} border-t border-zinc-300 dark:border-zinc-700 hover:bg-zinc-100 dark:hover:bg-zinc-800/80 transition-colors">
                                            <td
                                                class="p-2 text-center text-xs border-r border-zinc-300 dark:border-zinc-700 font-mono">
                                                {{ $p['id'] ?? '-' }}
                                            </td>
                                            <td
                                                class="p-2 text-left text-xs border-r border-zinc-300 dark:border-zinc-700 font-medium uppercase">
                                                {{ $p['nama'] ?? '-' }}
                                            </td>
                                            <td
                                                class="p-2 text-center text-xs border-r border-zinc-300 dark:border-zinc-700">
                                                {{ $p['jam_masuk'] ?? '-' }}
                                            </td>
                                            <td
                                                class="p-2 text-center text-xs border-r border-zinc-300 dark:border-zinc-700">
                                                {{ $p['jam_pulang'] ?? '-' }}
                                            </td>
                                            <td
                                                class="p-2 text-center text-xs border-r border-zinc-300 dark:border-zinc-700 text-yellow-600 dark:text-yellow-400 font-semibold">
                                                {{ $p['ijin'] ?? '-' }}
                                            </td>
                                            <td
                                                class="p-2 text-right text-xs border-r border-zinc-300 dark:border-zinc-700 font-bold {{ $potTarget > 0 ? 'text-red-400' : '' }}">
                                                {{ $potTarget > 0 ? number_format($potTarget) : '-' }}
                                            </td>
                                            <td class="p-2 text-left text-xs italic text-zinc-500">
                                                {{ $p['keterangan'] ?? '-' }}
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="7"
                                                class="p-4 text-center text-zinc-500 dark:text-zinc-400 text-sm italic">
                                                Tidak ada data pekerja untuk meja ini.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>

                                <tfoot
                                    class="bg-zinc-100 dark:bg-zinc-800 border-t-2 border-zinc-300 dark:border-zinc-600">
                                    <tr>
                                        <td colspan="7"
                                            class="p-3 text-center text-xs text-zinc-600 dark:text-zinc-400 space-x-4">
                                            <span class="font-semibold">Total Pegawai:</span>
                                            <strong class="text-zinc-900 dark:text-white">{{ $totalPekerja }}</strong>

                                            <span class="text-zinc-400">|</span>

                                            <span class="font-semibold">Total Potongan:</span>
                                            <strong class="font-mono text-zinc-900 dark:text-white">
                                                {{ $totalPotongan > 0 ? number_format($totalPotongan) : '-' }}
                                            </strong>
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>

                </div>
            </div>

        @empty
            <div
                class="text-center p-12 bg-white dark:bg-zinc-900 rounded-lg border border-dashed border-zinc-300 dark:border-zinc-700">
                <x-heroicon-o-document-magnifying-glass class="w-12 h-12 mx-auto text-zinc-400 mb-4" />
                <p class="text-lg text-zinc-500 dark:text-zinc-400 font-medium">
                    Tidak ditemukan data produksi potong afalan untuk tanggal ini.
                </p>
                <p class="text-sm text-zinc-400 mt-2">
                    Silakan pilih tanggal lain atau pastikan input produksi potong afalan sudah dilakukan.
                </p>
            </div>
        @endforelse
    </div>
</x-filament-panels::page>
