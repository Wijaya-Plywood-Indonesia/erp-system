<x-filament-panels::page>
    <div class="p-4 bg-white dark:bg-zinc-900 rounded-lg shadow">
        {{ $this->form }}
    </div>

    @if($isLoading)
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
    $groupedData = collect($dataProduksi)
    ->groupBy(function($item) {
    // RepairDataMap mengembalikan array linear, kita grupkan berdasarkan Meja & Kode Ukuran
    $kode = $item['kode_ukuran'] ?? 'UNKNOWN';
    $meja = $item['nomor_meja'] ?? '0';
    return $kode . '|' . $meja;
    })
    ->map(function($group) {
    $first = $group->first();
    if (!$first) return null;

    // Pastikan operasi matematika aman dari string/null
    $totalTarget = (float)($first['target'] ?? 0);
    $totalHasil = (float)($group->sum(fn($i) => is_numeric($i['hasil'] ?? null) ? $i['hasil'] : 0));

    return [
    'kode_ukuran' => $first['kode_ukuran'] ?? '-',
    'nomor_meja' => $first['nomor_meja'] ?? '-',
    'ukuran' => $first['ukuran'] ?? '-',
    'jenis_kayu' => $first['jenis_kayu'] ?? '-',
    'kw' => $first['kw'] ?? '-',
    'tanggal' => $first['tanggal'] ?? '-',
    'jam_kerja' => $first['jam_kerja'] ?? 0,
    'target' => $totalTarget,
    'hasil' => $totalHasil,
    'selisih' => $totalHasil - $totalTarget,
    // Mengambil data pekerja dari grup
    'pekerja' => $group->flatMap(fn($item) => $item['pekerja'] ?? [])->values()->all(),
    ];
    })
    ->filter()
    ->sortBy([
    ['nomor_meja', 'asc'],
    ['kode_ukuran', 'asc'],
    ])
    ->values();
    @endphp

    <div class="space-y-12 mt-4">
        @forelse ($groupedData as $data)
        @php
        $totalPekerja = count($data['pekerja']);
        $warna = $data['selisih'] >= 0 ? 'text-green-400' : 'text-red-400';
        $tanda = $data['selisih'] >= 0 ? '+' : '';
        @endphp

        <div
            class="bg-white dark:bg-zinc-900 rounded-sm shadow-lg border border-zinc-200 dark:border-zinc-700 overflow-hidden">
            <div class="bg-zinc-800 p-4 text-white">
                <h2 class="text-lg font-bold text-center">
                    MEJA {{ strtoupper($data["nomor_meja"]) }} -
                    @if($data['kode_ukuran'] === 'REPAIR-NOT-FOUND')
                    <span class="text-red-400">{{ $data["ukuran"] }} (Target Tidak Ditemukan)</span>
                    @else
                    {{ strtoupper($data["kode_ukuran"]) }}
                    @endif
                </h2>
            </div>

            <div class="p-4">
                <div class="w-full overflow-x-auto">
                    <div class="min-w-[800px]">
                        <table
                            class="w-full text-sm border-collapse border border-zinc-300 dark:border-zinc-600">
                            <thead>
                                <tr>
                                    <th
                                        colspan="7"
                                        class="p-4 text-xl font-bold text-center bg-zinc-700 text-white">
                                        DATA PEKERJA
                                    </th>
                                </tr>
                                <tr
                                    class="bg-zinc-200 dark:bg-zinc-700 text-zinc-800 dark:text-zinc-300 border-t border-zinc-300 dark:border-zinc-600">
                                    <th class="p-2 text-center text-xs font-medium w-16">ID</th>
                                    <th class="p-2 text-left text-xs font-medium w-40">Nama</th>
                                    <th class="p-2 text-center text-xs font-medium w-20">Masuk</th>
                                    <th class="p-2 text-center text-xs font-medium w-20">Pulang</th>
                                    <th class="p-2 text-center text-xs font-medium w-16">Ijin</th>
                                    <th class="p-2 text-right text-xs font-medium w-36">Potongan Target</th>
                                    <th class="p-2 text-left text-xs font-medium">Keterangan</th>
                                </tr>
                            </thead>

                            <tbody>
                                @forelse ($data['pekerja'] as $i => $p)
                                <tr
                                    class="{{
                                        $i % 2 === 1
                                            ? 'bg-zinc-50 dark:bg-zinc-800/50'
                                            : 'bg-white dark:bg-zinc-900'
                                    }} border-t border-zinc-300 dark:border-zinc-700">
                                    <td class="p-2 text-center text-xs border-r border-zinc-300 dark:border-zinc-700">
                                        {{ $p["id"] ?? "-" }}
                                    </td>
                                    <td class="p-2 text-left text-xs border-r border-zinc-300 dark:border-zinc-700 font-medium">
                                        {{ $p["nama"] ?? "-" }}
                                    </td>
                                    <td class="p-2 text-center text-xs border-r border-zinc-300 dark:border-zinc-700">
                                        {{ $p["jam_masuk"] ?? "-" }}
                                    </td>
                                    <td class="p-2 text-center text-xs border-r border-zinc-300 dark:border-zinc-700">
                                        {{ $p["jam_pulang"] ?? "-" }}
                                    </td>
                                    <td class="p-2 text-center text-xs border-r border-zinc-300 dark:border-zinc-700 text-yellow-600 dark:text-yellow-400">
                                        {{ $p["ijin"] ?? "-" }}
                                    </td>
                                    <td
                                        class="p-2 text-right text-xs border-r border-zinc-300 dark:border-zinc-700 font-bold {{
                                            $data['selisih'] < 0
                                                ? 'text-red-600 dark:text-red-400'
                                                : 'text-zinc-700'
                                        }}">
                                        {{ number_format((float)($p["pot_target"] ?? 0)) }}
                                    </td>
                                    <td class="p-2 text-left text-xs">
                                        {{ $p["keterangan"] ?? "-" }}
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="7" class="p-4 text-center text-zinc-500 dark:text-zinc-400 text-sm">
                                        Tidak ada data pekerja untuk meja ini.
                                    </td>
                                </tr>
                                @endforelse
                            </tbody>

                            <tfoot class="bg-zinc-100 dark:bg-zinc-800 border-t-2 border-zinc-300 dark:border-zinc-600">
                                <tr>
                                    <td colspan="7" class="p-3 text-center text-xs text-zinc-600 dark:text-zinc-400 space-x-3">
                                        <span class="font-medium">Pekerja:</span>
                                        <strong>{{ $totalPekerja }}</strong>

                                        <span class="text-zinc-400">|</span>

                                        <span class="font-medium">Target:</span>
                                        <strong class="font-mono">{{ number_format($data["target"]) }}</strong>

                                        <span class="text-zinc-400">|</span>

                                        <span class="font-medium">Jam Produksi:</span>
                                        <strong class="font-mono">{{ number_format($data["jam_kerja"]) }}</strong>

                                        <span class="text-zinc-400">|</span>

                                        <span class="font-medium">Hasil:</span>
                                        <strong class="font-mono {{ $warna }}">{{ number_format($data["hasil"]) }}</strong>

                                        <span class="text-zinc-400">|</span>

                                        <span class="font-medium">Selisih:</span>
                                        <strong class="font-mono {{ $warna }}">
                                            {{ $tanda }}{{ number_format(abs($data["selisih"])) }}
                                        </strong>

                                        <span class="text-zinc-400">|</span>

                                        <span class="text-xs">Tanggal: {{ $data["tanggal"] }}</span>
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        @empty
        <div class="text-center p-12 text-zinc-500 dark:text-zinc-400">
            <p class="text-lg">Tidak ada data produksi repair untuk tanggal ini.</p>
            <p class="text-sm mt-2">Silakan pilih tanggal lain atau periksa data di sistem.</p>
        </div>
        @endforelse
    </div>
</x-filament-panels::page>