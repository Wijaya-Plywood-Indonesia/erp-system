<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1" />
    <title>Laporan Pembelian Kayu</title>

    <!-- html2canvas & jsPDF via CDN -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>

    <style>
        .phone-wrapper {
            width: 360px;
            margin: 0 auto;
            background: #fff;
            padding: 6px;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            font-size: 10px;
            margin: 0;
            background: #e5e5e5;
            line-height: 1.05;
        }

        h3 {
            margin: 0 0 3px 0;
            font-size: 12px;
            text-align: center;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 2px;
        }

        th,
        td {
            border: 1px solid #444;
            padding: 2px;
            text-align: right;
        }

        .header-table td {
            border: none;
            padding: 1px;
            text-align: left;
        }

        .group-title {
            background: #eaeaea;
            font-weight: bold;
            padding: 2px;
            margin-top: 6px;
            border: 1px solid #444;
            font-size: 10px;
        }

        .signature td {
            border: none;
            padding: 2px;
            text-align: center;
        }

        .footer {
            font-size: 9px;
            text-align: right;
            margin-top: 6px;
        }

        /* ===== EXPORT TOOLBAR ===== */
        .export-toolbar {
            width: 360px;
            margin: 0 auto 10px auto;
            display: flex;
            gap: 6px;
            justify-content: center;
            padding: 8px 6px;
            background: #fff;
            border-radius: 6px;
            box-shadow: 0 1px 4px rgba(0, 0, 0, 0.12);
            box-sizing: border-box;
        }

        .export-btn {
            flex: 1;
            padding: 7px 4px;
            font-size: 11px;
            font-weight: bold;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 4px;
            transition: opacity 0.15s, transform 0.1s;
        }

        .export-btn:active {
            transform: scale(0.96);
            opacity: 0.85;
        }

        .btn-jpg {
            background: #f59e0b;
            color: #fff;
        }

        .btn-png {
            background: #3b82f6;
            color: #fff;
        }

        .btn-pdf {
            background: #ef4444;
            color: #fff;
        }

        .export-btn svg {
            width: 13px;
            height: 13px;
            fill: currentColor;
            flex-shrink: 0;
        }

        /* Loading overlay */
        #export-loading {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.45);
            z-index: 9999;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            gap: 10px;
            color: #fff;
            font-family: Arial, sans-serif;
            font-size: 14px;
        }

        #export-loading.show {
            display: flex;
        }

        .spinner {
            width: 36px;
            height: 36px;
            border: 4px solid rgba(255, 255, 255, 0.3);
            border-top-color: #fff;
            border-radius: 50%;
            animation: spin 0.7s linear infinite;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        /* Sembunyikan toolbar saat cetak browser biasa */
        @media print {
            .export-toolbar {
                display: none !important;
            }
        }

        @media (max-width: 360px) {

            .phone-wrapper,
            .export-toolbar {
                width: 100%;
            }
        }
    </style>
</head>

<body>

    <!-- ===== LOADING OVERLAY ===== -->
    <div id="export-loading">
        <div class="spinner"></div>
        <span id="loading-text">Memproses...</span>
    </div>

    <!-- ===== TOMBOL EXPORT ===== -->
    <div class="export-toolbar" id="export-toolbar">
        <button class="export-btn btn-jpg" onclick="exportNota('jpg')" title="Simpan sebagai JPG">
            <svg viewBox="0 0 24 24">
                <path
                    d="M5 3h14a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2zm7 3a5 5 0 1 0 0 10A5 5 0 0 0 12 6zm0 2a3 3 0 1 1 0 6 3 3 0 0 1 0-6z" />
            </svg>
            JPG
        </button>
        <button class="export-btn btn-png" onclick="exportNota('png')" title="Simpan sebagai PNG">
            <svg viewBox="0 0 24 24">
                <path
                    d="M5 3h14a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2zm2 4v10h2V9h4v2H9v2h4a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2H7z" />
            </svg>
            PNG
        </button>
        <button class="export-btn btn-pdf" onclick="exportNota('pdf')" title="Simpan sebagai PDF">
            <svg viewBox="0 0 24 24">
                <path
                    d="M6 2a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6H6zm7 1.5L18.5 9H13V3.5zM8 12h2c1.1 0 2 .9 2 2s-.9 2-2 2H9v2H8v-6zm1 1v2h1a1 1 0 0 0 0-2H9zm4-1h1.5c1.38 0 2.5 1.12 2.5 2.5S15.88 17 14.5 17H13v-5zm1 1v3h.5a1.5 1.5 0 0 0 0-3H14z" />
            </svg>
            PDF
        </button>
    </div>

    <!-- ===== KONTEN NOTA (yang akan di-capture) ===== -->
    <div class="phone-wrapper" id="nota-content">
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

        @php
            $details = $record->kayuMasuk->detailTurusanKayus ?? collect();
            $grouped = $details->groupBy(function ($item) {
                $kodeLahan = optional($item->lahan)->kode_lahan ?? '-';
                $grade = $item->grade ?? 0;
                $panjang = $item->panjang ?? '-';
                $jenis = optional($item->jenisKayu)->nama_kayu ?? '-';
                return "{$kodeLahan}|{$grade}|{$panjang}|{$jenis}";
            });
            $grandBatang = 0;
            $grandM3 = 0;
            $grandHarga = 0;
        @endphp

        @foreach ($grouped as $key => $items)
            @php
                [$kodeLahan, $grade, $panjang, $jenis] = explode('|', $key);
                $gradeText = $grade == 1 ? 'A' : ($grade == 2 ? 'B' : '-');
                $subtotalBatang = $items->sum('kuantitas');
                $subtotalM3 = $items->sum('kubikasi');
            @endphp

            <div class="group-title">
                {{ $kodeLahan }} &nbsp;&nbsp; {{ $panjang }} cm {{ $jenis }} ({{ $gradeText }})
            </div>

            @php
                $firstItem = $items->first();
                $idJenisKayu = optional($firstItem->jenisKayu)->id ?? ($firstItem->id_jenis_kayu ?? null);
                $groupedByDiameter = app(\App\Http\Controllers\NotaKayuController::class)->groupByRentangDiameter(
                    $items,
                    $idJenisKayu,
                    $grade,
                    $panjang,
                );

                // FIX: $items adalah koleksi model mentah (tidak punya kolom/atribut
                // 'total_harga' di database), jadi $items->sum('total_harga') selalu
                // menghasilkan 0. Subtotal harga yang benar harus diambil dari
                // $groupedByDiameter (array hasil olahan yang punya key 'total_harga').
                $subtotalHarga = $groupedByDiameter->sum('total_harga');

                $grandBatang += $subtotalBatang;
                $grandM3 += $subtotalM3;
                $grandHarga += $subtotalHarga;
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
                            <td style="text-align: center">{{ $detail['rentang'] }}</td>
                            <td style="text-align: right">{{ $detail['batang'] }}</td>
                            <td style="text-align: right">
                                {{ number_format($detail['kubikasi'], 4, ',', '.') }} m³
                            </td>
                            <td style="text-align: right">
                                {{ number_format($detail['harga_satuan'], 0, ',', '.') }}
                            </td>
                            <td style="text-align: right">
                                {{ number_format($detail['total_harga'], 0, ',', '.') }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" style="text-align: center">Tidak ada data</td>
                        </tr>
                    @endforelse
                </tbody>

                @php
                    $totalBatangGrup = $groupedByDiameter->sum('batang');
                    $totalKubikasiGrup = $groupedByDiameter->sum('kubikasi');
                    $totalHargaGrup = $groupedByDiameter->sum('total_harga');
                @endphp

                <tfoot>
                    <tr style="font-weight: bold; background: #f7f7f7">
                        <td style="text-align: center">Total</td>
                        <td style="text-align: right">{{ number_format($totalBatangGrup, 0, ',', '.') }}</td>
                        <td style="text-align: right">{{ number_format($totalKubikasiGrup, 4, ',', '.') }}</td>
                        <td></td>
                        <td style="text-align: right">{{ number_format($totalHargaGrup, 0, ',', '.') }}</td>
                    </tr>
                </tfoot>
            </table>
        @endforeach

        {{-- ============================================================
             FIX: Grand Total & Total Akhir dihitung ulang di sini berbasis
             $grandHarga (akumulasi per-kategori dari loop di atas, sudah
             terbukti benar / match dengan tabel yang tercetak).

             SEBELUMNYA blade memakai $grandTotal, $selisih, $hargaFinal
             yang dikirim dari controller — tapi controller menghitung
             groupedByDiameter untuk SEMUA 141 batang sekaligus dengan
             jenis/grade/panjang hanya dari item PERTAMA saja, sehingga
             tidak sesuai dengan grouping per-kategori (kodeLahan+grade+
             panjang+jenis) yang dipakai di blade. Akibatnya Grand Total
             dari controller tidak match dengan jumlah tabel yang tercetak.

             Sekarang semua dihitung ulang di sini berbasis $grandHarga,
             yang sudah pasti konsisten dengan tabel di atas karena berasal
             dari akumulasi $subtotalHarga per kategori yang sama persis.
             ============================================================ --}}
        @php
            $grandTotalFix = (int) round($grandHarga);

            $biayaTurunPerM3 = 5000;
            $hasilDasarFix = round($totalKubikasi * $biayaTurunPerM3);
            $biayaFloorFix = floor($hasilDasarFix / 1000) * 1000;
            $sisaRibuanFix = $grandTotalFix % 1000;
            $biayaTurunKayuFix = (int) ($biayaFloorFix + $sisaRibuanFix + 10000);

            $hargaBeliAkhirFix = (int) ($grandTotalFix - $biayaTurunKayuFix);

            // Tahap 1: bulatkan ke kelipatan 5000
            $modFix = $hargaBeliAkhirFix % 5000;
            $hargaBeliAkhirBulatFix =
                $modFix >= 2500 ? $hargaBeliAkhirFix + (5000 - $modFix) : $hargaBeliAkhirFix - $modFix;

            // Tahap 2: tambah adjustment manual (masih dari controller, tidak berubah)
            $totalAkhirFix = (int) ($hargaBeliAkhirBulatFix + $pembulatanManual);

            // Tahap 3: bulatkan lagi ke kelipatan 5000
            $modFinalFix = $totalAkhirFix % 5000;
            $totalAkhirFix =
                $modFinalFix >= 2500 ? $totalAkhirFix + (5000 - $modFinalFix) : $totalAkhirFix - $modFinalFix;

            $selisihFix = (int) ($grandTotalFix - $totalAkhirFix);
        @endphp

        <div style="margin-top: 20px; display: flex; justify-content: flex-end">
            <table style="border-collapse: collapse; text-align: right; min-width: 300px; width: 100%;">
                <tr>
                    <td style="border: 1px solid #000">Total Kubikasi</td>
                    <td style="border: 1px solid #000">
                        {{ number_format($totalKubikasi, 4, ',', '.') }} m³
                    </td>
                    <td style="text-align: right; border: 1px solid #000">Grand Total</td>
                    <td style="border: 1px solid #000">
                        Rp. {{ number_format($grandTotalFix, 0, ',', '.') }}
                    </td>
                </tr>
                <tr>
                    <td style="text-align: right; padding: 4px 10px; border: 1px solid #000">Total Batang</td>
                    <td style="padding: 4px 10px; border: 1px solid #000">
                        {{ number_format($totalBatang) }} Batang
                    </td>
                    <td></td>
                    <td style="padding: 4px 10px; border: 1px solid #000">
                        Rp. {{ number_format($selisihFix, 0, ',', '.') }}
                    </td>
                </tr>
                <tr>
                    <td colspan="4"
                        style="text-align: right; font-weight: bold; font-size: 18px; padding: 10px 12px; border: 2px solid #000; background: #f2f2f2;">
                        Total Akhir: Rp. {{ number_format($totalAkhirFix, 0, ',', '.') }}
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
    </div>

    <!-- ===== SCRIPT EXPORT ===== -->
    <script>
        // Nama file berdasarkan no_nota dari Blade
        const noNota = "{{ $record->no_nota ?? 'nota-kayu' }}";

        function showLoading(text) {
            document.getElementById('loading-text').textContent = text;
            document.getElementById('export-loading').classList.add('show');
        }

        function hideLoading() {
            document.getElementById('export-loading').classList.remove('show');
        }

        async function captureNota() {
            const el = document.getElementById('nota-content');
            return await html2canvas(el, {
                scale: 2, // resolusi 2x supaya tajam
                useCORS: true,
                backgroundColor: '#ffffff',
                logging: false
            });
        }

        async function exportNota(format) {
            try {
                showLoading(
                    format === 'jpg' ? 'Membuat JPG...' :
                    format === 'png' ? 'Membuat PNG...' :
                    'Membuat PDF...'
                );

                // Beri sedikit jeda agar loading muncul dulu
                await new Promise(r => setTimeout(r, 80));

                const canvas = await captureNota();
                const filename = `nota-kayu-${noNota}`;

                if (format === 'jpg') {
                    const url = canvas.toDataURL('image/jpeg', 0.92);
                    triggerDownload(url, `${filename}.jpg`);

                } else if (format === 'png') {
                    const url = canvas.toDataURL('image/png');
                    triggerDownload(url, `${filename}.png`);

                } else if (format === 'pdf') {
                    const {
                        jsPDF
                    } = window.jspdf;

                    const imgWidth = canvas.width;
                    const imgHeight = canvas.height;

                    // Konversi px -> mm (96dpi → 1px = 0.264583mm, lalu ÷ scale 2)
                    const pxToMm = 0.264583 / 2;
                    const pdfW = imgWidth * pxToMm;
                    const pdfH = imgHeight * pxToMm;

                    const pdf = new jsPDF({
                        orientation: pdfW > pdfH ? 'landscape' : 'portrait',
                        unit: 'mm',
                        format: [pdfW, pdfH] // halaman pas dengan konten
                    });

                    pdf.addImage(
                        canvas.toDataURL('image/jpeg', 0.92),
                        'JPEG', 0, 0, pdfW, pdfH
                    );
                    pdf.save(`${filename}.pdf`);
                }

            } catch (err) {
                alert('Gagal export: ' + err.message);
                console.error(err);
            } finally {
                hideLoading();
            }
        }

        function triggerDownload(dataUrl, filename) {
            const a = document.createElement('a');
            a.href = dataUrl;
            a.download = filename;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
        }
    </script>

</body>

</html>
