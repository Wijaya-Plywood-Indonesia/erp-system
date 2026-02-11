<div 
    x-data="{ open: false }"
    class="ml-6 bg-gray-800 rounded-lg border border-gray-700 overflow-hidden"
>

    {{-- HEADER AKUN --}}
    <div 
        @click="open = !open"
        class="flex justify-between items-center px-4 py-3 cursor-pointer hover:bg-gray-700 transition"
    >
        <div class="text-white font-medium">
            no akun: {{ $akun->kode_anak_akun }} - {{ $akun->nama_anak_akun }}
        </div>

        <div class="text-white font-semibold">
            Rp {{ number_format($this->getSaldoAkun($akun->kode_anak_akun)) }}
        </div>
    </div>

    {{-- BODY --}}
    <div x-show="open" x-transition class="p-4 bg-gray-900 border-t border-gray-700">

        {{-- ✅ Kalau masih punya children → recursive lagi --}}
        @if($akun->children->count())
            
            @foreach($akun->children as $child)
                @include('filament.pages.partials.buku-besar-anak', ['akun' => $child])
            @endforeach

        {{-- ✅ Kalau tidak punya children → tampilkan tabel jurnal --}}
        @else

            @php
                $transaksis = $this->getTransaksiByKode($akun->kode_anak_akun);
                $saldo = 0;
            @endphp

            <div class="overflow-x-auto">
                <table class="w-full text-sm text-white border border-gray-700">
                    <thead class="bg-gray-800">
                        <tr>
                            <th class="px-3 py-2 text-left">Tanggal</th>
                            <th class="px-3 py-2 text-left">Jurnal</th>
                            <th class="px-3 py-2 text-left">Keterangan</th>
                            <th class="px-3 py-2 text-right">Debit</th>
                            <th class="px-3 py-2 text-right">Kredit</th>
                            <th class="px-3 py-2 text-right">Saldo</th>
                        </tr>
                    </thead>
                    <tbody>

                        @foreach($transaksis as $trx)

                            @php
                                $qty = $trx->hit_kbk === 'banyak'
                                    ? ($trx->banyak ?? 0)
                                    : ($trx->m3 ?? 0);

                                $total = $qty * ($trx->harga ?? 0);

                                if ($trx->map === 'D') {
                                    $saldo += $total;
                                } else {
                                    $saldo -= $total;
                                }
                            @endphp

                            <tr class="border-t border-gray-700">
                                <td class="px-3 py-2">
                                    {{ \Carbon\Carbon::parse($trx->tgl)->format('d-m-Y') }}
                                </td>

                                <td class="px-3 py-2">
                                    {{ $trx->kode_jurnal ?? '-' }}
                                </td>

                                <td class="px-3 py-2">
                                    {{ $trx->keterangan ?? '-' }}
                                </td>

                                {{-- DEBIT --}}
                                <td class="px-3 py-2 text-right">
                                    @if($trx->map === 'D')
                                        {{ number_format($total,0,',','.') }}
                                    @endif
                                </td>

                                {{-- KREDIT --}}
                                <td class="px-3 py-2 text-right">
                                    @if($trx->map === 'K')
                                        {{ number_format($total,0,',','.') }}
                                    @endif
                                </td>

                                {{-- SALDO --}}
                                <td class="px-3 py-2 text-right font-semibold">
                                    {{ number_format($saldo,0,',','.') }}
                                </td>
                            </tr>

                        @endforeach

                    </tbody>
                </table>
            </div>

        @endif

    </div>

</div>
