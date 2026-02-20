<div class="max-w-4xl mx-auto
            bg-white dark:bg-gray-900
            text-gray-800 dark:text-gray-100
            shadow-2xl rounded-3xl p-10
            transition-colors duration-300">

    <h2 class="text-3xl font-bold text-center mb-10 tracking-wide">
        LAPORAN LABA RUGI
    </h2>

    {{-- ================= PENDAPATAN ================= --}}
    <div class="mb-10">
        <h3 class="text-lg font-semibold mb-4 
                   border-b border-gray-300 dark:border-gray-700 pb-2">
            PENDAPATAN
        </h3>

        <table class="w-full text-sm">
            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                @foreach ($akunPendapatan as $item)
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-800 transition">
                        <td class="w-24 py-2">{{ $item['kode'] }}</td>
                        <td class="py-2">{{ $item['nama'] }}</td>
                        <td class="text-right py-2 font-medium">
                            {{ number_format($item['total'], 2, ',', '.') }}
                        </td>
                    </tr>
                @endforeach
            </tbody>

            <tfoot>
                <tr class="border-t-2 border-gray-300 dark:border-gray-600 font-semibold">
                    <td colspan="2" class="pt-3">TOTAL PENDAPATAN</td>
                    <td class="text-right pt-3 text-green-600 dark:text-green-400">
                        {{ number_format($totalPendapatan, 2, ',', '.') }}
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>

    {{-- ================= HPP ================= --}}
    <div class="mb-10 
                bg-gray-50 dark:bg-gray-800 
                rounded-2xl p-6 shadow-inner">

        <table class="w-full text-sm">
            <tr>
                <td colspan="2" class="py-2 font-medium">
                    Harga Pokok Penjualan (HPP)
                </td>
                <td class="text-right py-2 text-red-600 dark:text-red-400 font-medium">
                    ({{ number_format($hpp, 2, ',', '.') }})
                </td>
            </tr>

            <tr class="border-t border-gray-300 dark:border-gray-600 font-semibold">
                <td colspan="2" class="pt-3">
                    PENDAPATAN KOTOR
                </td>
                <td class="text-right pt-3 text-blue-600 dark:text-blue-400">
                    {{ number_format($pendapatanKotor, 2, ',', '.') }}
                </td>
            </tr>
        </table>
    </div>

    {{-- ================= BIAYA ================= --}}
    <div class="mb-10">
        <h3 class="text-lg font-semibold mb-4 
                   border-b border-gray-300 dark:border-gray-700 pb-2">
            BIAYA OPERASIONAL
        </h3>

        <table class="w-full text-sm">
            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                @foreach ($akunBiaya as $item)
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-800 transition">
                        <td class="w-24 py-2">{{ $item['kode'] }}</td>
                        <td class="py-2">{{ $item['nama'] }}</td>
                        <td class="text-right py-2 text-red-600 dark:text-red-400 font-medium">
                            ({{ number_format($item['total'], 2, ',', '.') }})
                        </td>
                    </tr>
                @endforeach
            </tbody>

            <tfoot>
                <tr class="border-t-2 border-gray-300 dark:border-gray-600 font-semibold">
                    <td colspan="2" class="pt-3">TOTAL BIAYA</td>
                    <td class="text-right pt-3 text-red-600 dark:text-red-400">
                        ({{ number_format($totalBiaya, 2, ',', '.') }})
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>

    {{-- ================= SEBELUM PAJAK ================= --}}
    <div class="border-t border-gray-300 dark:border-gray-700 pt-6">
        <table class="w-full text-sm font-semibold">
            <tr>
                <td colspan="2" class="py-2">
                    PENDAPATAN SEBELUM PAJAK
                </td>
                <td class="text-right py-2 text-indigo-600 dark:text-indigo-400">
                    {{ number_format($pendapatanSebelumPajak, 2, ',', '.') }}
                </td>
            </tr>

            <tr>
                <td colspan="2" class="py-2">
                    BEBAN PAJAK (5900)
                </td>
                <td class="text-right py-2 text-red-600 dark:text-red-400">
                    ({{ number_format($bebanPajak, 2, ',', '.') }})
                </td>
            </tr>
        </table>
    </div>

    {{-- ================= LABA BERSIH ================= --}}
    <div class="border-t-4 border-gray-400 dark:border-gray-600 pt-6 mt-6">
        <table class="w-full text-lg font-bold">
            <tr>
                <td colspan="2">
                    LABA BERSIH
                </td>
                <td class="text-right 
                    {{ $labaBersih < 0 
                        ? 'text-red-600 dark:text-red-400' 
                        : 'text-green-600 dark:text-green-400' }}">
                    {{ number_format($labaBersih, 2, ',', '.') }}
                </td>
            </tr>
        </table>
    </div>

</div>
