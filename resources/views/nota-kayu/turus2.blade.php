<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1" />
    <title>Nota Turus Kayu (Landscape) - {{ $record->no_nota }}</title>

    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 10px;
            margin: 0;
            background: #e5e5e5;
            line-height: 1.05;
        }

        /* Screen Layout: App side-by-side */
        .app-layout {
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar Form panel */
        .sidebar-panel {
            position: sticky;
            top: 0;
            width: 320px;
            height: 100vh;
            background: #ffffff;
            border-right: 1px solid #ddd;
            box-sizing: border-box;
            box-shadow: 2px 0 10px rgba(0,0,0,0.05);
            z-index: 15;
            transition: width 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .sidebar-panel.collapsed {
            width: 32px;
        }

        .sidebar-content {
            width: 100%;
            height: 100%;
            padding: 20px;
            box-sizing: border-box;
            overflow-y: auto;
            overflow-x: hidden;
            display: flex;
            flex-direction: column;
            gap: 12px;
            transition: opacity 0.2s ease, visibility 0.2s ease;
            opacity: 1;
            visibility: visible;
        }

        .sidebar-panel.collapsed .sidebar-content {
            opacity: 0;
            visibility: hidden;
            pointer-events: none;
        }

        .sidebar-toggle-handle {
            position: absolute;
            top: 50%;
            right: -16px;
            transform: translateY(-50%);
            width: 16px;
            height: 60px;
            background: #2b2b2b;
            border: 1px solid #111;
            border-left: none;
            border-radius: 0 6px 6px 0;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 2px 0 5px rgba(0,0,0,0.1);
            z-index: 20;
            transition: background 0.2s ease;
        }

        .sidebar-toggle-handle:hover {
            background: #444;
        }

        .sidebar-toggle-handle span {
            color: #fff;
            font-size: 10px;
            font-weight: bold;
            transition: transform 0.3s ease;
        }

        .sidebar-panel.collapsed .sidebar-toggle-handle span {
            transform: rotate(180deg);
        }

        .sidebar-panel h2 {
            margin: 0 0 5px 0;
            font-size: 15px;
            font-weight: 800;
            color: #111;
            border-bottom: 2px solid #222;
            padding-bottom: 6px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .form-group label {
            font-size: 9px;
            font-weight: bold;
            color: #555;
            text-transform: uppercase;
        }

        .form-group input {
            padding: 8px 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 500;
            transition: all 0.2s ease;
        }

        .form-group input:focus {
            border-color: #222;
            outline: none;
            box-shadow: 0 0 4px rgba(0,0,0,0.1);
        }

        /* Preview Panel */
        .preview-panel {
            flex: 1;
            padding: 20px;
            overflow-y: auto;
            overflow-x: auto;
            width: 100%;
            box-sizing: border-box;
        }

        /* Responsive Layout for Tablets and Mobile Phones */
        @media (max-width: 1200px) {
            .app-layout {
                flex-direction: column;
            }

            .sidebar-panel {
                width: 100%;
                height: auto;
                position: relative;
                border-right: none;
                border-bottom: 1px solid #ddd;
                box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            }

            .sidebar-panel.collapsed {
                display: none !important;
            }

            .sidebar-toggle-handle {
                display: none !important;
            }

            .sidebar-content {
                width: 100%;
                height: auto;
                padding: 15px;
                overflow: visible;
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                gap: 12px;
                box-sizing: border-box;
            }

            .sidebar-content h2 {
                grid-column: 1 / -1;
                margin-bottom: 5px;
            }
            
            .preview-panel {
                padding: 10px;
            }
        }

        @media (max-width: 480px) {
            .sidebar-content {
                grid-template-columns: 1fr;
            }
        }

        .action-bar {
            text-align: center;
            margin-bottom: 15px;
        }

        .action-bar button {
            padding: 8px 16px;
            border: none;
            background: #222;
            color: white;
            border-radius: 4px;
            cursor: pointer;
            margin: 0 4px;
            font-size: 12px;
            font-weight: bold;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            transition: all 0.2s ease;
        }

        .action-bar button:hover {
            background: #444;
            transform: translateY(-1px);
        }

        /* A4 Landscape Page Container */
        .page-container {
            width: 297mm;
            height: 210mm;
            box-sizing: border-box;
            padding: 10mm;
            background: #fff;
            margin: 10px auto;
            box-shadow: 0 4px 15px rgba(0,0,0,0.15);
            position: relative;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        /* Prevent horizontal centering overflow cutoff scrollbug on viewports smaller than sheet + sidebar */
        @media (max-width: 1480px) {
            .page-container {
                margin: 10px 0;
            }
        }

        .page-header {
            margin-bottom: 8px;
            border-bottom: 1.5px solid #222;
            padding-bottom: 6px;
        }

        h3 {
            margin: 0 0 6px 0;
            font-size: 15px;
            text-align: center;
            text-transform: uppercase;
            letter-spacing: 1.2px;
            font-weight: bold;
            color: #111;
        }

        .header-table {
            width: 100%;
            border-collapse: collapse;
            background: #fdfdfd;
            border: 1px solid #333;
            border-radius: 4px;
            padding: 4px;
        }

        .header-table td {
            border: none;
            padding: 4px 15px;
            vertical-align: middle;
        }

        .header-table td:first-child {
            border-right: 1px solid #ddd;
        }

        .header-item {
            display: flex;
            align-items: center;
            font-size: 10px;
            line-height: 1.2;
        }

        .item-label {
            width: 45px;
            font-weight: bold;
            color: #555;
            text-transform: uppercase;
            font-size: 9px;
        }

        .item-separator {
            width: 15px;
            color: #666;
            font-weight: bold;
            text-align: center;
        }

        .item-value {
            font-weight: 700;
            color: #111;
            flex: 1;
        }

        .columns-container {
            column-count: 3;
            column-gap: 20px;
            column-fill: auto;
            height: 125mm;
            box-sizing: border-box;
            margin-bottom: 5px;
        }

        .group-wrapper {
            break-inside: avoid;
            page-break-inside: avoid;
            margin-bottom: 10px;
        }

        .group-title {
            background: #eaeaea;
            font-weight: bold;
            padding: 3px 5px;
            border: 1px solid #444;
            font-size: 9px;
            text-transform: uppercase;
            break-after: avoid;
        }

        table.data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 3px;
            break-inside: auto;
        }

        table.data-table th, table.data-table td {
            border: 1px solid #444;
            padding: 2px 4px;
            text-align: right;
            vertical-align: middle;
            font-size: 9px;
        }

        table.data-table th {
            background: #f7f7f7;
            text-align: center;
            font-weight: bold;
        }

        .tally-wrapper {
            display: flex;
            flex-wrap: wrap;
            gap: 1px;
            justify-content: flex-start;
            min-width: 60px;
        }

        .total-box {
            margin-top: 5px;
            margin-bottom: 5px;
        }

        .total-box table {
            width: 100%;
            border-collapse: collapse;
            border: 2px solid #000;
        }

        .total-box td {
            border: 1px solid #000;
            padding: 4px 8px;
            font-size: 11px;
        }

        .signature {
            width: 100%;
            border-collapse: collapse;
            margin-top: 5px;
        }

        .signature td {
            border: none;
            text-align: center;
            padding-top: 5px;
            font-size: 9px;
            width: 50%;
        }

        .footer-info {
            font-size: 8px;
            text-align: right;
            margin-top: 6px;
            color: #555;
            border-top: 1px dashed #ccc;
            padding-top: 3px;
        }

        .page-break {
            display: none;
        }

        @media print {
            @page {
                size: A4 landscape;
                margin: 0;
            }

            body {
                background: white;
            }

            .app-layout {
                display: block;
                min-height: auto;
            }

            .sidebar-panel {
                display: none !important;
            }

            .preview-panel {
                padding: 0;
                overflow: visible;
                display: block;
            }

            .page-container {
                margin: 0;
                box-shadow: none;
                width: 297mm;
                height: 210mm;
                page-break-after: always;
            }

            .action-bar {
                display: none;
            }

            .page-break {
                display: block;
                page-break-after: always;
            }
        }
    </style>
</head>

<body>

    <div class="app-layout">

        <!-- INTERACTIVE SIDEBAR FORM (Screen only) -->
        <div class="sidebar-panel">
            <div class="sidebar-content">
                <h2>Edit Data Cetak</h2>
                
                <div class="form-group">
                    <label>No. Nota</label>
                    <input type="text" id="input-no-nota" value="{{ $record->no_nota }}" />
                </div>
                
                <div class="form-group">
                    <label>Seri</label>
                    <input type="text" id="input-seri" value="{{ $record->kayuMasuk->seri }}" />
                </div>
                
                <div class="form-group">
                    <label>Nama Supplier</label>
                    <input type="text" id="input-nama" value="{{ $record->kayuMasuk->penggunaanSupplier->nama_supplier ?? '-' }}" />
                </div>
                
                <div class="form-group">
                    <label>Tanggal</label>
                    <input type="text" id="input-tanggal" value="{{ \Carbon\Carbon::parse($record->kayuMasuk->tgl_kayu_masuk)->format('d-m-Y') }}" />
                </div>
                
                <div class="form-group">
                    <label>No. Polisi (Nopol)</label>
                    <input type="text" id="input-nopol" value="{{ $record->kayuMasuk->penggunaanKendaraanSupplier->nopol_kendaraan ?? '-' }}" />
                </div>
                
                <div class="form-group">
                    <label>Dokumen Legal</label>
                    <input type="text" id="input-legal" value="{{ $record->kayuMasuk->penggunaanDokumenKayu->dokumen_legal ?? '-' }}" />
                </div>
                
                <div class="form-group" style="margin-top: 10px; border-top: 1px solid #eee; padding-top: 10px;">
                    <label>Penanggung Jawab</label>
                    <input type="text" id="input-penanggung-jawab" value="{{ $record->penanggung_jawab ?? 'via' }}" />
                </div>
                
                <div class="form-group">
                    <label>Grader Kayu</label>
                    <input type="text" id="input-grader" value="{{ $record->penerima ?? 'pak kadam' }}" />
                </div>
            </div>
            <!-- Floating vertical toggle handle -->
            <div class="sidebar-toggle-handle" onclick="toggleSidebar()">
                <span id="handle-arrow">&#10094;</span>
            </div>
        </div>

        <!-- PREVIEW PANEL -->
        <div class="preview-panel">

            <!-- ACTION BUTTONS -->
            <div class="action-bar">
                <button id="btn-toggle-sidebar" onclick="toggleSidebar()" style="background: #2b2b2b;">Sembunyikan Form</button>
                <button onclick="downloadPNG()">Export PNG</button>
                <button onclick="downloadJPG()">Export JPG</button>
                <button onclick="downloadPDF()">Export PDF</button>
                <button onclick="window.print()">Print</button>
            </div>

            <!-- AREA EXPORT -->
            <div id="nota-area-landscape">

                @foreach($pages as $pageIndex => $pageGroups)

                    <div class="page-container" id="page-{{ $pageIndex }}">

                        <!-- HEADER -->
                        <div class="page-header">
                            <h3>Nota Turus Kayu</h3>
                            
                            <table class="header-table">
                                <tr>
                                    <td width="50%">
                                        <div class="header-item">
                                            <span class="item-label">No</span>
                                            <span class="item-separator">:</span>
                                            <span class="item-value bind-no-nota">{{ $record->no_nota }}</span>
                                        </div>
                                    </td>
                                    <td width="50%">
                                        <div class="header-item">
                                            <span class="item-label">Seri</span>
                                            <span class="item-separator">:</span>
                                            <span class="item-value bind-seri">{{ $record->kayuMasuk->seri }}</span>
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        <div class="header-item">
                                            <span class="item-label">Nama</span>
                                            <span class="item-separator">:</span>
                                            <span class="item-value bind-nama" style="text-transform: capitalize;">{{ $record->kayuMasuk->penggunaanSupplier->nama_supplier ?? '-' }}</span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="header-item">
                                            <span class="item-label">Tgl</span>
                                            <span class="item-separator">:</span>
                                            <span class="item-value bind-tanggal">{{ \Carbon\Carbon::parse($record->kayuMasuk->tgl_kayu_masuk)->format('d-m-Y') }}</span>
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        <div class="header-item">
                                            <span class="item-label">Nopol</span>
                                            <span class="item-separator">:</span>
                                            <span class="item-value bind-nopol">{{ $record->kayuMasuk->penggunaanKendaraanSupplier->nopol_kendaraan ?? '-' }}</span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="header-item">
                                            <span class="item-label">Legal</span>
                                            <span class="item-separator">:</span>
                                            <span class="item-value bind-legal">{{ $record->kayuMasuk->penggunaanDokumenKayu->dokumen_legal ?? '-' }}</span>
                                        </div>
                                    </td>
                                </tr>
                            </table>
                        </div>

                        <!-- COLUMNS CONTENT -->
                        <div class="columns-container">

                            @foreach($pageGroups as $group)

                                <div class="group-wrapper">
                                    
                                    <div class="group-title">
                                        {{ $group['kodeLahan'] }}
                                        -
                                        {{ $group['panjang'] }} cm
                                        {{ $group['jenis'] }}
                                        ({{ $group['grade'] == 1 ? 'A' : 'B' }})
                                        @if($group['is_continued']) <span style="font-size: 8px; color: #555;">(Sbg)</span> @endif
                                    </div>

                                    <table class="data-table">
                                        <thead>
                                            <tr>
                                                <th style="width: 15%">D</th>
                                                <th style="width: 65%; text-align: left;">Turus</th>
                                                <th style="width: 20%">Btg</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse($group['rows'] as $row)
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
                                                                $tallyText = str_repeat('||||/ ', $groups) . str_repeat('|', $rem);
                                                            @endphp
                                                            <span style="position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0, 0, 0, 0); border: 0;">{{ $tallyText }}</span>

                                                            @for($i = 0; $i < $groups; $i++)
                                                                <svg
                                                                    width="16"
                                                                    height="16"
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
                                                                    width="16"
                                                                    height="16"
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
                                                    <td colspan="3" style="text-align: center">Data Kosong</td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                        
                                        @if(!isset($group['show_subtotal']) || $group['show_subtotal'])
                                            <tfoot>
                                                <tr style="background: #f7f7f7; font-weight: bold;">
                                                    <td colspan="2" style="text-align: center">Subtotal</td>
                                                    <td style="text-align: center">
                                                        {{ number_format($group['subBatang'], 0, ',', '.') }}
                                                    </td>
                                                </tr>
                                            </tfoot>
                                        @endif
                                    </table>

                                </div>

                            @endforeach

                        </div>

                        <!-- FOOTER -->
                        <div class="page-footer">
                            
                            @if($loop->last)
                                <!-- TOTAL KESELURUHANN (Hanya halaman terakhir) -->
                                <div class="total-box">
                                    <table>
                                        <tr>
                                            <td style="font-weight: bold; width: 50%;">
                                                Total Batang Keseluruhan
                                            </td>
                                            <td style="width: 50%; text-align: center; font-size: 13px; font-weight: bold; background: #fafafa;">
                                                {{ number_format($totalBatangGlobal) }} Btg
                                            </td>
                                        </tr>
                                    </table>
                                </div>
                            @else
                                <!-- Spacer untuk halaman non-terakhir agar signature terdorong rapi -->
                                <div style="height: 25px;"></div>
                            @endif

                            <!-- SIGNATURE BLOCK -->
                            <table class="signature">
                                <tr>
                                    <td>Penanggung Jawab</td>
                                    <td>Grader Kayu</td>
                                </tr>
                                <tr>
                                    <td style="height: 32px"></td>
                                    <td></td>
                                </tr>
                                <tr>
                                    <td>
                                        ( <span class="bind-penanggung-jawab">{{ $record->penanggung_jawab ?? 'via' }}</span> )
                                    </td>
                                    <td>
                                        ( <span class="bind-grader">{{ $record->penerima ?? 'pak kadam' }}</span> )
                                    </td>
                                </tr>
                            </table>

                            <!-- FOOTER DETAILS -->
                            <div class="footer-info">
                                Dicetak: {{ now()->format('d/m/Y H:i') }}
                            </div>
                        </div>

                    </div>

                    @if(!$loop->last)
                        <div class="page-break"></div>
                    @endif

                @endforeach

            </div>

        </div>

    </div>

    <!-- LIBRARY -->
    <script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/jspdf@2.5.1/dist/jspdf.umd.min.js"></script>

    <script>
        // Toggle Sidebar visibility
        function toggleSidebar() {
            const sidebar = document.querySelector('.sidebar-panel');
            const btn = document.getElementById('btn-toggle-sidebar');
            const isCollapsed = sidebar.classList.toggle('collapsed');
            
            if (isCollapsed) {
                btn.textContent = 'Tampilkan Form';
                btn.style.background = '#0284c7';
            } else {
                btn.textContent = 'Sembunyikan Form';
                btn.style.background = '#2b2b2b';
            }
        }

        // Two-way binding for live preview
        document.getElementById('input-no-nota').addEventListener('input', function(e) {
            document.querySelectorAll('.bind-no-nota').forEach(el => el.textContent = e.target.value);
        });
        document.getElementById('input-seri').addEventListener('input', function(e) {
            document.querySelectorAll('.bind-seri').forEach(el => el.textContent = e.target.value);
        });
        document.getElementById('input-nama').addEventListener('input', function(e) {
            document.querySelectorAll('.bind-nama').forEach(el => {
                el.textContent = e.target.value;
                el.style.textTransform = 'capitalize';
            });
        });
        document.getElementById('input-tanggal').addEventListener('input', function(e) {
            document.querySelectorAll('.bind-tanggal').forEach(el => el.textContent = e.target.value);
        });
        document.getElementById('input-nopol').addEventListener('input', function(e) {
            document.querySelectorAll('.bind-nopol').forEach(el => el.textContent = e.target.value);
        });
        document.getElementById('input-legal').addEventListener('input', function(e) {
            document.querySelectorAll('.bind-legal').forEach(el => el.textContent = e.target.value);
        });
        document.getElementById('input-penanggung-jawab').addEventListener('input', function(e) {
            document.querySelectorAll('.bind-penanggung-jawab').forEach(el => el.textContent = e.target.value);
        });
        document.getElementById('input-grader').addEventListener('input', function(e) {
            document.querySelectorAll('.bind-grader').forEach(el => el.textContent = e.target.value);
        });

        // Set of utility functions to export the content
        async function downloadPNG() {
            try {
                const pages = document.querySelectorAll('.page-container');
                for (let i = 0; i < pages.length; i++) {
                    const pageEl = pages[i];
                    const canvas = await html2canvas(pageEl, {
                        scale: 2, // Balanced crispness and small file size
                        useCORS: true,
                        allowTaint: true,
                        backgroundColor: '#ffffff',
                        logging: false
                    });
                    const link = document.createElement('a');
                    link.download = `nota-turus2-page${i+1}-{{ $record->no_nota }}.png`;
                    link.href = canvas.toDataURL('image/png');
                    link.click();
                }
            } catch (e) {
                alert('Gagal export PNG');
                console.error(e);
            }
        }

        async function downloadJPG() {
            try {
                const pages = document.querySelectorAll('.page-container');
                for (let i = 0; i < pages.length; i++) {
                    const pageEl = pages[i];
                    const canvas = await html2canvas(pageEl, {
                        scale: 2, // Optimized scale
                        useCORS: true,
                        allowTaint: true,
                        backgroundColor: '#ffffff',
                        logging: false
                    });
                    const link = document.createElement('a');
                    link.download = `nota-turus2-page${i+1}-{{ $record->no_nota }}.jpg`;
                    link.href = canvas.toDataURL('image/jpeg', 0.85); // Compress to 85% JPEG
                    link.click();
                }
            } catch (e) {
                alert('Gagal export JPG');
                console.error(e);
            }
        }

        async function downloadPDF() {
            try {
                const { jsPDF } = window.jspdf;
                const pdf = new jsPDF({
                    orientation: 'landscape',
                    unit: 'mm',
                    format: 'a4'
                });

                const pages = document.querySelectorAll('.page-container');
                for (let i = 0; i < pages.length; i++) {
                    const pageEl = pages[i];
                    if (i > 0) {
                        pdf.addPage();
                    }

                    const canvas = await html2canvas(pageEl, {
                        scale: 1.6, // Ultra-lightweight resolution (scale 1.6x) for sizes under 150KB
                        useCORS: true,
                        allowTaint: true,
                        backgroundColor: '#ffffff',
                        logging: false,
                        width: pageEl.offsetWidth,
                        height: pageEl.offsetHeight
                    });

                    // Super-efficient JPEG compression at 65% quality for fast sharing under 150KB!
                    const imgData = canvas.toDataURL('image/jpeg', 0.65);
                    pdf.addImage(imgData, 'JPEG', 0, 0, 297, 210);
                }

                pdf.save('nota-turus-2-{{ $record->no_nota }}.pdf');
            } catch (e) {
                alert('Gagal export PDF');
                console.error(e);
            }
        }
    </script>
</body>
</html>
