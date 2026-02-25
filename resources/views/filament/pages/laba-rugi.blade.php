<div class="max-w-4xl mx-auto bg-white dark:bg-gray-900 text-gray-800 dark:text-gray-100 shadow-xl rounded-2xl p-10">
    <h2 class="text-2xl font-bold text-center mb-10 text-uppercase"> LAPORAN LABA RUGI </h2>

    @php
    function rupiah($value, $isKredit = false) {
        // Jika nilai negatif atau dipaksa tampilan kredit (pakai kurung)
        if ($value < 0) {
            return '(' . number_format(abs($value), 0, ',', '.') . ')';
        }
        return number_format($value, 0, ',', '.');
    }
    @endphp

    <div class="mb-6 space-y-4">
        <label class="flex items-center space-x-2">
            <input type="checkbox" wire:model.live="useCustomFilter">
            <span>Gunakan Filter Custom</span>
        </label>
        @if($useCustomFilter)
            <div class="mt-3">
                <select wire:model.live="selectedAkun" multiple size="8" class="w-full border rounded p-2 dark:bg-gray-800">
                    @foreach($this->daftarAkun as $kode => $nama)
                        <option value="{{ $kode }}">{{ $kode }} - {{ $nama }}</option>
                    @endforeach
                </select>
            </div>
        @endif
    </div>

    <table class="w-full text-sm border-collapse">
        {{-- Header Kolom --}}
        <thead>
            <tr>
                <th class="w-28"></th>
                <th></th>
                {{-- <th class="w-40 text-right pr-4 italic text-gray-400 text-xs">Debit (Kiri)</th>
                <th class="w-40 text-right italic text-gray-400 text-xs">Kredit (Kanan)</th> --}}
            </tr>
        </thead>
@if(count($akunLainnya))
<tr>
    <td colspan="4" class="font-semibold pt-6 pb-2 border-b">
        Akun Lainnya
    </td>
</tr>

@foreach ($akunLainnya as $item)
<tr>
    <td>{{ $item['kode'] }}</td>
    <td class="pl-2">{{ $item['nama'] }}</td>

    <td>
        <select wire:model.live="akunMapping.{{ $item['kode'] }}"
                class="border rounded p-1 text-xs">
            <option value="">Lainnya</option>
            <option value="pendapatan">Pendapatan</option>
            <option value="biaya">Beban Operasional</option>
        </select>
    </td>

    <td class="text-right pr-4">
        {{ rupiah($item['total']) }}
    </td>
</tr>
@endforeach
@endif
        {{-- ================= PENDAPATAN ================= --}}
        <tr>
            <td colspan="4" class="font-semibold pb-2 border-b">Pendapatan Penjualan</td>
        </tr>
        @foreach ($akunPendapatan as $item)
        <tr>
            <td class="py-1">{{ $item['kode'] }}</td>
            <td class="pl-2">{{ $item['nama'] }}</td>
            <td class="text-right pr-4">{{ rupiah($item['total']) }}</td>
            <td></td>
        </tr>
        @endforeach
        {{-- Total Pendapatan di kolom kanan --}}
        <tr>
            <td colspan="2"></td>
            <td></td>
            <td class="text-right border-t border-gray-500 font-semibold">Rp {{ rupiah($totalPendapatan) }}</td>
        </tr>

        {{-- ================= HPP ================= --}}
        <tr>
            <td colspan="4" class="font-semibold pt-6 pb-2 border-b">Harga Pokok Penjualan</td>
        </tr>
        <tr>
            <td></td>
            <td class="pl-2">Harga Pokok Penjualan(HPP)</td>
            <td class="text-right pr-4">{{ rupiah($hpp) }}</td>
            <td></td>
        </tr>
        {{-- Total HPP --}}
        <tr>
            <td colspan="2"></td>
            <td></td>
            <td class="text-right border-t border-gray-500 font-semibold underline">{{ rupiah($hpp) }}</td>
        </tr>

        {{-- ================= LABA KOTOR ================= --}}
        <tr class="bg-gray-50 dark:bg-gray-800">
            <td colspan="2" class="font-bold py-2">LABA KOTOR</td>
            <td></td>
            <td class="text-right font-bold">Rp {{ rupiah($pendapatanKotor) }}</td>
        </tr>

        {{-- ================= BEBAN OPERASIONAL ================= --}}
        <tr>
            <td colspan="4" class="font-semibold pt-6 pb-2 border-b">Beban Operasional</td>
        </tr>
        @foreach ($akunBiaya as $item)
        <tr>
            <td class="py-1">{{ $item['kode'] }}</td>
            <td class="pl-2">{{ $item['nama'] }}</td>
            <td class="text-right pr-4">{{ rupiah($item['total']) }}</td>
            <td></td>
        </tr>
        @endforeach
        
        {{-- Total Biaya --}}
        <tr>
            <td colspan="2"></td>
            <td></td>
            <td class="text-right border-t border-gray-500 font-semibold italic">{{ rupiah($totalBiaya) }}</td>
        </tr>

        {{-- ================= LABA SEBELUM PAJAK ================= --}}
        <tr>
            <td colspan="2" class="font-semibold pt-6">Laba Sebelum Pajak</td>
            <td></td>
            <td class="text-right border-t border-black font-semibold pt-6">Rp {{ rupiah($pendapatanSebelumPajak) }}</td>
        </tr>

        {{-- ================= PAJAK ================= --}}
        <tr>
            <td></td>
            <td class="pl-2 py-1 italic">Pajak Penghasilan (5900)</td>
            <td class="text-right pr-4">{{ rupiah($bebanPajak) }}</td>
            <td></td>
        </tr>

        {{-- ================= LABA / RUGI BERSIH ================= --}}
        <tr>
            <td colspan="2" class="font-bold text-lg pt-10">
                {{ $labaBersih >= 0 ? 'LABA BERSIH' : 'RUGI' }}
            </td>
            <td></td>
            <td class="text-right font-bold text-lg pt-10">
                <span class="border-b-4 border-double border-black dark:border-white">
                    Rp {{ rupiah($labaBersih) }}
                </span>
            </td>
        </tr>
    </table>
</div>