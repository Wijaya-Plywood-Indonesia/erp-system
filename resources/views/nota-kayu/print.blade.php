<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1" />
    <title>Nota Turus - {{ $record->no_nota }}</title>

    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 10px;
            margin: 0;
            background: #e5e5e5;
            line-height: 1.05;
        }

        .page-wrapper{
            padding: 10px;
        }

        .action-bar{
            text-align:center;
            margin-bottom:10px;
        }

        .action-bar button{
            padding:8px 12px;
            border:none;
            background:#222;
            color:white;
            border-radius:4px;
            cursor:pointer;
            margin:2px;
            font-size:12px;
        }

        .action-bar button:hover{
            opacity:0.9;
        }

        .phone-wrapper {
            width: 360px;
            margin: 0 auto;
            background: #fff;
            padding: 6px;
            box-sizing: border-box;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }

        h3 {
            margin: 0 0 3px 0;
            font-size: 12px;
            text-align: center;
            text-transform: uppercase;
            text-decoration: underline;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 4px;
        }

        th, td {
            border: 1px solid #444;
            padding: 3px;
            text-align: right;
            vertical-align: middle;
        }

        .header-table td {
            border: none;
            padding: 1px;
            text-align: left;
        }

        .group-title {
            background: #eaeaea;
            font-weight: bold;
            padding: 3px;
            margin-top: 8px;
            border: 1px solid #444;
            font-size: 10px;
        }

        .tally-wrapper {
            display: flex;
            flex-wrap: wrap;
            gap: 2px;
            justify-content: flex-start;
            min-width: 80px;
        }

        .footer {
            font-size: 9px;
            text-align: right;
            margin-top: 10px;
        }

        .signature td {
            border: none;
            text-align: center;
            padding-top: 20px;
        }

        .total-box{
            margin-top: 15px;
        }

        .total-box table{
            border: 2px solid #000;
        }

        .total-box td{
            border: 1px solid #000;
        }

        @media (max-width: 360px) {
            .phone-wrapper {
                width: 100%;
            }
        }

        @media print {

            body {
                background: white;
            }

            .page-wrapper{
                padding:0;
            }

            .phone-wrapper {
                width: 100%;
                margin: 0;
                box-shadow:none;
            }

            .action-bar{
                display:none;
            }
        }
    </style>
</head>

<body>

<div class="page-wrapper">

    <!-- ACTION BUTTON -->
    <div class="action-bar">
        <button onclick="downloadPNG()">Export PNG</button>
        <button onclick="downloadJPG()">Export JPG</button>
        <button onclick="downloadPDF()">Export PDF</button>
        <button onclick="window.print()">Print</button>
    </div>

    <!-- AREA EXPORT -->
    <div id="nota-area">

        <div class="phone-wrapper">

            <h3>Nota Kayu Turus</h3>

            <table class="header-table">
                <tr>
                    <td width="15%">No</td>
                    <td width="2%">:</td>
                    <td width="33%">{{ $record->no_nota }}</td>

                    <td width="15%">Seri</td>
                    <td width="2%">:</td>
                    <td width="33%">{{ $record->kayuMasuk->seri }}</td>
                </tr>

                <tr>
                    <td>Nama</td>
                    <td>:</td>
                    <td>{{ $record->kayuMasuk->penggunaanSupplier->nama_supplier ?? '-' }}</td>

                    <td>Tgl</td>
                    <td>:</td>
                    <td>
                        {{ \Carbon\Carbon::parse($record->kayuMasuk->tgl_kayu_masuk)->format('d-m-Y') }}
                    </td>
                </tr>

                <tr>
                    <td>Nopol</td>
                    <td>:</td>
                    <td>{{ $record->kayuMasuk->penggunaanKendaraanSupplier->nopol_kendaraan ?? '-' }}</td>

                    <td>Legal</td>
                    <td>:</td>
                    <td>{{ $record->kayuMasuk->penggunaanDokumenKayu->dokumen_legal ?? '-' }}</td>
                </tr>
            </table>

            @foreach($groupedDetails as $key => $items)

                @php

                    [$kodeLahan, $grade, $panjang, $jenis] = explode('|', $key);

                    $gradeText = $grade == 1
                        ? 'A'
                        : ($grade == 2 ? 'B' : '-');

                    $firstItem = $items->first();

                    $idJenis = optional($firstItem->jenisKayu)->id
                        ?? $firstItem->id_jenis_kayu
                        ?? $jenisKayuId;

                    $dataTabel = $controller->groupByDiameterSpesifik(
                        $items,
                        $idJenis,
                        $grade,
                        $panjang
                    );

                    $subBatang = $dataTabel->sum('batang');

                @endphp

                <div class="group-title">
                    {{ $kodeLahan }}
                    -
                    {{ $panjang }} cm
                    {{ $jenis }}
                    ({{ $gradeText }})
                </div>

                <table>

                    <thead>
                        <tr style="background: #f7f7f7;">
                            <th style="text-align: center; width: 15%">
                                D
                            </th>

                            <th style="text-align: left; width: 65%">
                                Turus
                            </th>

                            <th style="text-align: center; width: 20%">
                                Btg
                            </th>
                        </tr>
                    </thead>

                    <tbody>

                        @forelse($dataTabel as $row)

                            <tr>

                                <td style="text-align: center; font-weight: bold;">
                                    {{ $row['diameter'] }}
                                </td>

                                <td style="text-align: left;">

                                    <div class="tally-wrapper">

                                        @php
                                            $cnt = (int)$row['batang'];
                                            $groups = floor($cnt / 5);
                                            $rem = $cnt % 5;
                                        @endphp

                                        @for($i = 0; $i < $groups; $i++)

                                            <svg
                                                width="18"
                                                height="18"
                                                viewBox="0 0 50 50"
                                                style="
                                                    stroke:#222;
                                                    fill:none;
                                                    stroke-width:5px;
                                                    stroke-linecap:round;
                                                    stroke-linejoin:round;
                                                "
                                            >
                                                <path d="M10 5 V45" />
                                                <path d="M20 5 V45" />
                                                <path d="M30 5 V45" />
                                                <path d="M40 5 V45" />
                                                <path
                                                    d="M5 45 L45 5"
                                                    style="
                                                        stroke:#d00;
                                                        opacity:0.7;
                                                    "
                                                />
                                            </svg>

                                        @endfor

                                        @if($rem > 0)

                                            <svg
                                                width="18"
                                                height="18"
                                                viewBox="0 0 50 50"
                                                style="
                                                    stroke:#222;
                                                    fill:none;
                                                    stroke-width:5px;
                                                    stroke-linecap:round;
                                                "
                                            >

                                                @for($j = 1; $j <= $rem; $j++)

                                                    <path d="M{{ $j * 10 }} 5 V45" />

                                                @endfor

                                            </svg>

                                        @endif

                                    </div>

                                </td>

                                <td style="text-align: center; font-weight: bold;">
                                    {{ number_format($row['batang'], 0, ',', '.') }}
                                </td>

                            </tr>

                        @empty

                            <tr>
                                <td colspan="3" style="text-align: center">
                                    Data Kosong
                                </td>
                            </tr>

                        @endforelse

                    </tbody>

                    <tfoot>

                        <tr style="background: #f7f7f7; font-weight: bold;">

                            <td colspan="2" style="text-align: center">
                                Subtotal
                            </td>

                            <td style="text-align: center">
                                {{ number_format($subBatang, 0, ',', '.') }}
                            </td>

                        </tr>

                    </tfoot>

                </table>

            @endforeach

            <!-- TOTAL -->
            <div class="total-box">

                <table>

                    <tr>

                        <td
                            style="
                                font-weight: bold;
                                width: 50%;
                            "
                        >
                            Total Batang Keseluruhan
                        </td>

                        <td
                            style="
                                width: 50%;
                                text-align: center;
                                font-size: 14px;
                                font-weight: bold;
                            "
                        >
                            {{ number_format($totalBatangGlobal) }} Btg
                        </td>

                    </tr>

                </table>

            </div>

            <!-- SIGNATURE -->
            <table class="signature">

                <tr>
                    <td>Penanggung Jawab</td>
                    <td>Grader Kayu</td>
                </tr>

                <tr>
                    <td style="height: 40px"></td>
                    <td></td>
                </tr>

                <tr>

                    <td>
                        (
                        {{ $record->penanggung_jawab ?? '...................' }}
                        )
                    </td>

                    <td>
                        (
                        {{ $record->penerima ?? '...................' }}
                        )
                    </td>

                </tr>

            </table>

            <div class="footer">
                Dicetak:
                {{ now()->format('d/m/Y H:i') }}
            </div>

        </div>

    </div>

</div>

<!-- LIBRARY -->
<script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>

<script src="https://cdn.jsdelivr.net/npm/jspdf@2.5.1/dist/jspdf.umd.min.js"></script>

<script>

    async function generateCanvas() {

        const source = document.querySelector('.phone-wrapper');
        const CONTENT_W = source.offsetWidth; // 360px

        // Build an isolated off-screen container exactly CONTENT_W wide
        const offscreen = document.createElement('div');
        Object.assign(offscreen.style, {
            position:   'fixed',
            top:        '0',
            left:       '0',
            width:      CONTENT_W + 'px',
            overflow:   'hidden',
            zIndex:     '-9999',
            background: '#fff',
            pointerEvents: 'none'
        });

        // Deep-clone the content and strip any box-shadow / margin
        const clone = source.cloneNode(true);
        Object.assign(clone.style, {
            width:     CONTENT_W + 'px',
            margin:    '0',
            boxShadow: 'none',
            position:  'static'
        });

        offscreen.appendChild(clone);
        document.body.appendChild(offscreen);

        // Let the browser lay out the clone before measuring
        await new Promise(r => requestAnimationFrame(() => requestAnimationFrame(r)));

        const contentHeight = clone.offsetHeight;

        const canvas = await html2canvas(offscreen, {
            scale:       4,
            useCORS:     true,
            allowTaint:  true,
            backgroundColor: '#ffffff',
            logging:     false,
            width:       CONTENT_W,
            height:      contentHeight,
            windowWidth: CONTENT_W,
            x:           0,
            y:           0
        });

        document.body.removeChild(offscreen);

        return canvas;

    }

    async function downloadPNG() {

        try {

            const canvas = await generateCanvas();

            const link = document.createElement('a');

            link.download = 'nota-turus-{{ $record->no_nota }}.png';

            link.href = canvas.toDataURL('image/png');

            link.click();

        } catch (e) {

            alert('Gagal export PNG');

            console.error(e);

        }

    }

    async function downloadJPG() {

        try {

            const canvas = await generateCanvas();

            const link = document.createElement('a');

            link.download = 'nota-turus-{{ $record->no_nota }}.jpg';

            link.href = canvas.toDataURL('image/jpeg', 1.0);

            link.click();

        } catch (e) {

            alert('Gagal export JPG');

            console.error(e);

        }

    }

    async function downloadPDF() {

        try {

            const canvas = await generateCanvas();

            const imgData = canvas.toDataURL('image/png');

            const { jsPDF } = window.jspdf;

            const pxToMm = 0.264583;

            const pdfWidth  = canvas.width  * pxToMm;
            const pdfHeight = canvas.height * pxToMm;

            const pdf = new jsPDF({

                orientation: pdfWidth > pdfHeight ? 'landscape' : 'portrait',

                unit: 'mm',

                format: [pdfWidth, pdfHeight]

            });

            pdf.addImage(
                imgData,
                'PNG',
                0,
                0,
                pdfWidth,
                pdfHeight
            );

            pdf.save('nota-turus-{{ $record->no_nota }}.pdf');

        } catch (e) {

            alert('Gagal export PDF');

            console.error(e);

        }

    }

</script>

</body>
</html>