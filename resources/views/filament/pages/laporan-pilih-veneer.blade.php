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
        // 1 kartu = 1 produksi (satu tim), pekerja & barang digabung dalam satu blok
        $tables = collect($dataProduksi ?? [])
            ->filter(fn($item) => is_array($item))
            ->values();
    @endphp

    <div class="space-y-12 mt-6">
        @forelse ($tables as $data)
            @php
                $capaianGlobal = ($data['pencapaian_global'] ?? 0) * 100;
                $tercapai = $capaianGlobal >= 100;
                $pekerjaList = $data['rekap_pekerja'] ?? [];
                $detailProduksi = $data['detail_produksi'] ?? [];
                $jumlahPekerja = (int) ($data['jumlah_pekerja'] ?? count($pekerjaList));
                $potonganTim = (float) ($data['potongan_total_tim'] ?? 0);
                $rataRataPerOrang = $jumlahPekerja > 0 ? $potonganTim / $jumlahPekerja : 0;
                $adaTarget = collect($detailProduksi)->contains(fn($d) => $d['has_target'] ?? false);
            @endphp

            <div
                class="bg-white dark:bg-zinc-900 rounded-sm shadow-lg border border-zinc-200 dark:border-zinc-700 overflow-hidden">
                {{-- Header Blok Produksi --}}
                <div class="bg-zinc-800 p-4 text-white flex justify-between items-center">
                    <h2 class="text-lg font-bold text-center">
                        {{ strtoupper($data['nomor_meja'] ?? 'PILIH VENEER') }}
                    </h2>
                    <div class="flex gap-4 items-center">
                        @if ($adaTarget)
                            <span class="text-xs px-2 py-1 rounded {{ $tercapai ? 'bg-green-700' : 'bg-red-700' }}">
                                Capaian Global: {{ number_format($capaianGlobal, 1, ',', '.') }}%
                            </span>
                        @endif
                        @if ($potonganTim > 0)
                            <span class="text-xs px-2 py-1 rounded bg-amber-600 font-bold"
                                title="Total tim dibagi sesuai jam kerja pekerja">
                                ⚠ Rp {{ number_format($potonganTim) }} ÷ {{ $jumlahPekerja }} org ≈ Rp
                                {{ number_format($rataRataPerOrang) }}/org
                            </span>
                        @endif
                        <span class="text-xs bg-zinc-700 px-2 py-1 rounded">
                            {{ $tercapai ? '✔ Tercapai' : '✘ Belum' }}
                        </span>
                    </div>
                </div>

                <div class="p-4 space-y-6">

                    {{-- ================= TABEL ATAS: DATA PEKERJA ================= --}}
                    <div class="w-full overflow-x-auto">
                        <div class="min-w-[800px]">
                            <table
                                class="w-full text-sm border-collapse border border-zinc-300 dark:border-zinc-600">
                                <thead>
                                    <tr>
                                        <th colspan="8"
                                            class="p-3 text-lg font-bold text-center bg-zinc-700 text-white">
                                            DATA PEKERJA
                                        </th>
                                    </tr>
                                    <tr
                                        class="bg-zinc-200 dark:bg-zinc-700 text-zinc-800 dark:text-zinc-300 border-t border-zinc-300 dark:border-zinc-600">
                                        <th class="p-2 text-center text-xs font-medium w-16">ID</th>
                                        <th class="p-2 text-left text-xs font-medium w-40">Nama</th>
                                        <th class="p-2 text-center text-xs font-medium w-20">Masuk</th>
                                        <th class="p-2 text-center text-xs font-medium w-20">Pulang</th>
                                        <th class="p-2 text-center text-xs font-medium w-24">Jam Aktual</th>
                                        <th class="p-2 text-center text-xs font-medium w-16">Ijin</th>
                                        <th class="p-2 text-left text-xs font-medium">Ket</th>
                                        <th class="p-2 text-right text-xs font-medium w-36">Potongan Target</th>
                                    </tr>
                                </thead>

                                <tbody>
                                    @forelse ($pekerjaList as $i => $p)
                                        @php $potTarget = (int) ($p['pot_target'] ?? 0); @endphp
                                        <tr
                                            class="{{ $i % 2 === 1 ? 'bg-zinc-50 dark:bg-zinc-800/50' : 'bg-white dark:bg-zinc-900' }} border-t border-zinc-300 dark:border-zinc-700">
                                            <td
                                                class="p-2 text-center text-xs border-r border-zinc-300 dark:border-zinc-700 font-mono">
                                                {{ $p['id'] ?? '-' }}
                                            </td>
                                            <td
                                                class="p-2 text-left text-xs border-r border-zinc-300 dark:border-zinc-700 font-medium">
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
                                                class="p-2 text-center text-xs border-r border-zinc-300 dark:border-zinc-700 font-mono">
                                                {{ isset($p['jam_aktual_bersih']) ? number_format($p['jam_aktual_bersih'], 2, ',', '.') . ' jam' : '-' }}
                                            </td>
                                            <td
                                                class="p-2 text-center text-xs border-r border-zinc-300 dark:border-zinc-700 text-yellow-600 dark:text-yellow-400">
                                                {{ $p['ijin'] ?? '-' }}
                                            </td>
                                            <td
                                                class="p-2 text-left text-xs border-r border-zinc-300 dark:border-zinc-700 italic text-zinc-500">
                                                {{ $p['keterangan'] ?? '-' }}
                                            </td>
                                            <td
                                                class="p-2 text-right text-xs font-bold {{ $potTarget > 0 ? 'text-red-500' : '' }}">
                                                {{ $potTarget > 0 ? 'Rp ' . number_format($potTarget) : '-' }}
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="8"
                                                class="p-4 text-center text-zinc-500 dark:text-zinc-400 text-sm italic">
                                                Tidak ada data pekerja.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>

                                <tfoot
                                    class="bg-zinc-100 dark:bg-zinc-800 border-t-2 border-zinc-300 dark:border-zinc-600">
                                    <tr>
                                        <td colspan="8"
                                            class="p-2 text-center text-[11px] text-zinc-500 dark:text-zinc-400">
                                            <span class="font-medium">Jumlah Pekerja:</span>
                                            <strong class="text-zinc-900 dark:text-white">{{ $jumlahPekerja }}</strong>
                                            <span class="text-zinc-400">|</span>
                                            <span class="font-medium">Rata-rata Jam Aktual Kru:</span>
                                            <strong class="font-mono text-zinc-900 dark:text-white">
                                                {{ number_format($data['jam_aktual'] ?? 0, 2, ',', '.') }} jam</strong>
                                            <span class="text-zinc-400">|</span>
                                            <span class="text-xs">Tgl: {{ $data['tanggal'] ?? '-' }}</span>
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>

                    {{-- ================= TABEL BAWAH: BARANG DIKERJAKAN ================= --}}
                    <div class="w-full overflow-x-auto">
                        <div class="min-w-[800px]">
                            <table
                                class="w-full text-sm border-collapse border border-zinc-300 dark:border-zinc-600">
                                <thead>
                                    <tr>
                                        <th colspan="7"
                                            class="p-3 text-lg font-bold text-center bg-zinc-700 text-white">
                                            DATA BARANG DIKERJAKAN
                                        </th>
                                    </tr>
                                    <tr
                                        class="bg-zinc-200 dark:bg-zinc-700 text-zinc-800 dark:text-zinc-300 border-t border-zinc-300 dark:border-zinc-600">
                                        <th class="p-2 text-left text-xs font-medium">Ukuran</th>
                                        <th class="p-2 text-center text-xs font-medium w-28">Jenis Kayu</th>
                                        <th class="p-2 text-center text-xs font-medium w-16">KW</th>
                                        <th class="p-2 text-center text-xs font-medium w-28">No. Palet</th>
                                        <th class="p-2 text-right text-xs font-medium w-24">Hasil</th>
                                        <th class="p-2 text-right text-xs font-medium w-28">Target (Adjusted)</th>
                                        <th class="p-2 text-right text-xs font-medium w-20">Capaian</th>
                                    </tr>
                                </thead>

                                <tbody>
                                    @forelse ($detailProduksi as $i => $prod)
                                        @php
                                            // 'PILIH-VENEER-NOT-FOUND' = relasi Ukuran/JenisKayu gagal di-resolve,
                                            // BUKAN berarti Target tidak ditemukan.
                                            $isUkuranNotFound =
                                                ($prod['kode_ukuran'] ?? null) === 'PILIH-VENEER-NOT-FOUND';
                                            $hasTarget = $prod['has_target'] ?? false;
                                        @endphp
                                        <tr
                                            class="{{ $i % 2 === 1 ? 'bg-zinc-50 dark:bg-zinc-800/50' : 'bg-white dark:bg-zinc-900' }} border-t border-zinc-300 dark:border-zinc-700">
                                            <td
                                                class="p-2 text-left text-xs border-r border-zinc-300 dark:border-zinc-700 font-medium">
                                                @if ($isUkuranNotFound || !$hasTarget)
                                                    <span class="text-red-500">{{ $prod['ukuran'] ?? '-' }} ⚠</span>
                                                @else
                                                    {{ $prod['ukuran'] ?? '-' }}
                                                @endif
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
                                                @if ($hasTarget)
                                                    {{ number_format($prod['target'] ?? 0) }}
                                                    @if (isset($prod['target_normal']))
                                                        <span class="block text-[10px] text-zinc-600">(normal:
                                                            {{ number_format($prod['target_normal']) }})</span>
                                                    @endif
                                                @else
                                                    -
                                                @endif
                                            </td>
                                            <td
                                                class="p-2 text-right text-xs font-bold {{ !$hasTarget ? 'text-red-500' : (($prod['capaian_persen'] ?? 0) >= 100 ? 'text-green-600 dark:text-green-400' : 'text-amber-600 dark:text-amber-400') }}">
                                                @if (!$hasTarget)
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
                                                Tidak ada barang dikerjakan.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>

                                <tfoot
                                    class="bg-zinc-100 dark:bg-zinc-800 border-t-2 border-zinc-300 dark:border-zinc-600">
                                    <tr>
                                        <td colspan="7"
                                            class="p-2 text-center text-[11px] text-zinc-500 dark:text-zinc-400 border-t border-zinc-300 dark:border-zinc-700">
                                            Capaian GLOBAL tim (jumlah persen semua ukuran hari ini, basis: target
                                            ADJUSTED ke total jam kerja tim):
                                            <strong
                                                class="{{ $tercapai ? 'text-green-600 dark:text-green-400' : 'text-red-500' }}">
                                                {{ number_format($capaianGlobal, 1, ',', '.') }}%
                                            </strong>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td colspan="7"
                                            class="px-3 py-2 text-center border-t border-zinc-300 dark:border-zinc-700">
                                            <span
                                                class="text-xs font-semibold {{ $potonganTim > 0 ? 'text-red-500 dark:text-red-400' : 'text-zinc-500 dark:text-zinc-400' }}">
                                                Potongan: Rp {{ number_format($potonganTim) }} / tim
                                                @if ($jumlahPekerja > 0)
                                                    ÷ {{ $jumlahPekerja }} orang (sesuai jam kerja)
                                                    ≈ <strong>Rp {{ number_format($rataRataPerOrang) }}/orang</strong>
                                                @endif
                                            </span>
                                            @if ($data['potongan_melebihi_gaji'] ?? false)
                                                <span
                                                    class="ml-2 px-2 py-0.5 rounded bg-yellow-600 text-white text-[10px] font-bold">
                                                    ⚠ MELEBIHI GAJI NORMAL TIM (Rp
                                                    {{ number_format($data['total_gaji_tim'] ?? 0) }})
                                                </span>
                                            @endif
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