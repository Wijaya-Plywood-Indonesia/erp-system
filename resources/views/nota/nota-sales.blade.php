<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Nota Sales - {{ $record->no_nota }}</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 0;
        }

        *, *::before, *::after {
            box-sizing: border-box;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }

        body {
            font-family: Arial, sans-serif;
            font-size: 13px;
            color: #000;
            margin: 0;
            padding: 0;
            background: #fff;
        }

        @media screen {
            body {
                background: #e2e8f0;
                padding: 20px;
            }
            .page {
                background: #fff;
                width: 210mm;
                min-height: 297mm;
                padding: 15mm 18mm;
                margin: 0 auto;
                box-shadow: 0 4px 10px rgba(0, 0, 0, 0.15);
            }
            .no-print {
                display: block;
                max-width: 210mm;
                margin: 0 auto 15px auto;
                text-align: right;
            }
        }

        @media print {
            body {
                margin: 0;
                padding: 0;
                background: #fff;
            }
            .no-print {
                display: none !important;
            }
            .page {
                width: 100%;
                min-height: auto;
                padding: 15mm 18mm;
                margin: 0;
                box-shadow: none;
            }
        }

        .title {
            text-align: center;
            font-size: 26px;
            font-weight: bold;
            margin: 0 0 25px 0;
            letter-spacing: 1px;
        }

        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 25px;
        }

        .header-table td {
            padding: 4px 0;
            vertical-align: top;
        }

        .header-label {
            width: 75px;
            font-size: 14px;
            font-weight: bold;
        }

        .header-val {
            font-size: 14px;
        }

        .header-right-label {
            text-align: right;
            font-size: 14px;
            font-weight: bold;
            width: 90px;
            padding-right: 12px !important;
        }

        .header-right-val {
            text-align: right;
            font-size: 14px;
            width: 220px;
        }

        /* TABEL BARANG YANG RAPI & PRESISI */
        .items-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            margin-bottom: 0;
        }

        .items-table th, 
        .items-table td {
            border: 1px solid #000;
            padding: 7px 6px;
            font-size: 12.5px;
            vertical-align: middle;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }

        .items-table th {
            text-align: center;
            font-weight: bold;
            background-color: #d1d5db !important;
        }

        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .text-left { text-align: left; }

        /* BARIS TOTAL (MEMANFAATKAN STRUKTUR TABEL YANG SAMA AGAR SEJAJAR PERSIS) */
        .items-table tfoot td {
            border: none;
            padding: 7px 6px;
            font-size: 13px;
            font-weight: bold;
        }

        .items-table tfoot td.total-label {
            text-align: right;
            padding-right: 15px;
        }

        .items-table tfoot td.total-value {
            border: 1px solid #000 !important;
            border-top: none !important;
            text-align: right;
            font-weight: bold;
        }

        .footer-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 30px;
        }

        .footer-table td {
            vertical-align: top;
        }

        .pembayaran-table {
            width: 320px;
            border-collapse: collapse;
            border: 1px solid #000;
        }

        .pembayaran-table th {
            background-color: #d1d5db !important;
            border: 1px solid #000;
            padding: 5px 8px;
            text-align: center;
            font-weight: bold;
            font-size: 13px;
            letter-spacing: 0.5px;
        }

        .pembayaran-table td {
            border: 1px solid #000;
            padding: 5px 8px;
            font-size: 12.5px;
        }

        .pembayaran-table .label-col {
            width: 85px;
            font-weight: bold;
        }

        .signatures-wrapper {
            display: flex;
            justify-content: space-around;
            text-align: center;
            padding-top: 5px;
        }

        .sig-col {
            text-align: center;
            font-size: 13px;
            width: 80px;
        }

        .sig-space {
            height: 50px;
        }

        .sig-line {
            font-size: 11px;
            letter-spacing: 1px;
        }

        .cek-box {
            margin-top: 25px;
            border: 1px solid #000;
            width: 100%;
            height: 42px;
            padding: 4px 8px;
            font-size: 12px;
            font-weight: bold;
        }

        .btn-print {
            background-color: #2563eb;
            color: #fff;
            padding: 8px 16px;
            font-size: 14px;
            font-weight: 600;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }
        .btn-print:hover {
            background-color: #1d4ed8;
        }
    </style>
</head>
<body onload="window.print()">
    <div class="no-print">
        <a href="{{ route('nota-bk.preview', ['record' => $record, 'jenis' => 'sales']) }}" style="margin-right: 10px; font-size: 13px; color: #4b5563; text-decoration: none;">&larr; Ubah Pembayaran</a>
        <button class="btn-print" onclick="window.print()">Cetak Dokumen</button>
    </div>

    <div class="page">
        <!-- Title -->
        <div class="title">NOTA</div>

        <!-- Header Info -->
        <table class="header-table">
            <tr>
                <td class="header-label">No:</td>
                <td class="header-val" style="width: 250px;">{{ $record->no_nota }}</td>
                <td colspan="2" style="text-align: right;">
                    <div style="display: flex; justify-content: flex-end; flex-wrap: wrap; gap: 24px;">
                        <span style="font-weight: bold; font-size: 14px;">Kepada:</span>
                        <span style="text-align: right; font-size: 14px;">{{ $record->tujuan_nota }}</span>
                    </div>
                </td>
            </tr>
            <tr>
                <td class="header-label">Tanggal:</td>
                <td class="header-val" colspan="3">{{ $record->tanggal ? $record->tanggal->format('d/m/Y') : '-' }}</td>
            </tr>
        </table>

        <!-- Table Barang -->
        <table class="items-table">
            <thead>
                <tr>
                    <th style="width: 6%;">No</th>
                    <th style="width: 28%;">Nama Barang</th>
                    <th style="width: 9%;">Satuan</th>
                    <th style="width: 7%;">Qty</th>
                    <th style="width: 13%;">Harga</th>
                    <th style="width: 11%;">Potongan/pcs</th>
                    <th style="width: 11%;">Total Potongan</th>
                    <th style="width: 15%;">Subtotal</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($items as $idx => $item)
                    <tr>
                        <td class="text-center">{{ $idx + 1 }}</td>
                        <td class="text-left">{{ $item->nama_barang }}</td>
                        <td class="text-center">{{ $item->satuan === 'lembar' ? 'Lbr' : $item->satuan }}</td>
                        <td class="text-center">{{ number_format($item->qty) }}</td>
                        <td class="text-right">{{ number_format($item->harga, 0, ',', '.') }}</td>
                        <td class="text-center">0</td>
                        <td class="text-center">0</td>
                        <td class="text-right">{{ number_format($item->subtotal, 0, ',', '.') }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="7" class="total-label">TOTAL</td>
                    <td class="total-value">{{ number_format($grandTotal, 0, ',', '.') }}</td>
                </tr>
            </tfoot>
        </table>

        <!-- Footer Section -->
        <table class="footer-table">
            <tr>
                <!-- Box Pembayaran -->
                <td style="width: 50%;">
                    <table class="pembayaran-table">
                        <thead>
                            <tr>
                                <th colspan="2">PEMBAYARAN</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td class="label-col">Metode</td>
                                <td>{{ $record->metode_pembayaran ?? 'Tunai' }}</td>
                            </tr>
                            @if (($record->metode_pembayaran ?? '') === 'Transfer' && $record->rekeningPerusahaan)
                                <tr>
                                    <td class="label-col">Bank</td>
                                    <td>{{ $record->rekeningPerusahaan->nama_bank }}</td>
                                </tr>
                                <tr>
                                    <td class="label-col">No Rek</td>
                                    <td>{{ $record->rekeningPerusahaan->no_rekening }}</td>
                                </tr>
                                <tr>
                                    <td class="label-col">Atas Nama</td>
                                    <td>{{ $record->rekeningPerusahaan->atas_nama }}</td>
                                </tr>
                            @elseif (($record->metode_pembayaran ?? '') === 'Transfer' && ! $record->rekeningPerusahaan)
                                <tr>
                                    <td class="label-col">Bank</td>
                                    <td>-</td>
                                </tr>
                                <tr>
                                    <td class="label-col">No Rek</td>
                                    <td>-</td>
                                </tr>
                                <tr>
                                    <td class="label-col">Atas Nama</td>
                                    <td>-</td>
                                </tr>
                            @endif
                        </tbody>
                    </table>
                </td>

                <!-- Kolom Tanda Tangan: Pengirim, Supir, Penerima -->
                <td style="width: 50%; vertical-align: middle;">
                    <div class="signatures-wrapper">
                        <div class="sig-col">
                            Pengirim
                            <div class="sig-space"></div>
                            <div class="sig-line">.........</div>
                        </div>
                        <div class="sig-col">
                            Supir
                            <div class="sig-space"></div>
                            <div class="sig-line">.........</div>
                        </div>
                        <div class="sig-col">
                            Penerima
                            <div class="sig-space"></div>
                            <div class="sig-line">.........</div>
                        </div>
                    </div>
                </td>
            </tr>
        </table>

        <!-- Box CEK -->
        <div class="cek-box">
            CEK
        </div>
    </div>
</body>
</html>