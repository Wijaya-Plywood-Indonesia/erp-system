<x-filament-panels::page>
    {{-- Form Filter Tanggal --}}
    <div class="p-4 bg-white dark:bg-zinc-900 rounded-lg shadow border border-zinc-200 dark:border-zinc-800">
        {{ $this->form }}
    </div>

    {{-- Loading Indicator --}}
    @if($isLoading ?? false)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-white/75 dark:bg-zinc-900/75">
        <div class="flex items-center space-x-3">
            <x-filament::loading-indicator class="w-8 h-8 text-sky-500" />
            <span class="text-lg font-medium text-zinc-700 dark:text-zinc-300">Memuat data repair...</span>
        </div>
    </div>
    @endif

    @php
    $dataProduksi = $dataProduksi ?? [];
    @endphp

    <div class="space-y-8 mt-6">
        @forelse ($dataProduksi as $data)
        @php
        $items = $data['items'] ?? [];
        $isMultiUkuran = count($items) > 1;
        $totalPekerja = count($data['pekerja'] ?? []);
        @endphp

        {{-- ========================================================================= --}}
        {{-- KONDISI 1: MEJA DENGAN MULTI-UKURAN (2 Ukuran atau Lebih)                   --}}
        {{-- ========================================================================= --}}
        @if($isMultiUkuran)
        @php
        $totalTarget = $data['total_target'] ?? 0;
        $totalHasil = $data['total_hasil'] ?? 0;
        $totalSelisih = $data['total_selisih'] ?? ($totalHasil - $totalTarget);
        $capaianTotal = $data['capaian_total'] ?? null;
        $isSuccessMeja = $totalSelisih >= 0;
        $warnaStatusMeja = $isSuccessMeja ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400';
        @endphp

        <div class="bg-white dark:bg-zinc-900 rounded-sm shadow-lg border border-zinc-200 dark:border-zinc-700 overflow-hidden">
            {{-- Header Card Meja Multi-Ukuran --}}
            <div class="bg-zinc-800 px-4 py-3 text-white flex justify-between items-center flex-wrap gap-2">
                <div class="flex items-center gap-3">
                    <span class="px-2.5 py-1 rounded bg-indigo-600 font-bold text-xs tracking-wider uppercase shadow-sm">
                        MEJA {{ $data['nomor_meja'] }}
                    </span>
                    <h2 class="text-base md:text-lg font-bold tracking-wide">
                        PENGERJAAN MULTI-UKURAN ({{ count($items) }} UKURAN)
                    </h2>
                </div>
                <div class="flex items-center gap-2 text-xs">
                    @if($capaianTotal !== null)
                    <span class="px-2.5 py-1 rounded font-bold {{ $isSuccessMeja ? 'bg-emerald-600 text-white' : 'bg-red-600 text-white' }} shadow-sm">
                        Capaian Total Meja: {{ number_format($capaianTotal, 1, ',', '.') }}% ({{ $isSuccessMeja ? 'Tercapai' : 'Kurang Target' }})
                    </span>
                    @endif
                </div>
            </div>

            <div class="p-4 space-y-6">
                {{-- TABEL 1: RINCIAN UKURAN / BARANG DIKERJAKAN --}}
                <div>
                    <h3 class="text-xs font-bold uppercase tracking-wider text-zinc-500 dark:text-zinc-400 mb-2 flex items-center gap-1.5">
                        <span class="w-2 h-2 rounded-full bg-indigo-500"></span>
                        1. Rincian Ukuran & Capaian di Meja {{ $data['nomor_meja'] }}
                    </h3>
                    <div class="w-full overflow-x-auto rounded border border-zinc-300 dark:border-zinc-700">
                        <table class="w-full text-xs text-left border-collapse">
                            <thead class="bg-zinc-100 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300 uppercase font-semibold border-b border-zinc-300 dark:border-zinc-700">
                                <tr>
                                    <th class="p-2.5 border-r border-zinc-300 dark:border-zinc-700">Kode Ukuran</th>
                                    <th class="p-2.5 text-center border-r border-zinc-300 dark:border-zinc-700 w-28">Jenis Kayu</th>
                                    <th class="p-2.5 text-center border-r border-zinc-300 dark:border-zinc-700 w-16">KW</th>
                                    <th class="p-2.5 text-right border-r border-zinc-300 dark:border-zinc-700 w-28">Target (Lbr)</th>
                                    <th class="p-2.5 text-right border-r border-zinc-300 dark:border-zinc-700 w-28">Hasil (Lbr)</th>
                                    <th class="p-2.5 text-right border-r border-zinc-300 dark:border-zinc-700 w-28">Selisih</th>
                                    <th class="p-2.5 text-right w-24">Capaian</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800 font-medium">
                                @foreach ($items as $i => $item)
                                @php
                                $selisihItem = $item['selisih'] ?? 0;
                                $isLunasItem = $selisihItem >= 0;
                                $tandaItem = $selisihItem >= 0 ? '+' : '';
                                $capaianItem = $item['capaian_persen'];
                                @endphp
                                <tr class="{{ $i % 2 === 1 ? 'bg-zinc-50 dark:bg-zinc-800/40' : 'bg-white dark:bg-zinc-900' }}">
                                    <td class="p-2.5 border-r border-zinc-300 dark:border-zinc-700 font-mono font-bold text-zinc-900 dark:text-zinc-100">
                                        @if(!$item['has_target'])
                                        <span class="text-red-600 dark:text-red-400">{{ $item['ukuran'] }} (Target Tidak Ditemukan)</span>
                                        @else
                                        {{ strtoupper($item['kode_ukuran']) }}
                                        @endif
                                    </td>
                                    <td class="p-2.5 text-center border-r border-zinc-300 dark:border-zinc-700 text-zinc-700 dark:text-zinc-300">{{ $item['jenis_kayu'] }}</td>
                                    <td class="p-2.5 text-center border-r border-zinc-300 dark:border-zinc-700 font-bold text-sky-600 dark:text-sky-400">KW {{ $item['kw'] }}</td>
                                    <td class="p-2.5 text-right border-r border-zinc-300 dark:border-zinc-700 font-mono text-zinc-700 dark:text-zinc-300">{{ number_format($item['target'], 0, ',', '.') }}</td>
                                    <td class="p-2.5 text-right border-r border-zinc-300 dark:border-zinc-700 font-mono font-bold {{ $isLunasItem ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400' }}">
                                        {{ number_format($item['hasil'], 0, ',', '.') }}
                                    </td>
                                    <td class="p-2.5 text-right border-r border-zinc-300 dark:border-zinc-700 font-mono font-bold {{ $isLunasItem ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400' }}">
                                        {{ $tandaItem }}{{ number_format($selisihItem, 0, ',', '.') }}
                                    </td>
                                    <td class="p-2.5 text-right font-bold {{ $isLunasItem ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400' }}">
                                        {{ $capaianItem !== null ? number_format($capaianItem, 1, ',', '.') . '%' : '-' }}
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                            <tfoot class="bg-zinc-100 dark:bg-zinc-800 font-bold border-t-2 border-zinc-300 dark:border-zinc-700">
                                <tr>
                                    <td colspan="3" class="p-2.5 text-left border-r border-zinc-300 dark:border-zinc-700 uppercase tracking-wider text-zinc-600 dark:text-zinc-400">
                                        Subtotal Gabungan Meja {{ $data['nomor_meja'] }}:
                                    </td>
                                    <td class="p-2.5 text-right border-r border-zinc-300 dark:border-zinc-700 font-mono text-zinc-900 dark:text-white">{{ number_format($totalTarget, 0, ',', '.') }}</td>
                                    <td class="p-2.5 text-right border-r border-zinc-300 dark:border-zinc-700 font-mono {{ $warnaStatusMeja }}">{{ number_format($totalHasil, 0, ',', '.') }}</td>
                                    <td class="p-2.5 text-right border-r border-zinc-300 dark:border-zinc-700 font-mono {{ $warnaStatusMeja }}">
                                        {{ $totalSelisih >= 0 ? '+' : '' }}{{ number_format($totalSelisih, 0, ',', '.') }}
                                    </td>
                                    <td class="p-2.5 text-right {{ $warnaStatusMeja }}">
                                        {{ $capaianTotal !== null ? number_format($capaianTotal, 1, ',', '.') . '%' : '-' }}
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>

                {{-- TABEL 2: DATA PEKERJA MEJA MULTI-UKURAN --}}
                <div>
                    <h3 class="text-xs font-bold uppercase tracking-wider text-zinc-500 dark:text-zinc-400 mb-2 flex items-center gap-1.5">
                        <span class="w-2 h-2 rounded-full bg-indigo-500"></span>
                        2. Data Pekerja Meja {{ $data['nomor_meja'] }}
                    </h3>
                    <div class="w-full overflow-x-auto rounded border border-zinc-300 dark:border-zinc-700">
                        <table class="w-full text-xs text-left border-collapse">
                            <thead class="bg-zinc-100 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300 uppercase font-semibold border-b border-zinc-300 dark:border-zinc-700">
                                <tr>
                                    <th class="p-2.5 text-center w-16 border-r border-zinc-300 dark:border-zinc-700">ID</th>
                                    <th class="p-2.5 text-left w-44 border-r border-zinc-300 dark:border-zinc-700">Nama Pekerja</th>
                                    <th class="p-2.5 text-center w-20 border-r border-zinc-300 dark:border-zinc-700">Masuk</th>
                                    <th class="p-2.5 text-center w-20 border-r border-zinc-300 dark:border-zinc-700">Pulang</th>
                                    <th class="p-2.5 text-center w-16 border-r border-zinc-300 dark:border-zinc-700">Izin</th>
                                    <th class="p-2.5 text-right w-36 border-r border-zinc-300 dark:border-zinc-700">Potongan Target</th>
                                    <th class="p-2.5 text-left">Keterangan</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800 font-medium">
                                @forelse ($data['pekerja'] as $i => $p)
                                @php $potTarget = (int) ($p['pot_target'] ?? 0); @endphp
                                <tr class="{{ $i % 2 === 1 ? 'bg-zinc-50 dark:bg-zinc-800/40' : 'bg-white dark:bg-zinc-900' }}">
                                    <td class="p-2.5 text-center border-r border-zinc-300 dark:border-zinc-700 font-mono text-zinc-600 dark:text-zinc-400">{{ $p["id"] ?? "-" }}</td>
                                    <td class="p-2.5 text-left border-r border-zinc-300 dark:border-zinc-700 font-bold text-zinc-900 dark:text-zinc-100">{{ $p["nama"] ?? "-" }}</td>
                                    <td class="p-2.5 text-center border-r border-zinc-300 dark:border-zinc-700 text-zinc-700 dark:text-zinc-300">{{ $p["jam_masuk"] ?? "-" }}</td>
                                    <td class="p-2.5 text-center border-r border-zinc-300 dark:border-zinc-700 text-zinc-700 dark:text-zinc-300">{{ $p["jam_pulang"] ?? "-" }}</td>
                                    <td class="p-2.5 text-center border-r border-zinc-300 dark:border-zinc-700 text-amber-600 dark:text-amber-400 font-medium">{{ $p["ijin"] ?? "-" }}</td>
                                    <td class="p-2.5 text-right border-r border-zinc-300 dark:border-zinc-700 font-mono font-bold {{ $potTarget > 0 ? 'text-red-600 dark:text-red-400' : 'text-zinc-600 dark:text-zinc-400' }}">
                                        {{ $potTarget > 0 ? 'Rp ' . number_format($potTarget, 0, ',', '.') : '-' }}
                                    </td>
                                    <td class="p-2.5 text-left text-zinc-700 dark:text-zinc-300">{{ $p["keterangan"] ?? "-" }}</td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="7" class="p-4 text-center text-zinc-500 dark:text-zinc-400 text-xs">
                                        Tidak ada pegawai terdaftar untuk meja ini.
                                    </td>
                                </tr>
                                @endforelse
                            </tbody>
                            <tfoot class="bg-zinc-100 dark:bg-zinc-800 border-t-2 border-zinc-300 dark:border-zinc-700">
                                <tr>
                                    <td colspan="7" class="p-2.5 text-center text-xs text-zinc-600 dark:text-zinc-300 space-x-2 md:space-x-3">
                                        <span>Pekerja: <strong class="text-zinc-900 dark:text-white">{{ $totalPekerja }}</strong></span>
                                        <span class="text-zinc-400">|</span>
                                        <span>Total Target: <strong class="font-mono text-zinc-900 dark:text-white">{{ number_format($totalTarget, 0, ',', '.') }} Lbr</strong></span>
                                        <span class="text-zinc-400">|</span>
                                        <span>Total Hasil: <strong class="font-mono font-bold {{ $warnaStatusMeja }}">{{ number_format($totalHasil, 0, ',', '.') }} Lbr</strong></span>
                                        <span class="text-zinc-400">|</span>
                                        <span>Total Selisih: <strong class="font-mono font-bold {{ $warnaStatusMeja }}">{{ $totalSelisih >= 0 ? '+' : '' }}{{ number_format($totalSelisih, 0, ',', '.') }} Lbr</strong></span>
                                        <span class="text-zinc-400">|</span>
                                        <span class="text-xs text-zinc-500">Tgl: {{ $data['tanggal'] }}</span>
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>

                {{-- Catatan / Kendala Meja Multi-Ukuran --}}
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

        {{-- ========================================================================= --}}
        {{-- KONDISI 2: MEJA DENGAN 1 UKURAN SAJA (FORMAT ASLI MEJA TUNGGAL)            --}}
        {{-- ========================================================================= --}}
        @else
        @php
        $targetSingle = $data['target'] ?? 0;
        $hasilSingle = $data['hasil'] ?? 0;
        $selisihSingle = $data['selisih'] ?? ($hasilSingle - $targetSingle);
        $isLunasSingle = $selisihSingle >= 0;
        $warnaTeksSingle = $isLunasSingle ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400';
        $tandaSingle = $isLunasSingle ? '+' : '';
        $isUkuranUnknown = ($data['kode_ukuran'] === 'REPAIR-NOT-FOUND') || !($data['has_target'] ?? true);
        @endphp

        <div class="bg-white dark:bg-zinc-900 rounded-sm shadow-md border border-zinc-200 dark:border-zinc-700 overflow-hidden">
            {{-- Header Card Meja --}}
            <div class="bg-zinc-800 px-4 py-3 text-white flex justify-between items-center flex-wrap gap-2">
                <div class="flex items-center gap-3">
                    <span class="px-2 py-0.5 rounded bg-zinc-700 font-bold text-xs tracking-wider uppercase">
                        MEJA {{ $data['nomor_meja'] }}
                    </span>
                    <h2 class="text-base md:text-lg font-bold tracking-wide">
                        @if($isUkuranUnknown)
                        <span class="text-red-400">REPAIR ({{ $data["ukuran"] }}) - Ukuran tidak dikenal</span>
                        @else
                        {{ strtoupper($data["kode_ukuran"]) }}
                        @endif
                    </h2>
                </div>
                <div class="flex items-center gap-2 text-xs">
                    @if($data['capaian_persen'] !== null)
                    <span class="px-2.5 py-1 rounded font-bold {{ $isLunasSingle ? 'bg-emerald-600 text-white' : 'bg-red-600 text-white' }} shadow-sm">
                        Capaian: {{ number_format($data['capaian_persen'], 1, ',', '.') }}%
                    </span>
                    @endif
                    <span class="bg-zinc-700 px-2.5 py-1 rounded font-medium">{{ $data['jenis_kayu'] ?? '-' }}</span>
                    <span class="bg-sky-600 text-white px-2.5 py-1 rounded font-bold">KW {{ $data['kw'] ?? '-' }}</span>
                </div>
            </div>

            <div class="p-4 space-y-4">
                <div class="w-full overflow-x-auto">
                    <div class="min-w-[800px]">
                        <table class="w-full text-sm border-collapse border border-zinc-200 dark:border-zinc-700">
                            <thead>
                                <tr>
                                    <th colspan="7" class="p-2.5 text-base font-bold text-center bg-zinc-700 text-white tracking-wider uppercase">
                                        DATA PEKERJA MEJA {{ $data['nomor_meja'] }}
                                    </th>
                                </tr>
                                <tr class="bg-zinc-100 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300 border-t border-zinc-200 dark:border-zinc-700 text-xs uppercase font-semibold">
                                    <th class="p-2 text-center w-16">ID</th>
                                    <th class="p-2 text-left w-48">Nama Pekerja</th>
                                    <th class="p-2 text-center w-20">Masuk</th>
                                    <th class="p-2 text-center w-20">Pulang</th>
                                    <th class="p-2 text-center w-16">Izin</th>
                                    <th class="p-2 text-right w-36">Potongan Target</th>
                                    <th class="p-2 text-left">Keterangan</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800 font-medium">
                                @forelse ($data['pekerja'] as $i => $p)
                                @php $potTarget = (int) ($p['pot_target'] ?? 0); @endphp
                                <tr class="{{ $i % 2 === 1 ? 'bg-zinc-50/50 dark:bg-zinc-800/30' : 'bg-white dark:bg-zinc-900' }} text-xs">
                                    <td class="p-2 text-center border-r border-zinc-200 dark:border-zinc-700 font-mono text-zinc-500">{{ $p["id"] ?? "-" }}</td>
                                    <td class="p-2 text-left border-r border-zinc-200 dark:border-zinc-700 font-bold text-zinc-900 dark:text-zinc-100">{{ $p["nama"] ?? "-" }}</td>
                                    <td class="p-2 text-center border-r border-zinc-200 dark:border-zinc-700 text-zinc-700 dark:text-zinc-300">{{ $p["jam_masuk"] ?? "-" }}</td>
                                    <td class="p-2 text-center border-r border-zinc-200 dark:border-zinc-700 text-zinc-700 dark:text-zinc-300">{{ $p["jam_pulang"] ?? "-" }}</td>
                                    <td class="p-2 text-center border-r border-zinc-200 dark:border-zinc-700 text-amber-600 dark:text-amber-400 font-medium">{{ $p["ijin"] ?? "-" }}</td>
                                    <td class="p-2 text-right border-r border-zinc-200 dark:border-zinc-700 font-bold font-mono {{ $potTarget > 0 ? 'text-red-600 dark:text-red-400' : 'text-zinc-600 dark:text-zinc-400' }}">
                                        {{ $potTarget > 0 ? 'Rp ' . number_format($potTarget, 0, ',', '.') : '-' }}
                                    </td>
                                    <td class="p-2 text-left text-zinc-500 dark:text-zinc-400">{{ $p["keterangan"] ?? "-" }}</td>
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
                                        <strong class="font-mono">{{ number_format($targetSingle, 0, ',', '.') }} Lbr</strong>

                                        <span class="text-zinc-400">|</span>

                                        <span class="font-medium">Hasil Produksi:</span>
                                        <strong class="font-mono text-sm font-bold {{ $warnaTeksSingle }}">{{ number_format($hasilSingle, 0, ',', '.') }} Lbr</strong>

                                        <span class="text-zinc-400">|</span>

                                        <span class="font-medium">Selisih:</span>
                                        <strong class="font-mono text-sm font-bold {{ $warnaTeksSingle }}">
                                            {{ $tandaSingle }}{{ number_format($selisihSingle, 0, ',', '.') }} Lbr
                                        </strong>

                                        <span class="text-zinc-400">|</span>

                                        <span class="text-xs text-zinc-500">Tgl: {{ $data["tanggal"] }}</span>
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>

                {{-- Catatan Tambahan --}}
                @if(($data['keterangan_hasil'] && $data['keterangan_hasil'] !== '—') || ($data['keterangan_kerja'] && $data['keterangan_kerja'] !== '—'))
                <div class="p-3 bg-zinc-50 dark:bg-zinc-800/50 rounded border border-zinc-200 dark:border-zinc-700/60 text-xs space-y-1">
                    @if($data['keterangan_hasil'] && $data['keterangan_hasil'] !== '—')
                    <p class="text-zinc-700 dark:text-zinc-300">
                        <strong>Catatan Hasil Repair:</strong> {{ $data['keterangan_hasil'] }}
                    </p>
                    @endif

                    @if($data['keterangan_kerja'] && $data['keterangan_kerja'] !== '—')
                    <p class="text-amber-700 dark:text-amber-400">
                        <strong>Kendala Produksi Hari Ini:</strong> {{ $data['keterangan_kerja'] }}
                    </p>
                    @endif
                </div>
                @endif
            </div>
        </div>
        @endif

        @empty
        <div class="text-center p-12 bg-white dark:bg-zinc-900 rounded-lg shadow border border-zinc-200 dark:border-zinc-800 text-zinc-500 dark:text-zinc-400">
            <x-heroicon-o-clipboard-document-list class="w-12 h-12 mx-auto text-zinc-400 mb-3" />
            <p class="text-base font-medium">Tidak ada data produksi repair untuk tanggal ini.</p>
            <p class="text-xs mt-1 text-zinc-400">Silakan pilih tanggal lain pada form filter di atas.</p>
        </div>
        @endforelse
    </div>
</x-filament-panels::page>