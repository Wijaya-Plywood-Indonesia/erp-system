<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Invoice Resmi - {{ $record->no_invoice_resmi ?? $record->no_nota }}</title>
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
            font-family: Arial, Helvetica, sans-serif;
            font-size: 11px;
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
                padding: 12mm 15mm;
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
                padding: 12mm 15mm;
                margin: 0;
                box-shadow: none;
            }
        }

        .title {
            text-align: center;
            font-size: 16px;
            font-weight: bold;
            margin-bottom: 2px;
            letter-spacing: 0.5px;
        }

        .subtitle-no {
            text-align: center;
            font-size: 12px;
            margin-bottom: 15px;
        }

        /* Identity Tables (PKP & Pembeli BKP) */
        .identity-box {
            width: 100%;
            border: 1px solid #000;
            margin-bottom: 8px;
            border-collapse: collapse;
        }

        .identity-header {
            font-weight: bold;
            background-color: #f3f4f6;
            padding: 4px 8px;
            border-bottom: 1px solid #000;
            font-size: 11px;
            text-transform: uppercase;
        }

        .identity-table {
            width: 100%;
            border-collapse: collapse;
        }

        .identity-table td {
            padding: 3px 8px;
            vertical-align: top;
            font-size: 11px;
        }

        .identity-label {
            width: 70px;
            font-weight: 500;
        }

        .identity-colon {
            width: 10px;
            text-align: center;
        }

        /* Items Table */
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            margin-bottom: 0;
        }

        .items-table th, .items-table td {
            border: 1px solid #000;
            padding: 5px 6px;
            font-size: 10.5px;
            vertical-align: middle;
        }

        .items-table th {
            text-align: center;
            font-weight: bold;
            background-color: #f9fafb;
        }

        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .text-left { text-align: left; }

        /* Calculation / Footer rows */
        .summary-table {
            width: 100%;
            border-collapse: collapse;
            border-left: 1px solid #000;
            border-right: 1px solid #000;
            border-bottom: 1px solid #000;
        }

        .summary-table td {
            padding: 4px 6px;
            font-size: 10.5px;
            border-top: 1px solid #000;
        }

        /* Footer Section */
        .footer-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }

        .footer-table td {
            vertical-align: top;
            font-size: 11px;
        }

        .payment-note-box {
            border: 1px solid #000;
            padding: 8px 10px;
            width: 90%;
            background-color: #fafafa;
        }

        .btn-print {
            background-color: #2563eb;
            color: #fff;
            padding: 8px 16px;
            font-size: 13px;
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
        <a href="{{ route('penjualan.preview', ['record' => $record, 'jenis' => 'kantor']) }}"
            style="margin-right: 10px; font-size: 13px; color: #4b5563; text-decoration: none;">&larr; Kembali ke Preview</a>
        <button class="btn-print" onclick="window.print()">Cetak Invoice</button>
    </div>

    <div class="page">
        <!-- Title -->
        <div class="title">INVOICE</div>
        <div class="subtitle-no">
            No : {{ $record->no_invoice_resmi ?: ($record->no_nota ? 'INV/WP/' . ($record->tanggal ? $record->tanggal->format('m/Y') : date('m/Y')) . '/' . $record->no_nota : '-') }}
        </div>

        <!-- PENGUSAHA KENA PAJAK -->
        <table class="identity-box">
            <tr>
                <td class="identity-header">PENGUSAHA KENA PAJAK</td>
            </tr>
            <tr>
                <td style="padding: 4px 0;">
                    <table class="identity-table">
                        <tr>
                            <td class="identity-label">Nama</td>
                            <td class="identity-colon">:</td>
                            <td><strong>{{ $record->pkp_nama ?: 'EDDY ANGKAWIJAYA' }}</strong></td>
                        </tr>
                        <tr>
                            <td class="identity-label">Alamat</td>
                            <td class="identity-colon">:</td>
                            <td>{{ $record->pkp_alamat ?: 'Jl.Dr. Cipto GG.Kalianyar Rt.005/Rw.001, Bedali Lawang Kab Malang Jawa Timur' }}</td>
                        </tr>
                        <tr>
                            <td class="identity-label">N.P.W.P</td>
                            <td class="identity-colon">:</td>
                            <td>{{ $record->pkp_npwp ?: '06.059.324.1-657.000' }}</td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>

        <!-- PEMBELI BKP / PENERIMA JKP -->
        <table class="identity-box">
            <tr>
                <td class="identity-header">PEMBELI BKP/PENERIMA JKP</td>
            </tr>
            <tr>
                <td style="padding: 4px 0;">
                    <table class="identity-table">
                        <tr>
                            <td class="identity-label">Nama</td>
                            <td class="identity-colon">:</td>
                            <td><strong>{{ $record->pembeli_bkp_nama ?: ($record->tujuan_nota ?: '-') }}</strong></td>
                        </tr>
                        <tr>
                            <td class="identity-label">Alamat</td>
                            <td class="identity-colon">:</td>
                            <td>{{ $record->pembeli_bkp_alamat ?: ($record->alamat ?: '-') }}</td>
                        </tr>
                        <tr>
                            <td class="identity-label">N.P.W.P</td>
                            <td class="identity-colon">:</td>
                            <td>{{ $record->pembeli_bkp_npwp ?: '-' }}</td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>

        <!-- Table Barang -->
        <table class="items-table">
            <thead>
                <tr>
                    <th style="width: 6%;">No. urut</th>
                    <th style="width: 48%;">Nama Barang Kena Pajak</th>
                    <th style="width: 12%;">Banyak (LB)</th>
                    <th style="width: 17%;">Harga Satuan</th>
                    <th style="width: 17%;">Jumlah</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($items as $idx => $item)
                    <tr>
                        <td class="text-center">{{ $idx + 1 }}.</td>
                        <td class="text-left">{{ $item->nama_barang }}</td>
                        <td class="text-center">{{ number_format($item->qty) }}</td>
                        <td class="text-right">Rp. {{ number_format($item->harga, 0, ',', '.') }}</td>
                        <td class="text-right">Rp. {{ number_format($item->subtotal, 0, ',', '.') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        @php
            $ppnPersen = (float) ($record->ppn_persen ?? 11);
            if ($ppnPersen <= 0) $ppnPersen = 11;
            
            // Perhitungan Harga Jual, PPN, dan Total
            // Harga Jual = grandTotal (Subtotal)
            $hargaJual = $grandTotal;
            $ppnNominal = round(($hargaJual * $ppnPersen) / 100, 2);
            $ongkir = (float) ($record->ongkir ?? 0);
            $totalInvoice = $hargaJual + $ppnNominal + $ongkir;
        @endphp

        <!-- Summary Subtotal, PPN, Ongkir & Total -->
        <table class="summary-table">
            <tr>
                <td style="width: 66%; text-align: right; font-weight: bold;">Harga Jual</td>
                <td style="width: 34%; text-align: right; font-weight: bold;">Rp. {{ number_format($hargaJual, 0, ',', '.') }}</td>
            </tr>
            <tr>
                <td style="text-align: right; font-weight: bold;">PPN {{ $ppnPersen }}%</td>
                <td style="text-align: right; font-weight: bold;">Rp. {{ number_format($ppnNominal, 0, ',', '.') }}</td>
            </tr>
            @if($ongkir > 0)
            <tr>
                <td style="text-align: right; font-weight: bold;">Ongkos Kirim</td>
                <td style="text-align: right; font-weight: bold;">Rp. {{ number_format($ongkir, 0, ',', '.') }}</td>
            </tr>
            @endif
            <tr style="background-color: #f9fafb;">
                <td style="text-align: right; font-weight: bold; font-size: 11.5px;">Total</td>
                <td style="text-align: right; font-weight: bold; font-size: 11.5px;">Rp. {{ number_format($totalInvoice, 0, ',', '.') }}</td>
            </tr>
        </table>

        <!-- Footer: Bank info, Surat Jalan, Tanda Tangan -->
        <table class="footer-table">
            <tr>
                <td style="width: 55%; padding-top: 10px;">
                    <div class="payment-note-box">
                        <strong>Note Payment:</strong><br>
                        @if($record->rekeningPerusahaan)
                            {{ $record->rekeningPerusahaan->nama_bank }} : {{ $record->rekeningPerusahaan->no_rekening }}<br>
                            An : {{ $record->rekeningPerusahaan->atas_nama }}
                        @else
                            BCA : 316-539-9999<br>
                            An : Eddy Angkawijaya
                        @endif
                        <br><br>
                        Surat jalan nomor : {{ $record->no_nota }}<br>
                        Tgl : {{ $record->tanggal ? $record->tanggal->translatedFormat('d F Y') : date('d F Y') }}
                    </div>
                </td>
                <td style="width: 45%; text-align: center; padding-top: 10px;">
                    Malang, {{ $record->tanggal ? $record->tanggal->translatedFormat('d F Y') : date('d F Y') }}
                    <br><br><br><br><br>
                    <strong>{{ $record->pembuat?->name ?? 'Safira Herditiya Jasmine' }}</strong><br>
                    <span style="font-size: 10px; color: #555;">Fin & Acc. Mgr</span>
                </td>
            </tr>
        </table>
    </div>
</body>
</html>
