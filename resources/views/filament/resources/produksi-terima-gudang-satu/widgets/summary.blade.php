<x-filament::widget>
    <div class="space-y-4">

        <div
            class="p-4 bg-white border border-gray-200 rounded-xl shadow-sm dark:bg-gray-900 dark:border-gray-800 border-l-4 border-l-gray-300 dark:border-l-gray-600">
            <span class="text-xs font-semibold tracking-wider uppercase text-gray-500 dark:text-gray-400">Total
                Pekerja</span>
            <div class="text-2xl font-black text-gray-800 dark:text-gray-100">
                {{ number_format($summary['totalPegawai'] ?? 0) }} <span
                    class="text-sm font-medium opacity-60">Orang</span></div>
        </div>

        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">

            {{-- TABEL BAHAN --}}
            <div class="p-4 bg-white border border-gray-200 rounded-xl shadow-sm dark:bg-gray-900 dark:border-gray-800">
                <h3 class="mb-3 text-sm font-bold text-gray-700 dark:text-gray-200">
                    Bahan Digunakan
                    <span class="ml-2 text-xs font-normal text-gray-400">(Total:
                        {{ number_format($summary['totalBahan'] ?? 0) }})</span>
                </h3>
                <table class="w-full text-sm text-left">
                    <thead class="text-xs uppercase text-gray-500 border-b dark:border-gray-700">
                        <tr>
                            <th class="py-2 pr-4">Jenis</th>
                            <th class="py-2 pr-4">Ukuran</th>
                            <th class="py-2 pr-4">Grade</th>
                            <th class="py-2 pr-4 text-right">Jumlah</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($summary['bahan'] ?? [] as $row)
                            <tr class="border-b last:border-0 dark:border-gray-800">
                                <td class="py-2 pr-4">{{ $row['jenis'] }}</td>
                                <td class="py-2 pr-4">{{ $row['ukuran'] }}</td>
                                <td class="py-2 pr-4">
                                    <span
                                        class="px-2 py-0.5 text-xs font-semibold rounded bg-gray-100 dark:bg-gray-800">{{ $row['grade'] }}</span>
                                </td>
                                <td class="py-2 pr-4 text-right">{{ number_format($row['jumlah']) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="py-4 text-center text-gray-400">Belum ada data bahan</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- TABEL HASIL --}}
            <div class="p-4 bg-white border border-gray-200 rounded-xl shadow-sm dark:bg-gray-900 dark:border-gray-800">
                <h3 class="mb-3 text-sm font-bold text-gray-700 dark:text-gray-200">
                    Hasil Produksi
                    <span class="ml-2 text-xs font-normal text-gray-400">(Total:
                        {{ number_format($summary['totalHasil'] ?? 0) }})</span>
                </h3>
                <table class="w-full text-sm text-left">
                    <thead class="text-xs uppercase text-gray-500 border-b dark:border-gray-700">
                        <tr>
                            <th class="py-2 pr-4">Jenis</th>
                            <th class="py-2 pr-4">Ukuran</th>
                            <th class="py-2 pr-4">Grade</th>
                            <th class="py-2 pr-4 text-right">Jumlah</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($summary['hasil'] ?? [] as $row)
                            <tr class="border-b last:border-0 dark:border-gray-800">
                                <td class="py-2 pr-4">{{ $row['jenis'] }}</td>
                                <td class="py-2 pr-4">{{ $row['ukuran'] }}</td>
                                <td class="py-2 pr-4">
                                    <span
                                        class="px-2 py-0.5 text-xs font-semibold rounded bg-gray-100 dark:bg-gray-800">{{ $row['grade'] }}</span>
                                </td>
                                <td class="py-2 pr-4 text-right">{{ number_format($row['jumlah']) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="py-4 text-center text-gray-400">Belum ada data hasil</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

        </div>
    </div>
</x-filament::widget>
