<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Nota Kantor - {{ $record->no_nota }}</title>
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
            letter-spacing: 0.5px;
        }

        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 25px;
        }

        .header-table td {
            padding: 3px 0;
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

        .header-right {
            text-align: right;
            font-size: 14px;
        }

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
            background-color: #fff;
        }

        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .text-left { text-align: left; }

        .items-table tfoot td {
            border: none;
            padding: 10px 6px;
            font-size: 14px;
            font-weight: bold;
        }

        .items-table tfoot td.total-label {
            text-align: right;
            padding-right: 15px;
        }

        .items-table tfoot td.total-value {
            text-align: right;
        }

        .footer-section {
            margin-top: 35px;
            width: 100%;
        }

        .pembayaran-box {
            background-color: #d1d5db !important;
            padding: 8px 12px;
            font-size: 13.5px;
            font-weight: bold;
            display: inline-block;
            min-width: 250px;
            vertical-align: top;
        }

        .pembayaran-detail {
            font-size: 12px;
            font-weight: normal;
            margin-top: 4px;
            line-height: 1.4;
        }

        .signature-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        .signature-table td {
            vertical-align: top;
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
        <a href="{{ route('nota-bk.preview', ['record' => $record, 'jenis' => 'kantor']) }}" style="margin-right: 10px; font-size: 13px; color: #4b5563; text-decoration: none;">&larr; Ubah Pembayaran</a>
        <button class="btn-print" onclick="window.print()">Cetak Dokumen</button>
    </div>

    <div class="page">
        <!-- Title -->
        <div class="title">Nota</div>

        <!-- Header Info -->
        <table class="header-table">
            <tr>
                <td class="header-label">No.</td>
                <td class="header-val" style="width: 250px;">{{ $record->no_nota }}</td>
                <td colspan="2" style="text-align: right;">
                    <div style="display: flex; justify-content: flex-end; flex-wrap: wrap; gap: 24px;">
                        <span style="font-weight: bold; font-size: 14px;">kepada</span>
                        <span style="font-weight: bold; font-size: 14px; text-align: right;">{{ $record->tujuan_nota }}</span>
                    </div>
                </td>
            </tr>
            <tr>
                <td class="header-label">Tanggal</td>
                <td class="header-val" colspan="3">{{ $record->tanggal ? $record->tanggal->format('d-M-y') : '-' }}</td>
            </tr>
        </table>

        <!-- Table Barang -->
        <table class="items-table">
            <thead>
                <tr>
                    <th style="width: 6%;">No.</th>
                    <th style="width: 37%;">Nama Barang</th>
                    <th style="width: 9%;">ketr</th>
                    <th style="width: 11%;">Satuan</th>
                    <th style="width: 8%;">Qty</th>
                    <th style="width: 14%;">Harga</th>
                    <th style="width: 15%;">Subtotal</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($items as $idx => $item)
                    <tr>
                        <td class="text-center">{{ $idx + 1 }}</td>
                        <td class="text-left">{{ $item->nama_barang }}</td>
                        <td class="text-center">{{ $item->keterangan ?? '' }}</td>
                        <td class="text-center">{{ $item->satuan }}</td>
                        <td class="text-center">{{ number_format($item->qty) }}</td>
                        <td class="text-right">{{ number_format($item->harga, 0, ',', '.') }}</td>
                        <td class="text-right">{{ number_format($item->subtotal, 0, ',', '.') }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="6" class="total-label">Total</td>
                    <td class="total-value">{{ number_format($grandTotal, 0, ',', '.') }}</td>
                </tr>
            </tfoot>
        </table>

        <!-- Footer Section -->
        <div class="footer-section">
            <table class="signature-table">
                <tr>
                    <td style="width: 50%; vertical-align: top;">
                        <div class="pembayaran-box">
                            Pembayaran : {{ $record->metode_pembayaran ?? 'Tunai' }}
                            @if (($record->metode_pembayaran ?? '') === 'Transfer' && $record->rekeningPerusahaan)
                                <div class="pembayaran-detail">
                                    Bank: {{ $record->rekeningPerusahaan->nama_bank }}<br>
                                    No. Rek: {{ $record->rekeningPerusahaan->no_rekening }}<br>
                                    A/N: {{ $record->rekeningPerusahaan->atas_nama }}
                                </div>
                            @endif
                        </div>
                    </td>
                    <td style="width: 20%; vertical-align: top; text-align: center; font-size: 14px;">
                        Cek
                    </td>
                    <td style="width: 30%; vertical-align: top; text-align: center; font-size: 14px;">
                        Hormat Kami
                        <div style="height: 55px;"></div>
                        <strong>{{ $record->pembuat?->name ?? 'Safira' }}</strong>
                    </td>
                </tr>
            </table>
        </div>
    </div>
</body>
</html>