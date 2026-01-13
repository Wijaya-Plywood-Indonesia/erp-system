<x-filament-panels::page>
    {{-- Form Filter Tanggal --}}
    <div class="bg-white dark:bg-gray-900 p-4 rounded-lg shadow mb-4">
        {{ $this->form }}
    </div>

    <div wire:loading wire:target="loadAllData" class="w-full text-center py-4">
        <span class="text-gray-500">Memuat data...</span>
    </div>

    <div wire:loading.remove>
        @forelse($dataKedi as $data)

        <div class="mb-8 border border-gray-700 rounded-lg overflow-hidden bg-gray-900 text-white shadow-lg">

            {{-- HEADER --}}
            <div class="p-4 bg-gray-800 border-b border-gray-700 flex justify-between items-center">
                <h3 class="text-lg font-bold">
                    LAPORAN PRODUKSI KEDI - {{ $data['tanggal'] }}
                </h3>
                <span class="px-3 py-1 text-xs font-semibold rounded bg-blue-600">
                    Status: {{ $data['status'] }}
                </span>
            </div>

            {{-- VALIDASI --}}
            <div class="p-4 bg-gray-800/50 text-sm border-b border-gray-700">
                <span class="text-gray-400 font-semibold">Validasi:</span>
                <span class="text-green-400 font-bold">
                    {{ $data['validasi_terakhir'] }}
                </span>
                <span class="text-gray-400">
                    ({{ $data['validasi_oleh'] }})
                </span>
            </div>

            {{-- ================= DETAIL MASUK ================= --}}
            @if(!empty($data['detail_masuk']))
            <div class="p-4">
                <h4 class="font-semibold mb-3 text-yellow-400">Detail Masuk</h4>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left text-gray-300">
                        <thead class="text-xs uppercase bg-gray-800 text-gray-400">
                            <tr>
                                <th class="px-4 py-2">No Palet</th>
                                <th class="px-4 py-2">Mesin</th>
                                <th class="px-4 py-2">Ukuran</th>
                                <th class="px-4 py-2">Jenis Kayu</th>
                                <th class="px-4 py-2">KW</th>
                                <th class="px-4 py-2">Jumlah</th>
                                <th class="px-4 py-2">Rencana Bongkar</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($data['detail_masuk'] as $row)
                            <tr class="border-b border-gray-800 hover:bg-gray-800">
                                <td class="px-4 py-2">{{ $row['no_palet'] }}</td>
                                <td class="px-4 py-2">{{ $row['mesin'] }}</td>
                                <td class="px-4 py-2">{{ $row['ukuran'] }}</td>
                                <td class="px-4 py-2">{{ $row['jenis_kayu'] }}</td>
                                <td class="px-4 py-2">{{ $row['kw'] }}</td>
                                <td class="px-4 py-2 font-bold text-green-400">{{ $row['jumlah'] }}</td>
                                <td class="px-4 py-2">{{ $row['rencana_bongkar'] }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            @endif

            {{-- ================= DETAIL BONGKAR ================= --}}
            @if(!empty($data['detail_bongkar']))
            <div class="p-4 border-t border-gray-700">
                <h4 class="font-semibold mb-3 text-blue-400">Detail Bongkar</h4>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left text-gray-300">
                        <thead class="text-xs uppercase bg-gray-800 text-gray-400">
                            <tr>
                                <th class="px-4 py-2">No Palet</th>
                                <th class="px-4 py-2">Mesin</th>
                                <th class="px-4 py-2">Ukuran</th>
                                <th class="px-4 py-2">Jenis Kayu</th>
                                <th class="px-4 py-2">KW</th>
                                <th class="px-4 py-2">Jumlah</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($data['detail_bongkar'] as $row)
                            <tr class="border-b border-gray-800 hover:bg-gray-800">
                                <td class="px-4 py-2">{{ $row['no_palet'] }}</td>
                                <td class="px-4 py-2">{{ $row['mesin'] }}</td>
                                <td class="px-4 py-2">{{ $row['ukuran'] }}</td>
                                <td class="px-4 py-2">{{ $row['jenis_kayu'] }}</td>
                                <td class="px-4 py-2">{{ $row['kw'] }}</td>
                                <td class="px-4 py-2 font-bold text-green-400">{{ $row['jumlah'] }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            @endif

        </div>

        @empty
        <div class="p-6 text-center bg-gray-800 rounded-lg">
            <p class="text-gray-400">
                Belum ada data Produksi Kedi untuk tanggal ini.
            </p>
        </div>
        @endforelse
    </div>
</x-filament-panels::page>
