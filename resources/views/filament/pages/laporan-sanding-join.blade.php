<x-filament-panels::page>
    <div class="p-4 bg-white dark:bg-zinc-900 rounded-lg shadow border border-zinc-200 dark:border-zinc-800">
        {{ $this->form }}
    </div>

    @if($isLoading)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-white/75 dark:bg-zinc-900/75">
        <div class="flex items-center space-x-3">
            <x-filament::loading-indicator class="w-8 h-8 text-sky-500" />
            <span class="text-lg font-medium text-zinc-700 dark:text-zinc-300">Memuat data produksi...</span>
        </div>
    </div>
    @endif

    @php
    $dataProduksi = $dataProduksi ?? [];
    $dataPekerja = $dataPekerja ?? []; // daftar pekerja GABUNGAN satu hari, sama untuk semua card
    $groupedByCard = collect($dataProduksi)->values(); // sudah unik per kode_ukuran, tidak perlu groupBy lagi
    @endphp

    <div class="space-y-8 mt-6">
        @forelse ($groupedByCard as $data)
        @php
        $totalPekerja = count($dataPekerja);
        $hasil = $data['hasil'] ?? 0;
        $target = $data['target'] ?? 0;
        $selisih = $data['selisih'] ?? ($hasil - $target);
        $warnaStatus = $selisih >= 0 ? 'text-emerald-500 dark:text-emerald-400 font-bold' : 'text-red-600 dark:text-red-400 font-bold';
        $tandaSelisih = $selisih > 0 ? '+' : '';
        $jamKerja = $data['jam_standar'] ?? 9.0;
        $tanggalFormat = $data['tanggal'] ?? date('d/m/Y');
        $cardTitle = ($data['kode_ukuran'] === 'SANDING-JOINT-NOT-FOUND')
        ? $data['ukuran'] . ' (Target Tidak Ditemukan)'
        : $data['kode_ukuran'];
        @endphp

        <div class="bg-white dark:bg-zinc-900 rounded-sm shadow-lg border border-zinc-200 dark:border-zinc-700 overflow-hidden">
            <div class="bg-zinc-800 p-4 text-white">
                <h2 class="text-lg font-bold text-center uppercase tracking-wide flex items-center justify-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full {{ $selisih >= 0 ? 'bg-emerald-500' : 'bg-amber-500' }}"></span>
                    SANDING JOINT: {{ strtoupper($cardTitle) }}
                </h2>
            </div>

            <div class="p-4">
                <div class="w-full overflow-x-auto">
                    <div class="min-w-[800px]">
                        <table class="w-full text-sm border-collapse border border-zinc-300 dark:border-zinc-600">
                            <thead>
                                <tr>
                                    <th colspan="7" class="p-4 text-xl font-bold text-center bg-zinc-700 text-white uppercase tracking-wider">
                                        DATA PEKERJA
                                    </th>
                                </tr>
                                <tr class="bg-zinc-200 dark:bg-zinc-700 text-zinc-800 dark:text-zinc-300 border-t border-zinc-300 dark:border-zinc-600 text-xs uppercase font-semibold">
                                    <th class="p-2 text-center w-16">ID</th>
                                    <th class="p-2 text-left w-40">Nama</th>
                                    <th class="p-2 text-center w-20">Masuk</th>
                                    <th class="p-2 text-center w-20">Pulang</th>
                                    <th class="p-2 text-center w-16">Ijin</th>
                                    <th class="p-2 text-right w-36">Potongan Target</th>
                                    <th class="p-2 text-left">Keterangan</th>
                                </tr>
                            </thead>

                            <tbody class="divide-y divide-zinc-300 dark:divide-zinc-700 font-medium">
                                @forelse ($dataPekerja as $i => $p)
                                @php $potRaw = (int) ($p['pot_target'] ?? 0); @endphp
                                <tr class="{{ $i % 2 === 1 ? 'bg-zinc-50 dark:bg-zinc-800/50' : 'bg-white dark:bg-zinc-900' }} hover:bg-zinc-800/40 transition">
                                    <td class="p-2 text-center text-xs border-r border-zinc-300 dark:border-zinc-700 text-zinc-700 dark:text-zinc-300 font-mono">
                                        {{ $p["id"] ?? "-" }}
                                    </td>
                                    <td class="p-2 text-left text-xs border-r border-zinc-300 dark:border-zinc-700 text-zinc-900 dark:text-zinc-100 font-bold">
                                        {{ $p["nama"] ?? "-" }}
                                    </td>
                                    <td class="p-2 text-center text-xs border-r border-zinc-300 dark:border-zinc-700 text-zinc-700 dark:text-zinc-300">
                                        {{ $p["jam_masuk"] ?? "-" }}
                                    </td>
                                    <td class="p-2 text-center text-xs border-r border-zinc-300 dark:border-zinc-700 text-zinc-700 dark:text-zinc-300">
                                        {{ $p["jam_pulang"] ?? "-" }}
                                    </td>
                                    <td class="p-2 text-center text-xs border-r border-zinc-300 dark:border-zinc-700 text-amber-500">
                                        {{ $p["ijin"] ?? "-" }}
                                    </td>
                                    <td class="p-2 text-right text-xs border-r border-zinc-300 dark:border-zinc-700 font-bold font-mono {{ $potRaw > 0 ? 'text-red-600 dark:text-red-400' : 'text-zinc-400' }}">
                                        {{ $potRaw > 0 ? 'Rp ' . number_format($potRaw, 0, ',', '.') : '-' }}
                                    </td>
                                    <td class="p-2 text-left text-xs text-zinc-700 dark:text-zinc-300">
                                        {{ $p["keterangan"] ?? "-" }}
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="7" class="p-4 text-center text-zinc-500 dark:text-zinc-400 text-xs">
                                        Tidak ada data pekerja untuk produksi ini.
                                    </td>
                                </tr>
                                @endforelse
                            </tbody>

                            <tfoot class="bg-zinc-100 dark:bg-zinc-800 border-t-2 border-zinc-300 dark:border-zinc-600">
                                <tr>
                                    <td colspan="7" class="p-3 text-center text-xs text-zinc-600 dark:text-zinc-400 space-x-3">
                                        <span class="font-medium">Pekerja:</span>
                                        <strong class="text-zinc-900 dark:text-zinc-100">{{ $totalPekerja }}</strong>

                                        <span class="text-zinc-400">|</span>

                                        <span class="font-medium">Target:</span>
                                        <strong class="font-mono text-zinc-900 dark:text-zinc-100">{{ number_format($target, 0, ',', '.') }}</strong>

                                        <span class="text-zinc-400">|</span>

                                        <span class="font-medium">Jam Produksi:</span>
                                        <strong class="font-mono text-zinc-900 dark:text-zinc-100">{{ number_format($jamKerja, 1) }} jam</strong>

                                        <span class="text-zinc-400">|</span>

                                        <span class="font-medium">Hasil:</span>
                                        <strong class="font-mono {{ $warnaStatus }}">{{ number_format($hasil, 0, ',', '.') }}</strong>

                                        <span class="text-zinc-400">|</span>

                                        <span class="font-medium">Selisih:</span>
                                        <strong class="font-mono {{ $warnaStatus }}">{{ $tandaSelisih }}{{ number_format($selisih, 0, ',', '.') }}</strong>

                                        <span class="text-zinc-400">|</span>

                                        <span class="text-xs">Tanggal: {{ $tanggalFormat }}</span>
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        @empty
        <div class="text-center p-12 text-zinc-500 dark:text-zinc-400 bg-white dark:bg-zinc-900 rounded-lg border border-zinc-200 dark:border-zinc-800 shadow">
            <p class="text-lg">Tidak ada data laporan produksi untuk tanggal ini.</p>
        </div>
        @endforelse
    </div>
</x-filament-panels::page>