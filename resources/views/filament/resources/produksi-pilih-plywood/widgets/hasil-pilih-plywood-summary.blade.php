<x-filament::widget>
    @php
        $summaryData = [];
        $grandTotalBahan = 0;
        $grandTotalCacat = 0;

        if ($record) {
            // Ambil semua bahan yang diinput untuk produksi ini
            $bahans = $record->bahanPilihPlywood()->with(['barangSetengahJadiHp.jenisBarang', 'barangSetengahJadiHp.ukuran', 'barangSetengahJadiHp.grade'])->get();

            foreach ($bahans as $bahan) {
                $barangId = $bahan->id_barang_setengah_jadi_hp;
                
                // Hitung cacat khusus untuk barang ini saja
                $cacatBarang = $record->hasilPilihPlywood()
                    ->where('id_barang_setengah_jadi_hp', $barangId)
                    ->sum('jumlah') ?? 0;

                $namaBarang = ($bahan->barangSetengahJadiHp->jenisBarang->nama_jenis_barang ?? '-') . ' (' . 
                             ($bahan->barangSetengahJadiHp->ukuran->nama_ukuran ?? '-') . ')';

                $summaryData[] = [
                    'nama' => $namaBarang,
                    'bahan' => $bahan->jumlah,
                    'cacat' => $cacatBarang,
                    'good' => $bahan->jumlah - $cacatBarang
                ];

                $grandTotalBahan += $bahan->jumlah;
                $grandTotalCacat += $cacatBarang;
            }
        }
    @endphp

    <div class="space-y-4">
        {{-- TOTAL GLOBAL (Tetap ditampilkan sebagai ringkasan utama) --}}
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="p-4 bg-white border border-gray-200 rounded-xl shadow-sm dark:bg-gray-900 dark:border-gray-700">
                <span class="text-xs font-bold uppercase text-gray-500">Total Bahan (Global)</span>
                <div class="text-2xl font-black">{{ number_format($grandTotalBahan) }} <span class="text-sm font-normal">Pcs</span></div>
            </div>
            <div class="p-4 bg-white border border-gray-200 rounded-xl shadow-sm dark:bg-gray-900 dark:border-gray-700 border-b-4 border-b-danger-500">
                <span class="text-xs font-bold uppercase text-danger-600">Total Cacat (Global)</span>
                <div class="text-2xl font-black text-danger-600">{{ number_format($grandTotalCacat) }} <span class="text-sm font-normal">Pcs</span></div>
            </div>
            <div class="p-4 bg-primary-50 border border-primary-200 rounded-xl shadow-sm dark:bg-primary-950 dark:border-primary-800 border-b-4 border-b-success-500">
                <span class="text-xs font-bold uppercase text-success-600">Hasil Bagus (Global)</span>
                <div class="text-2xl font-black text-success-600">{{ number_format($grandTotalBahan - $grandTotalCacat) }} <span class="text-sm font-normal">Pcs</span></div>
            </div>
        </div>

        {{-- DETAIL PER BARANG --}}
        <div class="bg-white border border-gray-200 rounded-xl shadow-sm dark:bg-gray-900 dark:border-gray-700 overflow-hidden">
            <table class="w-full text-left text-sm">
                <thead class="bg-gray-50 dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700">
                    <tr>
                        <th class="px-4 py-2 font-bold">Rincian Per Barang</th>
                        <th class="px-4 py-2 text-center">Bahan</th>
                        <th class="px-4 py-2 text-center text-danger-600">Cacat</th>
                        <th class="px-4 py-2 text-center text-success-600">Hasil Bagus</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @foreach($summaryData as $data)
                    <tr>
                        <td class="px-4 py-3 font-medium">{{ $data['nama'] }}</td>
                        <td class="px-4 py-3 text-center">{{ number_format($data['bahan']) }}</td>
                        <td class="px-4 py-3 text-center font-bold text-danger-600">{{ number_format($data['cacat']) }}</td>
                        <td class="px-4 py-3 text-center font-bold text-success-600 bg-success-50/30">{{ number_format($data['good']) }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</x-filament::widget>