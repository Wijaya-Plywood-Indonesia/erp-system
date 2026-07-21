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
            size: 210mm 330mm; /* F4 */
            margin: 10mm;
        }

        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
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
            height: 330mm;
            box-sizing: border-box;
        }

        .sj {
            height: 50%;
            padding: 5mm;
            box-sizing: border-box;
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
    </style>
</head>

<body onload="window.print()">
    <div class="page">
        @foreach (['Customer', 'Arsip'] as $copy)
            @if ($loop->last)
                <div class="cut-line"></div>
            @endif

            <div class="sj">
                <h2 class="text-center" style="margin-bottom: 0">Surat Jalan</h2>
                <p class="text-center" style="margin-top: 2px">
                    Barang Keluar ({{ $copy }})
                </p>

                <table class="mb-2">
                    <tr>
                        <td width="50%">
                            <strong>No:</strong> {{ $nota->no_nota }}<br />
                            <strong>Tanggal:</strong>
                            {{ $nota->tanggal?->format('d-M-y') }}
                        </td>
                        <td width="50%">
                            <strong>Pengiriman:</strong><br />
                            Sopir&nbsp;&nbsp;&nbsp;: ____________________<br />
                            Mobil&nbsp;&nbsp;&nbsp;: ____________________<br />
                            No Plat : ____________________
                        </td>
                    </tr>
                </table>

                <table class="mb-2">
                    <tr>
                        <td>
                            <strong>Kepada:</strong><br />
                            {{ $nota->tujuan_nota }}
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