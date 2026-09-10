<x-filament-panels::page>
    {{-- Form Filter Tanggal --}}
    <div class="p-4 bg-white dark:bg-zinc-900 rounded-lg shadow border border-zinc-200 dark:border-zinc-800">
        {{ $this->form }}
    </div>

    {{-- Loading Indicator --}}
    @if ($isLoading ?? false)
        <div
            class="fixed inset-0 z-50 flex items-center justify-center bg-white bg-opacity-75 dark:bg-zinc-900 dark:bg-opacity-75">
            <div class="flex items-center space-x-3">
                <x-filament::loading-indicator class="w-8 h-8 text-primary-600" />
                <span class="text-lg font-medium text-zinc-700 dark:text-zinc-300">Memuat data pilih veneer...</span>
            </div>
        </div>
    @endif

    @php
        $tables = collect($dataProduksi ?? [])->values();

        // Flatten all workers
        $semuaPekerja = $tables
            ->flatMap(function ($table) {
                return collect($table['rekap_pekerja'] ?? [])->map(function ($p) use ($table) {
                    $p['tanggal'] = $table['tanggal'] ?? '-';
                    $p['nomor_meja'] = $table['nomor_meja'] ?? '-';
                    $p['detail_produksi'] = $table['detail_produksi'] ?? [];
                    $p['pencapaian_global'] = $table['pencapaian_global'] ?? 0;
                    return $p;
                });
            })
            ->values();
    @endphp

    <div class="space-y-12 mt-6">
        @forelse ($semuaPekerja as $p)
            @php
                $pencapaianGlobal = $p['pencapaian'] ?? 0;
                $tercapai = $pencapaianGlobal >= 100;
                $potongan = (int) ($p['pot_target'] ?? 0);
                $detailProduksi = $p['detail_produksi'] ?? [];
                $totalUkuran = count($detailProduksi);
            @endphp

            <div
                class="bg-white dark:bg-zinc-900 rounded-sm shadow-lg border border-zinc-200 dark:border-zinc-700 overflow-hidden">
                {{-- Header Blok Produksi --}}
                <div class="bg-zinc-800 p-4 text-white flex justify-between items-center">
                    <h2 class="text-lg font-bold text-center">
                        {{ $p['id'] }} - {{ strtoupper($p['nama']) }}
                    </h2>
                    <div class="flex gap-4 items-center">
                        <span class="text-xs px-2 py-1 rounded bg-zinc-700 font-semibold uppercase">
                            Meja: {{ $p['nomor_meja'] }}
                        </span>
                        <span class="text-xs px-2 py-1 rounded {{ $tercapai ? 'bg-green-700' : 'bg-red-700' }}">
                            Capaian: {{ number_format($pencapaianGlobal, 1, ',', '.') }}%
                        </span>
                        @if ($potongan > 0)
                            <span class="text-xs px-2 py-1 rounded bg-amber-600 font-bold">
                                ⚠ Potongan: Rp {{ number_format($potongan) }}
                            </span>
                        @endif
                        <span class="text-xs bg-zinc-700 px-2 py-1 rounded">
                            {{ $tercapai ? '✔ Tercapai' : '✘ Belum' }}
                        </span>
                    </div>
                </div>

                <div class="p-4">
                    <div class="w-full overflow-x-auto">
                        <div class="min-w-[800px]">
                            <table class="w-full text-sm border-collapse border border-zinc-300 dark:border-zinc-600">
                                <thead>
                                    <tr>
                                        <th colspan="7"
                                            class="p-4 text-xl font-bold text-center bg-zinc-700 text-white uppercase tracking-wider">
                                            Detail Produksi Meja
                                        </th>
                                    </tr>
                                    <tr
                                        class="bg-zinc-200 dark:bg-zinc-700 text-zinc-800 dark:text-zinc-300 border-t border-zinc-300 dark:border-zinc-600">
                                        <th class="p-2 text-left text-xs font-semibold uppercase">Ukuran</th>
                                        <th class="p-2 text-center text-xs font-semibold w-24 uppercase">Jenis Kayu</th>
                                        <th class="p-2 text-center text-xs font-semibold w-16 uppercase">KW</th>
                                        <th class="p-2 text-center text-xs font-semibold w-28 uppercase">No. Palet</th>
                                        <th class="p-2 text-right text-xs font-semibold w-24 uppercase">Hasil</th>
                                        <th class="p-2 text-right text-xs font-semibold w-24 uppercase">Target</th>
                                        <th class="p-2 text-right text-xs font-semibold w-20 uppercase">Capaian</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($detailProduksi as $i => $prod)
                                        @php
                                            $isMencapaiTarget = ($prod['hasil'] ?? 0) >= ($prod['target'] ?? 0);
                                            $warnaStatus = $isMencapaiTarget
                                                ? 'text-green-500 font-bold'
                                                : 'text-red-500 font-bold';
                                            // NOTE: kode_ukuran === 'PILIH-VENEER-NOT-FOUND' berarti relasi
                                            // Ukuran/JenisKayu (model ModalPilihVeneer) gagal di-resolve,
                                            // BUKAN berarti Target tidak ditemukan. Jangan gabungkan dengan has_target.
                                            $isUkuranNotFound =
                                                ($prod['kode_ukuran'] ?? null) === 'PILIH-VENEER-NOT-FOUND';
                                        @endphp
                                        <tr
                                            class="{{ $i % 2 === 1 ? 'bg-zinc-50 dark:bg-zinc-800/50' : 'bg-white dark:bg-zinc-900' }} border-t border-zinc-300 dark:border-zinc-700 hover:bg-zinc-100 dark:hover:bg-zinc-800/80 transition-colors">
                                            <td
                                                class="p-2 text-left text-xs border-r border-zinc-300 dark:border-zinc-700 font-medium">
                                                {{ $prod['ukuran'] ?? '-' }}
                                                @if ($isUkuranNotFound)
                                                    <span class="text-red-400 font-semibold">(Ukuran/Jenis Kayu Tidak
                                                        Ditemukan)</span>
                                                @endif
                                            </td>
                                            <td
                                                class="p-2 text-center text-xs border-r border-zinc-300 dark:border-zinc-700 uppercase">
                                                {{ $prod['jenis_kayu'] ?? '-' }}
                                            </td>
                                            <td
                                                class="p-2 text-center text-xs border-r border-zinc-300 dark:border-zinc-700 uppercase">
                                                {{ $prod['kw'] ?? '-' }}
                                            </td>
                                            <td
                                                class="p-2 text-center text-xs border-r border-zinc-300 dark:border-zinc-700 text-zinc-500">
                                                {{ $prod['no_palet_list'] ?? '-' }}
                                            </td>
                                            <td
                                                class="p-2 text-right text-xs border-r border-zinc-300 dark:border-zinc-700 font-bold text-green-600 dark:text-green-400">
                                                {{ number_format($prod['hasil'] ?? 0) }}
                                            </td>
                                            <td
                                                class="p-2 text-right text-xs border-r border-zinc-300 dark:border-zinc-700 text-zinc-500">
                                                {{ $prod['has_target'] ? number_format($prod['target'] ?? 0) : '-' }}
                                            </td>
                                            <td
                                                class="p-2 text-right text-xs font-bold {{ !$prod['has_target'] ? 'text-red-500' : (($prod['capaian_persen'] ?? 0) >= 100 ? 'text-green-600 dark:text-green-400' : 'text-amber-600 dark:text-amber-400') }}">
                                                @if (!$prod['has_target'])
                                                    Target ?
                                                @else
                                                    {{ number_format($prod['capaian_persen'] ?? 0, 1, ',', '.') }}%
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="7"
                                                class="p-4 text-center text-zinc-500 dark:text-zinc-400 text-sm italic">
                                                Tidak ada detail produksi untuk meja ini.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                                <tfoot
                                    class="bg-zinc-100 dark:bg-zinc-800 border-t-2 border-zinc-300 dark:border-zinc-600">
                                    <tr>
                                        <td colspan="7"
                                            class="p-3 text-center text-xs text-zinc-600 dark:text-zinc-400 space-x-3">
                                            <span class="font-medium">Ukuran Dikerjakan:</span>
                                            <strong class="text-zinc-900 dark:text-white">{{ $totalUkuran }}</strong>

                                            <span class="text-zinc-400">|</span>

                                            <span class="font-medium">Masuk:</span>
                                            <strong
                                                class="font-mono text-zinc-900 dark:text-white">{{ $p['jam_masuk'] }}</strong>

                                            <span class="text-zinc-400">|</span>

                                            <span class="font-medium">Pulang:</span>
                                            <strong
                                                class="font-mono text-zinc-900 dark:text-white">{{ $p['jam_pulang'] }}</strong>

                                            <span class="text-zinc-400">|</span>

                                            <span class="font-medium">Ijin:</span>
                                            <strong
                                                class="text-yellow-600 dark:text-yellow-400">{{ $p['ijin'] }}</strong>

                                            <span class="text-zinc-400">|</span>

                                            <span class="font-medium">Jam Kerja:</span>
                                            <strong
                                                class="font-mono text-zinc-900 dark:text-white">{{ $p['jam_kerja'] }}</strong>

                                            <span class="text-zinc-400">|</span>

                                            <span class="text-xs">Tgl: {{ $p['tanggal'] }}</span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td colspan="7"
                                            class="p-2 text-center text-[11px] text-zinc-500 dark:text-zinc-400 border-t border-zinc-300 dark:border-zinc-700">
                                            Capaian GLOBAL (jumlah persen semua ukuran yang dikerjakan hari ini, basis:
                                            target per ukuran, BUKAN rata-rata):
                                            <strong
                                                class="{{ $tercapai ? 'text-green-600 dark:text-green-400' : 'text-red-500' }}">
                                                {{ number_format($pencapaianGlobal, 1, ',', '.') }}%
                                            </strong>
                                            @if (!empty($p['keterangan']) && $p['keterangan'] !== '-')
                                                <span class="text-zinc-400">|</span>
                                                <span class="italic">Ket: {{ $p['keterangan'] }}</span>
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <td colspan="7"
                                            class="px-3 py-2 text-center border-t border-zinc-300 dark:border-zinc-700 {{ $potongan > 0 ? 'bg-red-50 dark:bg-red-950/30' : '' }}">
                                            <span
                                                class="text-xs font-bold uppercase tracking-wide {{ $potongan > 0 ? 'text-red-600 dark:text-red-400' : 'text-zinc-500 dark:text-zinc-400' }}">
                                                Potongan Target:
                                            </span>
                                            <span
                                                class="text-base font-black {{ $potongan > 0 ? 'text-red-600 dark:text-red-400' : 'text-zinc-500 dark:text-zinc-400' }}">
                                                {{ $potongan > 0 ? 'Rp ' . number_format($potongan) : 'Rp 0' }}
                                            </span>
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
                    Tidak ditemukan data produksi pilih veneer untuk tanggal ini.
                </p>
                <p class="text-sm text-zinc-400 mt-2">
                    Silakan pilih tanggal lain atau pastikan input produksi pilih veneer sudah dilakukan.
                </p>
            </div>
        @endforelse
    </div>
</x-filament-panels::page>
