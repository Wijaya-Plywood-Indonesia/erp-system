<x-filament-panels::page>
    {{-- Form Filter Tanggal --}}
    <div class="p-4 bg-white dark:bg-zinc-900 rounded-lg shadow border border-zinc-200 dark:border-zinc-800">
        {{ $this->form }}
    </div>

    <div wire:loading wire:target="loadAllData" class="w-full text-center py-4">
        <x-filament::loading-indicator class="w-8 h-8 mx-auto text-primary-600 mb-2" />
        <span class="text-zinc-500 italic">Memproses laporan Produksi Sanding...</span>
    </div>

    <div wire:loading.remove class="space-y-12 mt-6">

        @php
            $produksiData = collect($reportData['produksi'] ?? []);
        @endphp

        {{-- ================= BLOK PER PRODUKSI (MESIN + SHIFT) ================= --}}
        @forelse ($produksiData as $data)
            @php
                $punyaTarget = $data['punya_target'] ?? false;
                $capaian = $data['capaian_global'] ?? 0;
                $tercapai = $punyaTarget && $capaian >= 100;
                $potTim = $data['potongan_total_tim'] ?? 0;
                $jmlPekerja = $data['jumlah_pekerja'] ?? 0;
                $rataPerOrang = $jmlPekerja > 0 ? $potTim / $jmlPekerja : 0;
            @endphp

            <div class="bg-white dark:bg-zinc-900 rounded-sm shadow-lg border border-zinc-200 dark:border-zinc-700 overflow-hidden">
                {{-- Header Blok --}}
                <div class="bg-zinc-800 p-4 text-white flex justify-between items-center">
                    <h2 class="text-lg font-bold">
                        {{ strtoupper($data['mesin']) }} - SHIFT {{ strtoupper($data['shift']) }}
                    </h2>
                    <div class="flex gap-4 items-center">
                        @if($punyaTarget)
                            <span class="text-xs px-2 py-1 rounded {{ $tercapai ? 'bg-green-700' : 'bg-red-700' }}">
                                Capaian Global: {{ number_format($capaian, 1, ',', '.') }}%
                            </span>
                        @endif
                        @if($potTim > 0)
                            <span class="text-xs px-2 py-1 rounded bg-amber-600 font-bold" title="Total tim dibagi rata jumlah pekerja">
                                ⚠ Rp {{ number_format($potTim) }} ÷ {{ $jmlPekerja }} org ≈ Rp {{ number_format($rataPerOrang) }}/org
                            </span>
                        @endif
                        <span class="text-xs bg-zinc-700 px-2 py-1 rounded">
                            @if(!$punyaTarget)
                                ⚠ Target belum ada
                            @else
                                {{ $tercapai ? '✔ Tercapai' : '✘ Belum' }}
                            @endif
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
                                        @php $potTarget = (int) ($p['pot_target'] ?? 0); @endphp
                                        <tr class="{{ $i % 2 === 1 ? 'bg-zinc-50 dark:bg-zinc-800/50' : 'bg-white dark:bg-zinc-900' }} border-t border-zinc-300 dark:border-zinc-700">
                                            <td class="p-2 text-center text-xs border-r border-zinc-300 dark:border-zinc-700 font-mono">
                                                {{ $p['id'] ?? '-' }}
                                            </td>
                                            <td class="p-2 text-left text-xs border-r border-zinc-300 dark:border-zinc-700 font-medium">
                                                {{ $p['nama'] ?? '-' }}
                                            </td>
                                            <td class="p-2 text-center text-xs border-r border-zinc-300 dark:border-zinc-700">
                                                {{ $p['jam_masuk'] ?? '-' }}
                                            </td>
                                            <td class="p-2 text-center text-xs border-r border-zinc-300 dark:border-zinc-700">
                                                {{ $p['jam_pulang'] ?? '-' }}
                                            </td>
                                            <td class="p-2 text-center text-xs border-r border-zinc-300 dark:border-zinc-700 font-mono">
                                                {{ isset($p['jam_aktual_bersih']) ? number_format($p['jam_aktual_bersih'], 2, ',', '.') . ' jam' : '-' }}
                                            </td>
                                            <td class="p-2 text-center text-xs border-r border-zinc-300 dark:border-zinc-700 text-yellow-600 dark:text-yellow-400">
                                                {{ $p['ijin'] ?? '-' }}
                                            </td>
                                            <td class="p-2 text-right text-xs font-bold {{ $potTarget > 0 ? 'text-red-500' : '' }}">
                                                {{ $potTarget > 0 ? 'Rp ' . number_format($potTarget) : '-' }}
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="7" class="p-4 text-center text-zinc-500 dark:text-zinc-400 text-sm italic">
                                                Tidak ada data pekerja untuk produksi ini.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>

                                <tfoot class="bg-zinc-100 dark:bg-zinc-800 border-t-2 border-zinc-300 dark:border-zinc-600">
                                    <tr>
                                        <td colspan="7" class="p-2 text-center text-[11px] text-zinc-500 dark:text-zinc-400">
                                            <span class="font-medium">Jumlah Pekerja:</span>
                                            <strong class="text-zinc-900 dark:text-white">{{ $jmlPekerja }}</strong>
                                            <span class="text-zinc-400">|</span>
                                            <span class="font-medium">Rata-rata Jam Aktual Kru:</span>
                                            <strong class="font-mono text-zinc-900 dark:text-white">{{ number_format($data['jam_aktual_rata'] ?? 0, 2, ',', '.') }} jam</strong>
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
                                        <th colspan="7" class="p-3 text-lg font-bold text-center bg-zinc-700 text-white">
                                            DATA BARANG DIKERJAKAN
                                        </th>
                                    </tr>
                                    <tr class="bg-zinc-200 dark:bg-zinc-700 text-zinc-800 dark:text-zinc-300 border-t border-zinc-300 dark:border-zinc-600">
                                        <th class="p-2 text-left text-xs font-medium">Ukuran</th>
                                        <th class="p-2 text-center text-xs font-medium w-24">Jenis Kayu</th>
                                        <th class="p-2 text-center text-xs font-medium w-36">Kategori / Grade</th>
                                        <th class="p-2 text-right text-xs font-medium w-24">Hasil</th>
                                        <th class="p-2 text-right text-xs font-medium w-28">Target (Adjusted)</th>
                                        <th class="p-2 text-right text-xs font-medium w-24">Selisih</th>
                                        <th class="p-2 text-right text-xs font-medium w-20">Capaian</th>
                                    </tr>
                                </thead>

                                <tbody>
                                    @forelse ($data['per_ukuran'] as $i => $item)
                                        @php
                                            $adaTarget = $item['has_target'] ?? false;
                                            $selisih = $item['selisih'] ?? 0;
                                        @endphp
                                        <tr class="{{ $i % 2 === 1 ? 'bg-zinc-50 dark:bg-zinc-800/50' : 'bg-white dark:bg-zinc-900' }} border-t border-zinc-300 dark:border-zinc-700">
                                            <td class="p-2 text-left text-xs border-r border-zinc-300 dark:border-zinc-700 font-medium">
                                                @if(!$adaTarget)
                                                    <span class="text-red-500">{{ $item['ukuran'] }} ⚠</span>
                                                @else
                                                    {{ $item['ukuran'] }}
                                                @endif
                                            </td>
                                            <td class="p-2 text-center text-xs border-r border-zinc-300 dark:border-zinc-700 uppercase">
                                                {{ $item['jenis_kayu'] ?? '-' }}
                                            </td>
                                            <td class="p-2 text-center text-xs border-r border-zinc-300 dark:border-zinc-700 uppercase">
                                                {{ $item['kategori'] ?? '-' }}
                                                <span class="block text-[10px] text-zinc-500">{{ $item['grade'] ?? '-' }}</span>
                                            </td>
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
                                        <tr>
                                            <td colspan="7" class="p-4 text-center text-zinc-500 dark:text-zinc-400 text-sm italic">
                                                Tidak ada barang dikerjakan.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>

                                <tfoot class="bg-zinc-100 dark:bg-zinc-800 border-t-2 border-zinc-300 dark:border-zinc-600">
                                    @if($punyaTarget)
                                        <tr>
                                            <td colspan="7" class="p-2 text-center text-[11px] text-zinc-500 dark:text-zinc-400 border-t border-zinc-300 dark:border-zinc-700">
                                                Capaian GLOBAL tim (jumlah persen semua ukuran, basis: target ADJUSTED ke total jam kerja tim):
                                                <strong class="{{ $tercapai ? 'text-green-600 dark:text-green-400' : 'text-red-500' }}">
                                                    {{ number_format($capaian, 1, ',', '.') }}%
                                                </strong>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td colspan="7" class="px-3 py-2 text-center border-t border-zinc-300 dark:border-zinc-700">
                                                <span class="text-xs font-semibold {{ $potTim > 0 ? 'text-red-500 dark:text-red-400' : 'text-zinc-500 dark:text-zinc-400' }}">
                                                    Potongan: Rp {{ number_format($potTim) }} / tim
                                                    @if($jmlPekerja > 0)
                                                        ÷ {{ $jmlPekerja }} orang
                                                        ≈ <strong>Rp {{ number_format($rataPerOrang) }}/orang</strong>
                                                    @endif
                                                </span>
                                                @if($data['potongan_melebihi_gaji'] ?? false)
                                                    <span class="ml-2 px-2 py-0.5 rounded bg-yellow-600 text-white text-[10px] font-bold">
                                                        ⚠ MELEBIHI GAJI NORMAL TIM (Rp {{ number_format($data['total_gaji_tim'] ?? 0) }})
                                                    </span>
                                                @endif
                                            </td>
                                        </tr>
                                    @else
                                        <tr>
                                            <td colspan="7" class="p-2 text-center text-[11px] text-red-500 border-t border-zinc-300 dark:border-zinc-700">
                                                Belum ada target untuk barang di produksi ini di Master Target (mesin + tebal + kategori), potongan belum bisa dihitung.
                                            </td>
                                        </tr>
                                    @endif
                                </tfoot>
                            </table>
                        </div>
                    </div>

                </div>
            </div>
        @empty
            <div class="p-16 text-center bg-zinc-50 dark:bg-zinc-900 rounded-xl border border-dashed border-zinc-300 dark:border-zinc-700">
                <x-heroicon-o-document-magnifying-glass class="w-12 h-12 mx-auto text-zinc-400 mb-4"/>
                <p class="text-zinc-500 italic text-lg">
                    Tidak ada data produksi Sanding untuk tanggal ini.
                </p>
            </div>
        @endforelse

        {{-- ================= REKAP PRODUKSI (dipakai juga untuk Excel) ================= --}}
        @if(!empty($reportData['detail']))
        <div class="bg-white dark:bg-zinc-900 rounded-sm shadow-lg border border-zinc-200 dark:border-zinc-700 overflow-hidden">
            <div class="bg-zinc-800 p-4 text-white text-center">
                <h2 class="text-lg font-bold uppercase tracking-widest">
                    REKAP PRODUKSI SANDING - {{ \Carbon\Carbon::parse($this->tanggal)->format('d F Y') }}
                </h2>
            </div>

            <div class="p-4 overflow-x-auto">
                <div class="flex flex-col lg:flex-row gap-8 min-w-[1400px]">

                    {{-- TABEL KIRI: DETAIL PRODUKSI --}}
                    <div class="flex-[2]">
                        <h3 class="text-sm font-bold mb-2 uppercase text-zinc-600 dark:text-zinc-400">Detail Produksi</h3>
                        <table class="w-full text-[11px] border-collapse border border-zinc-300 dark:border-zinc-700">
                            <thead>
                                <tr class="bg-zinc-100 dark:bg-zinc-800 text-zinc-800 dark:text-zinc-200 uppercase font-bold">
                                    <th class="p-2 border border-zinc-300 dark:border-zinc-700">Tanggal</th>
                                    <th class="p-2 border border-zinc-300 dark:border-zinc-700 text-left">Mesin</th>
                                    <th class="p-2 border border-zinc-300 dark:border-zinc-700">P</th>
                                    <th class="p-2 border border-zinc-300 dark:border-zinc-700">L</th>
                                    <th class="p-2 border border-zinc-300 dark:border-zinc-700">T</th>
                                    <th class="p-2 border border-zinc-300 dark:border-zinc-700 text-left">Jenis</th>
                                    <th class="p-2 border border-zinc-300 dark:border-zinc-700 text-center">Banyak</th>
                                    <th class="p-2 border border-zinc-300 dark:border-zinc-700 text-right bg-blue-50 dark:bg-blue-900/20">m3</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                                @foreach($reportData['detail'] as $d)
                                <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/50">
                                    <td class="p-2 border border-zinc-300 dark:border-zinc-700 text-center">{{ $d['tanggal'] }}</td>
                                    <td class="p-2 border border-zinc-300 dark:border-zinc-700">{{ $d['mesin'] }}</td>
                                    <td class="p-2 border border-zinc-300 dark:border-zinc-700 text-center">{{ $d['p'] }}</td>
                                    <td class="p-2 border border-zinc-300 dark:border-zinc-700 text-center">{{ $d['l'] }}</td>
                                    <td class="p-2 border border-zinc-300 dark:border-zinc-700 text-center">{{ $d['t'] }}</td>
                                    <td class="p-2 border border-zinc-300 dark:border-zinc-700">{{ $d['jenis'] }}</td>
                                    <td class="p-2 border border-zinc-300 dark:border-zinc-700 text-center font-bold">{{ number_format($d['banyak']) }}</td>
                                    <td class="p-2 border border-zinc-300 dark:border-zinc-700 text-right font-mono bg-blue-50/50 dark:bg-blue-900/10"></td>
                                </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                @php
                                    $totalBanyak = collect($reportData['detail'])->sum('banyak');
                                @endphp
                                <tr class="bg-zinc-100 dark:bg-zinc-800 font-bold">
                                    <td colspan="6" class="p-2 text-right border border-zinc-300 dark:border-zinc-700">TOTAL:</td>
                                    <td class="p-2 text-center border border-zinc-300 dark:border-zinc-700">{{ number_format($totalBanyak) }}</td>
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
                                    <th class="p-2 border border-zinc-300 dark:border-zinc-700 text-left">Mesin</th>
                                    <th class="p-2 border border-zinc-300 dark:border-zinc-700 text-center">Jml Pekerja</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                                @foreach($reportData['summary'] as $s)
                                <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/50">
                                    <td class="p-2 border border-zinc-300 dark:border-zinc-700 text-center">{{ $s['tanggal'] }}</td>
                                    <td class="p-2 border border-zinc-300 dark:border-zinc-700">{{ $s['mesin'] }}</td>
                                    <td class="p-2 border border-zinc-300 dark:border-zinc-700 text-center font-bold">{{ $s['jml_pkj'] }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                        <div class="mt-4 p-4 bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded text-[10px] text-yellow-800 dark:text-yellow-200 italic">
                            * Kolom Hasil Kubikasi, Harga, Ongkos(m3), dan Ongkos(lbr) akan tersedia sebagai kolom kosong di Excel untuk diisi oleh Manajemen.
                        </div>
                    </div>

                </div>
            </div>
        </div>
        @endif
    </div>
</x-filament-panels::page>