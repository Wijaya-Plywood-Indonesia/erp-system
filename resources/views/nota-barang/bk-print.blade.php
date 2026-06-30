<!DOCTYPE html>
<html>

<head>
    <title>Nota Barang Keluar</title>

    <meta name="viewport" content="width=device-width, initial-scale=1" />

    <style>
        body {
            font-family: "Segoe UI", Arial, sans-serif;
            font-size: 15px;
            margin: 25px auto;
            padding: 0 15px;
            max-width: 750px;
            line-height: 1.5;
            color: #222;
        }

        h2 {
            text-align: center;
            margin-bottom: 15px;
            font-size: 20px;
            letter-spacing: 0.5px;
        }

        .info-table,
        .detail-table,
        .signature-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            table-layout: fixed;
            /* kunci lebar tabel biar nggak melebar */
        }

        .info-table td {
            padding: 6px 0;
            vertical-align: top;
            word-wrap: break-word;
        }

        .detail-table th,
        .detail-table td {
            border: 1px solid #000;
            padding: 8px 10px;
            text-align: left;
            vertical-align: top;
            word-wrap: break-word;
            white-space: normal;
            /* biar teks turun ke bawah, tidak memanjang */
        }

        .detail-table th {
            background: #f5f5f5;
            font-weight: bold;
        }

        /* Kolom no dibikin kecil */
        .col-no {
            width: 10%;
            text-align: center;
        }

        /* Kolom jumlah & satuan dibuat sempit */
        .col-small {
            width: 15%;
        }

        .signature-table td {
            text-align: center;
            padding-top: 25px;
            word-wrap: break-word;
        }

        /* Responsif */
        @media (max-width: 600px) {
            body {
                font-size: 14px;
                margin: 10px auto;
                padding: 0 10px;
            }

            h2 {
                font-size: 18px;
            }

            .detail-table th,
            .detail-table td {
                font-size: 13px;
                padding: 6px 6px;
            }
        }

        @media print {
            body {
                margin: 10mm 15mm !important;
                padding: 0 !important;
                max-width: none;
            }
        }
    </style>
</head>

<body>
    <h2><strong>NOTA BARANG KELUAR</strong></h2>

    {{-- Informasi Nota --}}
    <table class="info-table">
        <tr>
            <td>
                <strong>Tanggal</strong>:
                {{ $record->tanggal->format('d-m-Y') }}
            </td>
            <td><strong>Kepada</strong>: {{ $record->tujuan_nota }}</td>
        </tr>
        <tr>
            <td><strong>No. Nota</strong>: {{ $record->no_nota }}</td>
            <td></td>
        </tr>
    </table>

    {{-- Detail Barang --}}
    <table class="detail-table">
        <thead>
            <tr>
                <th class="col-no">No</th>
                <th>Nama Barang</th>
                <th class="col-small">Jumlah</th>
                <th class="col-small">Satuan</th>
                <th>Keterangan</th>
            </tr>
        </thead>
        <tbody>
            @foreach($details as $item)
            <tr>
                <td class="col-no">{{ $loop->iteration }}</td>
                <td>{{ $item->nama_barang }}</td>
                <td class="col-small">{{ $item->jumlah }}</td>
                <td class="col-small">{{ $item->satuan }}</td>
                <td>{{ $item->keterangan }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    {{-- Tanda Tangan --}}
    <br /><br />

    <table class="signature-table">
        <tr>
            <td><strong>Penerima</strong></td>
            <td><strong>Pengirim</strong></td>
        </tr>
        <tr>
            <td style="height: 60px"></td>
            <td></td>
        </tr>
        <tr>
            <td>
                <strong>{{ $record->tujuan_nota}}</strong>
            </td>
            <td>
                <strong>{{ $record->pembuat->name ?? '-' }}</strong>
            </td>
        </tr>
    </table>
</body>

</html>