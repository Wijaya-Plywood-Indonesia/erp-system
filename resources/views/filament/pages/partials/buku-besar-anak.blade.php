{{-- x-data diatur ke true --}}
<div x-data="{ open: true }" class="mt-2 ml-6">

    {{-- HEADER AKUN ANAK --}}
    <div class="flex justify-between px-4 py-2 bg-gray-100 rounded-t-lg dark:bg-gray-800 border-x border-t border-gray-200 dark:border-gray-700">
        <span class="font-semibold text-gray-900 dark:text-white text-sm">
            no akun: {{ $akun->kode_anak_akun }} - {{ $akun->nama_anak_akun }}
        </span>

        <span class="font-bold text-gray-900 dark:text-white text-sm">
            Rp {{ number_format($this->getTotalRecursive($akun), 0, ',', '.') }}
        </span>
    </div>

    <div class="p-3 space-y-4 border border-gray-200 dark:border-gray-700 rounded-b-lg bg-white dark:bg-transparent">

        {{-- LEVEL 3 --}}
        @if($akun->children && $akun->children->count())
            @foreach($akun->children as $child)
                @include('filament.pages.partials.buku-besar-anak', ['akun' => $child])
            @endforeach

        {{-- LEVEL 4 - TRANSAKSI --}}
        @elseif($akun->subAnakAkuns && $akun->subAnakAkuns->count())
            @foreach($akun->subAnakAkuns as $sub)
                {{-- x-data diatur ke true --}}
                <div x-data="{ openSub: true }" class="ml-4 space-y-2">
                    <div class="flex justify-between px-3 py-2 border-l-4 border-primary-500 bg-gray-50 dark:bg-gray-800/50">
                        <span class="text-xs font-medium text-gray-700 dark:text-gray-200">
                            Sub Akun: {{ $sub->kode_sub_anak_akun }} - {{ $sub->nama_sub_anak_akun }}
                        </span>
                        <span class="text-xs font-bold text-gray-700 dark:text-gray-200">
                            Rp {{ number_format($this->getTotalRecursive($sub), 0, ',', '.') }}
                        </span>
                    </div>

                    {{-- TABEL --}}
                    <div class="overflow-x-auto">
                        @php
                            $saldoAwal = $this->getSaldoAwal($sub->kode_sub_anak_akun);
                            $saldoBerjalan = $saldoAwal;
                            $transaksis = $this->getTransaksiByKode($sub->kode_sub_anak_akun);
                            $totalDebit = 0;
                            $totalKredit = 0;
                            $tglAwalan = \Carbon\Carbon::parse($this->filterBulan)->startOfMonth()->subDay()->format('d-m-Y');
                        @endphp

                        <table class="w-full text-[10px] border-collapse">
                            <thead>
                                <tr class="bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-300">
                                    <th class="px-2 py-1 border border-gray-300 dark:border-gray-600">Tgl</th>
                                    <th class="px-2 py-1 border border-gray-300 dark:border-gray-600">Jurnal</th>
                                    <th class="px-2 py-1 border border-gray-300 dark:border-gray-600 text-left">Nama Akun</th>
                                    <th class="px-2 py-1 border border-gray-300 dark:border-gray-600 text-left">Keterangan</th>
                                    <th class="px-2 py-1 border border-gray-300 dark:border-gray-600 text-right">Debit</th>
                                    <th class="px-2 py-1 border border-gray-300 dark:border-gray-600 text-right">Kredit</th>
                                    <th class="px-2 py-1 border border-gray-300 dark:border-gray-600 text-right">Saldo</th>
                                </tr>
                            </thead>
                            <tbody class="text-gray-700 dark:text-gray-200">
                                <tr class="bg-gray-50/50 dark:bg-gray-800/30 italic">
                                    <td class="px-2 py-1 border border-gray-300 dark:border-gray-600 text-center">{{ $tglAwalan }}</td>
                                    <td class="px-2 py-1 border border-gray-300 dark:border-gray-600 text-center">-</td>
                                    <td class="px-2 py-1 border border-gray-300 dark:border-gray-600">{{ $sub->nama_sub_anak_akun }}</td>
                                    <td class="px-2 py-1 border border-gray-300 dark:border-gray-600 font-bold text-center uppercase">Awalan</td>
                                    <td class="px-2 py-1 border border-gray-300 dark:border-gray-600"></td>
                                    <td class="px-2 py-1 border border-gray-300 dark:border-gray-600"></td>
                                    <td class="px-2 py-1 border border-gray-300 dark:border-gray-600 text-right font-bold text-blue-600 dark:text-blue-400">
                                        {{ number_format($saldoAwal, 0, ',', '.') }}
                                    </td>
                                </tr>

                                @foreach($transaksis as $trx)
                                    @php
                                        $qty = $trx->hit_kbk === 'banyak' ? ($trx->banyak ?? 0) : ($trx->m3 ?? 0);
                                        $nominal = $qty * ($trx->harga ?? 0);
                                        if($trx->map === 'D') { $saldoBerjalan += $nominal; $totalDebit += $nominal; } 
                                        else { $saldoBerjalan -= $nominal; $totalKredit += $nominal; }
                                    @endphp
                                    <tr>
                                        <td class="px-2 py-1 border border-gray-300 dark:border-gray-600 text-center">{{ \Carbon\Carbon::parse($trx->tgl)->format('d-m-Y') }}</td>
                                        <td class="px-2 py-1 border border-gray-300 dark:border-gray-600 text-center">{{ $trx->jurnal ?? '-' }}</td>
                                        <td class="px-2 py-1 border border-gray-300 dark:border-gray-600">{{ $sub->nama_sub_anak_akun }}</td>
                                        <td class="px-2 py-1 border border-gray-300 dark:border-gray-600">{{ $trx->keterangan }}</td>
                                        <td class="px-2 py-1 border border-gray-300 dark:border-gray-600 text-right text-green-600 dark:text-green-400">{{ $trx->map === 'D' ? number_format($nominal, 0, ',', '.') : '' }}</td>
                                        <td class="px-2 py-1 border border-gray-300 dark:border-gray-600 text-right text-red-600 dark:text-red-400">{{ $trx->map === 'K' ? number_format($nominal, 0, ',', '.') : '' }}</td>
                                        <td class="px-2 py-1 border border-gray-300 dark:border-gray-600 text-right">{{ number_format($saldoBerjalan, 0, ',', '.') }}</td>
                                    </tr>
                                @endforeach

                                <tr class="font-bold bg-gray-100 dark:bg-gray-800">
                                    <td colspan="4" class="px-2 py-1 border border-gray-300 dark:border-gray-600 text-center text-amber-600 dark:text-yellow-500">TOTAL AKHIR / SISA</td>
                                    <td class="px-2 py-1 border border-gray-300 dark:border-gray-600 text-right text-green-600 dark:text-green-500">{{ number_format($totalDebit, 0, ',', '.') }}</td>
                                    <td class="px-2 py-1 border border-gray-300 dark:border-gray-600 text-right text-red-600 dark:text-red-500">{{ number_format($totalKredit, 0, ',', '.') }}</td>
                                    <td class="px-2 py-1 border border-gray-300 dark:border-gray-600 text-right bg-gray-200 dark:bg-gray-700 text-amber-700 dark:text-yellow-500">{{ number_format($saldoBerjalan, 0, ',', '.') }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            @endforeach
        @endif
    </div>
</div>