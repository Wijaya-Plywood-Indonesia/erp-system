<x-filament-panels::page>
    <div class="p-4 bg-white dark:bg-zinc-900 rounded-lg shadow">
        {{ $this->form }}
    </div>

    <div wire:loading wire:target="loadAllData" class="w-full text-center py-4">
        <x-filament::loading-indicator class="w-8 h-8 mx-auto text-primary-600 mb-2" />
        <span class="text-zinc-500 italic">Memproses laporan Hot Press...</span>
    </div>

    <div wire:loading.remove class="space-y-12 mt-6">
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
