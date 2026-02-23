<div class="max-w-4xl mx-auto
            bg-white dark:bg-gray-900
            text-gray-800 dark:text-gray-100
            shadow-xl rounded-2xl p-10">

    <h2 class="text-2xl font-bold text-center mb-10">
        LAPORAN LABA RUGI
    </h2>

    @php
        function rupiah($value) {
            if ($value < 0) {
                return '(Rp ' . number_format(abs($value), 0, ',', '.') . ')';
            }
            return 'Rp ' . number_format($value, 0, ',', '.');
        }
    @endphp

    <table class="w-full text-sm border-collapse">

        {{-- ================= PENDAPATAN ================= --}}
        <tr>
            <td colspan="4" class="font-semibold pb-2">Pendapatan</td>
        </tr>

        @foreach ($akunPendapatan as $item)
            <tr>
                <td class="w-28 py-1">{{ $item['kode'] }}</td>
                <td class="pl-2">{{ $item['nama'] }}</td>
                <td></td>
                <td class="w-40 text-right">
                    {{ rupiah($item['total']) }}
                </td>
            </tr>
        @endforeach

        {{-- Total Pendapatan --}}
        <tr>
            <td colspan="3"></td>
            <td class="text-right border-t border-gray-500 font-semibold">
                {{ rupiah($totalPendapatan) }}
            </td>
        </tr>


        {{-- ================= HPP ================= --}}
        <tr>
            <td colspan="4" class="font-semibold pt-6 pb-2">
                Harga Pokok Penjualan
            </td>
        </tr>

        <tr>
            <td></td>
            <td class="pl-2">Harga Pokok Penjualan</td>
            <td></td>
            <td class="text-right">
                {{ rupiah($hpp) }}
            </td>
        </tr>


        {{-- ================= LABA KOTOR ================= --}}
        <tr>
            <td colspan="3" class="font-semibold pt-2">
                Laba Kotor
            </td>
            <td class="text-right border-t border-gray-500 font-semibold">
                {{ rupiah($pendapatanKotor) }}
            </td>
        </tr>


        {{-- ================= BEBAN OPERASIONAL ================= --}}
        <tr>
            <td colspan="4" class="font-semibold pt-6 pb-2">
                Beban Operasional
            </td>
        </tr>

        @foreach ($akunBiaya as $item)
            <tr>
                <td class="py-1">{{ $item['kode'] }}</td>
                <td class="pl-2">{{ $item['nama'] }}</td>
                <td></td>
                <td class="text-right">
                    {{ rupiah($item['total']) }}
                </td>
            </tr>
        @endforeach

        {{-- Total Biaya --}}
        <tr>
            <td colspan="3"></td>
            <td class="text-right border-t border-gray-500 font-semibold">
                {{ rupiah($totalBiaya) }}
            </td>
        </tr>


        {{-- ================= LABA SEBELUM PAJAK ================= --}}
        <tr>
            <td colspan="3" class="font-semibold pt-6">
                Laba Sebelum Pajak
            </td>
            <td class="text-right border-t border-gray-500 font-semibold">
                {{ rupiah($pendapatanSebelumPajak) }}
            </td>
        </tr>


        {{-- ================= PAJAK ================= --}}
        <tr>
            <td></td>
            <td class="pl-2 py-1">Pajak Penghasilan (5900)</td>
            <td></td>
            <td class="text-right">
                {{ rupiah($bebanPajak) }}
            </td>
        </tr>


        {{-- ================= LABA / RUGI BERSIH ================= --}}
        <tr>
            <td colspan="3" class="font-bold text-base pt-6">
                {{ $labaBersih >= 0 ? 'Laba Bersih' : 'Rugi Bersih' }}
            </td>
            <td class="text-right font-bold text-base border-t-4 border-double border-black">
                {{ rupiah($labaBersih) }}
            </td>
        </tr>

    </table>

</div>