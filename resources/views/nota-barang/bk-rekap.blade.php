<!DOCTYPE html>
<html>
    <head>
        <meta charset="UTF-8" />
        <title>Rekap Nota Barang Keluar</title>

        <style>
            /* SETUP PRINT */
            @page {
                size: 21.6cm 33cm; /* F4 */
                margin: 15mm;
            }

            body {
                font-family: Arial, sans-serif;
                font-size: 11px;
                color: #000;
            }

            .toolbar {
                margin-bottom: 8px;
                text-align: right;
            }

            .export-btn {
                padding: 6px 12px;
                background: #1d4ed8;
                color: white;
                text-decoration: none;
                border-radius: 3px;
                font-size: 11px;
            }

            /* HEADER */
            .header {
                text-align: center;
                margin-bottom: 12px;
            }

            .header h2 {
                margin: 0;
                font-size: 14px;
                letter-spacing: 0.5px;
            }

            .sub {
                font-size: 10px;
            }

            /* TABLE */
            table {
                width: 100%;
                border-collapse: collapse;
                page-break-inside: auto;
            }

            thead {
                display: table-header-group; /* repeat header on new page */
            }

            tr {
                page-break-inside: avoid;
                page-break-after: auto;
            }

            th,
            td {
                border: 1px solid black;
                padding: 4px 6px;
                font-size: 10.5px;
            }

            th {
                background: #ededed;
                text-align: center;
            }

            td {
                vertical-align: top;
            }

            .center {
                text-align: center;
            }

            .right {
                text-align: right;
            }
        </style>
    </head>
    <body>
        {{-- BUTTON EXPORT (TIDAK IKUT PRINT) --}}
        <div class="toolbar no-print">
            <a
                href="{{ route('nota-bk.export') }}"
                class="export-btn"
                target="_blank"
            >
                ⬇ Export Excel
            </a>
        </div>

        <div class="header">
            <h2>REKAP NOTA BARANG KELUAR</h2>
            <div class="sub">Dicetak: {{ now()->format('d-m-Y H:i') }}</div>
        </div>

        <table>
            <thead>
                <tr>
                    <th width="4%">No</th>
                    <th width="11%">Tanggal</th>
                    <th width="15%">No Nota</th>
                    <th width="16%">Nama</th>

                    <th>Nama Barang</th>
                    <th width="8%">Jumlah</th>
                    <th width="8%">Satuan</th>
                </tr>
            </thead>

            <tbody>
                @forelse ($details as $i => $row)
                <tr>
                    <td class="center">{{ $i + 1 }}</td>

                    <td class="center">
                        {{ \Carbon\Carbon::parse($row->tanggal)->format('d-m-Y') }}
                    </td>

                    <td>{{ $row->no_nota }}</td>
                    <td>{{ $row->tujuan_nota }}</td>

                    <td>{{ $row->nama_barang }}</td>

                    <td class="right">{{ $row->jumlah }}</td>
                    <td class="center">{{ $row->satuan }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="center">Tidak ada data</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </body>
</html>
