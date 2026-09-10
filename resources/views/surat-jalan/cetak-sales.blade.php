<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <title>Surat Jalan - {{ $nota->no_nota }}</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 0; /* Remove browser headers/footers */
        }

        body {
            font-family: Arial, sans-serif;
            font-size: 14px;
            color: #000;
            line-height: normal;
            margin: 0;
            background: #fff;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }

        @media screen {
            body { background: #eee; padding: 20px; }
            .page {
                background: #fff;
                box-shadow: 0 0 6px rgba(0, 0, 0, 0.3);
                margin: 0 auto 20px auto;
                width: 210mm;
                min-height: 297mm; /* Full A4 */
                padding: 15mm;
                box-sizing: border-box;
            }
        }

        @media print {
            .page {
                width: 100%;
                height: 100%;
                padding: 15mm;
                box-sizing: border-box;
            }
        }

        .title {
            text-align: center;
            font-size: 26px;
            font-weight: bold;
            margin-bottom: 25px;
            letter-spacing: 1px;
        }

        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }

        .header-table td {
            padding: 8px 4px;
            vertical-align: top;
        }
        
        .border-bottom {
            border-bottom: 1px solid #000;
        }

        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 40px;
        }

        .items-table th, .items-table td {
            border: 1px solid #000;
            padding: 8px;
            text-align: center;
        }

        .items-table th {
            background-color: #a6a6a6 !important; /* Grey background */
            font-weight: bold;
        }
        
        .items-table td.text-left {
            text-align: left;
        }

        .signatures {
            width: 100%;
            text-align: center;
            margin-top: 20px;
        }

        .signatures td {
            width: 25%;
            vertical-align: top;
        }

        .sig-space {
            height: 70px;
        }
    </style>
</head>
<body onload="window.print()">
    @php
        $isLong = $details->count() > 15;
    @endphp

    <div>
        <div class="page">
            <div class="title">Surat Jalan</div>

            <table class="header-table">
                <tr>
                    <td style="width: 12%; font-weight: bold;">No.</td>
                    <td style="width: 48%;">{{ $nota->no_nota }}</td>
                    <td style="width: 18%;"></td>
                    <td style="width: 2%;"></td>
                    <td style="width: 20%;"></td>
                </tr>
                <tr>
                    <td class="border-bottom" style="font-weight: bold;">Tanggal</td>
                    <td class="border-bottom">{{ $nota->tanggal?->format('d-M-y') }}</td>
                    <td class="border-bottom" style="font-weight: bold;">Sopir</td>
                    <td class="border-bottom" style="font-weight: bold;">:</td>
                    <td class="border-bottom"></td>
                </tr>
                <tr>
                    <td rowspan="2" class="border-bottom" style="font-weight: bold; vertical-align: top; padding-top: 8px;">Kepada</td>
                    <td rowspan="2" class="border-bottom" style="font-weight: bold; vertical-align: top; padding-top: 8px;">
                        {{ $nota->tujuan_nota }}
                        @if($nota->alamat)
                            <br>
                            <span style="font-weight: normal;">{!! nl2br(e($nota->alamat)) !!}</span>
                        @endif
                    </td>
                    <td style="font-weight: bold;">Mobil</td>
                    <td style="font-weight: bold;">:</td>
                    <td></td>
                </tr>
                <tr>
                    <td class="border-bottom" style="font-weight: bold;">No Plat</td>
                    <td class="border-bottom" style="font-weight: bold;">:</td>
                    <td class="border-bottom"></td>
                </tr>
            </table>

        @php
            $hasM3 = collect($details)->contains(function($d) {
                return (str_starts_with($d->nama_barang, 'Veneer ') && App\Filament\Resources\DetailNotaBarangKeluars\Tables\DetailNotaBarangKeluarsTable::findVeneerDetail($d)?->m3 !== null);
            });
        @endphp
        <table class="items-table">
            <thead>
                <tr>
                    <th width="8%">No.</th>
                    <th>Nama Barang</th>
                    <th width="12%">Satuan</th>
                    <th width="12%">Jumlah</th>
                    @if($hasM3)
                    <th width="16%" style="white-space: nowrap;">m3</th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @foreach ($details as $i => $d)
                    <tr>
                        <td>{{ $i + 1 }}</td>
                        <td class="text-left">{{ $d->nama_barang }}</td>
                        <td>{{ $d->satuan }}</td>
                        <td>{{ number_format($d->jumlah) }}</td>
                        @if($hasM3)
                        @php
                            $m3 = null;
                            if (str_starts_with($d->nama_barang, 'Veneer ')) {
                                $m3 = App\Filament\Resources\DetailNotaBarangKeluars\Tables\DetailNotaBarangKeluarsTable::findVeneerDetail($d)?->m3;
                            }
                        @endphp
                        <td style="white-space: nowrap;">{{ $m3 !== null ? number_format($m3, 4, ',', '.') : '-' }}</td>
                        @endif
                    </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="3" style="text-align: right; font-weight: bold; padding-right: 15px;">Total</td>
                        <td style="font-weight: bold;">{{ number_format($details->sum('jumlah')) }}</td>
                        @if($hasM3)
                        <td></td>
                        @endif
                    </tr>
                </tfoot>
            </table>

            <table class="signatures">
                <tr>
                    <td>Penerima</td>
                    <td>Sopir</td>
                    <td>Gudang</td>
                    <td>Hormat Kami</td>
                </tr>
                <tr>
                    <td class="sig-space"></td>
                    <td></td>
                    <td></td>
                    <td></td>
                </tr>
                <tr>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                </tr>
            </table>
        </div>
    </div>
</body>
</html>
