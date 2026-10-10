<x-filament-panels::page>
    {{-- Form Filter Tanggal --}}
    <div class="p-4 bg-white dark:bg-zinc-900 rounded-lg shadow border border-zinc-200 dark:border-zinc-800">
        {{ $this->form }}
    </div>

    {{-- Loading Indicator --}}
    @if($isLoading ?? false)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-white bg-opacity-75 dark:bg-zinc-900 dark:bg-opacity-75">
        <div class="flex items-center space-x-3">
            <x-filament::loading-indicator class="w-8 h-8 text-primary-600" />
            <span class="text-lg font-medium text-zinc-700 dark:text-zinc-300">Memuat data repair...</span>
        </div>
    </div>
    @endif

    @php
    $dataProduksi = $dataProduksi ?? [];

    // ==========================================================================
    // NORMALISASI DATA
    // --------------------------------------------------------------------------
    // RepairDataMap punya 2 bentuk hasil:
    // 1) Meja multi-ukuran -> sudah ada key 'items' (array ukuran)
    // 2) Meja single-ukuran -> field ada di level atas (target, hasil,
    // kode_ukuran, dst), TIDAK ada key 'items'.
    //
    // Supaya blade di bawah bisa pakai SATU struktur kartu saja (persis gaya
    // Join: tabel pekerja + tabel barang dikerjakan), kita "bungkus" data
    // single-ukuran menjadi array items berisi 1 elemen di sini.
    // ==========================================================================
    $mejaData = collect($dataProduksi)
    ->map(function ($item) {
    if (!is_array($item)) { return null; }

    $items = $item['items'] ?? null;

    if ($items === null) {
    // Kasus single-ukuran: bentuk ulang jadi 1 baris items
    $items = [[
    'ukuran' => $item['ukuran'] ?? ($item['kode_ukuran'] ?? '-'),
    'kode_ukuran' => $item['kode_ukuran'] ?? '-',
    'jenis_kayu' => $item['jenis_kayu'] ?? '-',
    'kw' => $item['kw'] ?? '-',
    'target' => $item['target'] ?? 0,
    'hasil' => $item['hasil'] ?? 0,
    'selisih' => $item['selisih'] ?? (($item['hasil'] ?? 0) - ($item['target'] ?? 0)),
    'capaian_persen' => $item['capaian_persen'] ?? null,
    'has_target' => $item['has_target'] ?? true,
    ]];
    }

    $totalTarget = $item['total_target'] ?? collect($items)->sum('target');
    $totalHasil = $item['total_hasil'] ?? collect($items)->sum('hasil');
    $totalSelisih = $item['total_selisih'] ?? ($totalHasil - $totalTarget);
    $capaianTotal = $item['capaian_total'] ?? ($item['capaian_persen'] ?? null);

    return [
    'nomor_meja' => $item['nomor_meja'] ?? '-',
    'tanggal' => $item['tanggal'] ?? '-',
    'pekerja' => $item['pekerja'] ?? [],
    'items' => $items,
    'total_target' => $totalTarget,
    'total_hasil' => $totalHasil,
    'total_selisih' => $totalSelisih,
    'capaian_total' => $capaianTotal,
    'jam_kerja' => $item['jam_kerja'] ?? 0,
    'keterangan_hasil' => $item['keterangan_hasil'] ?? null,
    'keterangan_kerja' => $item['keterangan_kerja'] ?? null,
    ];
    })
    ->filter()
    ->sortBy('nomor_meja')
    ->values();
    @endphp

    <div class="space-y-12 mt-6">
        @forelse ($mejaData as $data)
        @php
        $totalPekerja = count($data['pekerja']);
        $tercapai = $data['total_selisih'] >= 0;
        $warnaStatus = $tercapai ? 'text-green-400' : 'text-red-400';
        @endphp

        <div class="bg-white dark:bg-zinc-900 rounded-sm shadow-lg border border-zinc-200 dark:border-zinc-700 overflow-hidden">
            {{-- Header Blok Produksi Repair (setara header meja Join) --}}
            <div class="bg-zinc-800 p-4 text-white flex justify-between items-center flex-wrap gap-2">
                <h2 class="text-lg font-bold text-center">
                    MEJA {{ strtoupper($data['nomor_meja']) }}
                </h2>
                <div class="flex gap-2 items-center flex-wrap">
                    @if($data['capaian_total'] !== null)
                    <span class="text-xs px-2 py-1 rounded {{ $tercapai ? 'bg-green-700' : 'bg-red-700' }}">
                        Capaian: {{ number_format($data['capaian_total'], 1, ',', '.') }}%
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
                        <table class="w-full text-sm border-collapse border border-zinc-300 dark:border-zinc-600">
                            <thead>
                                <tr>
                                    <th colspan="7" class="p-3 text-lg font-bold text-center bg-zinc-700 text-white">
                                        DATA PEKERJA
                                    </th>
                                </tr>
                                <tr class="bg-zinc-200 dark:bg-zinc-700 text-zinc-800 dark:text-zinc-300 border-t border-zinc-300 dark:border-zinc-600">
                                    <th class="p-2 text-center text-xs font-medium w-16">ID</th>
                                    <th class="p-2 text-left text-xs font-medium w-40">Nama</th>
                                    <th class="p-2 text-center text-xs font-medium w-20">Masuk</th>
                                    <th class="p-2 text-center text-xs font-medium w-20">Pulang</th>
                                    <th class="p-2 text-center text-xs font-medium w-16">Ijin</th>
                                    <th class="p-2 text-center text-xs font-medium w-28">Status</th>
                                    <th class="p-2 text-right text-xs font-medium w-36">Potongan Target</th>
                                </tr>
                            </thead>

                            <tbody>
                                @forelse ($data['pekerja'] as $i => $p)
                                @php
                                $potTarget = (int) ($p['pot_target'] ?? 0);
                                $statusKerja = $p['status_kerja'] ?? '-';
                                $isIndividu = $statusKerja === 'Individu';
                                @endphp
                                <tr class="{{ $i % 2 === 1 ? 'bg-zinc-50 dark:bg-zinc-800/50' : 'bg-white dark:bg-zinc-900' }} border-t border-zinc-300 dark:border-zinc-700">
                                    <td class="p-2 text-center text-xs border-r border-zinc-300 dark:border-zinc-700 font-mono">
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
                                    <td class="p-2 text-center text-xs border-r border-zinc-300 dark:border-zinc-700">
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold {{ $isIndividu ? 'bg-amber-600 text-white' : 'bg-sky-700 text-white' }}">
                                            {{ $statusKerja }}
                                        </span>
                                    </td>
                                    <td class="p-2 text-right text-xs font-bold {{ $potTarget > 0 ? 'text-red-500' : '' }}">
                                        {{ $potTarget > 0 ? 'Rp ' . number_format($potTarget) : '-' }}
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="7" class="p-4 text-center text-zinc-500 dark:text-zinc-400 text-sm italic">
                                        Tidak ada data pekerja untuk meja ini.
                                    </td>
                                </tr>
                                @endforelse
                            </tbody>

                            <tfoot class="bg-zinc-100 dark:bg-zinc-800 border-t-2 border-zinc-300 dark:border-zinc-600">
                                <tr>
                                    <td colspan="7" class="p-2 text-center text-[11px] text-zinc-500 dark:text-zinc-400">
                                        <span class="font-medium">Jumlah Pekerja:</span>
                                        <strong class="text-zinc-900 dark:text-white">{{ $totalPekerja }}</strong>
                                        <span class="text-zinc-400">|</span>
                                        <span class="font-medium">Jam Kerja (rata²):</span>
                                        <strong class="font-mono text-zinc-900 dark:text-white">{{ number_format($data['jam_kerja'], 2, ',', '.') }}</strong>
                                        <span class="text-zinc-400">|</span>
                                        <span class="text-xs">Tgl: {{ $data['tanggal'] }}</span>
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>

                {{-- ================= TABEL BAWAH: BARANG DIKERJAKAN ================= --}}
                <div class="w-full overflow-x-auto">
                    <div class="min-w-[800px]">
                        <table class="w-full text-sm border-collapse border border-zinc-300 dark:border-zinc-600">
                            <thead>
                                <tr>
                                    <th colspan="8" class="p-3 text-lg font-bold text-center bg-zinc-700 text-white">
                                        DATA BARANG DIKERJAKAN
                                    </th>
                                </tr>
                                <tr class="bg-zinc-200 dark:bg-zinc-700 text-zinc-800 dark:text-zinc-300 border-t border-zinc-300 dark:border-zinc-600">
                                    <th class="p-2 text-left text-xs font-medium">Ukuran</th>
                                    <th class="p-2 text-center text-xs font-medium w-28">Jenis Kayu</th>
                                    <th class="p-2 text-center text-xs font-medium w-16">KW</th>
                                    <th class="p-2 text-center text-xs font-medium w-20">Jml Pekerja</th>
                                    <th class="p-2 text-right text-xs font-medium w-24">Target</th>
                                    <th class="p-2 text-right text-xs font-medium w-24">Hasil</th>
                                    <th class="p-2 text-right text-xs font-medium w-24">Selisih</th>
                                    <th class="p-2 text-right text-xs font-medium w-20">Capaian</th>
                                </tr>
                            </thead>

                            <tbody>
                                @forelse ($data['items'] as $i => $item)
                                @php
                                $selisihItem = $item['selisih'] ?? 0;
                                $isLunasItem = $selisihItem >= 0;
                                $tandaItem = $selisihItem >= 0 ? '+' : '';
                                $jmlPekerjaItem = $item['jumlah_pekerja'] ?? null;
                                @endphp
                                <tr class="{{ $i % 2 === 1 ? 'bg-zinc-50 dark:bg-zinc-800/50' : 'bg-white dark:bg-zinc-900' }} border-t border-zinc-300 dark:border-zinc-700">
                                    <td class="p-2 text-left text-xs border-r border-zinc-300 dark:border-zinc-700 font-medium">
                                        @if(($item['kode_ukuran'] ?? '') === 'REPAIR-NOT-FOUND' || !($item['has_target'] ?? true))
                                        <span class="text-red-500">{{ $item['ukuran'] }} ⚠</span>
                                        @else
                                        {{ strtoupper($item['ukuran'] ?? $item['kode_ukuran']) }}
                                        @endif
                                    </td>
                                    <td class="p-2 text-center text-xs border-r border-zinc-300 dark:border-zinc-700 uppercase">
                                        {{ $item['jenis_kayu'] ?? '-' }}
                                    </td>
                                    <td class="p-2 text-center text-xs border-r border-zinc-300 dark:border-zinc-700 uppercase">
                                        {{ $item['kw'] ?? '-' }}
                                    </td>
                                    <td class="p-2 text-center text-xs border-r border-zinc-300 dark:border-zinc-700 font-mono {{ ($jmlPekerjaItem !== null && $jmlPekerjaItem !== 2) ? 'text-amber-600 dark:text-amber-400 font-bold' : 'text-zinc-500' }}">
                                        {{ $jmlPekerjaItem ?? '-' }}
                                    </td>
                                    <td class="p-2 text-right text-xs border-r border-zinc-300 dark:border-zinc-700 text-zinc-500">
                                        {{ number_format($item['target'] ?? 0, 0, ',', '.') }}
                                    </td>
                                    <td class="p-2 text-right text-xs border-r border-zinc-300 dark:border-zinc-700 font-bold {{ $isLunasItem ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                                        {{ number_format($item['hasil'] ?? 0, 0, ',', '.') }}
                                    </td>
                                    <td class="p-2 text-right text-xs border-r border-zinc-300 dark:border-zinc-700 font-bold {{ $isLunasItem ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                                        {{ $tandaItem }}{{ number_format($selisihItem, 0, ',', '.') }}
                                    </td>
                                    <td class="p-2 text-right text-xs font-bold {{ !($item['has_target'] ?? true) ? 'text-red-500' : (($item['capaian_persen'] ?? 0) >= 100 ? 'text-green-600 dark:text-green-400' : 'text-amber-600 dark:text-amber-400') }}">
                                        @if(!($item['has_target'] ?? true) || $item['capaian_persen'] === null)
                                        Target ?
                                        @else
                                        {{ number_format($item['capaian_persen'], 1, ',', '.') }}%
                                        @endif
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="8" class="p-4 text-center text-zinc-500 dark:text-zinc-400 text-sm italic">
                                        Tidak ada barang dikerjakan.
                                    </td>
                                </tr>
                                @endforelse
                            </tbody>

                            <tfoot class="bg-zinc-100 dark:bg-zinc-800 border-t-2 border-zinc-300 dark:border-zinc-600">
                                <tr>
                                    <td colspan="8" class="p-2 text-center text-[11px] text-zinc-500 dark:text-zinc-400 border-t border-zinc-300 dark:border-zinc-700">
                                        Subtotal Meja &mdash;
                                        Target: <strong class="text-zinc-900 dark:text-white">{{ number_format($data['total_target'], 0, ',', '.') }}</strong>
                                        <span class="text-zinc-400">|</span>
                                        Hasil: <strong class="{{ $warnaStatus }} font-bold">{{ number_format($data['total_hasil'], 0, ',', '.') }}</strong>
                                        <span class="text-zinc-400">|</span>
                                        Selisih: <strong class="{{ $warnaStatus }} font-bold">{{ $data['total_selisih'] >= 0 ? '+' : '' }}{{ number_format($data['total_selisih'], 0, ',', '.') }}</strong>
                                        @if($data['capaian_total'] !== null)
                                        <span class="text-zinc-400">|</span>
                                        Capaian: <strong class="{{ $tercapai ? 'text-green-600 dark:text-green-400' : 'text-red-500' }}">{{ number_format($data['capaian_total'], 1, ',', '.') }}%</strong>
                                        @endif
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>

                {{-- Catatan / Kendala --}}
                @if(($data['keterangan_hasil'] && $data['keterangan_hasil'] !== '—') || ($data['keterangan_kerja'] && $data['keterangan_kerja'] !== '—'))
                <div class="p-3 bg-zinc-50 dark:bg-zinc-800/50 rounded border border-zinc-200 dark:border-zinc-700/60 text-xs space-y-1">
                    @if($data['keterangan_hasil'] && $data['keterangan_hasil'] !== '—')
                    <p class="text-zinc-700 dark:text-zinc-300">
                        <strong>Catatan Hasil Repair:</strong> {{ $data['keterangan_hasil'] }}
                    </p>
                    @endif
                    @if($data['keterangan_kerja'] && $data['keterangan_kerja'] !== '—')
                    <p class="text-amber-700 dark:text-amber-400">
                        <strong>⚠ Kendala Produksi Hari Ini:</strong> {{ $data['keterangan_kerja'] }}
                    </p>
                    @endif
                </div>
                @endif
            </div>
        </div>

        @empty
        <div class="text-center p-12 bg-white dark:bg-zinc-900 rounded-lg border border-dashed border-zinc-300 dark:border-zinc-700">
            <x-heroicon-o-document-magnifying-glass class="w-12 h-12 mx-auto text-zinc-400 mb-4" />
            <p class="text-lg text-zinc-500 dark:text-zinc-400">
                Tidak ditemukan data produksi repair untuk tanggal ini.
            </p>
            <p class="text-sm text-zinc-400 mt-2">
                Silakan pilih tanggal lain atau periksa data di sistem.
            </p>
        </div>
        @endforelse
    </div>
</x-filament-panels::page>