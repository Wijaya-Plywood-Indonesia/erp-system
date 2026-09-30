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
                <span class="text-lg font-medium text-zinc-700 dark:text-zinc-300">Memuat data potong afalan...</span>
            </div>
        </div>
    @endif

    @php
        // 1 kartu = 1 produksi/meja (bukan 1 kartu per pegawai).
        // Pot Afalan Joint dikerjakan 2 orang untuk barang yang sama, jadi daftar pegawai tampil
        // di ATAS, lalu detail produksi (barang) SEKALI di bawahnya.
        $tables = collect($dataProduksi ?? [])->values();
    @endphp

    <div class="space-y-12 mt-6">
        @forelse ($tables as $table)
            @php
                $rekapPekerja = $table['rekap_pekerja'] ?? [];
                $detailProduksi = $table['detail_produksi'] ?? [];
                $totalUkuran = count($detailProduksi);

                $pencapaianGlobal = ($table['pencapaian_global'] ?? 0) * 100;
                $tercapai = $pencapaianGlobal >= 100;

                $potonganTim = (int) round($table['potongan_total_tim'] ?? 0);
                $potonganMelebihiGaji = (bool) ($table['potongan_melebihi_gaji'] ?? false);
                $jumlahPekerja = $table['jumlah_pekerja'] ?? count($rekapPekerja);
                $totalPotonganPegawai = collect($rekapPekerja)->sum(fn ($r) => (int) ($r['pot_target'] ?? 0));

                $ukuranTanpaTarget = collect($detailProduksi)
                    ->filter(fn ($d) => empty($d['has_target']))
                    ->pluck('ukuran')
                    ->unique()
                    ->values();
            @endphp

            <div
                class="bg-white dark:bg-zinc-900 rounded-sm shadow-lg border border-zinc-200 dark:border-zinc-700 overflow-hidden">
                {{-- Header Blok Produksi --}}
                <div class="bg-zinc-800 p-4 text-white flex flex-wrap gap-2 justify-between items-center">
                    <h2 class="text-lg font-bold">
                        MEJA: {{ strtoupper($table['nomor_meja'] ?? '-') }}
                        <span class="text-xs font-normal text-zinc-300 ml-2">Tgl: {{ $table['tanggal'] ?? '-' }}</span>
                    </h2>
                    <div class="flex flex-wrap gap-3 items-center">
                        <span class="text-xs px-2 py-1 rounded bg-zinc-700 font-semibold uppercase">
                            {{ $jumlahPekerja }} Pekerja
                        </span>
                        <span class="text-xs px-2 py-1 rounded {{ $tercapai ? 'bg-green-700' : 'bg-red-700' }}">
                            Capaian: {{ number_format($pencapaianGlobal, 1, ',', '.') }}%
                        </span>
                        @if ($totalPotonganPegawai > 0)
                            <span class="text-xs px-2 py-1 rounded bg-amber-600 font-bold">
                                ⚠ Total Potongan: Rp {{ number_format($totalPotonganPegawai) }}
                            </span>
                        @endif
                        <span class="text-xs bg-zinc-700 px-2 py-1 rounded">
                            {{ $tercapai ? '✔ Tercapai' : '✘ Belum' }}
                        </span>
                    </div>
                </div>

                {{-- Peringatan ukuran yang belum punya target --}}
                @if ($ukuranTanpaTarget->isNotEmpty())
                    <div
                        class="px-4 py-2 text-xs bg-red-50 dark:bg-red-950/30 text-red-700 dark:text-red-300 border-b border-red-200 dark:border-red-900">
                        <strong>Target belum ada</strong> untuk Pot Afalan Joint (grade AF) ukuran:
                        {{ $ukuranTanpaTarget->implode(', ') }}.
                        Tambahkan di menu Target agar capaian &amp; potongan terhitung.
                    </div>
                @endif

                <div class="p-4 space-y-6">
                    {{-- ====================== REKAP PEKERJA ====================== --}}
                    <div class="w-full overflow-x-auto">
                        <div class="min-w-[800px]">
                            <table class="w-full text-sm border-collapse border border-zinc-300 dark:border-zinc-600">
                                <thead>
                                    <tr>
                                        <th colspan="8"
                                            class="p-3 text-lg font-bold text-center bg-zinc-700 text-white uppercase tracking-wider">
                                            Rekap Pekerja &amp; Potongan
                                        </th>
                                    </tr>
                                    <tr
                                        class="bg-zinc-200 dark:bg-zinc-700 text-zinc-800 dark:text-zinc-300 border-t border-zinc-300 dark:border-zinc-600">
                                        <th class="p-2 text-left text-xs font-semibold uppercase">Pegawai</th>
                                        <th class="p-2 text-center text-xs font-semibold w-20 uppercase">Masuk</th>
                                        <th class="p-2 text-center text-xs font-semibold w-20 uppercase">Pulang</th>
                                        <th class="p-2 text-center text-xs font-semibold w-24 uppercase">Jam Kerja</th>
                                        <th class="p-2 text-center text-xs font-semibold w-20 uppercase">Ijin</th>
                                        <th class="p-2 text-right text-xs font-semibold w-24 uppercase">Capaian</th>
                                        <th class="p-2 text-right text-xs font-semibold w-32 uppercase">Potongan</th>
                                        <th class="p-2 text-left text-xs font-semibold uppercase">Ket</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($rekapPekerja as $i => $p)
                                        @php
                                            $potongan = (int) ($p['pot_target'] ?? 0);
                                            $capaianP = $p['pencapaian'] ?? 0;
                                        @endphp
                                        <tr
                                            class="{{ $i % 2 === 1 ? 'bg-zinc-50 dark:bg-zinc-800/50' : 'bg-white dark:bg-zinc-900' }} border-t border-zinc-300 dark:border-zinc-700">
                                            <td
                                                class="p-2 text-left text-xs border-r border-zinc-300 dark:border-zinc-700 font-semibold">
                                                {{ $p['id'] ?? '-' }} - {{ strtoupper($p['nama'] ?? '-') }}
                                            </td>
                                            <td
                                                class="p-2 text-center text-xs font-mono border-r border-zinc-300 dark:border-zinc-700">
                                                {{ $p['jam_masuk'] ?? '-' }}
                                            </td>
                                            <td
                                                class="p-2 text-center text-xs font-mono border-r border-zinc-300 dark:border-zinc-700">
                                                {{ $p['jam_pulang'] ?? '-' }}
                                            </td>
                                            <td
                                                class="p-2 text-center text-xs font-mono border-r border-zinc-300 dark:border-zinc-700">
                                                {{ $p['jam_kerja'] ?? '-' }}
                                            </td>
                                            <td
                                                class="p-2 text-center text-xs border-r border-zinc-300 dark:border-zinc-700 text-yellow-600 dark:text-yellow-400">
                                                {{ $p['ijin'] ?? '-' }}
                                            </td>
                                            <td
                                                class="p-2 text-right text-xs border-r border-zinc-300 dark:border-zinc-700 font-bold {{ $capaianP >= 100 ? 'text-green-600 dark:text-green-400' : 'text-red-500' }}">
                                                {{ number_format($capaianP, 1, ',', '.') }}%
                                            </td>
                                            <td
                                                class="p-2 text-right text-xs border-r border-zinc-300 dark:border-zinc-700 font-black {{ $potongan > 0 ? 'text-red-600 dark:text-red-400' : 'text-zinc-500 dark:text-zinc-400' }}">
                                                {{ $potongan > 0 ? 'Rp ' . number_format($potongan) : 'Rp 0' }}
                                            </td>
                                            <td class="p-2 text-left text-xs italic text-zinc-500">
                                                {{ ! empty($p['keterangan']) && $p['keterangan'] !== '-' ? $p['keterangan'] : '' }}
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="8"
                                                class="p-4 text-center text-zinc-500 dark:text-zinc-400 text-sm italic">
                                                Belum ada pegawai untuk meja ini.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                                <tfoot
                                    class="bg-zinc-100 dark:bg-zinc-800 border-t-2 border-zinc-300 dark:border-zinc-600">
                                    <tr>
                                        <td colspan="6"
                                            class="px-3 py-2 text-right text-xs font-bold uppercase text-zinc-600 dark:text-zinc-300">
                                            Total Potongan Tim
                                        </td>
                                        <td
                                            class="px-3 py-2 text-right text-sm font-black {{ $totalPotonganPegawai > 0 ? 'text-red-600 dark:text-red-400' : 'text-zinc-500 dark:text-zinc-400' }}">
                                            Rp {{ number_format($totalPotonganPegawai) }}
                                        </td>
                                        <td></td>
                                    </tr>
                                    @if ($potonganMelebihiGaji)
                                        <tr>
                                            <td colspan="8"
                                                class="px-3 py-2 text-center text-xs font-semibold text-red-600 dark:text-red-400 bg-red-50 dark:bg-red-950/30">
                                                ⚠ Potongan tim melebihi total gaji tim (Rp {{ number_format($potonganTim) }}), mohon dicek.
                                            </td>
                                        </tr>
                                    @endif
                                </tfoot>
                            </table>
                        </div>
                    </div>

                    {{-- ====================== DETAIL PRODUKSI ====================== --}}
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
                                            $hasTarget = ! empty($prod['has_target']);
                                            $isNotFound = ($prod['kode_ukuran'] ?? null) === 'POT-AFALAN-NOT-FOUND';
                                        @endphp
                                        <tr
                                            class="{{ $i % 2 === 1 ? 'bg-zinc-50 dark:bg-zinc-800/50' : 'bg-white dark:bg-zinc-900' }} border-t border-zinc-300 dark:border-zinc-700 hover:bg-zinc-100 dark:hover:bg-zinc-800/80 transition-colors">
                                            <td
                                                class="p-2 text-left text-xs border-r border-zinc-300 dark:border-zinc-700 font-medium">
                                                {{ $prod['ukuran'] ?? '-' }}
                                                @if ($isNotFound)
                                                    <span class="text-red-400 font-semibold">(Ukuran/Kayu Tidak
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
                                                {{ $hasTarget ? number_format($prod['target'] ?? 0) : '-' }}
                                            </td>
                                            <td
                                                class="p-2 text-right text-xs font-bold {{ ! $hasTarget ? 'text-red-500' : (($prod['capaian_persen'] ?? 0) >= 100 ? 'text-green-600 dark:text-green-400' : 'text-amber-600 dark:text-amber-400') }}">
                                                @if (! $hasTarget)
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
                                            <span class="font-medium">Pekerja:</span>
                                            <strong class="text-zinc-900 dark:text-white">{{ $jumlahPekerja }}</strong>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td colspan="7"
                                            class="p-2 text-center text-[11px] text-zinc-500 dark:text-zinc-400 border-t border-zinc-300 dark:border-zinc-700">
                                            Capaian GLOBAL meja (jumlah persen semua ukuran yang dikerjakan hari ini, basis: target per ukuran, BUKAN rata-rata):
                                            <strong
                                                class="{{ $tercapai ? 'text-green-600 dark:text-green-400' : 'text-red-500' }}">
                                                {{ number_format($pencapaianGlobal, 1, ',', '.') }}%
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