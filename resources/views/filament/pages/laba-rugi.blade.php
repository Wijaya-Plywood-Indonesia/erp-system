<div class="max-w-4xl mx-auto
            bg-white dark:bg-gray-900
            text-gray-800 dark:text-gray-100
            shadow-xl rounded-2xl p-10">

    <h2 class="text-2xl font-bold text-center mb-10">
        LAPORAN LABA RUGI
    </h2>

    @php
        function debit($amount) {
            return 'Rp ' . number_format(abs($amount), 0, ',', '.');
        }

        function kredit($amount) {
            return '(Rp ' . number_format(abs($amount), 0, ',', '.') . ')';
        }
    @endphp

    <table class="w-full text-sm border-collapse">

        {{-- ================= PENDAPATAN (KREDIT) ================= --}}
        <tr>
            <td colspan="3" class="font-semibold pb-2">Pendapatan</td>
        </tr>

        @foreach ($akunPendapatan as $item)
            <tr>
                <td class="w-28 py-1">{{ $item['kode'] }}</td>
                <td class="pl-2">{{ $item['nama'] }}</td>
                <td></td>
                <td class="w-40 text-right">
                    {{ kredit($item['total']) }}
                </td>
            </tr>
        @endforeach

        {{-- Total Pendapatan --}}
        <tr>
            <td colspan="3"></td>
            <td class="text-right border-t border-gray-500 font-semibold">
                {{ kredit($totalPendapatan) }}
            </td>
        </tr>


        {{-- ================= HPP (DEBIT) ================= --}}
        <tr>
            <td colspan="3" class="font-semibold pt-6 pb-2">
                Harga Pokok Penjualan
            </td>
        </tr>

        <tr>
            <td></td>
            <td class="pl-2">Harga Pokok Penjualan</td>
            <td class="text-right">
                {{ debit($hpp) }}
            </td>
            <td></td>
        </tr>


        {{-- ================= LABA KOTOR ================= --}}
        <tr>
            <td colspan="2" class="font-semibold pt-2">Laba Kotor</td>
            <td></td>
            <td class="text-right border-t border-gray-500 font-semibold">
                @if($pendapatanKotor >= 0)
                    {{ kredit($pendapatanKotor) }}
                @else
                    {{ debit($pendapatanKotor) }}
                @endif
            </td>
        </tr>


        {{-- ================= BEBAN OPERASIONAL (DEBIT) ================= --}}
        <tr>
            <td colspan="3" class="font-semibold pt-6 pb-2">
                Beban Operasional
            </td>
        </tr>

        @foreach ($akunBiaya as $item)
            <tr>
                <td class="py-1">{{ $item['kode'] }}</td>
                <td class="pl-2">{{ $item['nama'] }}</td>
                <td class="text-right">
                    {{ debit($item['total']) }}
                </td>
                <td></td>
            </tr>
        @endforeach

        {{-- Total Biaya --}}
        <tr>
            <td colspan="2"></td>
            <td class="text-right border-t border-gray-500 font-semibold">
                {{ debit($totalBiaya) }}
            </td>
            <td></td>
        </tr>


        {{-- ================= LABA SEBELUM PAJAK ================= --}}
        <tr>
            <td colspan="2" class="font-semibold pt-6">
                Laba Sebelum Pajak
            </td>
            <td></td>
            <td class="text-right border-t border-gray-500 font-semibold">
                @if($pendapatanSebelumPajak >= 0)
                    {{ kredit($pendapatanSebelumPajak) }}
                @else
                    {{ debit($pendapatanSebelumPajak) }}
                @endif
            </td>
        </tr>


        {{-- ================= PAJAK (DEBIT) ================= --}}
        <tr>
            <td></td>
            <td class="pl-2 py-1">Pajak Penghasilan (5900)</td>
            <td class="text-right">
                {{ debit($bebanPajak) }}
            </td>
            <td></td>
        </tr>


        {{-- ================= LABA / RUGI BERSIH ================= --}}
        <tr>
            <td colspan="2" class="font-bold text-base pt-6">
                {{ $labaBersih >= 0 ? 'Laba Bersih' : 'Rugi Bersih' }}
            </td>

            @if($labaBersih >= 0)
                {{-- LABA = Kredit --}}
                <td></td>
                <td class="text-right font-bold text-base border-t-4 border-double border-black">
                    {{ kredit($labaBersih) }}
                </td>
            @else
                {{-- RUGI = Debit --}}
                <td class="text-right font-bold text-base border-t-4 border-double border-black">
                    {{ debit($labaBersih) }}
                </td>
                <td></td>
            @endif
        </tr>

    </table>

</div>