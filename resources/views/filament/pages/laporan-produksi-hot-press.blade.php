<x-filament-panels::page>
    <div class="p-4 bg-white dark:bg-zinc-900 rounded-lg shadow">
        {{ $this->form }}
    </div>

    <div wire:loading wire:target="loadAllData" class="w-full text-center py-4">
        <x-filament::loading-indicator class="w-8 h-8 mx-auto text-primary-600 mb-2" />
        <span class="text-zinc-500 italic">Memproses laporan Hot Press...</span>
    </div>

    <div wire:loading.remove class="space-y-12 mt-6">
                {{-- ================= TARGET & POTONGAN HOT PRESS ================= --}}
        @php
            $targetDataCollection = collect($targetData ?? []);
        @endphp

        @forelse ($targetDataCollection as $data)
            @php
                $punyaTarget = $data['punya_target'] ?? false;
                $capaian = $data['capaian_global'] ?? 0;
                $tercapai = $punyaTarget && $capaian >= 100;
                $potTim = $data['potongan_total_tim'] ?? 0;
                $jmlPekerja = $data['jumlah_pekerja'] ?? 0;
                $rataPerOrang = $jmlPekerja > 0 ? $potTim / $jmlPekerja : 0;
            @endphp

            <div class="bg-white dark:bg-zinc-900 rounded-sm shadow-lg border border-zinc-200 dark:border-zinc-700 overflow-hidden">
                <div class="bg-zinc-800 p-4 text-white flex justify-between items-center">
                    <h2 class="text-lg font-bold">
                        TARGET HOT PRESS - SHIFT {{ strtoupper($data['shift']) }} - {{ $data['tanggal'] }}
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

                    <div class="w-full overflow-x-auto">
                        <div class="min-w-[900px]">
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
                                        <th class="p-2 text-left text-xs font-medium w-28">Mesin</th>
                                        <th class="p-2 text-center text-xs font-medium w-20">Masuk</th>
                                        <th class="p-2 text-center text-xs font-medium w-20">Pulang</th>
                                        <th class="p-2 text-center text-xs font-medium w-16">Ijin</th>
                                        <th class="p-2 text-right text-xs font-medium w-36">Potongan Target</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($data['pekerja'] as $i => $p)
                                        @php $potTarget = (int) ($p['pot_target'] ?? 0); @endphp
                                        <tr class="{{ $i % 2 === 1 ? 'bg-zinc-50 dark:bg-zinc-800/50' : 'bg-white dark:bg-zinc-900' }} border-t border-zinc-300 dark:border-zinc-700">
                                            <td class="p-2 text-center text-xs border-r border-zinc-300 dark:border-zinc-700 font-mono">{{ $p['id'] ?? '-' }}</td>
                                            <td class="p-2 text-left text-xs border-r border-zinc-300 dark:border-zinc-700 font-medium">{{ $p['nama'] ?? '-' }}</td>
                                            <td class="p-2 text-left text-xs border-r border-zinc-300 dark:border-zinc-700 uppercase text-zinc-500">{{ $p['mesin'] ?? '-' }}</td>
                                            <td class="p-2 text-center text-xs border-r border-zinc-300 dark:border-zinc-700">{{ $p['jam_masuk'] ?? '-' }}</td>
                                            <td class="p-2 text-center text-xs border-r border-zinc-300 dark:border-zinc-700">{{ $p['jam_pulang'] ?? '-' }}</td>
                                            <td class="p-2 text-center text-xs border-r border-zinc-300 dark:border-zinc-700 text-yellow-600 dark:text-yellow-400">{{ $p['ijin'] ?? '-' }}</td>
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
                                            <span class="font-medium">Rata-rata Jam Aktual:</span>
                                            <strong class="font-mono text-zinc-900 dark:text-white">{{ number_format($data['jam_aktual_rata'] ?? 0, 2, ',', '.') }} jam</strong>
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>

                    <div class="w-full overflow-x-auto">
                        <div class="min-w-[900px]">
                            <table class="w-full text-sm border-collapse border border-zinc-300 dark:border-zinc-600">
                                <thead>
                                    <tr>
                                        <th colspan="6" class="p-3 text-lg font-bold text-center bg-zinc-700 text-white">
                                            DATA BARANG DIKERJAKAN
                                        </th>
                                    </tr>
                                    <tr class="bg-zinc-200 dark:bg-zinc-700 text-zinc-800 dark:text-zinc-300 border-t border-zinc-300 dark:border-zinc-600">
                                        <th class="p-2 text-left text-xs font-medium">Ukuran</th>
                                        <th class="p-2 text-left text-xs font-medium">Keterangan</th>
                                        <th class="p-2 text-right text-xs font-medium w-24">Hasil</th>
                                        <th class="p-2 text-right text-xs font-medium w-32">Target (Adjusted)</th>
                                        <th class="p-2 text-right text-xs font-medium w-24">Selisih</th>
                                        <th class="p-2 text-right text-xs font-medium w-20">Capaian</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($data['per_barang'] as $i => $item)
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
                                            <td class="p-2 text-left text-xs border-r border-zinc-300 dark:border-zinc-700 text-zinc-500">
                                                {{ $item['keterangan'] }}
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
                                            <td colspan="6" class="p-4 text-center text-zinc-500 dark:text-zinc-400 text-sm italic">
                                                Tidak ada barang dikerjakan.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                                <tfoot class="bg-zinc-100 dark:bg-zinc-800 border-t-2 border-zinc-300 dark:border-zinc-600">
                                    @if($punyaTarget)
                                        <tr>
                                            <td colspan="6" class="p-2 text-center text-[11px] text-zinc-500 dark:text-zinc-400 border-t border-zinc-300 dark:border-zinc-700">
                                                Capaian GLOBAL (jumlah persen semua barang, basis: target ADJUSTED ke jumlah orang & jam kerja tim):
                                                <strong class="{{ $tercapai ? 'text-green-600 dark:text-green-400' : 'text-red-500' }}">
                                                    {{ number_format($capaian, 1, ',', '.') }}%
                                                </strong>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td colspan="6" class="px-3 py-2 text-center border-t border-zinc-300 dark:border-zinc-700">
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
                                            <td colspan="6" class="p-2 text-center text-[11px] text-red-500 border-t border-zinc-300 dark:border-zinc-700">
                                                Belum ada target yang cocok di Master Target, potongan belum bisa dihitung.
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
        @endforelse
        {{-- ================= AKHIR TARGET & POTONGAN HOT PRESS ================= --}}
        @forelse($dataHp as $data)
        <div class="bg-white dark:bg-zinc-900 rounded-sm shadow-lg border border-zinc-200 dark:border-zinc-700 overflow-hidden">
            <div class="bg-zinc-800 p-4 text-white text-center">
                <h2 class="text-lg font-bold uppercase tracking-widest">
                    LAPORAN PRODUKSI HOT PRESS: {{ $data['machine'] }} - {{ $data['tanggal'] }}
                </h2>
            </div>

            <div class="p-4 overflow-x-auto">
                <div class="flex flex-col lg:flex-row gap-8 min-w-[1200px]">
                    
                    {{-- TABEL KIRI: BAHAN & BIAYA --}}
                    <div class="flex-1">
                        <table class="w-full text-[11px] border-collapse border border-zinc-300 dark:border-zinc-700">
                            <thead>
                                <tr class="bg-zinc-100 dark:bg-zinc-800 text-zinc-800 dark:text-zinc-200 uppercase font-bold">
                                    <th class="p-2 border border-zinc-300 dark:border-zinc-700">Mesin</th>
                                    <th class="p-2 border border-zinc-300 dark:border-zinc-700">Tgl</th>
                                    <th class="p-2 border border-zinc-300 dark:border-zinc-700">Kategori Bahan</th>
                                    <th class="p-2 border border-zinc-300 dark:border-zinc-700 text-left">BAHAN</th>
                                    <th class="p-2 border border-zinc-300 dark:border-zinc-700 text-center">BANYAK</th>
                                    <th class="p-2 border border-zinc-300 dark:border-zinc-700 text-right">HARGA</th>
                                    <th class="p-2 border border-zinc-300 dark:border-zinc-700 text-right bg-yellow-50 dark:bg-yellow-900/20">TOTAL</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                                @foreach($data['material_usage'] as $m)
                                <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/50">
                                    <td class="p-2 border border-zinc-300 dark:border-zinc-700 text-center text-zinc-500">{{ $data['machine'] }}</td>
                                    <td class="p-2 border border-zinc-300 dark:border-zinc-700 text-center text-zinc-500">{{ $data['tanggal'] }}</td>
                                    <td class="p-2 border border-zinc-300 dark:border-zinc-700 text-center text-zinc-500">{{ $m['kategori'] }}</td>
                                    <td class="p-2 border border-zinc-300 dark:border-zinc-700 font-medium">{{ $m['nama'] }}</td>
                                    <td class="p-2 border border-zinc-300 dark:border-zinc-700 text-center">{{ $m['banyak'] > 0 ? number_format($m['banyak'], 0, ',', '.') : '' }}</td>
                                    <td class="p-2 border border-zinc-300 dark:border-zinc-700 text-right">{{ $m['harga'] > 0 ? 'Rp ' . number_format($m['harga'], 0, ',', '.') : '' }}</td>
                                    <td class="p-2 border border-zinc-300 dark:border-zinc-700 text-right font-bold bg-yellow-50/50 dark:bg-yellow-900/10">{{ $m['total'] > 0 ? 'Rp ' . number_format($m['total'], 0, ',', '.') : '' }}</td>
                                </tr>
                                @endforeach
                                
                                {{-- BIAYA LAIN-LAIN --}}
                                <tr class="bg-zinc-50 dark:bg-zinc-800/30 font-semibold italic">
                                    <td class="p-2 border border-zinc-300 dark:border-zinc-700 text-center text-zinc-400">{{ $data['machine'] }}</td>
                                    <td class="p-2 border border-zinc-300 dark:border-zinc-700 text-center text-zinc-400">{{ $data['tanggal'] }}</td>
                                    <td class="p-2 border border-zinc-300 dark:border-zinc-700 text-center text-zinc-400">Biaya Lain Lain</td>
                                    <td class="p-2 border border-zinc-300 dark:border-zinc-700">Penyusutan</td>
                                    <td class="p-2 border border-zinc-300 dark:border-zinc-700 text-center">3</td>
                                    <td class="p-2 border border-zinc-300 dark:border-zinc-700 text-right">Rp 635.000</td>
                                    <td class="p-2 border border-zinc-300 dark:border-zinc-700 text-right font-bold bg-yellow-50/50 dark:bg-yellow-900/10">Rp {{ number_format($data['penyusutan'], 0, ',', '.') }}</td>
                                </tr>
                                <tr class="bg-zinc-50 dark:bg-zinc-800/30 font-semibold italic">
                                    <td class="p-2 border border-zinc-300 dark:border-zinc-700 text-center text-zinc-400">{{ $data['machine'] }}</td>
                                    <td class="p-2 border border-zinc-300 dark:border-zinc-700 text-center text-zinc-400">{{ $data['tanggal'] }}</td>
                                    <td class="p-2 border border-zinc-300 dark:border-zinc-700 text-center text-zinc-400">Biaya Lain Lain</td>
                                    <td class="p-2 border border-zinc-300 dark:border-zinc-700">Bulanan</td>
                                    <td class="p-2 border border-zinc-300 dark:border-zinc-700 text-center">1</td>
                                    <td class="p-2 border border-zinc-300 dark:border-zinc-700 text-right">Rp 220.000</td>
                                    <td class="p-2 border border-zinc-300 dark:border-zinc-700 text-right font-bold bg-yellow-50/50 dark:bg-yellow-900/10">Rp 220.000</td>
                                </tr>
                                <tr class="bg-zinc-50 dark:bg-zinc-800/30 font-semibold italic">
                                    <td class="p-2 border border-zinc-300 dark:border-zinc-700 text-center text-zinc-400">{{ $data['machine'] }}</td>
                                    <td class="p-2 border border-zinc-300 dark:border-zinc-700 text-center text-zinc-400">{{ $data['tanggal'] }}</td>
                                    <td class="p-2 border border-zinc-300 dark:border-zinc-700 text-center text-zinc-400">Biaya Lain Lain</td>
                                    <td class="p-2 border border-zinc-300 dark:border-zinc-700">Pekerja</td>
                                    <td class="p-2 border border-zinc-300 dark:border-zinc-700 text-center">{{ $data['total_pekerja'] }}</td>
                                    <td class="p-2 border border-zinc-300 dark:border-zinc-700 text-right">Rp {{ number_format($data['harga_pekerja'], 0, ',', '.') }}</td>
                                    <td class="p-2 border border-zinc-300 dark:border-zinc-700 text-right font-bold bg-yellow-50/50 dark:bg-yellow-900/10">Rp {{ number_format($data['total_pekerja'] * $data['harga_pekerja'], 0, ',', '.') }}</td>
                                </tr>
                            </tbody>
                            <tfoot>
                                @php
                                    $totalBahan = collect($data['material_usage'])->sum('total');
                                    $totalLain = $data['penyusutan'] + 220000 + ($data['total_pekerja'] * $data['harga_pekerja']);
                                    $grandTotal = $totalBahan + $totalLain;
                                @endphp
                                <tr class="bg-zinc-800 text-white font-bold text-xs">
                                    <td colspan="6" class="p-3 text-right uppercase tracking-widest">Grand Total Biaya:</td>
                                    <td class="p-3 text-right bg-yellow-500 text-black">Rp {{ number_format($grandTotal, 0, ',', '.') }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    {{-- TABEL KANAN: HASIL PRODUKSI --}}
                    <div class="flex-1">
                        <table class="w-full text-[11px] border-collapse border border-zinc-300 dark:border-zinc-700">
                            <thead>
                                <tr class="bg-zinc-100 dark:bg-zinc-800 text-zinc-800 dark:text-zinc-200 uppercase font-bold">
                                    <th class="p-2 border border-zinc-300 dark:border-zinc-700">NO</th>
                                    <th class="p-2 border border-zinc-300 dark:border-zinc-700">Mesin</th>
                                    <th class="p-2 border border-zinc-300 dark:border-zinc-700">TGL</th>
                                    <th class="p-2 border border-zinc-300 dark:border-zinc-700">P</th>
                                    <th class="p-2 border border-zinc-300 dark:border-zinc-700">L</th>
                                    <th class="p-2 border border-zinc-300 dark:border-zinc-700">T</th>
                                    <th class="p-2 border border-zinc-300 dark:border-zinc-700 text-center">BANYAK</th>
                                    <th class="p-2 border border-zinc-300 dark:border-zinc-700 text-left">Jenis Kayu</th>
                                    <th class="p-2 border border-zinc-300 dark:border-zinc-700 text-left">Kwalitas</th>
                                    <th class="p-2 border border-zinc-300 dark:border-zinc-700 text-right bg-blue-50 dark:bg-blue-900/20">Kubikasi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                                @foreach($data['hasil'] as $index => $h)
                                <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/50">
                                    <td class="p-2 border border-zinc-300 dark:border-zinc-700 text-center">{{ $index + 1 }}</td>
                                    <td class="p-2 border border-zinc-300 dark:border-zinc-700 text-center">{{ $data['machine'] }}</td>
                                    <td class="p-2 border border-zinc-300 dark:border-zinc-700 text-center">{{ $data['tanggal'] }}</td>
                                    <td class="p-2 border border-zinc-300 dark:border-zinc-700 text-center bg-blue-50/30 dark:bg-blue-900/5">{{ $h['p'] }}</td>
                                    <td class="p-2 border border-zinc-300 dark:border-zinc-700 text-center bg-blue-50/30 dark:bg-blue-900/5">{{ $h['l'] }}</td>
                                    <td class="p-2 border border-zinc-300 dark:border-zinc-700 text-center bg-blue-50/30 dark:bg-blue-900/5">{{ $h['t'] }}</td>
                                    <td class="p-2 border border-zinc-300 dark:border-zinc-700 text-center font-bold text-primary-600">{{ $h['isi'] }}</td>
                                    <td class="p-2 border border-zinc-300 dark:border-zinc-700">{{ $h['jenis_kayu'] }}</td>
                                    <td class="p-2 border border-zinc-300 dark:border-zinc-700">{{ $h['kwalitas'] }}</td>
                                    <td class="p-2 border border-zinc-300 dark:border-zinc-700 text-right font-mono bg-blue-50/50 dark:bg-blue-900/10">{{ number_format($h['kubikasi'], 4, ',', '.') }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                @php
                                    $totalBanyak = collect($data['hasil'])->sum('isi');
                                    $totalKubikasi = collect($data['hasil'])->sum('kubikasi');
                                @endphp
                                <tr class="bg-zinc-100 dark:bg-zinc-800 font-bold">
                                    <td colspan="6" class="p-2 text-right border border-zinc-300 dark:border-zinc-700">TOTAL:</td>
                                    <td class="p-2 text-center border border-zinc-300 dark:border-zinc-700 text-primary-600">{{ number_format($totalBanyak) }}</td>
                                    <td colspan="2" class="p-2 border border-zinc-300 dark:border-zinc-700"></td>
                                    <td class="p-2 text-right border border-zinc-300 dark:border-zinc-700 font-mono">{{ number_format($totalKubikasi, 4, ',', '.') }}</td>
                                </tr>
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
                Tidak ada data produksi Hot Press untuk tanggal ini.
            </p>
        </div>
        @endforelse
    </div>
</x-filament-panels::page>
