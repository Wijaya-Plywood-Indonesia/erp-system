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
            <span class="text-lg font-medium text-zinc-700 dark:text-zinc-300">Memuat data sanding join...</span>
        </div>
    </div>
    @endif

    @php
    $dataProduksi = $dataProduksi ?? [];
    // Data sudah diproses melalui Transformer SandingJoinDataMap
    $groupedData = collect($dataProduksi)->values();
    @endphp

    <div class="space-y-12 mt-6">
        @forelse ($groupedData as $data)
        @php
        $totalPekerja = count($data['pekerja']);

        // LOGIKA WARNA: Merah jika hasil < target, Hijau jika hasil>= target
            $isMencapaiTarget = $data['hasil'] >= $data['target'];
            $warnaStatus = $isMencapaiTarget ? 'text-green-400 font-bold' : 'text-red-400 font-bold';
            $tanda = $data['selisih'] >= 0 ? '+' : '';
            @endphp

            <div class="bg-white dark:bg-zinc-900 rounded-sm shadow-lg border border-zinc-200 dark:border-zinc-700 overflow-hidden">
                {{-- Header Blok Produksi Sanding Joint --}}
                <div class="bg-zinc-800 p-4 text-white flex justify-between items-center">
                    <h2 class="text-lg font-bold">
                        @if($data['kode_ukuran'] === 'SANDING-NOT-FOUND')
                        <span class="text-red-400">{{ $data["ukuran"] }} (Target Tidak Ditemukan)</span>
                        @else
                        {{ strtoupper($data["kode_ukuran"]) }}
                        @endif
                    </h2>
                    <div class="flex gap-4 items-center">
                        <span class="text-xs bg-zinc-700 px-2 py-1 rounded">{{ $data['jenis_kayu'] }}</span>
                        <span class="text-xs bg-primary-600 px-2 py-1 rounded font-bold">KW {{ $data['kw'] }}</span>
                    </div>
                </div>

                <div class="p-4">
                    <div class="w-full overflow-x-auto">
                        <div class="min-w-[800px]">
                            <table class="w-full text-sm border-collapse border border-zinc-300 dark:border-zinc-600">
                                <thead>
                                    <tr>
                                        <th colspan="7" class="p-4 text-xl font-bold text-center bg-zinc-700 text-white uppercase">
                                            Data Pekerja Sanding Joint
                                        </th>
                                    </tr>
                                    <tr class="bg-zinc-200 dark:bg-zinc-700 text-zinc-800 dark:text-zinc-300 border-t border-zinc-300 dark:border-zinc-600">
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
                                    @php
                                    $potTarget = (int)($p['pot_target'] ?? 0);
                                    @endphp
                                    <tr class="{{ $i % 2 === 1 ? 'bg-zinc-50 dark:bg-zinc-800/50' : 'bg-white dark:bg-zinc-900' }} border-t border-zinc-300 dark:border-zinc-700 hover:bg-zinc-100 dark:hover:bg-zinc-800/80 transition-colors">
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
                                        <td class="p-2 text-right text-xs border-r border-zinc-300 dark:border-zinc-700 font-bold {{ $potTarget > 0 ? 'text-red-400' : '' }}">
                                            {{ $potTarget > 0 ? number_format($potTarget) : '-' }}
                                        </td>
                                        <td class="p-2 text-left text-xs italic text-zinc-500">
                                            {{ $p["keterangan"] ?? "-" }}
                                        </td>
                                    </tr>
                                    @empty
                                    <tr>
                                        <td colspan="7" class="p-4 text-center text-zinc-500 dark:text-zinc-400 text-sm italic">
                                            Tidak ada data pekerja untuk area ini.
                                        </td>
                                    </tr>
                                    @endforelse
                                </tbody>

                                <tfoot class="bg-zinc-100 dark:bg-zinc-800 border-t-2 border-zinc-300 dark:border-zinc-600">
                                    <tr>
                                        <td colspan="7" class="p-3 text-center text-xs text-zinc-600 dark:text-zinc-400 space-x-3">
                                            <span class="font-medium">Total Pegawai:</span>
                                            <strong class="text-zinc-900 dark:text-white">{{ $totalPekerja }}</strong>

                                            <span class="text-zinc-400">|</span>

                                            <span class="font-medium">Target:</span>
                                            <strong class="font-mono text-zinc-900 dark:text-white">{{ number_format($data["target"]) }}</strong>

                                            <span class="text-zinc-400">|</span>

                                            <span class="font-medium">Hasil Akhir:</span>
                                            <strong class="font-mono {{ $warnaStatus }}">{{ number_format($data["hasil"]) }}</strong>

                                            <span class="text-zinc-400">|</span>

                                            <span class="font-medium">Selisih:</span>
                                            <strong class="font-mono {{ $warnaStatus }}">
                                                {{ $tanda }}{{ number_format(abs($data["selisih"])) }}
                                            </strong>

                                            <span class="text-zinc-400">|</span>

                                            <span class="text-xs">Update: {{ $data["tanggal"] }}</span>
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
                <p class="text-lg text-zinc-500 dark:text-zinc-400 font-medium">
                    Tidak ditemukan data produksi sanding joint untuk tanggal ini.
                </p>
                <p class="text-sm text-zinc-400 mt-2">
                    Silakan pilih tanggal lain atau pastikan input produksi sudah dilakukan.
                </p>
            </div>
            @endforelse
    </div>
</x-filament-panels::page>