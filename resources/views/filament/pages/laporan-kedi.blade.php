<x-filament-panels::page>
    {{-- Form Filter Tanggal --}}
    <div class="p-4 bg-white dark:bg-zinc-900 rounded-lg shadow border border-zinc-200 dark:border-zinc-800">
        {{ $this->form }}
    </div>

    {{-- Loading Indicator --}}
    @if ($isLoading ?? false)
        <div
            class="fixed inset-0 z-50 flex items-center justify-center bg-white bg-opacity-75 dark:bg-zinc-900 dark:bg-opacity-75">
            <div class="flex items-center space-x-3">
                <x-filament::loading-indicator class="w-8 h-8 text-primary-600" />
                <span class="text-lg font-medium text-zinc-700 dark:text-zinc-300">Memuat data produksi kedi...</span>
            </div>
        </div>
    @endif

    {{-- Ringkasan potongan seluruh tanggal (sama dengan Excel) --}}
    @if (!empty($potonganGroups))
        @php
            $totalOrangSemua = collect($potonganGroups)->sum(fn($g) => count($g['items']));
            $rataRataPerOrang = $totalOrangSemua > 0 ? $totalPotonganSemua / $totalOrangSemua : 0;
            $orangKenaPotongan = collect($potonganGroups)
                ->flatMap(fn($g) => $g['items'])
                ->filter(fn($p) => (int) ($p['potongan_targ'] ?? 0) > 0)
                ->count();
        @endphp
        <div
            class="mt-6 p-4 rounded-lg border {{ $totalPotonganSemua > 0 ? 'bg-red-50 dark:bg-red-950/20 border-red-200 dark:border-red-900' : 'bg-zinc-50 dark:bg-zinc-800/50 border-zinc-200 dark:border-zinc-700' }} flex items-center justify-between flex-wrap gap-4">
            <div class="flex flex-col">
                <span
                    class="text-sm font-semibold uppercase tracking-wide {{ $totalPotonganSemua > 0 ? 'text-red-600 dark:text-red-400' : 'text-zinc-500' }}">
                    Rekap Potongan — {{ $tanggal }}
                </span>
                <span class="text-xs text-zinc-500 dark:text-zinc-400 normal-case">
                    {{ $orangKenaPotongan }} dari {{ $totalOrangSemua }} pekerja kena potongan. Rincian per nama ada di
                    tabel bawah.
                </span>
            </div>
            <div class="flex gap-6 items-end">
                <div class="text-right">
                    <div class="text-xs text-zinc-500 dark:text-zinc-400 uppercase font-semibold">Rata-rata / orang
                    </div>
                    <div
                        class="text-xl font-black {{ $totalPotonganSemua > 0 ? 'text-red-600 dark:text-red-400' : 'text-zinc-500' }}">
                        Rp {{ number_format($rataRataPerOrang) }}
                    </div>
                </div>
                <div class="text-right">
                    <div class="text-xs text-zinc-500 dark:text-zinc-400 uppercase font-semibold">Total gabungan</div>
                    <div class="text-sm font-semibold text-zinc-600 dark:text-zinc-400">
                        Rp {{ number_format($totalPotonganSemua) }}
                    </div>
                </div>
            </div>
        </div>
    @endif

    <div class="space-y-12 mt-6">
        @forelse($dataKedi as $data)
            <div
                class="bg-white dark:bg-zinc-900 rounded-sm shadow-lg border border-zinc-200 dark:border-zinc-700 overflow-hidden">

                {{-- HEADER --}}
                <div class="bg-zinc-800 p-4 text-white flex justify-between items-center flex-wrap gap-2">
                    <h2 class="text-lg font-bold">
                        LAPORAN PRODUKSI KEDI - {{ $data['tanggal_masuk'] }}
                        <span class="text-xs font-normal text-zinc-400 mx-1">s/d</span>
                        {{ $data['tanggal_keluar'] }}
                    </h2>
                    <div class="flex gap-2 items-center flex-wrap">
                        @if (!is_null($data['total_palet'] ?? null))
                            <span class="text-xs px-2 py-1 rounded bg-zinc-700 font-semibold uppercase">
                                Total Palet: {{ $data['total_palet'] }}
                            </span>
                        @endif
                        <span class="text-xs px-2 py-1 rounded bg-zinc-700 font-semibold uppercase">
                            Total Pekerja: {{ $data['total_pekerja'] }}
                        </span>
                        <span class="text-xs px-2 py-1 rounded bg-blue-700 font-semibold uppercase">
                            Status: {{ $data['status'] }}
                        </span>
                    </div>
                </div>

                {{-- VALIDASI --}}
                <div
                    class="px-4 py-2 bg-zinc-100 dark:bg-zinc-800 text-xs text-zinc-600 dark:text-zinc-400 border-b border-zinc-300 dark:border-zinc-700">
                    <span class="font-medium">Validasi terakhir:</span>
                    <strong class="text-green-600 dark:text-green-400">{{ $data['validasi_terakhir'] }}</strong>
                    <span class="text-zinc-400">({{ $data['validasi_oleh'] }})</span>
                </div>

                <div class="p-4 space-y-6">

                    {{-- ================= DETAIL MASUK ================= --}}
                    @if (!empty($data['detail_masuk']))
                        <div class="w-full overflow-x-auto">
                            <div class="min-w-[800px]">
                                <table
                                    class="w-full text-sm border-collapse border border-zinc-300 dark:border-zinc-600">
                                    <thead>
                                        <tr>
                                            <th colspan="7"
                                                class="p-4 text-xl font-bold text-center bg-amber-700 text-white uppercase tracking-wider">
                                                Detail Masuk
                                            </th>
                                        </tr>
                                        <tr
                                            class="bg-zinc-200 dark:bg-zinc-700 text-zinc-800 dark:text-zinc-300 border-t border-zinc-300 dark:border-zinc-600">
                                            <th class="p-2 text-left text-xs font-semibold uppercase">No Palet</th>
                                            <th class="p-2 text-center text-xs font-semibold uppercase">Mesin</th>
                                            <th class="p-2 text-center text-xs font-semibold uppercase">Ukuran</th>
                                            <th class="p-2 text-center text-xs font-semibold uppercase">Jenis Kayu</th>
                                            <th class="p-2 text-center text-xs font-semibold uppercase">KW</th>
                                            <th class="p-2 text-right text-xs font-semibold uppercase">Jumlah</th>
                                            <th class="p-2 text-center text-xs font-semibold uppercase">Rencana Bongkar
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($data['detail_masuk'] as $i => $row)
                                            <tr
                                                class="{{ $i % 2 === 1 ? 'bg-zinc-50 dark:bg-zinc-800/50' : 'bg-white dark:bg-zinc-900' }} border-t border-zinc-300 dark:border-zinc-700 hover:bg-zinc-100 dark:hover:bg-zinc-800/80 transition-colors">
                                                <td
                                                    class="p-2 text-left text-xs border-r border-zinc-300 dark:border-zinc-700 font-medium">
                                                    {{ $row['no_palet'] }}</td>
                                                <td
                                                    class="p-2 text-center text-xs border-r border-zinc-300 dark:border-zinc-700 uppercase">
                                                    {{ $row['mesin'] }}</td>
                                                <td
                                                    class="p-2 text-center text-xs border-r border-zinc-300 dark:border-zinc-700">
                                                    {{ $row['ukuran'] }}</td>
                                                <td
                                                    class="p-2 text-center text-xs border-r border-zinc-300 dark:border-zinc-700 uppercase">
                                                    {{ $row['jenis_kayu'] }}</td>
                                                <td
                                                    class="p-2 text-center text-xs border-r border-zinc-300 dark:border-zinc-700 uppercase">
                                                    {{ $row['kw'] }}</td>
                                                <td
                                                    class="p-2 text-right text-xs border-r border-zinc-300 dark:border-zinc-700 font-bold text-green-600 dark:text-green-400">
                                                    {{ $row['jumlah'] }}</td>
                                                <td class="p-2 text-center text-xs text-zinc-500">
                                                    {{ $row['rencana_bongkar'] }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endif

                    {{-- ================= DETAIL BONGKAR ================= --}}
                    @if (!empty($data['detail_bongkar']))
                        <div class="w-full overflow-x-auto">
                            <div class="min-w-[800px]">
                                <table
                                    class="w-full text-sm border-collapse border border-zinc-300 dark:border-zinc-600">
                                    <thead>
                                        <tr>
                                            <th colspan="6"
                                                class="p-4 text-xl font-bold text-center bg-blue-700 text-white uppercase tracking-wider">
                                                Detail Bongkar
                                                @if (!is_null($data['total_palet'] ?? null))
                                                    <span
                                                        class="block text-xs font-normal normal-case text-blue-100 mt-1">
                                                        Target dihitung per palet — total {{ $data['total_palet'] }}
                                                        palet
                                                    </span>
                                                @endif
                                            </th>
                                        </tr>
                                        <tr
                                            class="bg-zinc-200 dark:bg-zinc-700 text-zinc-800 dark:text-zinc-300 border-t border-zinc-300 dark:border-zinc-600">
                                            <th class="p-2 text-left text-xs font-semibold uppercase">No Palet</th>
                                            <th class="p-2 text-center text-xs font-semibold uppercase">Mesin</th>
                                            <th class="p-2 text-center text-xs font-semibold uppercase">Ukuran</th>
                                            <th class="p-2 text-center text-xs font-semibold uppercase">Jenis Kayu</th>
                                            <th class="p-2 text-center text-xs font-semibold uppercase">KW</th>
                                            <th class="p-2 text-right text-xs font-semibold uppercase">Jumlah</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($data['detail_bongkar'] as $i => $row)
                                            <tr
                                                class="{{ $i % 2 === 1 ? 'bg-zinc-50 dark:bg-zinc-800/50' : 'bg-white dark:bg-zinc-900' }} border-t border-zinc-300 dark:border-zinc-700 hover:bg-zinc-100 dark:hover:bg-zinc-800/80 transition-colors">
                                                <td
                                                    class="p-2 text-left text-xs border-r border-zinc-300 dark:border-zinc-700 font-medium">
                                                    {{ $row['no_palet'] }}</td>
                                                <td
                                                    class="p-2 text-center text-xs border-r border-zinc-300 dark:border-zinc-700 uppercase">
                                                    {{ $row['mesin'] }}</td>
                                                <td
                                                    class="p-2 text-center text-xs border-r border-zinc-300 dark:border-zinc-700">
                                                    {{ $row['ukuran'] }}</td>
                                                <td
                                                    class="p-2 text-center text-xs border-r border-zinc-300 dark:border-zinc-700 uppercase">
                                                    {{ $row['jenis_kayu'] }}</td>
                                                <td
                                                    class="p-2 text-center text-xs border-r border-zinc-300 dark:border-zinc-700 uppercase">
                                                    {{ $row['kw'] }}</td>
                                                <td
                                                    class="p-2 text-right text-xs font-bold text-green-600 dark:text-green-400">
                                                    {{ $row['jumlah'] }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endif

                    {{-- ================= DOWNTIME & KENDALA MESIN ================= --}}
                    @if (!empty($data['kendala_kedis']))
                        <div class="w-full overflow-x-auto">
                            <div class="min-w-[800px]">
                                <table
                                    class="w-full text-sm border-collapse border border-zinc-300 dark:border-zinc-600">
                                    <thead>
                                        <tr>
                                            <th colspan="7"
                                                class="p-4 text-xl font-bold text-center bg-red-700 text-white uppercase tracking-wider">
                                                Downtime & Kendala Mesin
                                            </th>
                                        </tr>
                                        <tr
                                            class="bg-zinc-200 dark:bg-zinc-700 text-zinc-800 dark:text-zinc-300 border-t border-zinc-300 dark:border-zinc-600">
                                            <th class="p-2 text-center text-xs font-semibold w-10 uppercase">No</th>
                                            <th class="p-2 text-center text-xs font-semibold uppercase">Tanggal</th>
                                            <th class="p-2 text-center text-xs font-semibold uppercase">Mesin</th>
                                            <th class="p-2 text-center text-xs font-semibold uppercase">Mulai</th>
                                            <th class="p-2 text-center text-xs font-semibold uppercase">Selesai</th>
                                            <th class="p-2 text-center text-xs font-semibold uppercase">Durasi</th>
                                            <th class="p-2 text-left text-xs font-semibold uppercase">Keterangan Kendala
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($data['kendala_kedis'] as $index => $row)
                                            <tr
                                                class="{{ $index % 2 === 1 ? 'bg-zinc-50 dark:bg-zinc-800/50' : 'bg-white dark:bg-zinc-900' }} border-t border-zinc-300 dark:border-zinc-700 hover:bg-zinc-100 dark:hover:bg-zinc-800/80 transition-colors">
                                                <td
                                                    class="p-2 text-center text-xs border-r border-zinc-300 dark:border-zinc-700">
                                                    {{ $index + 1 }}</td>
                                                <td
                                                    class="p-2 text-center text-xs border-r border-zinc-300 dark:border-zinc-700">
                                                    {{ $row['tanggal'] }}</td>
                                                <td
                                                    class="p-2 text-center text-xs border-r border-zinc-300 dark:border-zinc-700 uppercase">
                                                    {{ $row['mesin'] }}</td>
                                                <td
                                                    class="p-2 text-center text-xs border-r border-zinc-300 dark:border-zinc-700 font-mono">
                                                    {{ $row['waktu_mulai'] }}</td>
                                                <td
                                                    class="p-2 text-center text-xs border-r border-zinc-300 dark:border-zinc-700 font-mono">
                                                    {{ $row['waktu_selesai'] }}</td>
                                                <td
                                                    class="p-2 text-center text-xs border-r border-zinc-300 dark:border-zinc-700 font-semibold text-amber-600 dark:text-amber-400">
                                                    {{ $row['durasi_menit'] ? $row['durasi_menit'] . ' menit' : '-' }}
                                                </td>
                                                <td
                                                    class="p-2 text-left text-xs font-medium text-red-600 dark:text-red-400">
                                                    {{ $row['kendala'] }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endif

                </div>
            </div>

        @empty
            <div
                class="text-center p-12 bg-white dark:bg-zinc-900 rounded-lg border border-dashed border-zinc-300 dark:border-zinc-700">
                <x-heroicon-o-document-magnifying-glass class="w-12 h-12 mx-auto text-zinc-400 mb-4" />
                <p class="text-lg text-zinc-500 dark:text-zinc-400 font-medium">
                    Belum ada data Produksi Kedi untuk tanggal ini.
                </p>
                <p class="text-sm text-zinc-400 mt-2">
                    Silakan pilih tanggal lain atau pastikan input produksi kedi sudah dilakukan.
                </p>
            </div>
        @endforelse
    </div>

    {{-- ================= REKAP POTONGAN TARGET (GABUNGAN PER TANGGAL) ================= --}}
    {{-- Dihitung sekali untuk seluruh tanggal, sama persis dengan sheet "Potongan" di Excel export --}}
    @if (!empty($potonganGroups))
        <div class="mt-10 space-y-8">
            <h2 class="text-xl font-bold text-zinc-800 dark:text-zinc-100">
                Rincian Potongan Target — {{ $tanggal }}
            </h2>

            @foreach ($potonganGroups as $group)
                @php
                    $sum = $group['summary'];
                    $groupTotal = (int) ($group['total'] ?? 0);
                    $groupOrang = count($group['items']);
                    $groupRata = $groupOrang > 0 ? $groupTotal / $groupOrang : 0;
                    $adaPot = $groupTotal > 0;
                @endphp

                <div
                    class="bg-white dark:bg-zinc-900 rounded-sm shadow-lg border border-zinc-200 dark:border-zinc-700 overflow-hidden">
                    <div class="p-4 text-xl font-bold text-center bg-zinc-700 text-white uppercase tracking-wider">
                        {{ $group['label'] }}
                    </div>

                    @if ($sum)
                        <div
                            class="px-4 py-2 bg-orange-50 dark:bg-orange-950/20 text-xs text-zinc-700 dark:text-zinc-300 border-b border-zinc-200 dark:border-zinc-700">
                            Hasil Aktual: <strong>{{ number_format($sum['hasil']) }} {{ $sum['satuan'] }}</strong>
                            &nbsp;|&nbsp; Target:
                            <strong>{{ $sum['target'] !== null ? number_format($sum['target']) : '-' }}
                                {{ $sum['satuan'] }}</strong>
                            &nbsp;|&nbsp; Selisih:
                            <strong>{{ $sum['selisih'] !== null ? ($sum['selisih'] >= 0 ? '+' : '') . number_format($sum['selisih']) : '-' }}
                                {{ $sum['satuan'] }}</strong>
                        </div>
                        <div
                            class="px-4 py-2 bg-blue-50 dark:bg-blue-950/20 text-xs text-zinc-700 dark:text-zinc-300 border-b border-zinc-200 dark:border-zinc-700">
                            Jam Target (normal):
                            <strong>{{ $sum['jam_normal'] !== null ? number_format($sum['jam_normal'], 1) . ' jam' : '-' }}</strong>
                            &nbsp;|&nbsp; Jam Aktual Total:
                            <strong>{{ number_format($sum['jam_aktual_total'] ?? 0, 1) }} jam</strong>
                            &nbsp;|&nbsp; Rata-rata:
                            <strong>{{ number_format($sum['jam_aktual_rata'] ?? 0, 1) }} jam/orang</strong>
                        </div>
                    @endif

                    <div class="w-full overflow-x-auto p-4">
                        <div class="min-w-[800px]">
                            <table class="w-full text-sm border-collapse border border-zinc-300 dark:border-zinc-600">
                                <thead>
                                    <tr class="bg-zinc-200 dark:bg-zinc-700 text-zinc-800 dark:text-zinc-300">
                                        <th class="p-2 text-left text-xs font-semibold uppercase">Kode</th>
                                        <th class="p-2 text-left text-xs font-semibold uppercase">Nama</th>
                                        <th class="p-2 text-center text-xs font-semibold uppercase">Masuk</th>
                                        <th class="p-2 text-center text-xs font-semibold uppercase">Pulang</th>
                                        <th class="p-2 text-center text-xs font-semibold uppercase">Ijin</th>
                                        <th class="p-2 text-right text-xs font-semibold uppercase">Potongan</th>
                                        <th class="p-2 text-left text-xs font-semibold uppercase">Keterangan</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($group['items'] as $i => $p)
                                        @php $adaPotBaris = (int) ($p['potongan_targ'] ?? 0) > 0; @endphp
                                        <tr
                                            class="{{ $i % 2 === 1 ? 'bg-zinc-50 dark:bg-zinc-800/50' : 'bg-white dark:bg-zinc-900' }} border-t border-zinc-300 dark:border-zinc-700">
                                            <td
                                                class="p-2 text-left text-xs border-r border-zinc-300 dark:border-zinc-700 text-zinc-500">
                                                {{ $p['kodep'] }}</td>
                                            <td
                                                class="p-2 text-left text-xs border-r border-zinc-300 dark:border-zinc-700 font-medium uppercase">
                                                {{ $p['nama'] }}</td>
                                            <td
                                                class="p-2 text-center text-xs border-r border-zinc-300 dark:border-zinc-700 font-mono">
                                                {{ $p['masuk'] ?: '-' }}</td>
                                            <td
                                                class="p-2 text-center text-xs border-r border-zinc-300 dark:border-zinc-700 font-mono">
                                                {{ $p['pulang'] ?: '-' }}</td>
                                            <td
                                                class="p-2 text-center text-xs border-r border-zinc-300 dark:border-zinc-700 text-yellow-600 dark:text-yellow-400">
                                                {{ $p['ijin'] ?: '-' }}</td>
                                            <td
                                                class="p-2 text-right text-xs border-r border-zinc-300 dark:border-zinc-700 font-bold {{ $adaPotBaris ? 'text-red-600 dark:text-red-400' : 'text-zinc-500' }}">
                                                {{ $adaPotBaris ? 'Rp ' . number_format($p['potongan_targ']) : '-' }}
                                            </td>
                                            <td class="p-2 text-left text-xs italic text-zinc-500">
                                                {{ $p['keterangan'] ?: '-' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                                <tfoot
                                    class="bg-zinc-100 dark:bg-zinc-800 border-t-2 border-zinc-300 dark:border-zinc-600">
                                    <tr>
                                        <td colspan="7"
                                            class="px-3 py-2 text-center {{ $adaPot ? 'bg-red-50 dark:bg-red-950/30' : '' }}">
                                            <span
                                                class="text-xs font-bold uppercase tracking-wide {{ $adaPot ? 'text-red-600 dark:text-red-400' : 'text-zinc-500 dark:text-zinc-400' }}">
                                                Rata-rata / orang ({{ $groupOrang }} orang):
                                            </span>
                                            <span
                                                class="text-base font-black {{ $adaPot ? 'text-red-600 dark:text-red-400' : 'text-zinc-500 dark:text-zinc-400' }}">
                                                Rp {{ number_format($groupRata) }}
                                            </span>
                                            <span class="text-xs text-zinc-400 ml-3">
                                                (Total gabungan: Rp {{ number_format($groupTotal) }})
                                            </span>
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</x-filament-panels::page>
