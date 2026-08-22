<x-filament-panels::page>
    <!-- HEADER FILTER TANGGAL -->
    <div class="p-4 bg-white dark:bg-zinc-900 rounded-lg shadow border border-zinc-200 dark:border-zinc-800">
        {{ $this->form }}
    </div>

    <!-- Loading Indicator Overlay -->
    @if($isLoading)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-white/75 dark:bg-zinc-900/75">
        <div class="flex items-center space-x-3">
            <x-filament::loading-indicator class="w-8 h-8 text-amber-500" />
            <span class="text-lg font-medium text-zinc-700 dark:text-zinc-300">Memuat data...</span>
        </div>
    </div>
    @endif

    <div class="space-y-10 mt-6">
        @forelse ($dataProduksi as $item)
        @php
        $pekerja = $item['pekerja'] ?? [];
        $daftarHasil = $item['daftar_hasil'] ?? [];
        $totalPekerja = count($pekerja);
        $hasilPalet = $item['hasil_palet'] ?? 0;
        $totalLembar = $item['total_lembar'] ?? 0;
        $targetPalet = $item['target_palet'] ?? 9;
        $selisihPalet = $item['selisih_palet'] ?? 0;
        $warnaSelisih = $selisihPalet >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400';
        $tandaSelisih = $selisihPalet > 0 ? '+' : '';
        $jamKerja = $item['jam_kerja'] ?? 9;
        $totalKendalaMenit = $item['total_kendala_menit'] ?? 0;
        $totalDowntimeFormatted = $item['total_downtime_formatted'] ?? '-';
        $daftarKendala = $item['daftar_kendala'] ?? [];
        @endphp

        <!-- CARD MESIN LAPORAN TERPADU -->
        <div class="bg-white dark:bg-zinc-900 rounded-lg shadow-lg border border-zinc-200 dark:border-zinc-700 overflow-hidden space-y-6">

            <!-- HEADER CARD -->
            <div class="bg-zinc-800 p-4 text-white flex justify-between items-center flex-wrap gap-2">
                <h2 class="text-lg font-bold uppercase tracking-wide flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-amber-500 animate-pulse"></span>
                    PRODUKSI: {{ strtoupper($item['mesin'] ?? 'MESIN STIK') }}
                </h2>
                <span class="text-xs text-zinc-300 bg-zinc-900/80 px-3 py-1.5 rounded border border-zinc-700">
                    Tanggal Produksi: <strong class="text-white font-mono">{{ $item['tanggal'] }}</strong>
                </span>
            </div>

            <div class="p-4 md:p-6 space-y-8">

                <!-- SECTION 1: TABEL RINCIAN HASIL PALET STIK -->
                <div>
                    <div class="flex items-center justify-between mb-3">
                        <h3 class="text-xs font-bold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider flex items-center gap-2">
                            Rincian Hasil Stik (Per Nomor Palet)
                        </h3>
                        <span class="text-xs text-amber-600 dark:text-amber-400 font-semibold bg-amber-500/10 px-2.5 py-1 rounded border border-amber-500/20">
                            {{ $hasilPalet }} Palet Terdaftar
                        </span>
                    </div>

                    <div class="w-full overflow-x-auto rounded-md border border-zinc-300 dark:border-zinc-700">
                        <table class="w-full text-xs text-left border-collapse">
                            <thead class="bg-zinc-100 dark:bg-zinc-800 uppercase text-zinc-700 dark:text-zinc-300 font-semibold border-b border-zinc-300 dark:border-zinc-700">
                                <tr>
                                    <th class="p-2.5 text-center w-24">No. Palet</th>
                                    <th class="p-2.5 text-left">Jenis Kayu</th>
                                    <th class="p-2.5 text-left">Ukuran</th>
                                    <th class="p-2.5 text-center w-28">Kualitas</th>
                                    <th class="p-2.5 text-right w-36">Total Lembar</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800 font-medium">
                                @forelse ($daftarHasil as $i => $hasil)
                                <tr class="{{ $i % 2 === 1 ? 'bg-zinc-50 dark:bg-zinc-800/40' : 'bg-white dark:bg-zinc-900' }} hover:bg-amber-500/5 transition">
                                    <td class="p-2.5 text-center font-mono font-bold text-amber-600 dark:text-amber-400">
                                        <span class="bg-amber-500/10 px-2 py-0.5 rounded border border-amber-500/20">
                                            {{ $hasil['no_palet'] }}
                                        </span>
                                    </td>
                                    <td class="p-2.5 text-zinc-900 dark:text-zinc-100 text-sm">{{ $hasil['jenis_kayu'] }}</td>
                                    <td class="p-2.5 font-mono text-zinc-600 dark:text-zinc-400 text-sm">{{ $hasil['ukuran'] }}</td>
                                    <td class="p-2.5 text-center">
                                        <span class="bg-zinc-200 dark:bg-zinc-800 px-2 py-0.5 rounded text-zinc-700 dark:text-zinc-300">
                                            {{ $hasil['kualitas'] }}
                                        </span>
                                    </td>
                                    <td class="p-2.5 text-right font-bold font-mono text-zinc-900 dark:text-white text-sm">
                                        {{ number_format($hasil['total_lembar'], 0, ',', '.') }}
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="5" class="p-4 text-center text-zinc-500 dark:text-zinc-400">
                                        Belum ada data palet stik terdaftar.
                                    </td>
                                </tr>
                                @endforelse
                            </tbody>
                            <tfoot class="bg-zinc-100 dark:bg-zinc-800/90 border-t-2 border-zinc-300 dark:border-zinc-700 font-bold text-xs">
                                <tr>
                                    <td colspan="4" class="p-2.5 text-right text-zinc-600 dark:text-zinc-400 uppercase tracking-wide">
                                        Subtotal Hasil (Lembar):
                                    </td>
                                    <td class="p-2.5 text-right font-mono text-amber-600 dark:text-amber-400 text-sm">
                                        {{ number_format($totalLembar, 0, ',', '.') }} Lembar
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>

                <!-- SECTION 2: TABEL DATA PEKERJA -->
                <div>
                    <h3 class="text-xs font-bold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider mb-3 flex items-center gap-2">
                        Data Pekerja Shift Ini
                    </h3>

                    <div class="w-full overflow-x-auto rounded-md border border-zinc-300 dark:border-zinc-700">
                        <table class="w-full text-xs text-left border-collapse">
                            <thead class="bg-zinc-100 dark:bg-zinc-800 uppercase text-zinc-700 dark:text-zinc-300 font-semibold border-b border-zinc-300 dark:border-zinc-700">
                                <tr>
                                    <th class="p-2.5 text-center w-16">ID</th>
                                    <th class="p-2.5 text-left w-44">Nama</th>
                                    <th class="p-2.5 text-center w-20">Masuk</th>
                                    <th class="p-2.5 text-center w-20">Pulang</th>
                                    <th class="p-2.5 text-center w-16">Ijin</th>
                                    <th class="p-2.5 text-right w-36">Potongan Target</th>
                                    <th class="p-2.5 text-left">Keterangan</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800 font-medium">
                                @forelse ($pekerja as $i => $p)
                                <tr class="{{ $i % 2 === 1 ? 'bg-zinc-50 dark:bg-zinc-800/40' : 'bg-white dark:bg-zinc-900' }}">
                                    <td class="p-2.5 text-center font-mono text-zinc-500 dark:text-zinc-400">
                                        {{ $p["id"] ?? "-" }}
                                    </td>
                                    <td class="p-2.5 text-left text-zinc-900 dark:text-zinc-100 font-bold">
                                        {{ $p["nama"] ?? "-" }}
                                    </td>
                                    <td class="p-2.5 text-center text-zinc-700 dark:text-zinc-300">
                                        {{ $p["jam_masuk"] ?? "-" }}
                                    </td>
                                    <td class="p-2.5 text-center text-zinc-700 dark:text-zinc-300">
                                        {{ $p["jam_pulang"] ?? "-" }}
                                    </td>
                                    <td class="p-2.5 text-center text-amber-600 dark:text-amber-400">
                                        {{ $p["ijin"] ?? "-" }}
                                    </td>
                                    <td class="p-2.5 text-right font-mono font-bold {{ $selisihPalet < 0 && !empty($p['pot_target']) && $p['pot_target'] !== 'Rp 0' ? 'text-red-600 dark:text-red-400' : 'text-zinc-700 dark:text-zinc-300' }}">
                                        {{ $p["pot_target"] ?? "-" }}
                                    </td>
                                    <td class="p-2.5 text-left text-zinc-500 dark:text-zinc-400">
                                        {{ $p["keterangan"] ?? "-" }}
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="7" class="p-4 text-center text-zinc-500 dark:text-zinc-400">
                                        Tidak ada data pekerja untuk shift ini.
                                    </td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- SECTION 3: SUMMARY FOOTER BAR (DALAM SATUAN PALET) -->
                <div class="bg-zinc-100 dark:bg-zinc-950 rounded-lg border border-zinc-200 dark:border-zinc-800 p-4">
                    <div class="grid grid-cols-2 md:grid-cols-6 gap-3 text-center divide-x divide-zinc-200 dark:divide-zinc-800">
                        <div class="p-1">
                            <span class="block text-[11px] text-zinc-500 dark:text-zinc-400 uppercase tracking-wide">Pekerja</span>
                            <strong class="text-sm font-bold text-zinc-900 dark:text-white mt-0.5 block">
                                {{ $totalPekerja }} Orang
                            </strong>
                        </div>
                        <div class="p-1">
                            <span class="block text-[11px] text-zinc-500 dark:text-zinc-400 uppercase tracking-wide">Target Palet</span>
                            <strong class="text-sm font-bold text-zinc-900 dark:text-white mt-0.5 block font-mono">
                                {{ $targetPalet }} Palet
                            </strong>
                        </div>
                        <div class="p-1">
                            <span class="block text-[11px] text-zinc-500 dark:text-zinc-400 uppercase tracking-wide">Jam Produksi</span>
                            <strong class="text-sm font-bold text-zinc-900 dark:text-white mt-0.5 block font-mono">
                                {{ number_format($jamKerja, 1) }} jam
                            </strong>
                        </div>
                        <div class="p-1">
                            <span class="block text-[11px] text-zinc-500 dark:text-zinc-400 uppercase tracking-wide">Hasil Palet</span>
                            <strong class="text-sm font-bold text-amber-600 dark:text-amber-400 mt-0.5 block font-mono">
                                {{ $hasilPalet }} Palet
                            </strong>
                        </div>
                        <div class="p-1">
                            <span class="block text-[11px] text-zinc-500 dark:text-zinc-400 uppercase tracking-wide">Selisih Target</span>
                            <strong class="text-sm font-bold {{ $warnaSelisih }} mt-0.5 block font-mono">
                                {{ $tandaSelisih }}{{ $selisihPalet }} Palet
                            </strong>
                        </div>
                        <div class="p-1">
                            <span class="block text-[11px] text-zinc-500 dark:text-zinc-400 uppercase tracking-wide">Total Downtime</span>
                            <strong class="text-sm font-bold text-zinc-700 dark:text-zinc-300 mt-0.5 block font-mono {{ $totalKendalaMenit > 0 ? 'text-red-600 dark:text-red-400' : '' }}">
                                {{ $totalDowntimeFormatted }}
                            </strong>
                        </div>
                    </div>

                    <!-- KENDALA / DOWNTIME DETAIL JIKA ADA -->
                    @if(count($daftarKendala) > 0 || (!empty($item['kendala']) && $item['kendala'] !== '-'))
                    <div class="mt-4 pt-3 border-t border-zinc-200 dark:border-zinc-800 text-xs">
                        <div class="flex items-start gap-2">
                            <span class="font-bold text-zinc-600 dark:text-zinc-400 whitespace-nowrap">
                                Kendala:
                            </span>
                            <div class="space-y-1 text-zinc-700 dark:text-zinc-300">
                                @forelse($daftarKendala as $k)
                                <div>
                                    <span class="font-semibold text-red-600 dark:text-red-400">{{ $k['kendala'] }}</span>
                                    @if(!empty($k['durasi_menit']))
                                    <span class="text-zinc-500">— {{ $k['durasi_menit'] }} menit</span>
                                    @endif
                                </div>
                                @empty
                                <span class="text-red-500">{{ $item['kendala'] }}</span>
                                @endforelse
                            </div>
                        </div>
                    </div>
                    @endif
                </div>

            </div>
        </div>
        @empty
        <div class="text-center p-12 text-zinc-500 dark:text-zinc-400 bg-white dark:bg-zinc-900 rounded-lg border border-zinc-200 dark:border-zinc-800 shadow">
            <p class="text-lg">Tidak ada data laporan produksi stik untuk tanggal ini.</p>
        </div>
        @endforelse
    </div>
</x-filament-panels::page>