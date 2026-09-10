<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8" />
    <title>Nota {{ $notaBarangKeluar->no_nota }}</title>

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
            padding: 5mm;
            position: relative;
            overflow: hidden;
        }

        /* =====================
           GARIS POTONG
           ===================== */
        .cut-line {
            position: absolute;
            top: 50%;
            left: 5mm;
            right: 5mm;
            transform: translateY(-50%);
            border-top: 1px dashed #000;
        }

        .cut-text {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: #fff;
            padding: 0 6px;
            font-size: 10px;
        }

        /* =====================
           WATERMARK BELUM LUNAS (dimatikan sementara, status selalu LUNAS)
           ===================== */
        .watermark {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 50%;
            z-index: 999;
            pointer-events: none;
            user-select: none;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .watermark span {
            font-size: 70px;
            font-weight: bold;
            color: rgba(255, 0, 0, 0.35);
            border: 6px solid rgba(255, 0, 0, 0.35);
            padding: 10px 30px;
            white-space: nowrap;
            transform: rotate(-35deg);
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
            border: 1px solid #000;
            padding: 12px;
        }

        .text-right {
            text-align: right;
        }

        .text-center {
            text-align: center;
        }
    </style>
</head>

<body onload="window.print()">
    <div class="page">

        {{-- STATUS TRANSAKSI: ALWAYS LUNAS (belum ada kolom status di NotaBarangKeluar) --}}
        {{-- Watermark sengaja tidak ditampilkan karena status selalu LUNAS --}}

        <h2 class="text-center">Nota</h2>

        <table style="border: none">
            <tr>
                <td style="border: none">
                    <strong>No:</strong> {{ $notaBarangKeluar->no_nota }}<br />
                    <strong>Tanggal:</strong>
                    {{ $notaBarangKeluar->tanggal ? $notaBarangKeluar->tanggal->format('d-m-Y') : '-' }}
                </td>
                <td style="border: none" class="text-right">
                    <strong>Kepada:</strong><br />
                    {{ $notaBarangKeluar->tujuan_nota }}
                </td>
            </tr>
        </table>

        <br />

        <table>
            <thead>
                <tr>
                    <th>No</th>
                    <th>Nama Barang</th>
                    <th>Satuan</th>
                    <th>Qty</th>
                    <th>Harga</th>
                    <th>Subtotal</th>
                </tr>
            </thead>
            <tbody>
                @php
                    $grandTotal = 0;
                    $itemList =
                        $items ??
                        ($notaBarangKeluar->plywoodMutasi && $notaBarangKeluar->plywoodMutasi->details->isNotEmpty()
                            ? $notaBarangKeluar->plywoodMutasi->details
                            : $notaBarangKeluar->detail);
                @endphp
                @foreach ($itemList as $i => $detail)
                    @php
                        $namaBarang = $detail->barang?->label ?? ($detail->nama_barang ?? '-');
                        $satuan = $detail->satuan ?? 'Lembar';
                        $qty = $detail->qty ?? ($detail->jumlah ?? 0);
                        $hargaSatuan = $detail->harga ?? 0;
                        $subtotal = $hargaSatuan * $qty;
                        $grandTotal += $subtotal;
                    @endphp
                    <tr>
                        <td class="text-center">{{ $i + 1 }}</td>
                        <td>{{ $namaBarang }}</td>
                        <td class="text-center">{{ $satuan }}</td>
                        <td class="text-right">
                            {{ number_format($qty) }}
                        </td>
                        <td class="text-right">
                            {{ number_format($hargaSatuan) }}
                        </td>
                        <td class="text-right">
                            {{ number_format($subtotal) }}
                        </td>
                    </tr>
                @endforeach
                <tr>
                    <td colspan="5" class="text-right">
                        <strong>Total</strong>
                    </td>
                    <td class="text-right">
                        <strong>{{ number_format($grandTotal) }}</strong>
                    </td>
                </tr>
            </tbody>
        </table>

        <br />

        {{-- METODE PEMBAYARAN: null dulu, jadi block ini tidak ditampilkan --}}

        <br /><br />

        <table style="border: none">
            <tr>
                <td style="border: none">Cek</td>
                <td style="border: none" class="text-right">
                    Hormat Kami<br /><br /><br />
                    <strong>{{ $notaBarangKeluar->pembuat->name ?? '-' }}</strong>
                </td>
            </tr>
        </table>

        <!-- GARIS POTONG -->
        <div class="cut-line"></div>
        <div class="cut-text">✂ Potong di sini</div>

    </div>
</body>

</html>
