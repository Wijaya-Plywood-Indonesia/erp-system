<?php

namespace App\Exports;

use App\Models\ProduksiRotary;
use App\Models\DetailHasilPaletRotary;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use App\Models\DetailTurusanKayu;

class LaporanProduksiExport implements WithMultipleSheets
{
    protected $dataProduksi;
    protected $tanggal;

    public function __construct($dataProduksi, $tanggal = null)
    {
        $this->dataProduksi = $dataProduksi;
        $this->tanggal = $tanggal;
    }

    public function sheets(): array
    {
        return [
            new LaporanProduksiDetailSheet($this->dataProduksi),
            new LaporanProduksiRekapSheet($this->tanggal),
            new LaporanProduksiJurnalSheet($this->tanggal),
        ];
    }
}

class LaporanProduksiDetailSheet implements FromCollection, WithHeadings, WithTitle
{
    protected $dataProduksi;

    public function __construct($dataProduksi)
    {
        $this->dataProduksi = collect($dataProduksi)->groupBy('mesin');
    }

    public function collection()
    {
        $rows = collect();
        foreach ($this->dataProduksi as $mesinNama => $produksiList) {
            $first = $produksiList->first();
            $pekerja = $first['pekerja'] ?? [];
            $kendala = $first['kendala'] ?? 'Tidak ada kendala.';
            $tanggal = $first['tanggal'] ?? '';
            $target = $first['target'] ?? 0;
            $jamKerja = $first['jam_kerja'] ?? 0;
            $targetPerJam = $first['target_per_jam'] ?? 0;
            $hasil = $first['total_target_harian'] ?? 0;
            $selisih = $first['selisih'] ?? 0;

            $rows->push(['MESIN: ' . strtoupper($mesinNama)]);
            $rows->push(['TANGGAL: ' . $tanggal]);
            $rows->push([]);
            $rows->push(['ID', 'Nama', 'Masuk', 'Pulang', 'Ijin', 'Potongan Target', 'Keterangan', '', 'Target Harian', 'Jam Kerja', 'Target/Jam', 'Hasil', 'Selisih', 'Kendala']);
            foreach ($pekerja as $p) {
                $potTargetRaw = (float) str_replace('.', '', $p['pot_target'] ?? '0');
                $rows->push([
                    $p['id'] ?? '-', $p['nama'] ?? '-', $p['jam_masuk'] ?? '-', $p['jam_pulang'] ?? '-', $p['ijin'] ?? '-',
                    $potTargetRaw > 0 ? (int) $potTargetRaw : '-', $p['keterangan'] ?? '-', '', (int) $target, (int) $jamKerja, round((float) $targetPerJam, 2), (int) $hasil, $selisih >= 0 ? '+' . (int) abs($selisih) : (int) $selisih, $kendala
                ]);
            }
            $totalPotongan = collect($pekerja)->sum(fn($p) => (float) str_replace('.', '', $p['pot_target'] ?? '0'));
            $rows->push(['TOTAL', '', '', '', '', $totalPotongan > 0 ? (int) $totalPotongan : '', '', '', (int) $target, (int) $jamKerja, round((float) $targetPerJam, 2), (int) $hasil, $selisih >= 0 ? '+' . (int) abs($selisih) : (int) $selisih, '', count($pekerja) . ' pekerja']);
            $rows->push([]); $rows->push([]);
        }
        return $rows;
    }

    public function headings(): array { return []; }
    public function title(): string { return 'Detail Per Mesin'; }
}

class LaporanProduksiRekapSheet implements FromCollection, WithHeadings, WithStyles, WithEvents, WithTitle
{
    protected $tanggal;
    protected $mergeData = [];
    protected $uniqueKws = ['1', '2', '3', '4'];

    public function __construct($tanggal)
    {
        $this->tanggal = $tanggal;
        $this->determineKws();
    }

    private function determineKws()
    {
        $kws = DetailHasilPaletRotary::whereHas('produksi', function($q) { $q->whereDate('tgl_produksi', $this->tanggal); })->distinct()->pluck('kw')->toArray();
        foreach ($kws as $kw) { $kwStr = (string)$kw; if ($kwStr !== '' && !in_array($kwStr, $this->uniqueKws)) $this->uniqueKws[] = $kwStr; }
        sort($this->uniqueKws);
    }

    public function collection()
    {
        $produksis = ProduksiRotary::with(['mesin', 'detailPaletRotary.ukuran', 'detailPaletRotary.penggunaanLahan.jenisKayu', 'detailPegawaiRotary'])->whereDate('tgl_produksi', $this->tanggal)->get();
        $rows = collect(); $currentRow = 2; $this->mergeData = [];
        foreach ($produksis as $index => $produksi) {
            $namaMesin = strtoupper($produksi->mesin->nama_mesin ?? '-');
            $tgl = Carbon::parse($produksi->tgl_produksi)->format('d/m/Y');
            $details = $produksi->detailPaletRotary->groupBy(function($item) {
                $u = $item->ukuran; $j = $item->penggunaanLahan->jenisKayu->kode_kayu ?? '-';
                return ($u->panjang ?? 0) . '|' . ($u->lebar ?? 0) . '|' . ($u->tebal ?? 0) . '|' . $j;
            });
            if ($details->count() > 0) {
                $this->mergeData[] = ['start' => $currentRow, 'end' => $currentRow + $details->count() - 1];
                foreach ($details as $key => $items) {
                    [$p, $l, $t, $jenis] = explode('|', $key);
                    $rowData = ['mesin' => $namaMesin, 'tanggal' => $tgl, 'p' => str_replace('.', ',', (string)(float)$p), 'l' => str_replace('.', ',', (string)(float)$l), 't' => str_replace('.', ',', (string)(float)$t), 'jenis' => $jenis];
                    foreach ($this->uniqueKws as $kwLabel) { $rowData['kw_' . $kwLabel] = $items->filter(fn($i) => (string)$i->kw === (string)$kwLabel)->sum('total_lembar') ?: ''; }
                    $rowData['ttl_pkj'] = $produksi->detailPegawaiRotary->count();
                    $rows->push($rowData); $currentRow++;
                }
                if ($index < $produksis->count() - 1) { $rows->push(array_fill(0, count($this->headings()), '')); $currentRow++; }
            }
        }
        return $rows;
    }

    public function headings(): array
    {
        $headers = ['Mesin', 'Tanggal', 'p', 'l', 't', 'jenis'];
        foreach ($this->uniqueKws as $kwLabel) $headers[] = 'kw' . $kwLabel;
        $headers[] = 'TTL PKJ';
        return $headers;
    }

    public function styles(Worksheet $sheet) { return [1 => ['font' => ['bold' => true], 'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]]]; }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate(); $peachColor = 'F9CB9C';
                $lastCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($this->headings()));
                $sheet->getStyle("A1:{$lastCol}1")->applyFromArray(['borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]], 'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER], 'font' => ['bold' => true]]);
                foreach ($this->mergeData as $range) {
                    $start = $range['start']; $end = $range['end'];
                    if ($start < $end) { $sheet->mergeCells("A{$start}:A{$end}"); $sheet->mergeCells("{$lastCol}{$start}:{$lastCol}{$end}"); }
                    $sheet->getStyle("A{$start}:{$lastCol}{$end}")->applyFromArray(['borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]], 'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER]]);
                    $sheet->getStyle("A{$start}:B{$end}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($peachColor);
                    $sheet->getStyle("F{$start}:{$lastCol}{$end}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($peachColor);
                    $sheet->getStyle("A{$start}:B{$end}")->getFont()->setBold(true);
                    $sheet->getStyle("{$lastCol}{$start}:{$lastCol}{$end}")->getFont()->setBold(true);
                }
                $sheet->getRowDimension(1)->setRowHeight(30);
                $sheet->getColumnDimension('A')->setWidth(18); $sheet->getColumnDimension('B')->setWidth(15); $sheet->getColumnDimension('C')->setWidth(8); $sheet->getColumnDimension('D')->setWidth(8); $sheet->getColumnDimension('E')->setWidth(8);
                for ($i = 6; $i < count($this->headings()); $i++) { $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i); $sheet->getColumnDimension($col)->setWidth(10); }
                $sheet->getColumnDimension($lastCol)->setWidth(12);
            },
        ];
    }
    public function title(): string { return 'Rekap Produksi Custom'; }
}

class LaporanProduksiJurnalSheet extends DefaultValueBinder implements FromCollection, WithTitle, WithStyles, WithEvents, WithCustomValueBinder
{
    protected $tanggal;
    protected $titleRows = [];
    protected $headerRows = [];
    protected $dataRanges = [];

    public function bindValue(Cell $cell, $value)
    {
        if ($cell->getColumn() === 'D') {
            $cell->setValueExplicit($value, DataType::TYPE_STRING);
            return true;
        }
        return parent::bindValue($cell, $value);
    }

    public function __construct($tanggal)
    {
        $this->tanggal = $tanggal;
    }

    public function collection()
    {
        $rows = collect();
        $service = new \App\Services\Akuntansi\RotaryJurnalService();
        $payload = $service->buildJurnalPayloadPreview($this->tanggal);

        if (!$payload || empty($payload['jurnal_items'])) {
            $rows->push(['Tidak ada data jurnal produksi untuk tanggal ini.']);
            return $rows;
        }

        $rawRows = [];

        // Preload ongkos_mesin dari tabel mesins (keyed by nama_mesin)
        $mesinOngkos = \App\Models\Mesin::all()
            ->keyBy(fn($m) => strtoupper(trim($m->nama_mesin)))
            ->map(fn($m) => (float)($m->ongkos_mesin ?? 0));

        foreach ($payload['jurnal_items'] as $item) {
            $namaAkun = $item['nama_akun'];
            $noAkun   = $item['no_akun'];
            $mapDK    = $item['map'];

            // Skip Upah Tenaga Kerja (510-01) — struktur baru tidak memunculkan sisi debit upah
            if ($noAkun === '510-01') {
                continue;
            }

            foreach ($item['items'] as $subItem) {
                $bagian = '-';
                $keteranganSpesifikasi = $subItem['keterangan'] ?? '-';

                if (($subItem['jenis_pihak'] ?? '') === 'produksi') {
                    $bagian = $subItem['nama_pihak'] ?? '-';
                    if (($subItem['nama_barang'] ?? '') !== 'Mesin' && ($subItem['nama_barang'] ?? '') !== '-') {
                        $keteranganSpesifikasi = $subItem['nama_barang'] ?? '-';
                    } else {
                        $keteranganSpesifikasi = ($subItem['keterangan'] ?? '') . ' (' . ($subItem['ukuran'] ?? '') . ')';
                    }
                } elseif (($subItem['jenis_pihak'] ?? '') === 'karyawan') {
                    $parts = explode(' - ', $subItem['keterangan'] ?? '');
                    $bagian = count($parts) > 1 ? trim($parts[1]) : '-';
                    $keteranganSpesifikasi = ''; // Blank as requested to group workers under the same machine
                } elseif (($subItem['jenis_pihak'] ?? '') === 'pemasok') {
                    // Skip Persediaan Kayu — fokus hanya data produksi
                    continue;
                } else {
                    $bagian = '-';
                    $keteranganSpesifikasi = $subItem['keterangan'] ?? '-';
                }

                $tipe = 'b';
                if (($subItem['jenis_pihak'] ?? '') === 'produksi') {
                    $tipe = 'm';
                }

                $banyak = $subItem['banyak'];
                if (($subItem['jenis_pihak'] ?? '') === 'karyawan') {
                    $banyak = 1; // set to 1 so we can sum the total workers
                }

                $volume = $subItem['m3'];
                $harga  = $subItem['harga'];
                $jumlah = $subItem['jumlah'];

                // Khusus export Excel: harga veneer ambil dari ongkos_mesin di tabel mesins
                if (($subItem['jenis_pihak'] ?? '') === 'produksi') {
                    $namaM   = strtoupper(trim($bagian));
                    $ongkos  = $mesinOngkos[$namaM] ?? 0;
                    $harga   = $ongkos;
                    $jumlah  = $volume !== null ? round((float)$volume * $ongkos, 4) : null;
                }

                // Khusus export Excel: harga pekerja di-hardcode 150.000
                // (tidak ambil dari database HargaPegawai)
                if (($subItem['jenis_pihak'] ?? '') === 'karyawan') {
                    $harga  = 150_000;
                    $jumlah = 150_000; // 1 orang × 150.000
                }

                // Map original accounts (115-07, 115-08, 210-02) based on wood type (Sengon vs Meranti)
                $mappedNoAkun = $noAkun;
                $mappedNamaAkun = $namaAkun;

                if ($noAkun === '115-07') {
                    // Veneer Basah F/B
                    if (stripos($subItem['keterangan'] ?? '', 'sengon') !== false) {
                        $mappedNoAkun = '1421.00';
                        $mappedNamaAkun = 'Veneer Basah 260 face/back sengon WJY';
                    } else {
                        $mappedNoAkun = '1422.00';
                        $mappedNamaAkun = 'Veneer Basah 260 face/back meranti WJY';
                    }
                } elseif ($noAkun === '115-08') {
                    // Veneer Basah CORE
                    if (stripos($subItem['keterangan'] ?? '', 'sengon') !== false) {
                        $mappedNoAkun = '1426.00';
                        $mappedNamaAkun = 'Veneer Basah 130 core sengon WJY';
                    } else {
                        $mappedNoAkun = '1427.00';
                        $mappedNamaAkun = 'Veneer Basah 130 core meranti WJY';
                    }
                } elseif ($noAkun === '210-02') {
                    // Hutang Gaji
                    $mappedNoAkun = '2231.00';
                    $mappedNamaAkun = 'Hutang Gaji';
                }

                $rawRows[] = [
                    'nama_akun'  => $mappedNamaAkun,
                    'no_akun'    => $mappedNoAkun,
                    'bagian'     => $bagian,
                    'keterangan' => $keteranganSpesifikasi,
                    'dk'         => $mapDK,
                    'tipe'       => $tipe,
                    'banyak'     => $banyak !== null ? (float)$banyak : null,
                    'volume'     => $volume !== null ? (float)$volume : null,
                    'harga'      => $harga !== null ? (float)$harga : null,
                    'jumlah'     => $jumlah !== null ? (float)$jumlah : null,
                ];
            }
        }

        // Group raw rows by machine (bagian)
        $rawRowsByMachine = [];
        foreach ($rawRows as $row) {
            $machine = $row['bagian'];
            if ($machine === '-') {
                continue;
            }
            $rawRowsByMachine[$machine][] = $row;
        }

        $machineTables = [];
        foreach ($rawRowsByMachine as $machine => $rowsOfMachine) {
            $grouped = [];
            $totalDebit = 0.0;
            $totalKredit = 0.0;

            foreach ($rowsOfMachine as $row) {
                $key = implode('|', [
                    $row['no_akun'],
                    $row['keterangan'],
                    $row['dk'],
                    $row['tipe'],
                    $row['nama_akun']
                ]);

                if (!isset($grouped[$key])) {
                    $grouped[$key] = [
                        'nama_akun'  => $row['nama_akun'],
                        'no_akun'    => $row['no_akun'],
                        'bagian'     => $machine,
                        'keterangan' => $row['keterangan'],
                        'dk'         => $row['dk'],
                        'tipe'       => $row['tipe'],
                        'banyak'     => 0.0,
                        'volume'     => 0.0,
                        'harga'      => $row['harga'],
                        'jumlah'     => 0.0,
                        'has_qty'    => $row['banyak'] !== null,
                        'has_vol'    => $row['volume'] !== null,
                    ];
                }

                if ($row['banyak'] !== null) {
                    $grouped[$key]['banyak'] += $row['banyak'];
                    $grouped[$key]['has_qty'] = true;
                }
                if ($row['volume'] !== null) {
                    $grouped[$key]['volume'] += $row['volume'];
                    $grouped[$key]['has_vol'] = true;
                }
                if ($row['jumlah'] !== null) {
                    $grouped[$key]['jumlah'] += $row['jumlah'];
                }
            }

            foreach ($grouped as $g) {
                $isVeneer = in_array($g['no_akun'], ['115-07', '115-08', '1421.00', '1422.00', '1426.00', '1427.00']);
                $isHutangGaji = in_array($g['no_akun'], ['210-02', '2231.00']);
                $isWood = in_array($g['no_akun'], ['115-01', '115-02', '1411.01', '1411.02', '1411.03', '1411.04']);

                $rowHarga = 0.0;
                if ($isVeneer) {
                    $noAkunVal = $g['no_akun'] ?? '';
                    $namaAkunVal = strtolower($g['nama_akun'] ?? '');
                    if ($noAkunVal === '1421.00' || ($noAkunVal === '115-07' && str_contains($namaAkunVal, 'sengon'))) {
                        $rowHarga = 2700000.0;
                    } elseif ($noAkunVal === '1422.00' || ($noAkunVal === '115-07' && str_contains($namaAkunVal, 'meranti'))) {
                        $rowHarga = 8000000.0;
                    } elseif ($noAkunVal === '1426.00' || ($noAkunVal === '115-08' && str_contains($namaAkunVal, 'sengon'))) {
                        $rowHarga = 1700000.0;
                    } elseif ($noAkunVal === '1427.00' || ($noAkunVal === '115-08' && str_contains($namaAkunVal, 'meranti'))) {
                        $rowHarga = 2100000.0;
                    } else {
                        if (str_contains($namaAkunVal, 'core')) {
                            $rowHarga = str_contains($namaAkunVal, 'sengon') ? 1700000.0 : 2100000.0;
                        } else {
                            $rowHarga = str_contains($namaAkunVal, 'sengon') ? 2700000.0 : 8000000.0;
                        }
                    }
                } elseif ($isHutangGaji) {
                    $rowHarga = 150000.0;
                } elseif ($isWood) {
                    $rowHarga = (float)($g['harga'] ?? 0.0);
                } else {
                    $rowHarga = (float)($g['jumlah'] ?? 0.0);
                }

                $rowTotal = 0.0;
                if ($g['has_vol'] && $g['volume'] !== null && $g['volume'] > 0) {
                    $rowTotal = (float)$g['volume'] * $rowHarga;
                } elseif ($g['has_qty'] && $g['banyak'] !== null && $g['banyak'] > 0) {
                    $rowTotal = (float)$g['banyak'] * $rowHarga;
                } else {
                    $rowTotal = $rowHarga;
                }

                if ($g['dk'] === 'd') {
                    $totalDebit += $rowTotal;
                } else {
                    $totalKredit += $rowTotal;
                }
            }

            // Selisih → selalu masuk ke 'hpp triplek' (6111.00) sebagai KREDIT
            $selisih = round($totalDebit - $totalKredit, 2);
            $grouped[] = [
                'nama_akun'  => 'hpp triplek',
                'no_akun'    => '6111.00',
                'bagian'     => $machine,
                'keterangan' => '',
                'dk'         => 'k',
                'tipe'       => 'b',
                'banyak'     => null,
                'volume'     => null,
                'harga'      => null,
                'jumlah'     => $selisih > 0 ? abs($selisih) : 0,
                'has_qty'    => false,
                'has_vol'    => false,
            ];

            $machineTables[$machine] = $grouped;
        }

        $dateStr = \Carbon\Carbon::parse($this->tanggal)->format('Ymd');
        $currentRow = 1;

        foreach ($machineTables as $machine => $groupedRows) {
            // Title Row
            $noJurnal = 'ROT/' . $dateStr . '/' . strtoupper(str_replace(' ', '', $machine));
            $rows->push([
                'No. Jurnal: ' . $noJurnal, '', '', '', '', '', '', '', '', '', '', '', '', ''
            ]);
            $this->titleRows[] = $currentRow;
            $currentRow++;

            // Header Row
            $rows->push([
                'Nama Akun',
                'tgl',
                'jurnal',
                'No Akun',
                'No',
                'mm',
                'Nama',
                'Keterangan',
                'map',
                'hit kbk',
                'Banyak',
                'M3',
                'Harga',
                'Total'
            ]);
            $this->headerRows[] = $currentRow;
            $currentRow++;

            // Data Rows
            $dataStart = $currentRow;
            $tglVal = \Carbon\Carbon::parse($this->tanggal)->format('d-m-Y');
            foreach ($groupedRows as $g) {
                // Whitelist mapped accounts for formatting logic
                $isVeneer = in_array($g['no_akun'], ['115-07', '115-08', '1421.00', '1422.00', '1426.00', '1427.00']);
                $isHutangGaji = in_array($g['no_akun'], ['210-02', '2231.00']);
                $isWood = in_array($g['no_akun'], ['115-01', '115-02', '1411.01', '1411.02', '1411.03', '1411.04']);

                // Format `Nama` (Col 7 / G)
                if ($isVeneer) {
                    $namaVal = 'KUPASAN (M - ' . strtoupper($g['bagian']) . ')';
                } else {
                    $namaVal = 'KUPASAN';
                }

                // Format `hit kbk` (Col 10 / J)
                $hitKbkVal = '';
                if ($isVeneer) {
                    $hitKbkVal = 'm';
                } elseif ($isHutangGaji) {
                    $hitKbkVal = 'b';
                }

                // Format `Harga` (Col 13 / M)
                $hargaVal = null;
                if ($isVeneer) {
                    $noAkunVal = $g['no_akun'] ?? '';
                    $namaAkunVal = strtolower($g['nama_akun'] ?? '');
                    if ($noAkunVal === '1421.00' || ($noAkunVal === '115-07' && str_contains($namaAkunVal, 'sengon'))) {
                        $hargaVal = 2700000;
                    } elseif ($noAkunVal === '1422.00' || ($noAkunVal === '115-07' && str_contains($namaAkunVal, 'meranti'))) {
                        $hargaVal = 8000000;
                    } elseif ($noAkunVal === '1426.00' || ($noAkunVal === '115-08' && str_contains($namaAkunVal, 'sengon'))) {
                        $hargaVal = 1700000;
                    } elseif ($noAkunVal === '1427.00' || ($noAkunVal === '115-08' && str_contains($namaAkunVal, 'meranti'))) {
                        $hargaVal = 2100000;
                    } else {
                        if (str_contains($namaAkunVal, 'core')) {
                            $hargaVal = str_contains($namaAkunVal, 'sengon') ? 1700000 : 2100000;
                        } else {
                            $hargaVal = str_contains($namaAkunVal, 'sengon') ? 2700000 : 8000000;
                        }
                    }
                } elseif ($isHutangGaji) {
                    $hargaVal = 150000;
                } elseif ($isWood) {
                    $hargaVal = $g['harga'];
                } else {
                    $hargaVal = $g['jumlah'];
                }

                // Calculate Total: volume * Harga if volume is present, Banyak * Harga if qty is present, else Harga
                $totalVal = 0.0;
                if ($g['has_vol'] && $g['volume'] !== null && $g['volume'] > 0) {
                    $totalVal = (float)$g['volume'] * (float)$hargaVal;
                } elseif ($g['has_qty'] && $g['banyak'] !== null && $g['banyak'] > 0) {
                    $totalVal = (float)$g['banyak'] * (float)$hargaVal;
                } else {
                    $totalVal = (float)$hargaVal;
                }

                $rows->push([
                    $g['nama_akun'],                                    // 1. Nama Akun
                    $tglVal,                                            // 2. tgl
                    '',                                                 // 3. jurnal
                    $g['no_akun'],                                      // 4. No Akun
                    '',                                                 // 5. No
                    '',                                                 // 6. mm
                    $namaVal,                                           // 7. Nama
                    $g['keterangan'],                                   // 8. Keterangan
                    $g['dk'],                                           // 9. map
                    $hitKbkVal,                                         // 10. hit kbk
                    $g['has_qty'] ? $g['banyak'] : null,                // 11. Banyak
                    $g['has_vol'] ? $g['volume'] : null,                // 12. M3
                    $hargaVal,                                          // 13. Harga
                    $totalVal                                           // 14. Total
                ]);
                $currentRow++;
            }
            $dataEnd = $currentRow - 1;
            $this->dataRanges[] = ['start' => $dataStart, 'end' => $dataEnd];

            // 2 Blank separating rows
            $rows->push(['', '', '', '', '', '', '', '', '', '', '', '', '', '']);
            $rows->push(['', '', '', '', '', '', '', '', '', '', '', '', '', '']);
            $currentRow += 2;
        }

        return $rows;
    }

    public function title(): string
    {
        return 'Jurnal Pembantu Produksi';
    }

    public function styles(Worksheet $sheet)
    {
        return [];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                // Style Title Rows
                foreach ($this->titleRows as $row) {
                    $sheet->mergeCells("A{$row}:N{$row}");
                    $sheet->getStyle("A{$row}:N{$row}")->applyFromArray([
                        'font' => ['bold' => true, 'size' => 11, 'color' => ['argb' => 'FF1D2939']],
                        'fill' => [
                            'fillType' => Fill::FILL_SOLID,
                            'startColor' => ['argb' => 'FFD2E4F0']
                        ],
                        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
                    ]);
                    $sheet->getRowDimension($row)->setRowHeight(25);
                }

                // Style Header Rows
                foreach ($this->headerRows as $row) {
                    $sheet->getStyle("A{$row}:N{$row}")->applyFromArray([
                        'font' => ['bold' => true, 'size' => 10],
                        'alignment' => [
                            'horizontal' => Alignment::HORIZONTAL_CENTER,
                            'vertical' => Alignment::VERTICAL_CENTER,
                        ],
                        'fill' => [
                            'fillType' => Fill::FILL_SOLID,
                            'startColor' => ['argb' => 'FFE5E8EB']
                        ],
                        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
                    ]);
                    $sheet->getRowDimension($row)->setRowHeight(25);
                }

                // Style Data Rows
                foreach ($this->dataRanges as $range) {
                    $start = $range['start'];
                    $end = $range['end'];
                    if ($start > $end) continue;

                    $sheet->getStyle("A{$start}:N{$end}")->applyFromArray([
                        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
                    ]);

                    $sheet->getStyle("A{$start}:A{$end}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                    $sheet->getStyle("B{$start}:F{$end}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle("G{$start}:H{$end}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                    $sheet->getStyle("I{$start}:J{$end}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle("K{$start}:N{$end}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

                    $sheet->getStyle("K{$start}:K{$end}")->getNumberFormat()->setFormatCode('#,##0');
                    $sheet->getStyle("L{$start}:L{$end}")->getNumberFormat()->setFormatCode('#,##0.0000');
                    $sheet->getStyle("M{$start}:M{$end}")->getNumberFormat()->setFormatCode('#,##0');
                    $sheet->getStyle("N{$start}:N{$end}")->getNumberFormat()->setFormatCode('#,##0');
                }

                // Column Widths
                $sheet->getColumnDimension('A')->setWidth(25); // Nama Akun
                $sheet->getColumnDimension('B')->setWidth(15); // tgl
                $sheet->getColumnDimension('C')->setWidth(12); // jurnal
                $sheet->getColumnDimension('D')->setWidth(15); // No Akun
                $sheet->getColumnDimension('E')->setWidth(10); // No
                $sheet->getColumnDimension('F')->setWidth(10); // mm
                $sheet->getColumnDimension('G')->setWidth(30); // Nama
                $sheet->getColumnDimension('H')->setWidth(40); // Keterangan
                $sheet->getColumnDimension('I')->setWidth(10); // map
                $sheet->getColumnDimension('J')->setWidth(10); // hit kbk
                $sheet->getColumnDimension('N')->setWidth(18); // Total
            }
        ];
    }
}