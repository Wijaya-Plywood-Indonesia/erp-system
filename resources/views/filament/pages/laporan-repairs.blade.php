<x-filament-panels::page>
    <div class="p-4 bg-white dark:bg-zinc-900 rounded-lg shadow">
        {{ $this->form }}
    </div>

    @if($isLoading)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-white/75 dark:bg-zinc-900/75">
        <div class="flex items-center space-x-3">
            <x-filament::loading-indicator class="w-8 h-8 text-primary-600" />
            <span class="text-lg font-medium text-zinc-700 dark:text-zinc-300">Memuat data laporan...</span>
        </div>
    </div>
    @endif

    @php
    $dataProduksi = $dataProduksi ?? [];
    $groupedData = collect($dataProduksi)
    ->groupBy(function($item) {
    $kode = $item['kode_ukuran'] ?? 'UNKNOWN';
    $meja = $item['nomor_meja'] ?? '0';
    return $kode . '|' . $meja;
    })
    ->map(function($group) {
    $first = $group->first();
    if (!$first) return null;

    $totalTarget = (float)($first['target'] ?? 0);

    // Mengambil angka total hasil langsung dari baris DetailHasilRepair
    $totalHasil = (float)($first['hasil'] ?? $group->sum('hasil'));

    return [
    'id_detail' => $first['id_detail'] ?? null,
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
    'keterangan_hasil' => $first['keterangan_hasil'] ?? '—',
    'keterangan_kerja' => $first['keterangan_kerja'] ?? '—',
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

    <div class="space-y-8 mt-4">
        @forelse ($groupedData as $data)
        @php
        $totalPekerja = count($data['pekerja']);
        $isLunasTarget = $data['selisih'] >= 0;
        $warnaTeks = $isLunasTarget ? 'text-emerald-500 dark:text-emerald-400' : 'text-rose-500 dark:text-rose-400';
        $tanda = $isLunasTarget ? '+' : '';
        @endphp

        <div class="bg-white dark:bg-zinc-900 rounded-lg shadow-md border border-zinc-200 dark:border-zinc-700 overflow-hidden">
            {{-- Header Card Meja --}}
            <div class="bg-zinc-800 px-4 py-3 text-white flex justify-between items-center flex-wrap gap-2">
                <h2 class="text-base md:text-lg font-bold tracking-wide">
                    MEJA {{ strtoupper($data["nomor_meja"]) }} —
                    @if($data['kode_ukuran'] === 'REPAIR-NOT-FOUND')
                    <span class="text-rose-400">{{ $data["ukuran"] }} (Target Tidak Ditemukan)</span>
                    @else
                    {{ strtoupper($data["kode_ukuran"]) }}
                    @endif
                </h2>
            </div>

            <div class="p-4 space-y-4">
                <div class="w-full overflow-x-auto">
                    <div class="min-w-[800px]">
                        <table class="w-full text-sm border-collapse border border-zinc-200 dark:border-zinc-700">
                            <thead>
                                <tr>
                                    <th colspan="7" class="p-2.5 text-base font-bold text-center bg-zinc-700 text-white tracking-wider">
                                        DATA PEKERJA
                                    </th>
                                </tr>
                                <tr class="bg-zinc-100 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300 border-t border-zinc-200 dark:border-zinc-700 text-xs">
                                    <th class="p-2 text-center font-semibold w-16">ID</th>
                                    <th class="p-2 text-left font-semibold w-48">Nama Pekerja</th>
                                    <th class="p-2 text-center font-semibold w-20">Masuk</th>
                                    <th class="p-2 text-center font-semibold w-20">Pulang</th>
                                    <th class="p-2 text-center font-semibold w-16">Izin</th>
                                    <th class="p-2 text-right font-semibold w-36">Potongan Target</th>
                                    <th class="p-2 text-left font-semibold">Keterangan Absen</th>
                                </tr>
                            </thead>

                            <tbody>
                                @forelse ($data['pekerja'] as $i => $p)
                                <tr class="{{ $i % 2 === 1 ? 'bg-zinc-50/50 dark:bg-zinc-800/30' : 'bg-white dark:bg-zinc-900' }} border-t border-zinc-200 dark:border-zinc-700 text-xs">
                                    <td class="p-2 text-center border-r border-zinc-200 dark:border-zinc-700 font-mono text-zinc-500">
                                        {{ $p["id"] ?? "-" }}
                                    </td>
                                    <td class="p-2 text-left border-r border-zinc-200 dark:border-zinc-700 font-medium">
                                        {{ $p["nama"] ?? "-" }}
                                    </td>
                                    <td class="p-2 text-center border-r border-zinc-200 dark:border-zinc-700">
                                        {{ $p["jam_masuk"] ?? "-" }}
                                    </td>
                                    <td class="p-2 text-center border-r border-zinc-200 dark:border-zinc-700">
                                        {{ $p["jam_pulang"] ?? "-" }}
                                    </td>
                                    <td class="p-2 text-center border-r border-zinc-200 dark:border-zinc-700 text-amber-600 dark:text-amber-400 font-medium">
                                        {{ $p["ijin"] ?? "-" }}
                                    </td>
                                    <td class="p-2 text-right border-r border-zinc-200 dark:border-zinc-700 font-bold font-mono {{ $p['pot_target'] > 0 ? 'text-rose-500' : 'text-zinc-600 dark:text-zinc-400' }}">
                                        Rp {{ number_format((float)($p["pot_target"] ?? 0)) }}
                                    </td>
                                    <td class="p-2 text-left text-zinc-500 dark:text-zinc-400">
                                        {{ $p["keterangan"] ?? "-" }}
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="7" class="p-4 text-center text-zinc-500 dark:text-zinc-400 text-xs italic">
                                        Tidak ada pegawai terdaftar untuk meja pengerjaan ini.
                                    </td>
                                </tr>
                                @endforelse
                            </tbody>

                            {{-- Summary Baris Bawah --}}
                            <tfoot class="bg-zinc-100 dark:bg-zinc-800 border-t-2 border-zinc-300 dark:border-zinc-600">
                                <tr>
                                    <td colspan="7" class="p-3 text-center text-xs text-zinc-600 dark:text-zinc-300 space-x-2 md:space-x-4">
                                        <span class="font-medium">Total Pekerja:</span>
                                        <strong>{{ $totalPekerja }}</strong>

                                        <span class="text-zinc-400">|</span>

                                        <span class="font-medium">Target Meja:</span>
                                        <strong class="font-mono">{{ number_format($data["target"]) }} Lbr</strong>

                                        <span class="text-zinc-400">|</span>

                                        <span class="font-medium">Hasil Produksi:</span>
                                        <strong class="font-mono text-sm {{ $warnaTeks }}">{{ number_format($data["hasil"]) }} Lbr</strong>

                                        <span class="text-zinc-400">|</span>

                                        <span class="font-medium">Selisih:</span>
                                        <strong class="font-mono text-sm {{ $warnaTeks }}">
                                            {{ $tanda }}{{ number_format($data["selisih"]) }} Lbr
                                        </strong>

                                        <span class="text-zinc-400">|</span>

                                        <span class="text-xs text-zinc-500">Tgl: {{ $data["tanggal"] }}</span>
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>

                {{-- Catatan Tambahan (Keterangan Hasil & Kendala Kerja) --}}
                @if(($data['keterangan_hasil'] && $data['keterangan_hasil'] !== '—') || ($data['keterangan_kerja'] && $data['keterangan_kerja'] !== '—'))
                <div class="p-3 bg-zinc-50 dark:bg-zinc-800/50 rounded border border-zinc-200 dark:border-zinc-700/60 text-xs space-y-1">
                    @if($data['keterangan_hasil'] && $data['keterangan_hasil'] !== '—')
                    <p class="text-zinc-700 dark:text-zinc-300">
                        <strong>Catatan Hasil Repair:</strong> {{ $data['keterangan_hasil'] }}
                    </p>
                    @endif

                    @if($data['keterangan_kerja'] && $data['keterangan_kerja'] !== '—')
                    <p class="text-amber-700 dark:text-amber-400">
                        <strong>⚠️ Kendala Produksi Hari Ini:</strong> {{ $data['keterangan_kerja'] }}
                    </p>
                    @endif
                </div>
                @endif
            </div>
        </div>

        @empty
        <div class="text-center p-12 bg-white dark:bg-zinc-900 rounded-lg shadow border border-zinc-200 dark:border-zinc-800 text-zinc-500 dark:text-zinc-400">
            <x-heroicon-o-clipboard-document-list class="w-12 h-12 mx-auto text-zinc-400 mb-3" />
            <p class="text-base font-medium">Tidak ada data produksi repair untuk tanggal ini.</p>
            <p class="text-xs mt-1 text-zinc-400">Silakan pilih tanggal lain pada form filter di atas.</p>
        </div>
        @endforelse
    </div>
</x-filament-panels::page>