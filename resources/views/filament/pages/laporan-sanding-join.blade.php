<x-filament-panels::page>
    {{-- Form Filter Tanggal --}}
    <div class="p-4 bg-white dark:bg-zinc-900 rounded-lg shadow border border-zinc-200 dark:border-zinc-800">
        {{ $this->form }}
    </div>

    {{-- Loading Indicator --}}
    @if($isLoading)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-white/75 dark:bg-zinc-900/75">
        <div class="flex items-center space-x-3">
            <x-filament::loading-indicator class="w-8 h-8 text-sky-500" />
            <span class="text-lg font-medium text-zinc-700 dark:text-zinc-300">Memuat data sanding joint...</span>
        </div>
    </div>
    @endif

    @php
    $dataProduksi = $dataProduksi ?? [];
    $dataPekerja = $dataPekerja ?? [];
    $groupedData = collect($dataProduksi)->values();
    $totalPekerja = count($dataPekerja);

    // Ambil capaian global & potongan dari item pertama
    $firstItem = $groupedData->first();
    $capaianGlobal = $firstItem['rata2_capaian_tim'] ?? null;
    $potonganTotalTim = $firstItem['potongan_total_tim'] ?? 0;
    $potonganMelebihiGaji = $firstItem['potongan_melebihi_gaji'] ?? false;
    $totalGajiTim = $firstItem['total_gaji_tim'] ?? 0;
    $isSuccessGlobal = ($capaianGlobal !== null) ? ($capaianGlobal >= 100) : false;
    @endphp

    <div class="space-y-6 mt-6">
        @if($groupedData->isEmpty())
        <div class="text-center p-12 bg-white dark:bg-zinc-900 rounded-lg border border-dashed border-zinc-300 dark:border-zinc-700">
            <x-heroicon-o-document-magnifying-glass class="w-12 h-12 mx-auto text-zinc-400 mb-4" />
            <p class="text-lg text-zinc-500 dark:text-zinc-400 font-medium">
                Tidak ditemukan data produksi sanding joint untuk tanggal ini.
            </p>
            <p class="text-sm text-zinc-400 mt-2">
                Silakan pilih tanggal lain atau pastikan input produksi sudah dilakukan.
            </p>
        </div>
        @else

        {{-- Wrapper Kalimat Informasi Capaian Global (Success / Danger) --}}
        @if($capaianGlobal !== null)
        <div class="p-3.5 rounded-lg border text-sm flex items-center justify-between transition-colors shadow-sm {{ $isSuccessGlobal ? 'bg-emerald-50 border-emerald-300 text-emerald-800 dark:bg-emerald-950/40 dark:border-emerald-500/30 dark:text-emerald-300' : 'bg-red-50 border-red-300 text-red-800 dark:bg-red-950/40 dark:border-red-500/30 dark:text-red-300' }}">
            <div class="flex items-center gap-2 flex-wrap">
                <span class="w-2.5 h-2.5 rounded-full {{ $isSuccessGlobal ? 'bg-emerald-500' : 'bg-red-500' }}"></span>
                <span>
                    Capaian GLOBAL tim (jumlah persen semua ukuran hari ini):
                    <strong class="font-bold text-base {{ $isSuccessGlobal ? 'text-emerald-700 dark:text-emerald-400' : 'text-red-700 dark:text-red-400' }}">
                        {{ number_format($capaianGlobal, 1, ',', '.') }}%
                    </strong>
                </span>
                <span class="opacity-40">|</span>
                <span>
                    Potongan total tim: <strong class="font-semibold">Rp {{ number_format($potonganTotalTim, 0, ',', '.') }}</strong>
                </span>
                @if($potonganMelebihiGaji)
                <span class="px-2 py-0.5 rounded bg-amber-500 text-white text-xs font-bold shadow-sm">
                    ⚠ Potongan Melebihi Total Gaji Tim (Rp {{ number_format($totalGajiTim, 0, ',', '.') }})
                </span>
                @endif
            </div>
            <span class="text-xs uppercase font-bold tracking-wider px-2 py-0.5 rounded {{ $isSuccessGlobal ? 'bg-emerald-200/80 text-emerald-900 dark:bg-emerald-500/20 dark:text-emerald-300' : 'bg-red-200/80 text-red-900 dark:bg-red-500/20 dark:text-red-300' }}">
                {{ $isSuccessGlobal ? 'Target Tercapai' : 'Kurang Target' }}
            </span>
        </div>
        @endif

        {{-- Looping Card Per Ukuran Mesin --}}
        @foreach ($groupedData as $data)
        @php
        $hasil = $data['hasil'] ?? 0;
        $target = $data['target'] ?? $data['target_adjusted'] ?? 0;
        $selisih = $data['selisih'] ?? ($hasil - $target);
        $warnaStatus = $selisih >= 0 ? 'text-emerald-600 dark:text-emerald-400 font-bold' : 'text-red-600 dark:text-red-400 font-bold';
        $tanda = $selisih >= 0 ? '+' : '';
        $jamKerja = $data['jam_standar'] ?? $data['jam_aktual'] ?? 9.0;
        $persenUkuran = ($target > 0) ? ($hasil / $target) * 100 : null;
        $pekerjaList = !empty($data['pekerja']) ? $data['pekerja'] : $dataPekerja;
        $isUkuranUnknown = ($data['kode_ukuran'] === 'SANDING-JOINT-NOT-FOUND') || !($data['has_target'] ?? true);
        @endphp

        <div class="bg-white dark:bg-zinc-900 rounded-sm shadow border border-zinc-200 dark:border-zinc-700 overflow-hidden">
            {{-- Header Card --}}
            <div class="bg-zinc-800 p-4 text-white flex justify-between items-center">
                <h2 class="text-base font-bold">
                    @if($isUkuranUnknown)
                    <span class="text-red-400">SANDING JOINT ({{ $data["ukuran"] }}) - Ukuran tidak dikenal</span>
                    @else
                    {{ strtoupper($data["kode_ukuran"]) }}
                    @endif
                </h2>
                <div class="flex gap-2 items-center text-xs">
                    @if($persenUkuran !== null)
                    <span class="px-2 py-0.5 rounded font-bold {{ $persenUkuran >= 100 ? 'bg-emerald-600 text-white' : 'bg-red-600 text-white' }}">
                        Capaian: {{ number_format($persenUkuran, 1, ',', '.') }}%
                    </span>
                    @endif
                    <span class="bg-zinc-700 px-2 py-0.5 rounded font-medium">{{ $data['jenis_kayu'] ?? '-' }}</span>
                    <span class="bg-sky-600 text-white px-2 py-0.5 rounded font-bold">KW {{ $data['kw'] ?? '-' }}</span>
                </div>
            </div>

            <div class="p-4">
                <div class="w-full overflow-x-auto">
                    <div class="min-w-[800px]">
                        <table class="w-full text-sm border-collapse border border-zinc-300 dark:border-zinc-600">
                            <thead>
                                <tr>
                                    <th colspan="7" class="p-3 text-lg font-bold text-center bg-zinc-700 text-white uppercase tracking-wider">
                                        DATA PEKERJA SANDING JOINT
                                    </th>
                                </tr>
                                <tr class="bg-zinc-200 dark:bg-zinc-700 text-zinc-800 dark:text-zinc-300 border-t border-zinc-300 dark:border-zinc-600 text-xs">
                                    <th class="p-2 text-center w-16">ID</th>
                                    <th class="p-2 text-left w-40">Nama</th>
                                    <th class="p-2 text-center w-20">Masuk</th>
                                    <th class="p-2 text-center w-20">Pulang</th>
                                    <th class="p-2 text-center w-16">Ijin</th>
                                    <th class="p-2 text-right w-36">Potongan Target</th>
                                    <th class="p-2 text-left">Keterangan</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-300 dark:divide-zinc-700">
                                @forelse ($pekerjaList as $i => $p)
                                @php $potTarget = (int) ($p['pot_target'] ?? 0); @endphp
                                <tr class="{{ $i % 2 === 1 ? 'bg-zinc-50 dark:bg-zinc-800/50' : 'bg-white dark:bg-zinc-900' }}">
                                    <td class="p-2 text-center text-xs border-r border-zinc-300 dark:border-zinc-700 font-mono text-zinc-600 dark:text-zinc-400">{{ $p["id"] ?? "-" }}</td>
                                    <td class="p-2 text-left text-xs border-r border-zinc-300 dark:border-zinc-700 font-medium text-zinc-900 dark:text-zinc-100">{{ $p["nama"] ?? "-" }}</td>
                                    <td class="p-2 text-center text-xs border-r border-zinc-300 dark:border-zinc-700 text-zinc-700 dark:text-zinc-300">{{ $p["jam_masuk"] ?? "-" }}</td>
                                    <td class="p-2 text-center text-xs border-r border-zinc-300 dark:border-zinc-700 text-zinc-700 dark:text-zinc-300">{{ $p["jam_pulang"] ?? "-" }}</td>
                                    <td class="p-2 text-center text-xs border-r border-zinc-300 dark:border-zinc-700 text-amber-500 font-medium">{{ $p["ijin"] ?? "-" }}</td>
                                    <td class="p-2 text-right text-xs border-r border-zinc-300 dark:border-zinc-700 font-bold font-mono {{ $potTarget > 0 ? 'text-red-600 dark:text-red-400' : 'text-zinc-600 dark:text-zinc-400' }}">
                                        {{ $potTarget > 0 ? 'Rp ' . number_format($potTarget, 0, ',', '.') : '-' }}
                                    </td>
                                    <td class="p-2 text-left text-xs text-zinc-700 dark:text-zinc-300">{{ $p["keterangan"] ?? "-" }}</td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="7" class="p-4 text-center text-zinc-500 dark:text-zinc-400 text-xs">
                                        Tidak ada data pekerja untuk ukuran ini.
                                    </td>
                                </tr>
                                @endforelse
                            </tbody>
                            <tfoot class="bg-zinc-100 dark:bg-zinc-800 border-t-2 border-zinc-300 dark:border-zinc-600">
                                <tr>
                                    <td colspan="7" class="p-3 text-center text-xs text-zinc-600 dark:text-zinc-400 space-x-3">
                                        <span class="font-medium">Pekerja:</span>
                                        <strong class="text-zinc-900 dark:text-zinc-100">{{ count($pekerjaList) }}</strong>

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
                                        <strong class="font-mono {{ $warnaStatus }}">{{ $tanda }}{{ number_format($selisih, 0, ',', '.') }}</strong>

                                        <span class="text-zinc-400">|</span>

                                        <span class="text-xs">Tanggal: {{ $data['tanggal'] ?? '-' }}</span>
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        @endforeach
        @endif
    </div>
</x-filament-panels::page>