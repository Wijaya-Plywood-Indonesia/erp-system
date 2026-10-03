<x-filament-panels::page>
    <div class="p-4 bg-white dark:bg-zinc-900 rounded-lg shadow">
        {{ $this->form }}
    </div>

    <div wire:loading wire:target="loadAllData" class="w-full text-center py-4">
        <x-filament::loading-indicator class="w-8 h-8 mx-auto text-primary-600 mb-2" />
        <span class="text-zinc-500 italic">Memproses laporan Produksi Dempul...</span>
    </div>

    <div wire:loading.remove class="space-y-12 mt-6">
        {{-- ================= TARGET & POTONGAN DEMPUL ================= --}}
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
                        TARGET DEMPUL / MALIK PLATFORM - {{ $data['tanggal'] }}
                    </h2>
                    <div class="flex gap-4 items-center">
                        @if($punyaTarget)
                            <span class="text-xs px-2 py-1 rounded {{ $tercapai ? 'bg-green-700' : 'bg-red-700' }}">
                                Capaian: {{ number_format($capaian, 1, ',', '.') }}%
                            </span>
                        @endif
                        @if($potTim > 0)
                            <span class="text-xs px-2 py-1 rounded bg-amber-600 font-bold" title="Total pasangan dibagi rata jumlah pekerja">
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
                        <div class="min-w-[800px]">
                            <table class="w-full text-sm border-collapse border border-zinc-300 dark:border-zinc-600">
                                <thead>
                                    <tr>
                                        <th colspan="6" class="p-3 text-lg font-bold text-center bg-zinc-700 text-white">
                                            DATA PEKERJA
                                        </th>
                                    </tr>
                                    <tr class="bg-zinc-200 dark:bg-zinc-700 text-zinc-800 dark:text-zinc-300 border-t border-zinc-300 dark:border-zinc-600">
                                        <th class="p-2 text-center text-xs font-medium w-16">ID</th>
                                        <th class="p-2 text-left text-xs font-medium w-40">Nama</th>
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
                                            <td class="p-2 text-center text-xs border-r border-zinc-300 dark:border-zinc-700">{{ $p['jam_masuk'] ?? '-' }}</td>
                                            <td class="p-2 text-center text-xs border-r border-zinc-300 dark:border-zinc-700">{{ $p['jam_pulang'] ?? '-' }}</td>
                                            <td class="p-2 text-center text-xs border-r border-zinc-300 dark:border-zinc-700 text-yellow-600 dark:text-yellow-400">{{ $p['ijin'] ?? '-' }}</td>
                                            <td class="p-2 text-right text-xs font-bold {{ $potTarget > 0 ? 'text-red-500' : '' }}">
                                                {{ $potTarget > 0 ? 'Rp ' . number_format($potTarget) : '-' }}
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="6" class="p-4 text-center text-zinc-500 dark:text-zinc-400 text-sm italic">
                                                Tidak ada data pekerja untuk produksi ini.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                                <tfoot class="bg-zinc-100 dark:bg-zinc-800 border-t-2 border-zinc-300 dark:border-zinc-600">
                                    <tr>
                                        <td colspan="6" class="p-2 text-center text-[11px] text-zinc-500 dark:text-zinc-400">
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
                        <div class="min-w-[800px]">
                            <table class="w-full text-sm border-collapse border border-zinc-300 dark:border-zinc-600">
                                <thead>
                                    <tr>
                                        <th colspan="5" class="p-3 text-lg font-bold text-center bg-zinc-700 text-white">
                                            DATA BARANG DIKERJAKAN
                                        </th>
                                    </tr>
                                    <tr class="bg-zinc-200 dark:bg-zinc-700 text-zinc-800 dark:text-zinc-300 border-t border-zinc-300 dark:border-zinc-600">
                                        <th class="p-2 text-left text-xs font-medium">Ukuran</th>
                                        <th class="p-2 text-left text-xs font-medium">Jenis</th>
                                        <th class="p-2 text-left text-xs font-medium">Grade</th>
                                        <th class="p-2 text-center text-xs font-medium w-24">No. Palet</th>
                                        <th class="p-2 text-right text-xs font-medium w-24">Hasil</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($data['per_barang'] as $i => $item)
                                        <tr class="{{ $i % 2 === 1 ? 'bg-zinc-50 dark:bg-zinc-800/50' : 'bg-white dark:bg-zinc-900' }} border-t border-zinc-300 dark:border-zinc-700">
                                            <td class="p-2 text-left text-xs border-r border-zinc-300 dark:border-zinc-700 font-medium">{{ $item['ukuran'] }}</td>
                                            <td class="p-2 text-left text-xs border-r border-zinc-300 dark:border-zinc-700">{{ $item['jenis'] }}</td>
                                            <td class="p-2 text-left text-xs border-r border-zinc-300 dark:border-zinc-700">{{ $item['grade'] }}</td>
                                            <td class="p-2 text-center text-xs border-r border-zinc-300 dark:border-zinc-700">{{ $item['nomor_palet'] }}</td>
                                            <td class="p-2 text-right text-xs font-bold text-green-600 dark:text-green-400">{{ number_format($item['hasil']) }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="p-4 text-center text-zinc-500 dark:text-zinc-400 text-sm italic">
                                                Tidak ada barang dikerjakan.
                                            </td>
                                        </tr>
                                    @endforelse

                                    @if(!empty($data['per_barang']))
                                        <tr class="bg-zinc-200 dark:bg-zinc-800 border-t-2 border-zinc-400 dark:border-zinc-600 font-bold">
                                            <td colspan="4" class="p-2 text-left text-xs border-r border-zinc-300 dark:border-zinc-700">
                                                TOTAL (dibanding 1 target Dempul/Malik Platform)
                                            </td>
                                            <td class="p-2 text-right text-xs text-green-600 dark:text-green-400">
                                                {{ number_format($data['hasil_total'] ?? 0) }}
                                            </td>
                                        </tr>
                                    @endif
                                </tbody>
                                <tfoot class="bg-zinc-100 dark:bg-zinc-800 border-t-2 border-zinc-300 dark:border-zinc-600">
                                    @if($punyaTarget)
                                        <tr>
                                            <td colspan="5" class="p-2 text-center text-[11px] text-zinc-500 dark:text-zinc-400 border-t border-zinc-300 dark:border-zinc-700">
                                                Target ADJUSTED (ke jumlah orang & jam kerja pasangan ini):
                                                <strong class="text-zinc-900 dark:text-white">{{ number_format($data['target_total'] ?? 0) }}</strong>
                                                <span class="text-zinc-400">
                                                    (normal: {{ number_format($data['target_normal'] ?? 0) }} / {{ number_format($data['target_normal_jam'] ?? 0) }} jam / {{ $data['target_normal_orang'] ?? 0 }} org)
                                                </span>
                                                | Selisih:
                                                <strong class="{{ ($data['selisih_total'] ?? 0) >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-500' }}">
                                                    {{ ($data['selisih_total'] ?? 0) >= 0 ? '+' : '' }}{{ number_format($data['selisih_total'] ?? 0) }}
                                                </strong>
                                                | Capaian:
                                                <strong class="{{ $tercapai ? 'text-green-600 dark:text-green-400' : 'text-red-500' }}">
                                                    {{ number_format($capaian, 1, ',', '.') }}%
                                                </strong>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td colspan="5" class="px-3 py-2 text-center border-t border-zinc-300 dark:border-zinc-700">
                                                <span class="text-xs font-semibold {{ $potTim > 0 ? 'text-red-500 dark:text-red-400' : 'text-zinc-500 dark:text-zinc-400' }}">
                                                    Potongan: Rp {{ number_format($potTim) }} / pasangan
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
                                            <td colspan="5" class="p-2 text-center text-[11px] text-red-500 border-t border-zinc-300 dark:border-zinc-700">
                                                @if($data['tanpa_pasangan'] ?? false)
                                                    Barang ini belum ditandai dikerjakan pasangan siapa (pegawai belum dipilih di Hasil Dempul), potongan tidak dihitung.
                                                @else
                                                    Belum ada target Dempul di Master Target (mesin DEMPUL), potongan belum bisa dihitung.
                                                @endif
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
        {{-- ================= AKHIR TARGET & POTONGAN DEMPUL ================= --}}

        @if(!empty($reportData['detail']))
        <div class="bg-white dark:bg-zinc-900 rounded-sm shadow-lg border border-zinc-200 dark:border-zinc-700 overflow-hidden">
            <div class="bg-zinc-800 p-4 text-white text-center">
                <h2 class="text-lg font-bold uppercase tracking-widest">
                    LAPORAN PRODUKSI DEMPUL - {{ \Carbon\Carbon::parse($this->tanggal)->format('d F Y') }}
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
                                    <th class="p-2 border border-zinc-300 dark:border-zinc-700 text-left">Grade</th>
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
                                    <td class="p-2 border border-zinc-300 dark:border-zinc-700">{{ $d['grade'] }}</td>
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
                                    <td colspan="6" class="p-2 text-right border border-zinc-300 dark:border-zinc-700">TOTAL:</td>
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
                                    <th class="p-2 border border-zinc-300 dark:border-zinc-700 text-right">Total m3</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                                @foreach($reportData['summary'] as $s)
                                <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/50">
                                    <td class="p-2 border border-zinc-300 dark:border-zinc-700 text-center">{{ $s['tanggal'] }}</td>
                                    <td class="p-2 border border-zinc-300 dark:border-zinc-700 text-center font-bold">{{ $s['ttl_pkj'] }}</td>
                                    <td class="p-2 border border-zinc-300 dark:border-zinc-700 text-right font-mono"></td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                        <div class="mt-4 p-4 bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded text-[10px] text-yellow-800 dark:text-yellow-200 italic">
                            * Kolom HARGA, Total m3, ONGKOS PER M3, dan ONGKOS PER LB akan tersedia sebagai kolom kosong di Excel untuk diisi oleh Manajemen.
                        </div>
                    </div>

                </div>
            </div>
        </div>
        @else
        <div class="p-16 text-center bg-zinc-50 dark:bg-zinc-900 rounded-xl border border-dashed border-zinc-300 dark:border-zinc-700">
            <x-heroicon-o-document-magnifying-glass class="w-12 h-12 mx-auto text-zinc-400 mb-4"/>
            <p class="text-zinc-500 italic text-lg">
                Tidak ada data produksi Dempul untuk tanggal ini.
            </p>
        </div>
        @endif
    </div>
</x-filament-panels::page>