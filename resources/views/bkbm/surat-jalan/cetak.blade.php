<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8" />
    <title>Surat Jalan - {{ $nota->no_nota }}</title>
    <style>
        /* =====================
           SETTING KERTAS F4
           ===================== */
        @page {
            size: 210mm 330mm;
            /* F4 */
            margin: 10mm;
        }

        body {
            font-family: Arial, sans-serif;
            font-size: 13px;
            color: #000;
            line-height: normal;
            margin: 0;
        }

        /* =====================
           SIMULASI KERTAS DI LAYAR
           ===================== */
        @media screen {
            body {
                background: #eee;
            }

            .page {
                background: #fff;
                box-shadow: 0 0 6px rgba(0, 0, 0, 0.3);
                margin: 10px auto;
            }
        }

        /* =====================
           LAYOUT HALAMAN
           ===================== */
        .page {
            width: 210mm;
            min-height: 330mm;
            box-sizing: border-box;
        }

        .sj {
            height: 50%;
            padding: 5mm;
            box-sizing: border-box;
        }

        /* Mode "banyak barang": tiap copy jadi 1 halaman penuh, tanpa garis potong */
        .sj-full {
            height: auto;
            min-height: 330mm;
            padding: 5mm;
            box-sizing: border-box;
        }

        .page-break {
            page-break-after: always;
        }

        .cut-line {
            border-top: 1px dashed #000;
            margin: 3mm 0;
        }

        /* =====================
           UTILITIES
           ===================== */
        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            padding: 6px;
        }

        .border {
            border: 1px solid #000;
        }

        .text-center {
            text-align: center;
        }

        .text-right {
            text-align: right;
        }

        .mb-2 {
            margin-bottom: 10px;
        }

        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }

        .header-table td {
            padding: 3px 0;
            vertical-align: top;
            border: none;
        }

        .header-label {
            width: 75px;
            font-size: 13px;
            font-weight: bold;
        }

        .header-val {
            font-size: 13px;
        }

        .header-right {
            text-align: right;
            font-size: 13px;
        }
    </style>
</head>

<body onload="window.print()">
    @php
        // Ambang batas jumlah baris barang sebelum layout pindah ke mode
        // "1 lembar penuh per copy tanpa garis potong". Silakan diubah
        // sesuai kebutuhan (misal disesuaikan dengan tinggi baris tabel).
        $isLong = $details->count() > 15;

        $noNotaGap = str_replace(',', ', ', $nota->no_nota);
        $tujuanNotaGap = str_replace(',', ', ', $nota->tujuan_nota);
    @endphp

    <div class="page">
        @foreach (['Customer', 'Arsip'] as $copy)
            @if (!$isLong && $loop->last)
                <div class="cut-line"></div>
            @endif

            <div class="{{ $isLong ? 'sj-full' : 'sj' }} {{ $isLong && !$loop->last ? 'page-break' : '' }}">
                <h2 class="text-center" style="margin-bottom: 0">Surat Jalan</h2>
                <p class="text-center" style="margin-top: 2px">
                    Barang Keluar ({{ $copy }})
                </p>

                <table class="header-table">
                    <tr>
                        <td width="55%">
                            <table style="width: 100%; border-collapse: collapse;">
                                <tr>
                                    <td class="header-label">No:</td>
                                    <td class="header-val">{{ $noNotaGap }}</td>
                                </tr>
                                <tr>
                                    <td class="header-label">Tanggal:</td>
                                    <td class="header-val">{{ $nota->tanggal?->format('d-M-y') }}</td>
                                </tr>
                                <tr>
                                    <td class="header-label">Kepada:</td>
                                    <td class="header-val">{{ $tujuanNotaGap }}</td>
                                </tr>
                            </table>
                        </td>
                        <td width="45%" class="header-right" style="line-height: 1.6;">
                            <strong>Pengiriman:</strong><br />
                            Sopir&nbsp;&nbsp;&nbsp;: ____________________<br />
                            Mobil&nbsp;&nbsp;&nbsp;: ____________________<br />
                            No Plat : ____________________
                        </td>
                    </tr>
                </table>

                <table class="border">
                    <thead>
                        <tr>
                            <th class="border text-center" width="5%">No</th>
                            <th class="border">Nama Barang</th>
                            <th class="border text-center" width="10%">Satuan</th>
                            <th class="border text-center" width="10%">Qty</th>
                            <th class="border text-center" width="20%">Ket</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($details as $i => $d)
                            <tr>
                                <td class="border text-center">{{ $i + 1 }}</td>
                                <td class="border">{{ $d->nama_barang }}</td>
                                <td class="border text-center">{{ $d->satuan }}</td>
                                <td class="border text-center">
                                    {{ number_format($d->jumlah) }}
                                </td>
                                <td class="border text-center">
                                    {{ $d->keterangan ?? '' }}
                                </td>
                            </tr>
                        @endforeach
                        <tr>
                            <td colspan="3" class="border text-right">
                                <strong>Total</strong>
                            </td>
                            <td class="border text-center">
                                <strong>{{ number_format($details->sum('jumlah')) }}</strong>
                            </td>
                            <td class="border"></td>
                        </tr>
                    </tbody>
                </table>

                <table width="100%" style="margin-top: 25px; text-align: center">
                    <tr>
                        <td width="25%"><strong>Penerima</strong></td>
                        <td width="25%"><strong>Sopir</strong></td>
                        <td width="25%"><strong>Cek</strong></td>
                        <td width="25%"><strong>Hormat Kami</strong></td>
                    </tr>
                    <tr>
                        <td style="height: 40px"></td>
                        <td></td>
                        <td></td>
                        <td></td>
                    </tr>
                    <tr>
                        <td>( __________ )</td>
                        <td>( __________ )</td>
                        <td>( __________ )</td>
                        <td>{{ $nota->pembuat?->name ?? '-' }}</td>
                    </tr>
                </table>
            </div>
        @endforeach
    </div>
</body>

</html>
