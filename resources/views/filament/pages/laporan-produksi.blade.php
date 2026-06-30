<x-filament-panels::page>
    <!-- HEADER DENGAN FORM DI KANAN -->
    <div class="p-4 bg-white dark:bg-zinc-900 rounded-lg shadow">
        {{ $this->form }}
    </div>

    <!-- Loading Indicator -->
    @if($isLoading)
    <div
        class="fixed inset-0 z-50 flex items-center justify-center bg-white bg-opacity-75 dark:bg-zinc-900 dark:bg-opacity-75"
    >
        <div class="flex items-center space-x-3">
            <x-filament::loading-indicator class="w-8 h-8 text-primary-600" />
            <span class="text-lg font-medium text-zinc-700 dark:text-zinc-300"
                >Memuat data...</span
            >
        </div>
    </div>
    @endif @php $dataProduksi = $dataProduksi ?? []; $groupedByMesin =
    collect($dataProduksi)->groupBy('mesin'); @endphp

    <div class="space-y-12 mt-6">
        @forelse ($groupedByMesin as $mesinNama => $produksiList) @php $first =
        $produksiList->first(); $pekerja = $first['pekerja'] ?? []; $kodeUkuran
        = $first['ukuran'] ?? 'TIDAK ADA UKURAN'; $totalPekerja =
        count($pekerja); $hasil = $first['hasil'] ?? 0; $target =
        $first['target'] ?? 0; $targetNormal = $first['target_normal'] ?? 0;
        $targetPerJam = $first['target_per_jam'] ?? 0; $targetPerMenit =
        $first['target_per_menit'] ?? 0; $selisih = $first['selisih'] ?? 0;
        $warna = $selisih >= 0 ? 'text-green-400' : 'text-red-400'; $tanda =
        $selisih >= 0 ? '+' : ''; $jamKerja = $first['jam_kerja'] ?? 0;
        $jamKerjaEfektif = $first['jam_kerja_efektif'] ?? 0; $totalKendalaMenit
        = $first['total_kendala_menit'] ?? 0; $totalDowntimeFormatted = 
        $first['total_downtime_formatted'] ?? '-'; $kendala = $first['kendala'] ??
        '-'; $daftarKendala = $first['daftar_kendala'] ?? []; @endphp

        <!-- CARD MESIN -->
        <div
            class="bg-white dark:bg-zinc-900 rounded-sm shadow-lg border border-zinc-200 dark:border-zinc-700 overflow-hidden"
        >
            <div class="bg-zinc-800 p-4 text-white">
                <h2 class="text-lg font-bold text-center">
                    PEKERJA MESIN: {{ strtoupper($mesinNama) }} -
                    {{ strtoupper($kodeUkuran) }}
                </h2>
            </div>

            <div class="p-4">
                <div class="w-full overflow-x-auto">
                    <div class="min-w-[800px]">
                        <table
                            class="w-full text-sm border-collapse border border-zinc-300 dark:border-zinc-600"
                        >
                            <thead>
                                <tr>
                                    <th
                                        colspan="7"
                                        class="p-4 text-xl font-bold text-center bg-zinc-700 text-white"
                                    >
                                        DATA PEKERJA
                                    </th>
                                </tr>

                                <tr
                                    class="bg-zinc-200 dark:bg-zinc-700 text-zinc-800 dark:text-zinc-300 border-t border-zinc-300 dark:border-zinc-600"
                                >
                                    <th
                                        class="p-2 text-center text-xs font-medium w-16"
                                    >
                                        ID
                                    </th>
                                    <th
                                        class="p-2 text-left text-xs font-medium w-40"
                                    >
                                        Nama
                                    </th>
                                    <th
                                        class="p-2 text-center text-xs font-medium w-20"
                                    >
                                        Masuk
                                    </th>
                                    <th
                                        class="p-2 text-center text-xs font-medium w-20"
                                    >
                                        Pulang
                                    </th>
                                    <th
                                        class="p-2 text-center text-xs font-medium w-16"
                                    >
                                        Ijin
                                    </th>
                                    <th
                                        class="p-2 text-right text-xs font-medium w-36"
                                    >
                                        Potongan Target
                                    </th>
                                    <th
                                        class="p-2 text-left text-xs font-medium"
                                    >
                                        Keterangan
                                    </th>
                                </tr>
                            </thead>

                            <tbody>
                                @forelse ($pekerja as $i => $p)
                                <tr
                                    class="{{
                                        $i % 2 === 1
                                            ? 'bg-zinc-50 dark:bg-zinc-800/50'
                                            : 'bg-white dark:bg-zinc-900'
                                    }} border-t border-zinc-300 dark:border-zinc-700"
                                >
                                    <td
                                        class="p-2 text-center text-xs border-r border-zinc-300 dark:border-zinc-700 text-zinc-700 dark:text-zinc-300"
                                    >
                                        {{ $p["id"] ?? "-" }}
                                    </td>

                                    <td
                                        class="p-2 text-left text-xs border-r border-zinc-300 dark:border-zinc-700 text-zinc-900 dark:text-zinc-100 font-medium"
                                    >
                                        {{ $p["nama"] ?? "-" }}
                                    </td>

                                    <td
                                        class="p-2 text-center text-xs border-r border-zinc-300 dark:border-zinc-700 text-zinc-700 dark:text-zinc-300"
                                    >
                                        {{ $p["jam_masuk"] ?? "-" }}
                                    </td>

                                    <td
                                        class="p-2 text-center text-xs border-r border-zinc-300 dark:border-zinc-700 text-zinc-700 dark:text-zinc-300"
                                    >
                                        {{ $p["jam_pulang"] ?? "-" }}
                                    </td>

                                    <td
                                        class="p-2 text-center text-xs border-r border-zinc-300 dark:border-zinc-700 text-yellow-600 dark:text-yellow-400"
                                    >
                                        {{ $p["ijin"] ?? "-" }}
                                    </td>

                                    <td
                                        class="p-2 text-right text-xs border-r border-zinc-300 dark:border-zinc-700 font-bold {{
                                            $selisih < 0
                                                ? 'text-red-600 dark:text-red-400'
                                                : 'text-zinc-700'
                                        }}"
                                    >
                                        Rp {{ $p["pot_target"] ?? 0 }}
                                    </td>

                                    <td
                                        class="p-2 text-left text-xs text-zinc-700 dark:text-zinc-300"
                                    >
                                        {{ $p["keterangan"] ?? "-" }}
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td
                                        colspan="7"
                                        class="p-4 text-center text-zinc-500 dark:text-zinc-400 text-sm"
                                    >
                                        Tidak ada data pekerja untuk mesin ini.
                                    </td>
                                </tr>
                                @endforelse
                            </tbody>

                            <tfoot
                                class="bg-zinc-100 dark:bg-zinc-800 border-t-2 border-zinc-300 dark:border-zinc-600"
                            >
                                <!-- BARIS 1: DATA UTAMA -->
                                <tr>
                                    <td
                                        colspan="7"
                                        class="p-3 text-center text-xs text-zinc-600 dark:text-zinc-400 space-x-3"
                                    >
                                        <span class="font-medium">Pekerja:</span>
                                        <strong>{{ $totalPekerja }}</strong>

                                        <span class="text-zinc-400">|</span>

                                        <span class="font-medium">Target:</span>
                                        <strong class="font-mono">{{ number_format($targetNormal) }}</strong>

                                        <span class="text-zinc-400">|</span>

                                        <span class="font-medium">Jam Produksi:</span>
                                        <strong class="font-mono">{{ number_format($jamKerja, 1) }} jam</strong>

                                        <span class="text-zinc-400">|</span>

                                        <span class="font-medium">Hasil:</span>
                                        <strong class="font-mono {{ $warna }}">{{ number_format($hasil) }}</strong>

                                        <span class="text-zinc-400">|</span>

                                        <span class="font-medium">Selisih:</span>
                                        <strong class="font-mono {{ $warna }}">{{ $tanda }}{{ number_format(abs($selisih)) }}</strong>

                                        <span class="text-zinc-400">|</span>

                                        <span class="text-xs">Tanggal: {{ $first["tanggal"] }}</span>

                                        <span class="text-zinc-400">|</span>

                                        <span class="font-medium">Total Downtime:</span>
                                        <strong class="font-mono {{ $totalKendalaMenit > 0 ? 'text-red-600 dark:text-red-400' : '' }}">
                                            {{ $totalDowntimeFormatted }}
                                        </strong>
                                    </td>
                                </tr>

                                <!-- BARIS 2: KENDALA (hanya jika ada) -->
                                @if(count($daftarKendala) > 0)
                                <tr>
                                    <td
                                        colspan="7"
                                        class="p-3 text-xs border-t border-zinc-300 dark:border-zinc-600"
                                    >
                                        <div class="flex items-start justify-center gap-2">
                                            <span class="font-medium text-zinc-600 dark:text-zinc-400 whitespace-nowrap">
                                                Kendala:
                                            </span>
                                            <div class="flex-1 max-w-3xl">
                                                <div class="space-y-1">
                                                    @foreach($daftarKendala as $k)
                                                    <div class="text-zinc-700 dark:text-zinc-300">
                                                        <span class="font-semibold text-red-600 dark:text-red-400">
                                                            {{ $k['kendala'] }}
                                                        </span>
                                                        <span class="text-zinc-500 dark:text-zinc-400">
                                                            — {{ $k['durasi_menit'] }} menit 
                                                            ({{ $k['jam_mulai'] }} - {{ $k['jam_selesai'] }})
                                                        </span>
                                                        @if($k['keterangan'] !== '-')
                                                        <span class="text-zinc-600 dark:text-zinc-400 italic">
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
        <div class="text-center p-12 text-zinc-500 dark:text-zinc-400">
            <p class="text-lg">Tidak ada data produksi untuk tanggal ini.</p>
        </div>
        @endforelse
    </div>
</x-filament-panels::page>