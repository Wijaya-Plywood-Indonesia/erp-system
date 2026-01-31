<x-filament::widget>
    @php
    $summaryData = [];
    $grandTotalBahan = 0;
    $grandTotalCacat = 0;
    $totalPekerja = 0;

    if ($record) {
    $totalPekerja = $record->pegawaiPilihPlywood()
    ->whereNotNull('id_pegawai')
    ->distinct('id_pegawai')
    ->count('id_pegawai');

    $bahans = $record->bahanPilihPlywood()->with(['barangSetengahJadiHp.jenisBarang', 'barangSetengahJadiHp.ukuran', 'barangSetengahJadiHp.grade'])->get();

    foreach ($bahans as $bahan) {
    $barangId = $bahan->id_barang_setengah_jadi_hp;

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
        <div class="p-4 bg-white border border-gray-200 rounded-xl shadow-sm dark:bg-gray-900 dark:border-gray-800 border-l-4 border-l-gray-300 dark:border-l-gray-600">
            <span class="text-xs font-semibold tracking-wider uppercase text-gray-500 dark:text-gray-400">Total Pekerja</span>
            <div class="text-2xl font-black text-gray-800 dark:text-gray-100">{{ number_format($totalPekerja) }} <span class="text-sm font-medium opacity-60">Orang</span></div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="p-4 bg-white border border-gray-200 rounded-xl shadow-sm dark:bg-gray-900 dark:border-gray-800 border-l-4 border-l-gray-300 dark:border-l-gray-600">
                <span class="text-xs font-semibold tracking-wider uppercase text-gray-500 dark:text-gray-400">Total Bahan</span>
                <div class="text-2xl font-black text-gray-700 dark:text-gray-200">{{ number_format($grandTotalBahan) }} <span class="text-sm font-medium opacity-50">Pcs</span></div>
            </div>

            <div class="p-4 bg-white border border-gray-200 rounded-xl shadow-sm dark:bg-gray-900 dark:border-gray-800 border-l-4 border-l-red-400">
                <span class="text-xs font-semibold tracking-wider uppercase text-gray-500 dark:text-gray-400">Total Cacat</span>
                <div class="text-2xl font-black text-red-500 dark:text-red-400/90">{{ number_format($grandTotalCacat) }} <span class="text-sm font-medium opacity-50">Pcs</span></div>
            </div>

            <div class="p-4 bg-white border border-gray-200 rounded-xl shadow-sm dark:bg-gray-900 dark:border-gray-800 border-l-4 border-l-green-400">
                <span class="text-xs font-semibold tracking-wider uppercase text-gray-500 dark:text-gray-400">Hasil Bagus</span>
                <div class="text-2xl font-black text-green-600 dark:text-green-400/90">{{ number_format($grandTotalBahan - $grandTotalCacat) }} <span class="text-sm font-medium opacity-50">Pcs</span></div>
            </div>
        </div>

        <div class="bg-white border border-gray-200 rounded-xl shadow-sm dark:bg-gray-900 dark:border-gray-800 overflow-hidden">
            <table class="w-full text-left text-sm">
                <thead class="bg-gray-50/50 dark:bg-gray-800/50 border-b border-gray-200 dark:border-gray-800">
                    <tr>
                        <th class="px-4 py-3 font-bold text-gray-600 dark:text-gray-400 italic">Rincian Per Barang</th>
                        <th class="px-4 py-3 text-center text-gray-600 dark:text-gray-400 uppercase tracking-tighter text-[10px]">Bahan</th>
                        <th class="px-4 py-3 text-center text-red-500 dark:text-red-400 uppercase tracking-tighter text-[10px] font-bold">Cacat</th>
                        <th class="px-4 py-3 text-center text-green-600 dark:text-green-400 uppercase tracking-tighter text-[10px] font-bold">Bagus</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse($summaryData as $data)
                    <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-800/30 transition-colors">
                        <td class="px-4 py-3 font-medium text-gray-700 dark:text-gray-300">{{ $data['nama'] }}</td>
                        <td class="px-4 py-3 text-center text-gray-500 dark:text-gray-400">{{ number_format($data['bahan']) }}</td>
                        <td class="px-4 py-3 text-center font-bold text-red-400/90 border-r border-gray-50 dark:border-gray-800">{{ number_format($data['cacat']) }}</td>
                        <td class="px-4 py-3 text-center font-bold text-green-500/90">{{ number_format($data['good']) }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="4" class="px-4 py-8 text-center text-gray-400 dark:text-gray-600 italic font-light">Tidak ada data untuk ditampilkan</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-filament::widget>