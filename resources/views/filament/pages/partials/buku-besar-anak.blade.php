@php
// Definisikan kode unik berdasarkan level akun
$kodeAkun = $akun->kode_anak_akun ?? $akun->kode_sub_anak_akun;

// Ambil data pendukung untuk validasi
$saldoAwal = $this->getSaldoAwal($kodeAkun);
$saldoAkhir = $this->getTotalRecursive($akun);
$transaksis = $this->getTransaksiByKode($kodeAkun);
$jumlahTransaksi = $transaksis->count();

// Logika 3 Validasi: Tampilkan hanya jika salah satu terpenuhi
$tampilkan = ($saldoAwal != 0) || ($saldoAkhir != 0) || ($jumlahTransaksi > 0);
@endphp


@if($tampilkan)
<div x-data="{ open: true }" class="mt-2 ml-6">

    {{-- HEADER AKUN --}}
    <div
        class="flex justify-between px-4 py-2 bg-gray-100 rounded-t-lg dark:bg-gray-800 border-x border-t border-gray-200 dark:border-gray-700">
        <span class="font-semibold text-gray-900 dark:text-white text-xs">
            no akun: {{ $kodeAkun }} - {{ $akun->nama_anak_akun ?? $akun->nama_sub_anak_akun }}
        </span>

        <span class="font-bold text-gray-900 dark:text-white text-xs">
            Rp {{ number_format($saldoAkhir, 0, ',', '.') }}
        </span>
    </div>

    <div class="p-3 border border-gray-200 dark:border-gray-700 rounded-b-lg bg-white dark:bg-transparent shadow-inner">

        @php
        $children = collect();

        if (isset($akun->children)) {
        $children = $children->merge($akun->children);
        }

        if (isset($akun->subAnakAkuns)) {
        $children = $children->merge($akun->subAnakAkuns);
        }
        @endphp

        @if($children->count())
        @foreach($children as $child)
        @include('filament.pages.partials.buku-besar-anak', ['akun' => $child])
        @endforeach

        {{-- TAMPILKAN TABEL HANYA DI LEVEL TERAKHIR (SUB ANAK atau AKUN TANPA CHILD) --}}
        @else
        <div class="overflow-x-auto mt-2">
            @php
            $saldoBerjalan = $saldoAwal;
            $totalDebit = 0;
            $totalKredit = 0;
            $tglAwalan = \Carbon\Carbon::parse($this->filterBulan)->startOfMonth()->subDay()->format('d-m-Y');
            @endphp

            <table class="w-full text-[10px] border-collapse border border-gray-300 dark:border-gray-600">
                <thead>
                    <tr class="bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-300">
                        <th class="px-2 py-1 border border-gray-300 dark:border-gray-600">Tgl</th>
                        <th class="px-2 py-1 border border-gray-300 dark:border-gray-600 text-left">Jurnal</th>
                        <th class="px-2 py-1 border border-gray-300 dark:border-gray-600 text-left">Keterangan</th>
                        <th class="px-2 py-1 border border-gray-300 dark:border-gray-600 text-right">Debit</th>
                        <th class="px-2 py-1 border border-gray-300 dark:border-gray-600 text-right">Kredit</th>
                        <th class="px-2 py-1 border border-gray-300 dark:border-gray-600 text-right">Saldo</th>
                    </tr>
                </thead>
                <tbody class="text-gray-700 dark:text-gray-200">
                    {{-- Baris Awalan --}}
                    <tr class="bg-gray-50/50 dark:bg-gray-800/30 italic">
                        <td class="px-2 py-1 border border-gray-300 dark:border-gray-600 text-center">{{ $tglAwalan }}
                        </td>
                        <td class="px-2 py-1 border border-gray-300 dark:border-gray-600 text-center">-</td>
                        <td
                            class="px-2 py-1 border border-gray-300 dark:border-gray-600 font-bold text-center uppercase">
                            Saldo Awalan</td>
                        <td class="px-2 py-1 border border-gray-300 dark:border-gray-600"></td>
                        <td class="px-2 py-1 border border-gray-300 dark:border-gray-600"></td>
                        <td
                            class="px-2 py-1 border border-gray-300 dark:border-gray-600 text-right font-bold text-blue-600 dark:text-blue-400">
                            {{ number_format($saldoAwal, 0, ',', '.') }}
                        </td>
                    </tr>

                    @foreach($transaksis as $trx)
                    @php
                    $mode = strtolower($trx->hit_kbk ?? '');

                    if($mode === '' || $mode === null) {
                    $nominal = $trx->harga ?? 0;
                    } elseif($mode === 'b' || $mode === 'banyak') {
                    $nominal = ($trx->banyak ?? 0) * ($trx->harga ?? 0);
                    } else {
                    $nominal = ($trx->m3 ?? 0) * ($trx->harga ?? 0);
                    }

                    if(strtolower($trx->map) === 'd') {
                    $saldoBerjalan += $nominal;
                    $totalDebit += $nominal;
                    } else {
                    $saldoBerjalan -= $nominal;
                    $totalKredit += $nominal;
                    }
                    @endphp
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                        <td class="px-2 py-1 border border-gray-300 dark:border-gray-600 text-center">{{
                            \Carbon\Carbon::parse($trx->tgl)->format('d-m-Y') }}</td>
                        <td class="px-2 py-1 border border-gray-300 dark:border-gray-600">{{ $trx->jurnal ?? '-' }}</td>
                        <td class="px-2 py-1 border border-gray-300 dark:border-gray-600">{{ $trx->keterangan }}</td>
                        <td
                            class="px-2 py-1 border border-gray-300 dark:border-gray-600 text-right text-green-600 dark:text-green-400">
                            {{ strtolower($trx->map) === 'd' ? number_format($nominal, 0, ',', '.') : '' }}</td>
                        <td
                            class="px-2 py-1 border border-gray-300 dark:border-gray-600 text-right text-red-600 dark:text-red-400">
                            {{ strtolower($trx->map) === 'k' ? number_format($nominal, 0, ',', '.') : '' }}</td>
                        <td class="px-2 py-1 border border-gray-300 dark:border-gray-600 text-right">{{
                            number_format($saldoBerjalan, 0, ',', '.') }}</td>
                    </tr>
                    @endforeach

                    {{-- Footer --}}
                    <tr class="font-bold bg-gray-100 dark:bg-gray-800">
                        <td colspan="3"
                            class="px-2 py-1 border border-gray-300 dark:border-gray-600 text-center text-amber-600 dark:text-yellow-500 uppercase">
                            Sisa Saldo</td>
                        <td
                            class="px-2 py-1 border border-gray-300 dark:border-gray-600 text-right text-green-600 dark:text-green-500">
                            {{ number_format($totalDebit, 0, ',', '.') }}</td>
                        <td
                            class="px-2 py-1 border border-gray-300 dark:border-gray-600 text-right text-red-600 dark:text-red-500">
                            {{ number_format($totalKredit, 0, ',', '.') }}</td>
                        <td
                            class="px-2 py-1 border border-gray-300 dark:border-gray-600 text-right bg-gray-200 dark:bg-gray-700 text-amber-700 dark:text-yellow-500">
                            {{ number_format($saldoBerjalan, 0, ',', '.') }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        @endif
    </div>
</div>
@endif