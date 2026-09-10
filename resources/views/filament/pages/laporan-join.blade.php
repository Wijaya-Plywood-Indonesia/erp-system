<x-filament-panels::page>
    {{-- Form Filter Tanggal --}}
    <div class="p-4 bg-white dark:bg-zinc-900 rounded-lg shadow border border-zinc-200 dark:border-zinc-800">
        {{ $this->form }}
    </div>

    {{-- Loading Indicator --}}
    @if($isLoading)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-white bg-opacity-75 dark:bg-zinc-900 dark:bg-opacity-75">
        <div class="flex items-center space-x-3">
            <x-filament::loading-indicator class="w-8 h-8 text-primary-600" />
            <span class="text-lg font-medium text-zinc-700 dark:text-zinc-300">Memuat data jointing...</span>
        </div>
    </div>
    @endif

    @php
    $dataProduksi = $dataProduksi ?? [];

    $mejaData = collect($dataProduksi)
    ->map(function($item) {
    if (!is_array($item)) { return null; }
    return [
    'nomor_meja'             => $item['nomor_meja'] ?? '-',
    'tanggal'                => $item['tanggal'] ?? '-',
    'jam_aktual'             => $item['jam_aktual'] ?? 0,
    'jumlah_pekerja'         => $item['jumlah_pekerja'] ?? 0,
    'capaian_global_persen'  => $item['capaian_global_persen'] ?? null,
    'potongan_total_tim'     => $item['potongan_total_tim'] ?? 0,
    'potongan_melebihi_gaji' => $item['potongan_melebihi_gaji'] ?? false,
    'total_gaji_tim'         => $item['total_gaji_tim'] ?? 0,
    'pekerja'                => $item['pekerja'] ?? [],
    'items'                  => $item['items'] ?? [],
    ];
    })
    ->filter()
    ->sortBy('nomor_meja')
    ->values();
    @endphp

    <div class="space-y-12 mt-6">
        @forelse ($mejaData as $data)
        @php
        $tercapai = ($data['capaian_global_persen'] ?? 0) >= 100;
        $totalHasilMeja = collect($data['items'])->sum('hasil');
        $totalTargetMeja = collect($data['items'])->sum('target');
        $rataRataPerOrang = $data['jumlah_pekerja'] > 0 ? $data['potongan_total_tim'] / $data['jumlah_pekerja'] : 0;
        @endphp

        <div class="bg-white dark:bg-zinc-900 rounded-sm shadow-lg border border-zinc-200 dark:border-zinc-700 overflow-hidden">
            {{-- Header Blok Produksi Joint (setara header meja) --}}
            <div class="bg-zinc-800 p-4 text-white flex justify-between items-center">
                <h2 class="text-lg font-bold text-center">
                    MEJA {{ strtoupper($data['nomor_meja']) }}
                </h2>
                <div class="flex gap-4 items-center">
                    @if($data['capaian_global_persen'] !== null)
                    <span class="text-xs px-2 py-1 rounded {{ $tercapai ? 'bg-green-700' : 'bg-red-700' }}">
                        Capaian Global: {{ number_format($data['capaian_global_persen'], 1, ',', '.') }}%
                    </span>
                    @endif
                    @if($data['potongan_total_tim'] > 0)
                    <span class="text-xs px-2 py-1 rounded bg-amber-600 font-bold" title="Total tim dibagi rata jumlah pekerja">
                        ⚠ Rp {{ number_format($data['potongan_total_tim']) }} ÷ {{ $data['jumlah_pekerja'] }} org ≈ Rp {{ number_format($rataRataPerOrang) }}/org
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
                                    <th class="p-2 text-center text-xs font-medium w-24">Jam Aktual</th>
                                    <th class="p-2 text-center text-xs font-medium w-16">Ijin</th>
                                    <th class="p-2 text-right text-xs font-medium w-36">Potongan Target</th>
                                </tr>
                            </thead>

                            <tbody>
                                @forelse ($data['pekerja'] as $i => $p)
                                @php
                                $potTarget = (int)($p['pot_target'] ?? 0);
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
                                    <td class="p-2 text-center text-xs border-r border-zinc-300 dark:border-zinc-700 font-mono">
                                        {{ isset($p["jam_aktual_bersih"]) ? number_format($p["jam_aktual_bersih"], 2, ',', '.') . ' jam' : '-' }}
                                    </td>
                                    <td class="p-2 text-center text-xs border-r border-zinc-300 dark:border-zinc-700 text-yellow-600 dark:text-yellow-400">
                                        {{ $p["ijin"] ?? "-" }}
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
                                        <strong class="text-zinc-900 dark:text-white">{{ $data['jumlah_pekerja'] }}</strong>
                                        <span class="text-zinc-400">|</span>
                                        <span class="font-medium">Rata-rata Jam Aktual Kru:</span>
                                        <strong class="font-mono text-zinc-900 dark:text-white">{{ number_format($data['jam_aktual'], 2, ',', '.') }} jam</strong>
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
                                    <th colspan="6" class="p-3 text-lg font-bold text-center bg-zinc-700 text-white">
                                        DATA BARANG DIKERJAKAN
                                    </th>
                                </tr>
                                <tr class="bg-zinc-200 dark:bg-zinc-700 text-zinc-800 dark:text-zinc-300 border-t border-zinc-300 dark:border-zinc-600">
                                    <th class="p-2 text-left text-xs font-medium">Ukuran</th>
                                    <th class="p-2 text-center text-xs font-medium w-28">Jenis Kayu</th>
                                    <th class="p-2 text-center text-xs font-medium w-16">KW</th>
                                    <th class="p-2 text-right text-xs font-medium w-24">Hasil</th>
                                    <th class="p-2 text-right text-xs font-medium w-28">Target (Adjusted)</th>
                                    <th class="p-2 text-right text-xs font-medium w-20">Capaian</th>
                                </tr>
                            </thead>

                            <tbody>
                                @forelse ($data['items'] as $i => $item)
                                <tr class="{{ $i % 2 === 1 ? 'bg-zinc-50 dark:bg-zinc-800/50' : 'bg-white dark:bg-zinc-900' }} border-t border-zinc-300 dark:border-zinc-700">
                                    <td class="p-2 text-left text-xs border-r border-zinc-300 dark:border-zinc-700 font-medium">
                                        @if($item['kode_ukuran'] === 'JOINT-NOT-FOUND' || !($item['has_target'] ?? false))
                                        <span class="text-red-500">{{ $item['ukuran'] }} ⚠</span>
                                        @else
                                        {{ $item['ukuran'] }}
                                        @endif
                                    </td>
                                    <td class="p-2 text-center text-xs border-r border-zinc-300 dark:border-zinc-700 uppercase">
                                        {{ $item['jenis_kayu'] }}
                                    </td>
                                    <td class="p-2 text-center text-xs border-r border-zinc-300 dark:border-zinc-700 uppercase">
                                        {{ $item['kw'] }}
                                    </td>
                                    <td class="p-2 text-right text-xs border-r border-zinc-300 dark:border-zinc-700 font-bold text-green-600 dark:text-green-400">
                                        {{ number_format($item['hasil']) }}
                                    </td>
                                    <td class="p-2 text-right text-xs border-r border-zinc-300 dark:border-zinc-700 text-zinc-500">
                                        @if($item['has_target'] ?? false)
                                        {{ number_format($item['target']) }}
                                        @if(isset($item['target_normal']))
                                        <span class="block text-[10px] text-zinc-600">(normal: {{ number_format($item['target_normal']) }})</span>
                                        @endif
                                        @else
                                        -
                                        @endif
                                    </td>
                                    <td class="p-2 text-right text-xs font-bold {{ !($item['has_target'] ?? false) ? 'text-red-500' : (($item['capaian_persen'] ?? 0) >= 100 ? 'text-green-600 dark:text-green-400' : 'text-amber-600 dark:text-amber-400') }}">
                                        @if(!($item['has_target'] ?? false))
                                        Target ?
                                        @else
                                        {{ number_format($item['capaian_persen'], 1, ',', '.') }}%
                                        @endif
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="6" class="p-4 text-center text-zinc-500 dark:text-zinc-400 text-sm italic">
                                        Tidak ada barang dikerjakan.
                                    </td>
                                </tr>
                                @endforelse
                            </tbody>

                            <tfoot class="bg-zinc-100 dark:bg-zinc-800 border-t-2 border-zinc-300 dark:border-zinc-600">
                                <tr>
                                    <td colspan="6" class="p-2 text-center text-[11px] text-zinc-500 dark:text-zinc-400 border-t border-zinc-300 dark:border-zinc-700">
                                        Capaian GLOBAL tim (jumlah persen semua ukuran hari ini, basis: target ADJUSTED ke total jam kerja tim):
                                        <strong class="{{ $tercapai ? 'text-green-600 dark:text-green-400' : 'text-red-500' }}">
                                            {{ number_format($data['capaian_global_persen'], 1, ',', '.') }}%
                                        </strong>
                                    </td>
                                </tr>
                                <tr>
                                    <td colspan="6" class="px-3 py-2 text-center border-t border-zinc-300 dark:border-zinc-700">
                                        <span class="text-xs font-semibold {{ $data['potongan_total_tim'] > 0 ? 'text-red-500 dark:text-red-400' : 'text-zinc-500 dark:text-zinc-400' }}">
                                            Potongan: Rp {{ number_format($data['potongan_total_tim']) }} / tim
                                            @if($data['jumlah_pekerja'] > 0)
                                            ÷ {{ $data['jumlah_pekerja'] }} orang
                                            ≈ <strong>Rp {{ number_format($rataRataPerOrang) }}/orang</strong>
                                            @endif
                                        </span>
                                        @if($data['potongan_melebihi_gaji'])
                                        <span class="ml-2 px-2 py-0.5 rounded bg-yellow-600 text-white text-[10px] font-bold">
                                            ⚠ MELEBIHI GAJI NORMAL TIM (Rp {{ number_format($data['total_gaji_tim']) }})
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
        <div class="text-center p-12 bg-white dark:bg-zinc-900 rounded-lg border border-dashed border-zinc-300 dark:border-zinc-700">
            <x-heroicon-o-document-magnifying-glass class="w-12 h-12 mx-auto text-zinc-400 mb-4" />
            <p class="text-lg text-zinc-500 dark:text-zinc-400">
                Tidak ditemukan data produksi joint untuk tanggal ini.
            </p>
            <p class="text-sm text-zinc-400 mt-2">
                Silakan pilih tanggal lain atau periksa data di sistem.
            </p>
        </div>
        @endforelse
    </div>
</x-filament-panels::page>  