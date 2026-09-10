<x-filament-panels::page>
    <!-- HEADER DENGAN FORM DI KANAN -->
    <div class="p-4 bg-white dark:bg-zinc-900 rounded-lg shadow border border-zinc-200 dark:border-zinc-800">
        {{ $this->form }}
    </div>

    <!-- Loading Indicator -->
    @if ($isLoading)
        <div
            class="fixed inset-0 z-50 flex items-center justify-center bg-white bg-opacity-75 dark:bg-zinc-900 dark:bg-opacity-75">
            <div class="flex items-center space-x-3">
                <x-filament::loading-indicator class="w-8 h-8 text-primary-600" />
                <span class="text-lg font-medium text-zinc-700 dark:text-zinc-300">Memuat data...</span>
            </div>
        </div>
    @endif

    @php
        $dataProduksi = $dataProduksi ?? [];
        $groupedByMesin = collect($dataProduksi)->groupBy('mesin');
    @endphp

    <div class="space-y-12 mt-6">
        @forelse ($groupedByMesin as $mesinNama => $produksiList)
            @php
                $first = $produksiList->first();
                $pekerja = $first['pekerja'] ?? [];
                $kodeUkuran = $first['ukuran'] ?? 'TIDAK ADA UKURAN';
                $totalPekerja = count($pekerja);

                $hasil = $first['hasil'] ?? 0;
                $target = $first['target'] ?? 0;
                $targetNormal = $first['target_normal'] ?? 0;
                $targetPerJam = $first['target_per_jam'] ?? 0;
                $targetPerMenit = $first['target_per_menit'] ?? 0;
                $selisih = $first['selisih'] ?? 0;

                $jamKerja = $first['jam_kerja'] ?? 0;
                $jamKerjaEfektif = $first['jam_kerja_efektif'] ?? 0;
                $totalKendalaMenit = $first['total_kendala_menit'] ?? 0;
                $totalDowntimeFormatted = $first['total_downtime_formatted'] ?? '-';
                $daftarKendala = $first['daftar_kendala'] ?? [];

                $potonganTotal = $first['potongan_total'] ?? 0;
                $potonganPerOrang = $first['potongan_per_orang'] ?? 0;
                $hasTarget = $first['has_target'] ?? false;

                // Capaian % dihitung dari target yang SUDAH disesuaikan (target adjusted),
                // konsisten dengan basis perhitungan potongan di ProduksiDataMap.
                $capaianPersen = $target > 0 ? ($hasil / $target) * 100 : 0;
                $tercapai = $hasTarget ? $capaianPersen >= 100 : null;
            @endphp

            <!-- CARD MESIN -->
            <div
                class="bg-white dark:bg-zinc-900 rounded-sm shadow-lg border border-zinc-200 dark:border-zinc-700 overflow-hidden">

                {{-- Header Blok Produksi (disamakan dengan Pot Af Join) --}}
                <div class="bg-zinc-800 p-4 text-white flex flex-wrap justify-between items-center gap-2">
                    <h2 class="text-lg font-bold">
                        MESIN: {{ strtoupper($mesinNama) }} - {{ strtoupper($kodeUkuran) }}
                    </h2>
                    <div class="flex gap-2 items-center flex-wrap">
                        <span class="text-xs px-2 py-1 rounded bg-zinc-700 font-semibold uppercase">
                            Pekerja: {{ $totalPekerja }}
                        </span>

                        @if ($hasTarget)
                            <span class="text-xs px-2 py-1 rounded {{ $tercapai ? 'bg-green-700' : 'bg-red-700' }}">
                                Capaian: {{ number_format($capaianPersen, 1, ',', '.') }}%
                            </span>
                            <span class="text-xs bg-zinc-700 px-2 py-1 rounded">
                                {{ $tercapai ? '✔ Tercapai' : '✘ Belum' }}
                            </span>
                        @else
                            <span class="text-xs px-2 py-1 rounded bg-zinc-600">
                                Target ?
                            </span>
                        @endif

                        @if ($potonganTotal > 0)
                            <span class="text-xs px-2 py-1 rounded bg-amber-600 font-bold">
                                ⚠ Potongan: Rp {{ number_format($potonganTotal) }}
                            </span>
                        @endif

                        @if ($totalKendalaMenit > 0)
                            <span class="text-xs px-2 py-1 rounded bg-red-800 font-semibold">
                                Downtime: {{ $totalDowntimeFormatted }}
                            </span>
                        @endif
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
                                            Data Pekerja
                                        </th>
                                    </tr>

                                    <tr
                                        class="bg-zinc-200 dark:bg-zinc-700 text-zinc-800 dark:text-zinc-300 border-t border-zinc-300 dark:border-zinc-600">
                                        <th class="p-2 text-center text-xs font-semibold uppercase w-16">ID</th>
                                        <th class="p-2 text-left text-xs font-semibold uppercase w-40">Nama</th>
                                        <th class="p-2 text-center text-xs font-semibold uppercase w-20">Masuk</th>
                                        <th class="p-2 text-center text-xs font-semibold uppercase w-20">Pulang</th>
                                        <th class="p-2 text-center text-xs font-semibold uppercase w-16">Ijin</th>
                                        <th class="p-2 text-right text-xs font-semibold uppercase w-36">Potongan Target
                                        </th>
                                        <th class="p-2 text-left text-xs font-semibold uppercase">Keterangan</th>
                                    </tr>
                                </thead>

                                <tbody>
                                    @forelse ($pekerja as $i => $p)
                                        <tr
                                            class="{{ $i % 2 === 1 ? 'bg-zinc-50 dark:bg-zinc-800/50' : 'bg-white dark:bg-zinc-900' }} border-t border-zinc-300 dark:border-zinc-700 hover:bg-zinc-100 dark:hover:bg-zinc-800/80 transition-colors">
                                            <td
                                                class="p-2 text-center text-xs border-r border-zinc-300 dark:border-zinc-700 text-zinc-700 dark:text-zinc-300">
                                                {{ $p['id'] ?? '-' }}
                                            </td>
                                            <td
                                                class="p-2 text-left text-xs border-r border-zinc-300 dark:border-zinc-700 text-zinc-900 dark:text-zinc-100 font-medium">
                                                {{ $p['nama'] ?? '-' }}
                                            </td>
                                            <td
                                                class="p-2 text-center text-xs border-r border-zinc-300 dark:border-zinc-700 text-zinc-700 dark:text-zinc-300">
                                                {{ $p['jam_masuk'] ?? '-' }}
                                            </td>
                                            <td
                                                class="p-2 text-center text-xs border-r border-zinc-300 dark:border-zinc-700 text-zinc-700 dark:text-zinc-300">
                                                {{ $p['jam_pulang'] ?? '-' }}
                                            </td>
                                            <td
                                                class="p-2 text-center text-xs border-r border-zinc-300 dark:border-zinc-700 text-yellow-600 dark:text-yellow-400">
                                                {{ $p['ijin'] ?? '-' }}
                                            </td>
                                            <td
                                                class="p-2 text-right text-xs border-r border-zinc-300 dark:border-zinc-700 font-bold {{ ($p['pot_target'] ?? 0) > 0 ? 'text-red-600 dark:text-red-400' : 'text-zinc-700 dark:text-zinc-300' }}">
                                                Rp {{ number_format($p['pot_target'] ?? 0) }}
                                            </td>
                                            <td class="p-2 text-left text-xs text-zinc-700 dark:text-zinc-300">
                                                {{ $p['keterangan'] ?? '-' }}
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="7"
                                                class="p-4 text-center text-zinc-500 dark:text-zinc-400 text-sm italic">
                                                Tidak ada data pekerja untuk mesin ini.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>

                                <tfoot
                                    class="bg-zinc-100 dark:bg-zinc-800 border-t-2 border-zinc-300 dark:border-zinc-600">
                                    <!-- BARIS 1: DATA UTAMA -->
                                    <tr>
                                        <td colspan="7"
                                            class="p-3 text-center text-xs text-zinc-600 dark:text-zinc-400 space-x-3">
                                            <span class="font-medium">Target Normal:</span>
                                            <strong
                                                class="font-mono text-zinc-900 dark:text-white">{{ number_format($targetNormal) }}</strong>

                                            <span class="text-zinc-400">|</span>

                                            <span class="font-medium">Target Disesuaikan:</span>
                                            <strong
                                                class="font-mono text-zinc-900 dark:text-white">{{ number_format($target) }}</strong>

                                            <span class="text-zinc-400">|</span>

                                            <span class="font-medium">Hasil:</span>
                                            <strong
                                                class="font-mono {{ $selisih >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                                                {{ number_format($hasil) }}
                                            </strong>

                                            <span class="text-zinc-400">|</span>

                                            <span class="font-medium">Selisih:</span>
                                            <strong
                                                class="font-mono {{ $selisih >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                                                {{ $selisih >= 0 ? '+' : '' }}{{ number_format(abs($selisih)) }}
                                            </strong>

                                            <span class="text-zinc-400">|</span>

                                            <span class="font-medium">Jam Kerja:</span>
                                            <strong
                                                class="font-mono text-zinc-900 dark:text-white">{{ number_format($jamKerja, 1) }}
                                                jam</strong>

                                            <span class="text-zinc-400">|</span>

                                            <span class="font-medium">Jam Efektif:</span>
                                            <strong
                                                class="font-mono text-zinc-900 dark:text-white">{{ number_format($jamKerjaEfektif, 1) }}
                                                jam</strong>

                                            <span class="text-zinc-400">|</span>

                                            <span class="text-xs">Tgl: {{ $first['tanggal'] }}</span>
                                        </td>
                                    </tr>

                                    <!-- BARIS 2: CAPAIAN GLOBAL (mirip Pot Af Join) -->
                                    <tr>
                                        <td colspan="7"
                                            class="p-2 text-center text-[11px] text-zinc-500 dark:text-zinc-400 border-t border-zinc-300 dark:border-zinc-700">
                                            Capaian mesin (basis target yang sudah disesuaikan dengan jam kerja efektif
                                            & jumlah pekerja):
                                            @if ($hasTarget)
                                                <strong
                                                    class="{{ $tercapai ? 'text-green-600 dark:text-green-400' : 'text-red-500' }}">
                                                    {{ number_format($capaianPersen, 1, ',', '.') }}%
                                                </strong>
                                            @else
                                                <strong class="text-red-500">Target tidak ditemukan</strong>
                                            @endif
                                        </td>
                                    </tr>

                                    <!-- BARIS 3: POTONGAN (mirip Pot Af Join) -->
                                    <tr>
                                        <td colspan="7"
                                            class="px-3 py-2 text-center border-t border-zinc-300 dark:border-zinc-700 {{ $potonganTotal > 0 ? 'bg-red-50 dark:bg-red-950/30' : '' }}">
                                            <span
                                                class="text-xs font-bold uppercase tracking-wide {{ $potonganTotal > 0 ? 'text-red-600 dark:text-red-400' : 'text-zinc-500 dark:text-zinc-400' }}">
                                                Total Potongan:
                                            </span>
                                            <span
                                                class="text-base font-black {{ $potonganTotal > 0 ? 'text-red-600 dark:text-red-400' : 'text-zinc-500 dark:text-zinc-400' }}">
                                                Rp {{ number_format($potonganTotal) }}
                                            </span>
                                            <span class="text-zinc-400 mx-2">|</span>
                                            <span class="text-xs text-zinc-500 dark:text-zinc-400">
                                                (± Rp {{ number_format($potonganPerOrang) }} / orang)
                                            </span>
                                        </td>
                                    </tr>

                                    <!-- BARIS 4: KENDALA (hanya jika ada) -->
                                    @if (count($daftarKendala) > 0)
                                        <tr>
                                            <td colspan="7"
                                                class="p-3 text-xs border-t border-zinc-300 dark:border-zinc-600">
                                                <div class="flex items-start justify-center gap-2">
                                                    <span
                                                        class="font-medium text-zinc-600 dark:text-zinc-400 whitespace-nowrap">
                                                        Kendala:
                                                    </span>
                                                    <div class="flex-1 max-w-3xl">
                                                        <div class="space-y-1">
                                                            @foreach ($daftarKendala as $k)
                                                                <div class="text-zinc-700 dark:text-zinc-300">
                                                                    <span
                                                                        class="font-semibold text-red-600 dark:text-red-400">
                                                                        {{ $k['kendala'] }}
                                                                    </span>
                                                                    <span class="text-zinc-500 dark:text-zinc-400">
                                                                        — {{ $k['durasi_menit'] }} menit
                                                                        ({{ $k['jam_mulai'] }} -
                                                                        {{ $k['jam_selesai'] }})
                                                                    </span>
                                                                    @if ($k['keterangan'] !== '-')
                                                                        <span
                                                                            class="text-zinc-600 dark:text-zinc-400 italic">
                                                                            — {{ $k['keterangan'] }}
                                                                        </span>
                                                                    @endif
                                                                </div>
                                                            @endforeach
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    @endif
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
                    Tidak ditemukan data produksi untuk tanggal ini.
                </p>
                <p class="text-sm text-zinc-400 mt-2">
                    Silakan pilih tanggal lain atau pastikan input produksi rotary sudah dilakukan.
                </p>
            </div>
        @endforelse
    </div>
</x-filament-panels::page>
