<x-filament-panels::page>
    <div class="p-4 bg-white dark:bg-zinc-900 rounded-lg shadow border border-zinc-200 dark:border-zinc-800">
        {{ $this->form }}
    </div>

    <div wire:loading wire:target="loadAllData" class="w-full text-center py-4">
        <x-filament::loading-indicator class="w-8 h-8 mx-auto text-primary-600 mb-2" />
        <span class="text-zinc-500 italic">Memproses laporan Produksi Nyusup...</span>
    </div>

    <div wire:loading.remove class="space-y-12 mt-6">

        @php
            $produksiData = collect($reportData['produksi'] ?? []);
        @endphp

        {{-- ================= BLOK TARGET & POTONGAN PER PRODUKSI ================= --}}
        @foreach ($produksiData as $data)
            @php
                $potTotal = $data['potongan_total'] ?? 0;
                $jmlPekerja = $data['jumlah_pekerja'] ?? 0;
            @endphp

            <div class="bg-white dark:bg-zinc-900 rounded-sm shadow-lg border border-zinc-200 dark:border-zinc-700 overflow-hidden">
                {{-- Header Blok --}}
                <div class="bg-zinc-800 p-4 text-white flex justify-between items-center">
                    <h2 class="text-lg font-bold">NYUSUP - {{ $data['tanggal'] }}</h2>
                    <div class="flex gap-3 items-center">
                        <span class="text-xs bg-zinc-700 px-2 py-1 rounded">{{ $jmlPekerja }} pekerja</span>
                        @if($potTotal > 0)
                            <span class="text-xs px-2 py-1 rounded bg-amber-600 font-bold">
                                ⚠ Total potongan Rp {{ number_format($potTotal) }}
                            </span>
                        @endif
                        @if($data['ada_tanpa_target'] ?? false)
                            <span class="text-xs px-2 py-1 rounded bg-red-700">⚠ Ada barang tanpa target</span>
                        @endif
                    </div>
                </div>

                <div class="p-4 space-y-6">

                    @if(!($data['mesin_ditemukan'] ?? true))
                        <div class="p-3 rounded bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-xs text-red-600 dark:text-red-400">
                            Mesin bernama "NYUSUP" belum ada di Master Mesin, jadi target tidak bisa dicari.
                        </div>
                    @endif

                    {{-- ================= TABEL ATAS: DATA PEKERJA ================= --}}
                    <div class="w-full overflow-x-auto">
                        <div class="min-w-[900px]">
                            <table class="w-full text-sm border-collapse border border-zinc-300 dark:border-zinc-600">
                                <thead>
                                    <tr>
                                        <th colspan="9" class="p-3 text-lg font-bold text-center bg-zinc-700 text-white">
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
                                        <th class="p-2 text-right text-xs font-medium w-24">Hasil (Pcs)</th>
                                        <th class="p-2 text-right text-xs font-medium w-24">Capaian</th>
                                        <th class="p-2 text-right text-xs font-medium w-36">Potongan Target</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($data['pegawai'] as $i => $p)
                                        @php
                                            $pot = (int) ($p['pot_target'] ?? 0);
                                            $cap = $p['capaian_global'];
                                        @endphp
                                        <tr class="{{ $i % 2 === 1 ? 'bg-zinc-50 dark:bg-zinc-800/50' : 'bg-white dark:bg-zinc-900' }} border-t border-zinc-300 dark:border-zinc-700">
                                            <td class="p-2 text-center text-xs border-r border-zinc-300 dark:border-zinc-700 font-mono">{{ $p['id'] }}</td>
                                            <td class="p-2 text-left text-xs border-r border-zinc-300 dark:border-zinc-700 font-medium">
                                                {{ $p['nama'] }}
                                                @if(!empty($p['tugas']))
                                                    <span class="block text-[10px] text-zinc-500">{{ $p['tugas'] }}</span>
                                                @endif
                                            </td>
                                            <td class="p-2 text-center text-xs border-r border-zinc-300 dark:border-zinc-700">{{ $p['jam_masuk'] }}</td>
                                            <td class="p-2 text-center text-xs border-r border-zinc-300 dark:border-zinc-700">{{ $p['jam_pulang'] }}</td>
                                            <td class="p-2 text-center text-xs border-r border-zinc-300 dark:border-zinc-700 font-mono">
                                                {{ isset($p['jam_aktual_bersih']) ? number_format($p['jam_aktual_bersih'], 2, ',', '.') . ' jam' : '-' }}
                                            </td>
                                            <td class="p-2 text-center text-xs border-r border-zinc-300 dark:border-zinc-700 text-yellow-600 dark:text-yellow-400">{{ $p['ijin'] }}</td>
                                            <td class="p-2 text-right text-xs border-r border-zinc-300 dark:border-zinc-700 font-bold text-green-600 dark:text-green-400">
                                                {{ number_format($p['hasil_total']) }}
                                            </td>
                                            <td class="p-2 text-right text-xs border-r border-zinc-300 dark:border-zinc-700 font-bold {{ $cap === null ? 'text-zinc-500' : ($cap >= 100 ? 'text-green-600 dark:text-green-400' : 'text-amber-600 dark:text-amber-400') }}">
                                                {{ $cap === null ? '-' : number_format($cap, 1, ',', '.') . '%' }}
                                            </td>
                                            <td class="p-2 text-right text-xs font-bold {{ $pot > 0 ? 'text-red-500' : '' }}">
                                                {{ $pot > 0 ? 'Rp ' . number_format($pot) : '-' }}
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="9" class="p-4 text-center text-zinc-500 dark:text-zinc-400 text-sm italic">
                                                Tidak ada data pekerja.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                                <tfoot class="bg-zinc-100 dark:bg-zinc-800 border-t-2 border-zinc-300 dark:border-zinc-600">
                                    <tr>
                                        <td colspan="9" class="p-2 text-center text-[11px] text-zinc-500 dark:text-zinc-400">
                                            <span class="font-medium">Jumlah Pekerja:</span>
                                            <strong class="text-zinc-900 dark:text-white">{{ $jmlPekerja }}</strong>
                                            <span class="text-zinc-400">|</span>
                                            <span class="font-medium">Total Potongan:</span>
                                            <strong class="{{ $potTotal > 0 ? 'text-red-500' : 'text-zinc-900 dark:text-white' }}">Rp {{ number_format($potTotal) }}</strong>
                                            <span class="text-zinc-400">|</span>
                                            <span class="text-xs">Tgl: {{ $data['tanggal'] }}</span>
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>

                    {{-- ================= TABEL BAWAH: BARANG DIKERJAKAN PER PEKERJA ================= --}}
                    <div class="w-full overflow-x-auto">
                        <div class="min-w-[900px]">
                            <table class="w-full text-sm border-collapse border border-zinc-300 dark:border-zinc-600">
                                <thead>
                                    <tr>
                                        <th colspan="7" class="p-3 text-lg font-bold text-center bg-zinc-700 text-white">
                                            DATA BARANG DIKERJAKAN
                                        </th>
                                    </tr>
                                    <tr class="bg-zinc-200 dark:bg-zinc-700 text-zinc-800 dark:text-zinc-300 border-t border-zinc-300 dark:border-zinc-600">
                                        <th class="p-2 text-left text-xs font-medium">Ukuran</th>
                                        <th class="p-2 text-center text-xs font-medium w-24">Jenis Kayu</th>
                                        <th class="p-2 text-center text-xs font-medium w-24">Grade</th>
                                        <th class="p-2 text-right text-xs font-medium w-24">Hasil</th>
                                        <th class="p-2 text-right text-xs font-medium w-32">Target (Adjusted)</th>
                                        <th class="p-2 text-right text-xs font-medium w-24">Selisih</th>
                                        <th class="p-2 text-right text-xs font-medium w-20">Capaian</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($data['pegawai'] as $p)
                                        @php
                                            $pot = (int) ($p['pot_target'] ?? 0);
                                            $cap = $p['capaian_global'];
                                        @endphp

                                        {{-- Sub-header per pekerja --}}
                                        <tr class="bg-zinc-100 dark:bg-zinc-800 border-t-2 border-zinc-300 dark:border-zinc-600">
                                            <td colspan="7" class="p-2 text-xs font-bold text-zinc-800 dark:text-zinc-200">
                                                {{ $p['id'] }} - {{ $p['nama'] }}
                                                <span class="ml-3 font-normal text-zinc-500">
                                                    {{ isset($p['jam_aktual_bersih']) ? number_format($p['jam_aktual_bersih'], 2, ',', '.') . ' jam' : '-' }}
                                                </span>
                                                @if($cap !== null)
                                                    <span class="ml-3 px-2 py-0.5 rounded text-[10px] text-white {{ $cap >= 100 ? 'bg-green-700' : 'bg-red-700' }}">
                                                        Capaian {{ number_format($cap, 1, ',', '.') }}%
                                                    </span>
                                                @endif
                                                @if($pot > 0)
                                                    <span class="ml-2 px-2 py-0.5 rounded text-[10px] text-white bg-amber-600">
                                                        Potongan Rp {{ number_format($pot) }}
                                                    </span>
                                                @endif
                                            </td>
                                        </tr>

                                        @forelse ($p['items'] as $item)
                                            @php
                                                $adaTarget = $item['has_target'] ?? false;
                                                $selisih = $item['selisih'] ?? 0;
                                            @endphp
                                            <tr class="bg-white dark:bg-zinc-900 border-t border-zinc-300 dark:border-zinc-700">
                                                <td class="p-2 text-left text-xs border-r border-zinc-300 dark:border-zinc-700 font-medium">
                                                    @if(!$adaTarget)
                                                        <span class="text-red-500">{{ $item['ukuran'] }} ⚠</span>
                                                    @else
                                                        {{ $item['ukuran'] }}
                                                    @endif
                                                </td>
                                                <td class="p-2 text-center text-xs border-r border-zinc-300 dark:border-zinc-700 uppercase">{{ $item['jenis_kayu'] }}</td>
                                                <td class="p-2 text-center text-xs border-r border-zinc-300 dark:border-zinc-700 uppercase">{{ $item['grade'] }}</td>
                                                <td class="p-2 text-right text-xs border-r border-zinc-300 dark:border-zinc-700 font-bold text-green-600 dark:text-green-400">
                                                    {{ number_format($item['hasil']) }}
                                                </td>
                                                <td class="p-2 text-right text-xs border-r border-zinc-300 dark:border-zinc-700 text-zinc-500">
                                                    @if($adaTarget)
                                                        {{ number_format($item['target']) }}
                                                        @if(isset($item['target_normal']))
                                                            <span class="block text-[10px] text-zinc-600">(normal: {{ number_format($item['target_normal']) }})</span>
                                                        @endif
                                                    @else
                                                        -
                                                    @endif
                                                </td>
                                                <td class="p-2 text-right text-xs border-r border-zinc-300 dark:border-zinc-700 font-mono {{ !$adaTarget ? 'text-zinc-500' : ($selisih >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-500') }}">
                                                    @if($adaTarget)
                                                        {{ $selisih >= 0 ? '+' : '' }}{{ number_format($selisih) }}
                                                    @else
                                                        -
                                                    @endif
                                                </td>
                                                <td class="p-2 text-right text-xs font-bold {{ !$adaTarget ? 'text-red-500' : (($item['capaian_persen'] ?? 0) >= 100 ? 'text-green-600 dark:text-green-400' : 'text-amber-600 dark:text-amber-400') }}">
                                                    @if(!$adaTarget)
                                                        Target ?
                                                    @else
                                                        {{ number_format($item['capaian_persen'], 1, ',', '.') }}%
                                                    @endif
                                                </td>
                                            </tr>
                                        @empty
                                            <tr class="bg-white dark:bg-zinc-900 border-t border-zinc-300 dark:border-zinc-700">
                                                <td colspan="7" class="p-2 text-center text-xs text-zinc-500 italic">
                                                    Belum ada hasil untuk pekerja ini.
                                                </td>
                                            </tr>
                                        @endforelse
                                    @empty
                                        <tr>
                                            <td colspan="7" class="p-4 text-center text-zinc-500 dark:text-zinc-400 text-sm italic">
                                                Tidak ada barang dikerjakan.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                                <tfoot class="bg-zinc-100 dark:bg-zinc-800 border-t-2 border-zinc-300 dark:border-zinc-600">
                                    <tr>
                                        <td colspan="7" class="p-2 text-center text-[11px] text-zinc-500 dark:text-zinc-400">
                                            Capaian tiap pekerja = jumlah persen semua barang, target ADJUSTED ke jam kerja bersih pekerja itu sendiri.
                                            Potongan = kekurangan capaian x nilai target satu hari penuh, dibulatkan kelipatan 500.
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>

                </div>
            </div>
        @endforeach

        {{-- ================= REKAP PRODUKSI (dipakai juga untuk Excel) ================= --}}
        @if(!empty($reportData['detail']))
        <div class="bg-white dark:bg-zinc-900 rounded-sm shadow-lg border border-zinc-200 dark:border-zinc-700 overflow-hidden">
            <div class="bg-zinc-800 p-4 text-white text-center">
                <h2 class="text-lg font-bold uppercase tracking-widest">
                    LAPORAN PRODUKSI NYUSUP - {{ \Carbon\Carbon::parse($this->tanggal)->format('d F Y') }}
                </h2>
            </div>

            <div class="p-4 overflow-x-auto">
                <div class="flex flex-col lg:flex-row gap-8 min-w-[1200px]">

                    {{-- TABEL KIRI: DETAIL PRODUKSI --}}
                    <div class="flex-[2]">
                        <h3 class="text-sm font-bold mb-2 uppercase text-zinc-600 dark:text-zinc-400">Detail Produksi</h3>
                        <table class="w-full text-[11px] border-collapse border border-zinc-300 dark:border-zinc-700">
                            <thead>
                                <tr class="bg-zinc-100 dark:bg-zinc-800 text-zinc-800 dark:text-zinc-200 uppercase font-bold">
                                    <th class="p-2 border border-zinc-300 dark:border-zinc-700">Tanggal</th>
                                    <th class="p-2 border border-zinc-300 dark:border-zinc-700">P</th>
                                    <th class="p-2 border border-zinc-300 dark:border-zinc-700">L</th>
                                    <th class="p-2 border border-zinc-300 dark:border-zinc-700">T</th>
                                    <th class="p-2 border border-zinc-300 dark:border-zinc-700 text-left">Jenis</th>
                                    <th class="p-2 border border-zinc-300 dark:border-zinc-700 text-center">Byk</th>
                                    <th class="p-2 border border-zinc-300 dark:border-zinc-700 text-right bg-blue-50 dark:bg-blue-900/20">m3</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                                @foreach($reportData['detail'] as $d)
                                <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/50">
                                    <td class="p-2 border border-zinc-300 dark:border-zinc-700 text-center">{{ $d['tanggal'] }}</td>
                                    <td class="p-2 border border-zinc-300 dark:border-zinc-700 text-center">{{ $d['p'] }}</td>
                                    <td class="p-2 border border-zinc-300 dark:border-zinc-700 text-center">{{ $d['l'] }}</td>
                                    <td class="p-2 border border-zinc-300 dark:border-zinc-700 text-center">{{ $d['t'] }}</td>
                                    <td class="p-2 border border-zinc-300 dark:border-zinc-700">{{ $d['jenis'] }}</td>
                                    <td class="p-2 border border-zinc-300 dark:border-zinc-700 text-center font-bold">{{ number_format($d['byk']) }}</td>
                                    <td class="p-2 border border-zinc-300 dark:border-zinc-700 text-right font-mono bg-blue-50/50 dark:bg-blue-900/10"></td>
                                </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                @php
                                    $totalByk = collect($reportData['detail'])->sum('byk');
                                @endphp
                                <tr class="bg-zinc-100 dark:bg-zinc-800 font-bold">
                                    <td colspan="5" class="p-2 text-right border border-zinc-300 dark:border-zinc-700">TOTAL:</td>
                                    <td class="p-2 text-center border border-zinc-300 dark:border-zinc-700">{{ number_format($totalByk) }}</td>
                                    <td class="p-2 border border-zinc-300 dark:border-zinc-700"></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    {{-- TABEL KANAN: REKAP HARGA/ONGKOS (Preview Column) --}}
                    <div class="flex-1">
                        <h3 class="text-sm font-bold mb-2 uppercase text-zinc-600 dark:text-zinc-400">Rekap Ongkos (Preview)</h3>
                        <table class="w-full text-[11px] border-collapse border border-zinc-300 dark:border-zinc-700">
                            <thead>
                                <tr class="bg-zinc-100 dark:bg-zinc-800 text-zinc-800 dark:text-zinc-200 uppercase font-bold">
                                    <th class="p-2 border border-zinc-300 dark:border-zinc-700">Tanggal</th>
                                    <th class="p-2 border border-zinc-300 dark:border-zinc-700 text-center">TTL PKJ</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                                @foreach($reportData['summary'] as $s)
                                <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/50">
                                    <td class="p-2 border border-zinc-300 dark:border-zinc-700 text-center">{{ $s['tanggal'] }}</td>
                                    <td class="p-2 border border-zinc-300 dark:border-zinc-700 text-center font-bold">{{ $s['ttl_pkj'] }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                        <div class="mt-4 p-4 bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded text-[10px] text-yellow-800 dark:text-yellow-200 italic">
                            * Kolom HARGA, TOTAL PRODUKSI (M3), ONGKOS PER M3, dan ONGKOS PER LB akan tersedia sebagai kolom kosong di Excel untuk diisi oleh Manajemen.
                        </div>
                    </div>

                </div>
            </div>
        </div>
        @endif

        @if($produksiData->isEmpty() && empty($reportData['detail']))
        <div class="p-16 text-center bg-zinc-50 dark:bg-zinc-900 rounded-xl border border-dashed border-zinc-300 dark:border-zinc-700">
            <x-heroicon-o-document-magnifying-glass class="w-12 h-12 mx-auto text-zinc-400 mb-4"/>
            <p class="text-zinc-500 italic text-lg">
                Tidak ada data produksi Nyusup untuk tanggal ini.
            </p>
        </div>
        @endif
    </div>
</x-filament-panels::page>