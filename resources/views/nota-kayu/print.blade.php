<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1" />
    <title>Laporan Pembelian Kayu</title>

    <style>
        .phone-wrapper {
            width: 360px;
            margin: 0 auto;
            background: #fff;
            padding: 6px;
            /* lebih rapat */
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            font-size: 10px;
            margin: 0;
            background: #e5e5e5;
            line-height: 1.05;
            /* sangat rapat */
        }

        h3 {
            margin: 0 0 3px 0;
            /* lebih rapat */
            font-size: 12px;
            text-align: center;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 2px;
            /* jarak antar tabel sangat rapat */
        }

        th,
        td {
            border: 1px solid #444;
            padding: 2px;
            /* padding diperkecil */
            text-align: right;
        }

        .header-table td {
            border: none;
            padding: 1px;
            /* header lebih rapat */
            text-align: left;
        }

        .group-title {
            background: #eaeaea;
            font-weight: bold;
            padding: 2px;
            /* lebih rapat */
            margin-top: 6px;
            /* antar group dirapatkan */
            border: 1px solid #444;
            font-size: 10px;
        }

        .signature td {
            border: none;
            padding: 2px;
            /* tanda tangan lebih rapat */
            text-align: center;
        }

        .footer {
            font-size: 9px;
            text-align: right;
            margin-top: 6px;
            /* rapat */
        }

        @media (max-width: 360px) {
            .phone-wrapper {
                width: 100%;
            }
        }
    </style>
</head>

<body>
    <div class="phone-wrapper">
        <h3 style="text-align: center">NOTA KAYU</h3>

        <table class="header-table">
            <tr>
                <td>No : {{ $record->no_nota }}</td>
                <td>Seri : {{ $record->kayuMasuk->seri }}</td>
                <td>{{ $record->kayuMasuk->tgl_kayu_masuk }}</td>
            </tr>
            <tr>
                <td>
                    {{ $record->kayuMasuk->penggunaanSupplier->nama_supplier ?? '-' }}
                </td>
                <td>
                    {{ $record->kayuMasuk->penggunaanKendaraanSupplier->nopol_kendaraan ?? '-' }}
                </td>
                <td>
                    {{ $record->kayuMasuk->penggunaanDokumenKayu->dokumen_legal ?? '-' }}
                </td>
            </tr>
        </table>

        @php $details = $record->kayuMasuk->detailTurusanKayus ?? collect();
        $grouped = $details->groupBy(function($item) { $kodeLahan =
        optional($item->lahan)->kode_lahan ?? '-'; $grade = $item->grade ?? 0;
        $panjang = $item->panjang ?? '-'; $jenis =
        optional($item->jenisKayu)->nama_kayu ?? '-'; return
        "{$kodeLahan}|{$grade}|{$panjang}|{$jenis}"; }); $grandBatang = 0;
        $grandM3 = 0; $grandHarga = 0; @endphp @foreach($grouped as $key =>
        $items) @php [$kodeLahan, $grade, $panjang, $jenis] = explode('|',
        $key); $gradeText = $grade == 1 ? 'A' : ($grade == 2 ? 'B' : '-');
        $subtotalBatang = $items->sum('kuantitas'); $subtotalM3 =
        $items->sum('kubikasi'); $subtotalHarga = $items->sum('total_harga');
        $grandBatang += $subtotalBatang; $grandM3 += $subtotalM3; $grandHarga +=
        $subtotalHarga; @endphp

        <div class="group-title">
            {{ $kodeLahan }} &nbsp;&nbsp; {{ $panjang }} cm {{ $jenis }} ({{
                $gradeText
            }})
        </div>
        @php $firstItem = $items->first(); $idJenisKayu =
        optional($firstItem->jenisKayu)->id ?? $firstItem->id_jenis_kayu ??
        null; $groupedByDiameter =
        app(\App\Http\Controllers\NotaKayuController::class)
        ->groupByRentangDiameter($items, $idJenisKayu, $grade, $panjang);
        @endphp

        {{-- === Rekap per Rentang Diameter === --}}
        <table border="1" cellspacing="0" cellpadding="5" width="100%">
            <thead>
                <tr>
                    <th style="text-align: center">Rentang D (cm)</th>
                    <th style="text-align: center">Btg</th>
                    <th style="text-align: center">m³</th>
                    <th style="text-align: center">Harga</th>
                    <th style="text-align: center">Poin</th>
                </tr>
            </thead>
            <tbody>
                @forelse($groupedByDiameter as $detail)
                <tr>
                    <td style="text-align: center">{{ $detail["rentang"] }}</td>
                    <td style="text-align: right">{{ $detail["batang"] }}</td>
                    <td style="text-align: right">
                        {{ number_format($detail["kubikasi"], 4, ",", ".") }} m³
                    </td>
                    <td style="text-align: right">
                        {{
                            number_format($detail["harga_satuan"], 0, ",", ".")
                        }}
                    </td>
                    <td style="text-align: right">
                        {{ number_format($detail["total_harga"], 0, ",", ".") }}
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" style="text-align: center">
                        Tidak ada data
                    </td>
                </tr>
                @endforelse
            </tbody>
            @php $totalBatangGrup = $groupedByDiameter->sum('batang');
            $totalKubikasiGrup = $groupedByDiameter->sum('kubikasi');
            $totalHargaGrup = $groupedByDiameter->sum('total_harga'); @endphp

            <tfoot>
                <tr style="font-weight: bold; background: #f7f7f7">
                    <td style="text-align: center">Total</td>
                    <td style="text-align: right">
                        {{ number_format($totalBatangGrup, 0, ",", ".") }}
                    </td>
                    <td style="text-align: right">
                        {{ number_format($totalKubikasiGrup, 4, ",", ".") }}
                    </td>
                    <td></td>
                    <td style="text-align: right">
                        {{ number_format($totalHargaGrup, 0, ",", ".") }}
                    </td>
                </tr>
            </tfoot>
        </table>

        {{-- === Tabel lama masih ada di bawah untuk perbandingan === --}}
        @endforeach
        <div style="margin-top: 20px; display: flex; justify-content: flex-end">
            <table
                style="
                    border-collapse: collapse;
                    text-align: right;
                    min-width: 300px;
                    width: 100%;
                ">
                <tr>
                    <td style="border: 1px solid #000">Total Kubikasi</td>
                    <td style="border: 1px solid #000">
                        {{ number_format($totalKubikasi, 4, ",", ".") }} m³
                    </td>
                    <td style="text-align: right; border: 1px solid #000">
                        Grand Total
                    </td>
                    <td style="border: 1px solid #000">
                        Rp. {{ number_format($grandTotal, 0, ",", ".") }}
                    </td>
                </tr>

                <tr>
                    <td
                        style="
                            text-align: right;
                            padding: 4px 10px;
                            border: 1px solid #000;
                        ">
                        Total Batang
                    </td>
                    <td style="padding: 4px 10px; border: 1px solid #000">
                        {{ number_format($totalBatang) }} Batang
                    </td>
                    <td></td>
                    <td style="padding: 4px 10px; border: 1px solid #000">
                        Rp. {{ number_format($selisih, 0, ",", ".") }}
                    </td>
                </tr>

                <tr>
                    <td
                        colspan="4"
                        style="
                            text-align: right;
                            font-weight: bold;
                            font-size: 18px;
                            padding: 10px 12px;
                            border: 2px solid #000;
                            background: #f2f2f2;
                        ">
                        Total Akhir: Rp.
                        {{ number_format($hargaFinal, 0, ",", ".") }}
                    </td>
                </tr>
            </table>
        </div>

        <table class="signature" style="width: 100%">
            <tr>
                <td>Penanggung Jawab Kayu</td>
                <td>Grader Kayu</td>
            </tr>
            <tr>
                <td style="height: 10px"></td>
                <td></td>
            </tr>
            <tr>
                <td>{{ $record->penanggung_jawab ?? '-' }}</td>
                <td>{{ $record->penerima ?? '-' }}</td>
            </tr>
        </table>

        <div class="footer">Dicetak pada: {{ now()->format('d-m-Y H:i') }}</div>
</body>

</html>