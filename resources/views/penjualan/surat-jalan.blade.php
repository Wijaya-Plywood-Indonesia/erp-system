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
            background-color: #fff;
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

        /* Khusus Surat Jalan PPN */
        .sj-ppn-header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 25px;
        }

        .sj-ppn-header-table td {
            padding: 4px 0;
            vertical-align: top;
            font-size: 14.5px;
        }

        .sj-ppn-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 50px;
        }

        .sj-ppn-table th, .sj-ppn-table td {
            border: 1px solid #000;
            padding: 8px 10px;
            font-size: 14px;
        }

        .sj-ppn-table th {
            font-weight: normal;
            background-color: #fff;
            text-align: center;
        }

        .sj-ppn-table td {
            text-align: center;
        }

        .sj-ppn-table td.text-left {
            text-align: left;
        }

        .sj-ppn-signatures {
            width: 100%;
            margin-top: 30px;
        }

        .sj-ppn-signatures td {
            width: 50%;
            vertical-align: top;
            font-size: 15px;
        }
    </style>
</head>
<body onload="window.print()">
    @php
        $isLong = $details->count() > 15;
        $isPpn = (bool) ($nota->pakai_ppn ?? false);
    @endphp

    <div>
        <div class="page">
            @if ($isPpn)
                {{-- ================= TAMPILAN SURAT JALAN PPN ================= --}}
                <div class="title" style="text-transform: uppercase; font-size: 20px; font-weight: bold; margin-bottom: 35px; letter-spacing: 0.5px;">SURAT JALAN</div>

                <table class="sj-ppn-header-table">
                    <tr>
                        <td style="width: 12%;">No</td>
                        <td style="width: 3%;">:</td>
                        <td style="width: 45%;">{{ $nota->no_nota }}</td>
                        <td style="width: 15%;"></td>
                        <td style="width: 3%;"></td>
                        <td style="width: 22%;"></td>
                    </tr>
                    <tr>
                        <td style="border-bottom: 2px solid #000; padding-bottom: 8px;">Tanggal</td>
                        <td style="border-bottom: 2px solid #000; padding-bottom: 8px;">:</td>
                        <td style="border-bottom: 2px solid #000; padding-bottom: 8px;">
                            {{ $nota->tanggal ? $nota->tanggal->translatedFormat('d F Y') : date('d F Y') }}
                        </td>
                        <td colspan="3" style="border-bottom: 2px solid #000; padding-bottom: 8px;"></td>
                    </tr>
                    <tr>
                        <td colspan="6" style="height: 15px;"></td>
                    </tr>
                    <tr>
                        <td colspan="3" style="vertical-align: top;">
                            <table style="width: 100%; border-collapse: collapse;">
                                <tr>
                                    <td style="width: 70px; font-weight: bold; vertical-align: top; padding: 0;">Kepada :</td>
                                    <td style="font-weight: bold; vertical-align: top; padding: 0;">
                                        {{ $nota->pembeli_bkp_nama ?: $nota->tujuan_nota }}
                                        @if($nota->pembeli_bkp_alamat ?: $nota->alamat)
                                            <div style="font-weight: bold; margin-top: 2px;">
                                                {!! nl2br(e($nota->pembeli_bkp_alamat ?: $nota->alamat)) !!}
                                            </div>
                                        @endif
                                    </td>
                                </tr>
                            </table>
                        </td>
                        <td colspan="3" style="vertical-align: top; padding-left: 20px;">
                            <table style="width: 100%; border-collapse: collapse;">
                                <tr>
                                    <td style="width: 45%; font-weight: bold;">Mobil</td>
                                    <td style="width: 5%; font-weight: bold;">:</td>
                                    <td style="width: 50%;">{{ $nota->kendaraan ?? '' }}</td>
                                </tr>
                                <tr>
                                    <td style="font-weight: bold;">No Plat</td>
                                    <td style="font-weight: bold;">:</td>
                                    <td>{{ $nota->plat_kendaraan ?? '' }}</td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>

                <table class="sj-ppn-table">
                    <thead>
                        <tr>
                            <th style="width: 8%;">No</th>
                            <th style="width: 56%;">Nama Barang</th>
                            <th style="width: 18%;">Satuan</th>
                            <th style="width: 18%;">QTY</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($items as $i => $item)
                            <tr>
                                <td>{{ $i + 1 }}</td>
                                <td class="text-left">{{ $item->nama_barang }}</td>
                                <td style="text-transform: capitalize;">{{ $item->satuan }}</td>
                                <td>{{ number_format($item->qty) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                <table class="sj-ppn-signatures">
                    <tr>
                        <td style="text-align: center; vertical-align: top;">
                            <div style="font-weight: bold; text-decoration: underline; margin-bottom: 5px;">Penerima</div>
                            <div style="height: 85px;"></div>
                            <div style="font-weight: normal;">
                                (&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;)
                            </div>
                        </td>
                        <td style="text-align: center; vertical-align: top;">
                            <div style="font-weight: bold; margin-bottom: 5px;">Hormat Kami</div>
                            <div style="position: relative; height: 85px; display: flex; align-items: center; justify-content: center;">
                                @php
                                    $ttdPath = public_path('images/ttd_safira.png');
                                    $ttdBase64 = file_exists($ttdPath) ? 'data:image/png;base64,' . base64_encode(file_get_contents($ttdPath)) : asset('images/ttd_safira.png');
                                @endphp
                                <img src="{{ $ttdBase64 }}" alt="TTD Safira" style="max-height: 85px; max-width: 150px; object-fit: contain;">
                            </div>
                            <div style="margin-top: 5px; font-weight: normal;">
                                (&nbsp;&nbsp;&nbsp;&nbsp; Safira &nbsp;&nbsp;&nbsp;&nbsp;)
                            </div>
                        </td>
                    </tr>
                </table>
            @else
                {{-- ================= TAMPILAN SURAT JALAN BIASA (NON-PPN) ================= --}}
                <div class="title">Surat Jalan</div>

                <table class="header-table">
                    <tr>
                        <td style="width: 12%; font-weight: bold;">No.</td>
                        <td style="width: 48%; font-weight: bold;">{{ $nota->no_nota }}</td>
                        <td style="width: 18%; font-weight: bold;">Pengiriman</td>
                        <td style="width: 2%; font-weight: bold;">:</td>
                        <td style="width: 20%;"></td>
                    </tr>
                    <tr>
                        <td class="border-bottom" style="font-weight: bold;">Tanggal</td>
                        <td class="border-bottom" style="font-weight: bold;">{{ $nota->tanggal?->format('d-M-y') }}</td>
                        <td class="border-bottom" style="font-weight: bold;">Sopir</td>
                        <td class="border-bottom" style="font-weight: bold;">:</td>
                        <td class="border-bottom">{{ $nota->nama_sopir ?? '-' }}</td>
                    </tr>
                    <tr>
                        <td rowspan="2" class="border-bottom" style="font-weight: bold; vertical-align: top; padding-top: 8px;">Kepada</td>
                        <td rowspan="2" class="border-bottom" style="vertical-align: top; padding-top: 8px;">
                            <strong>{{ $nota->tujuan_nota }}</strong>
                            @if($nota->alamat)
                                <br>
                                {!! nl2br(e($nota->alamat)) !!}
                            @endif
                        </td>
                        <td style="font-weight: bold;">Mobil</td>
                        <td style="font-weight: bold;">:</td>
                        <td>{{ $nota->kendaraan ?? '-' }}</td>
                    </tr>
                    <tr>
                        <td class="border-bottom" style="font-weight: bold;">No Plat</td>
                        <td class="border-bottom" style="font-weight: bold;">:</td>
                        <td class="border-bottom">{{ $nota->plat_kendaraan ?? '-' }}</td>
                    </tr>
                </table>

            @php
                // m3 hanya tampil jika perhitungan memakai kubikasi (hit_kbk = 'm'); jika pakai qty, m3 disembunyikan.
                $hasM3 = collect($items)->contains(fn($i) => ($i->hit_kbk ?? 'b') === 'm' && $i->m3 !== null);
            @endphp
            <table class="items-table">
                <thead>
                    <tr>
                        <th width="8%">No.</th>
                        <th>Nama Barang</th>
                        <th width="12%">Satuan</th>
                        <th width="12%">Qty</th>
                        @if($hasM3)
                        <th width="16%" style="white-space: nowrap;">m3</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @foreach ($items as $i => $item)
                        <tr>
                            <td>{{ $i + 1 }}</td>
                            <td class="text-left">{{ $item->nama_barang }}</td>
                            <td>{{ $item->satuan }}</td>
                            <td>{{ number_format($item->qty) }}</td>
                            @if($hasM3)
                            <td style="white-space: nowrap;">{{ ($item->hit_kbk ?? 'b') === 'm' && $item->m3 !== null ? number_format($item->m3, 4, ',', '.') : '-' }}</td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="3" style="text-align: right; font-weight: bold; padding-right: 15px;">Total</td>
                            <td style="font-weight: bold;">{{ number_format(collect($items)->sum('qty')) }}</td>
                            @if($hasM3)
                            <td style="font-weight: bold;">{{ number_format(collect($items)->filter(fn($i) => ($i->hit_kbk ?? 'b') === 'm')->sum('m3'), 4, ',', '.') }}</td>
                            @endif
                        </tr>
                    </tfoot>
                </table>

                <table class="signatures">
                    <tr>
                        <td>Penerima</td>
                        <td>Sopir</td>
                        <td>Cek</td>
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
            @endif
        </div>
    </div>
</body>
</html>