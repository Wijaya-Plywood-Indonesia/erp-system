<x-filament-panels::page>
    {{-- Form Filter Tanggal --}}
    <div class="bg-white dark:bg-gray-900 p-4 rounded-lg shadow mb-4">
        {{ $this->form }}
    </div>

    <div wire:loading wire:target="loadAllData" class="w-full text-center py-4">
        <span class="text-gray-500">Memuat data...</span>
    </div>

    <div wire:loading.remove>
        @forelse($dataStik as $data)
            @php
                // Hitung Selisih untuk Tampilan
                $selisih = $data['selisih'] ?? 0;
                // Nilai selisih dibalik (dikalikan -1) agar jika kurang target, nilainya negatif di tampilan, sesuai contoh Anda.
                $selisihTampil = $selisih * -1; 
                
                // Logika Warna
                // Selisih: Hijau jika <= 0 (Target tercapai), Merah jika > 0 (Kurang Target)
                $warnaSelisih = $selisih <= 0 ? 'text-green-400' : 'text-red-400';
            @endphp
            
            <div class="mb-8 border border-gray-700 rounded-lg overflow-hidden bg-gray-900 text-white shadow-lg">
                
                {{-- Header Section Report --}}
                <div class="p-4 bg-gray-800 border-b border-gray-700 flex justify-between items-center">
                    <h3 class="text-lg font-bold text-white">
                        LAPORAN PRODUKSI STIK - {{ $data['tanggal'] ?? '-' }}
                    </h3>
                    <span class="px-3 py-1 text-xs font-semibold rounded bg-blue-600 text-white">
                        Target: {{ number_format($data['target_harian'] ?? 0, 0, ',', '.') }}
                    </span>
                </div>
                
                {{-- Kendala (Dipindahkan ke atas tabel, di bawah header) --}}
                <div class="p-4 bg-gray-800/50 text-sm border-b border-gray-700">
                    <span class="text-gray-400 font-semibold">Kendala:</span> 
                    <span class="font-bold text-yellow-500 text-xs">{{ $data['kendala'] ?? 'Tidak ada kendala.' }}</span>
                </div>

                {{-- Table Data Pekerja --}}
                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left text-gray-300">
                        <thead class="text-xs text-gray-400 uppercase bg-gray-800">
                            <tr>
                                <th scope="col" class="px-6 py-3">ID</th>
                                <th scope="col" class="px-6 py-3">Nama</th>
                                <th scope="col" class="px-6 py-3">Masuk</th>
                                <th scope="col" class="px-6 py-3">Pulang</th>
                                <th scope="col" class="px-6 py-3">Ijin</th>
                                <th scope="col" class="px-6 py-3">Keterangan</th>
                                <th scope="col" class="px-6 py-3 text-red-400">Potongan Target</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($data['pekerja'] as $pekerja)
                                <tr class="bg-gray-900 border-b border-gray-800 hover:bg-gray-800">
                                    <td class="px-6 py-4">{{ $pekerja['id'] ?? '-' }}</td>
                                    <td class="px-6 py-4 font-medium text-white">{{ $pekerja['nama'] ?? '-' }}</td>
                                    <td class="px-6 py-4">{{ $pekerja['jam_masuk'] ?? '-' }}</td>
                                    <td class="px-6 py-4">{{ $pekerja['jam_pulang'] ?? '-' }}</td>
                                    <td class="px-6 py-4">{{ $pekerja['ijin'] ?? '-' }}</td>
                                    <td class="px-6 py-4">{{ $pekerja['keterangan'] ?? '-' }}</td>
                                    <td class="px-6 py-4 text-red-400 font-bold">
                                        {{ $pekerja['pot_target'] !== '-' ? 'Rp ' . $pekerja['pot_target'] : '-' }}
                                    </td>
                                </tr>
                            @endforeach
                            @if(empty($data['pekerja']))
                                <tr>
                                    <td colspan="7" class="px-6 py-4 text-center text-gray-500">
                                        Tidak ada data pekerja
                                    </td>
                                </tr>
                            @endif
                        </tbody>
                        
                        {{-- FOOTER: Ringkasan data dipindahkan ke bawah tabel --}}
                        <tfoot class="bg-gray-800 text-xs font-semibold border-t border-gray-700">
                            <tr>
                                <td colspan="7" class="px-6 py-3 text-right">
                                    <span class="mr-4">Pekerja: <strong class="text-yellow-400">{{ $data['summary']['jumlah_pekerja'] ?? 0 }}</strong> |</span>
                                    <span class="mr-4">Target: <strong>{{ number_format($data['target_harian'] ?? 0, 0, ',', '.') }}</strong> |</span>
                                    <span class="mr-4">Jam Produksi: <strong>{{ $data['jam_kerja'] ?? 0 }}</strong> |</span>
                                    <span class="mr-4">Hasil: <strong class="text-green-400">{{ number_format($data['hasil_harian'] ?? 0, 0, ',', '.') }}</strong> |</span>
                                    <span class="mr-4">Selisih: <strong class="{{ $warnaSelisih }}">{{ number_format($selisihTampil, 0, ',', '.') }}</strong> |</span>
                                    <span>Tanggal: <strong>{{ $data['tanggal'] ?? '-' }}</strong></span>
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        @empty
            <div class="p-6 text-center bg-gray-800 rounded-lg">
                <p class="text-gray-400">Belum ada data produksi stik untuk tanggal ini.</p>
            </div>
        @endforelse
    </div>
</x-filament-panels::page>