<?php

namespace App\Exports;

use App\Models\HppAverageLog;
use App\Models\ProduksiRotary;
use App\Models\DetailTurusanKayu;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
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

class LaporanKayuKeluarExport implements WithMultipleSheets
{
    protected string $tanggal;

    public function __construct(string $tanggal)
    {
        $this->tanggal = $tanggal;
    }

    public function sheets(): array
    {
        return [
            new LaporanProduksiKayuHabisSheet($this->tanggal),
        ];
    }

    public static function getAccountDetails(string $jenisKayuNama, $panjang): array
    {
        $jenis = strtolower(trim($jenisKayuNama));
        $panjang = (int) $panjang;

        $isWHN = false;
        if (request()) {
            $host = request()->getHost();
            if ($host === 'wahana.wijayaplywoods.com' || env('APP_COMPANY') === 'WHN') {
                $isWHN = true;
            }
        }

        $isLunak = (str_contains($jenis, 'sengon') || str_contains($jenis, 'lunak'));
        $isMeranti = str_contains($jenis, 'meranti');
        $isRijek = str_contains($jenis, 'rijek');
        $isLogCore = str_contains($jenis, 'log core') || str_contains($jenis, 'core');
        $isBaloan = str_contains($jenis, 'baloan') || str_contains($jenis, 'balo,an');

        if ($isWHN) {
            if ($isMeranti) {
                return ['no_akun' => '1413.09', 'nama_akun' => 'Kayu Meranti WHN'];
            }
            if ($isRijek) {
                return ['no_akun' => '1413.10', 'nama_akun' => 'Kayu Rijek WHN'];
            }
            if ($isLogCore) {
                if ($panjang === 130) {
                    return ['no_akun' => '1414.00', 'nama_akun' => 'log core 130 WHN'];
                }
                return ['no_akun' => '1413.13', 'nama_akun' => 'log core 260 WHN'];
            }
            if ($isBaloan) {
                return ['no_akun' => '1413.05', 'nama_akun' => 'kayu balo,an'];
            }

            // Lunak
            if ($isLunak) {
                if ($panjang === 130) {
                    return ['no_akun' => '1413.07', 'nama_akun' => 'Kayu Lunak 130 WHN'];
                }
                if ($panjang === 230) {
                    return ['no_akun' => '1413.11', 'nama_akun' => 'kayu Lunak 230 WHN'];
                }
                if ($panjang === 100) {
                    return ['no_akun' => '1413.12', 'nama_akun' => 'kayu Lunak 100 WHN'];
                }
                return ['no_akun' => '1413.01', 'nama_akun' => 'kayu Lunak 260 WHN'];
            }

            // Keras (default)
            if ($panjang === 130) {
                return ['no_akun' => '1413.08', 'nama_akun' => 'Kayu Keras 130 WHN'];
            }
            return ['no_akun' => '1413.06', 'nama_akun' => 'Kayu Keras 260 WHN'];
        } else {
            // WJY
            if ($isMeranti) {
                return ['no_akun' => '1411.05', 'nama_akun' => 'Kayu Meranti WJY'];
            }
            if ($isRijek) {
                return ['no_akun' => '1411.06', 'nama_akun' => 'Kayu Rijek WJY'];
            }
            if ($isLogCore) {
                if ($panjang === 130) {
                    return ['no_akun' => '1413.04', 'nama_akun' => 'log core 130 WJY'];
                }
                return ['no_akun' => '1413.03', 'nama_akun' => 'log core 260 WJY'];
            }
            if ($isBaloan) {
                return ['no_akun' => '1413.05', 'nama_akun' => 'kayu balo,an'];
            }

            // Lunak
            if ($isLunak) {
                if ($panjang === 130) {
                    return ['no_akun' => '1411.03', 'nama_akun' => 'Kayu Lunak 130 WJY'];
                }
                if ($panjang === 230) {
                    return ['no_akun' => '1411.07', 'nama_akun' => 'kayu Lunak 230 WJY'];
                }
                if ($panjang === 100) {
                    return ['no_akun' => '1411.08', 'nama_akun' => 'kayu Lunak 100 WJY'];
                }
                return ['no_akun' => '1411.01', 'nama_akun' => 'kayu Lunak 260 WJY'];
            }

            // Keras (default)
            if ($panjang === 130) {
                return ['no_akun' => '1411.04', 'nama_akun' => 'Kayu Keras 130 WJY'];
            }
            return ['no_akun' => '1411.02', 'nama_akun' => 'Kayu Keras 260 WJY'];
        }
    }
}

class LaporanKayuKeluarDetailSheet implements FromCollection, WithTitle, ShouldAutoSize, WithStyles
{
    protected string $tanggal;

    public function __construct(string $tanggal)
    {
        $this->tanggal = $tanggal;
    }

    /**
     * Fetch and structure outgoing logs per Lahan for Excel export
     */
    public function collection()
    {
        $records = HppAverageLog::with(['lahan', 'jenisKayu'])
            ->whereDate('tanggal', $this->tanggal)
            ->where('tipe_transaksi', 'keluar')
            ->orderBy('id', 'asc')
            ->get();

        $grouped = $records->groupBy(fn($item) => $item->id_lahan ?? 0);

        $rows = [];

        // Main Title Block
        $rows[] = ['LAPORAN KAYU KELUAR PER LAHAN - ' . Carbon::parse($this->tanggal)->format('d/m/Y')];
        $rows[] = []; // Spacer row

        if ($grouped->isEmpty()) {
            $rows[] = ['Tidak ada data transaksi kayu keluar untuk tanggal ini.'];
            return collect($rows);
        }

        foreach ($grouped as $lahanId => $logs) {
            $lahanModel = $logs->first()?->lahan;
            $lahanLabel = $lahanModel ? ($lahanModel->kode_lahan . ' - ' . $lahanModel->nama_lahan) : 'Tanpa Lahan';

            // Ambil semua seri kayu masuk untuk id_lahan ini dari HppAverageLog
            $seriLogs = HppAverageLog::where('id_lahan', $lahanId)
                ->where('tipe_transaksi', 'masuk')
                ->get();

            $seriList = [];
            foreach ($seriLogs as $sLog) {
                $seri = null;
                if ($sLog->referensi_type === 'App\Models\NotaKayu' || $sLog->referensi_type === 'NotaKayu') {
                    $ref = $sLog->referensi;
                    if ($ref) {
                        $seri = $ref->kayuMasuk->seri ?? null;
                    }
                }
                if (!$seri && preg_match('/SERI:\s*(\d+)/i', $sLog->keterangan, $matches)) {
                    $seri = $matches[1];
                }
                if ($seri) {
                    $seriList[] = $seri;
                }
            }
            $uniqueSeri = array_unique($seriList);
            asort($uniqueSeri);
            $seriLabel = !empty($uniqueSeri) ? implode(', ', $uniqueSeri) : '-';

            // Lahan Title Header row
            $rows[] = ['LAHAN: ' . $lahanLabel . ' (Seri Kayu: ' . $seriLabel . ')'];
            
            // Table Column Headers
            $rows[] = [
                'No',
                'Jenis Kayu',
                'Panjang (cm)',
                'Batang Keluar',
                'Volume Keluar (M3)',
                'Sisa Batang',
                'Sisa Volume (M3)',
                'Status',
                'Keterangan'
            ];

            $no = 1;
            foreach ($logs as $log) {
                $rows[] = [
                    $no++,
                    $log->jenisKayu?->nama_kayu ?? '-',
                    $log->panjang ?? '-',
                    $log->total_batang ?? 0,
                    $log->total_kubikasi ?? 0,
                    $log->stok_batang_after ?? 0,
                    $log->stok_kubikasi_after ?? 0,
                    ($log->stok_batang_after == 0) ? 'Habis (0)' : 'Tersisa',
                    $log->keterangan ?? '-',
                ];
            }

            // Spacers between different Lahan tables
            $rows[] = [];
            $rows[] = [];
        }

        return collect($rows);
    }

    public function title(): string
    {
        return 'Laporan Kayu Keluar';
    }

    public function styles(Worksheet $sheet)
    {
        // Set first row (Main Title) bold and large
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);

        $highestRow = $sheet->getHighestRow();

        for ($row = 2; $row <= $highestRow; $row++) {
            $cellValue = $sheet->getCell("A{$row}")->getValue();

            // Style Lahan title row
            if (str_starts_with((string)$cellValue, 'LAHAN:')) {
                $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(11);
                $sheet->mergeCells("A{$row}:I{$row}");
                $sheet->getStyle("A{$row}:I{$row}")->getFill()->setFillType(Fill::FILL_SOLID);
                $sheet->getStyle("A{$row}:I{$row}")->getFill()->getStartColor()->setARGB('F4F4F5'); // Zinc 100
            } 
            // Style table columns header row
            elseif ($cellValue === 'No') {
                $sheet->getStyle("A{$row}:I{$row}")->getFont()->setBold(true);
                $sheet->getStyle("A{$row}:I{$row}")->getFill()->setFillType(Fill::FILL_SOLID);
                $sheet->getStyle("A{$row}:I{$row}")->getFill()->getStartColor()->setARGB('E4E4E7'); // Zinc 200
                $sheet->getStyle("A{$row}:I{$row}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
            } 
            // Style actual log rows
            elseif (is_numeric($cellValue)) {
                $sheet->getStyle("A{$row}:I{$row}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
                
                // Alignments
                $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("C{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("H{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                
                // Formatting for numbers / volumes
                $sheet->getStyle("D{$row}")->getNumberFormat()->setFormatCode('#,##0');
                $sheet->getStyle("E{$row}")->getNumberFormat()->setFormatCode('#,##0.0000');
                $sheet->getStyle("F{$row}")->getNumberFormat()->setFormatCode('#,##0');
                $sheet->getStyle("G{$row}")->getNumberFormat()->setFormatCode('#,##0.0000');

                // Color coding Status column based on "Habis (0)" or "Tersisa"
                $statusValue = $sheet->getCell("H{$row}")->getValue();
                if ($statusValue === 'Habis (0)') {
                    $sheet->getStyle("H{$row}")->getFont()->getColor()->setARGB('DC2626'); // Red 600
                    $sheet->getStyle("H{$row}")->getFont()->setBold(true);
                } else {
                    $sheet->getStyle("H{$row}")->getFont()->getColor()->setARGB('16A34A'); // Green 600
                    $sheet->getStyle("H{$row}")->getFont()->setBold(true);
                }
            }
        }

        return [];
    }
}

class LaporanProduksiJurnalGabungSheet extends DefaultValueBinder implements FromCollection, WithTitle, WithStyles, WithEvents, WithCustomValueBinder
{
    protected $tanggal;
    protected $titleRows = [];
    protected $headerRows = [];
    protected $dataRanges = [];

    public function bindValue(Cell $cell, $value)
    {
        if ($cell->getColumn() === 'D') {
            if (is_numeric($value)) {
                $cell->setValueExplicit((float)$value, DataType::TYPE_NUMERIC);
                $cell->getWorksheet()->getStyle($cell->getCoordinate())->getNumberFormat()->setFormatCode('0.00');
                return true;
            }
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

        // Preload mesin dan jenis kayu untuk pencarian dinamis
        $mesins = \App\Models\Mesin::all()->keyBy(fn($m) => strtoupper(trim($m->nama_mesin)));
        $jenisKayus = \App\Models\JenisKayu::all()->keyBy(fn($jk) => strtoupper(trim($jk->nama_kayu)));

        // Preload harga veneer kering
        $hargaVeneerMap = [];
        $referensiHargaKering = \App\Models\ReferensiHargaProduksi::where('jenis_barang', 'Veneer Kering')->get();
        foreach ($referensiHargaKering as $rhp) {
            $ukKey = strtolower(trim($rhp->kw ?? ''));
            if (str_starts_with($ukKey, 'kw 1 - ')) {
                $ukKey = substr($ukKey, 7);
            }
            $ukKey = str_replace(' ', '_', $ukKey);
            $hargaVeneerMap[$rhp->id_jenis_kayu][$ukKey] = (float) $rhp->harga;
        }

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
                    $parts = explode(' - ', $subItem['keterangan'] ?? '');
                    $bagian = count($parts) > 1 ? trim($parts[1]) : '-';

                    $is130 = ($noAkun === '115-01');
                    $acc = LaporanKayuKeluarExport::getAccountDetails($parts[0] ?? '', $is130 ? 130 : 260);
                    $mappedNoAkun = $acc['no_akun'];
                    $mappedNamaAkun = $acc['nama_akun'];

                    $lahanName = $subItem['nama_pihak'] ?? '';
                    if (stripos($lahanName, 'Lahan ') === 0) {
                        $keteranganSpesifikasi = 'lahan ' . substr($lahanName, 6);
                    } else {
                        $keteranganSpesifikasi = 'lahan ' . $lahanName;
                    }
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

                // Khusus export Excel: harga veneer mengikuti harga veneer kering dari tabel harga_veneers
                if (($subItem['jenis_pihak'] ?? '') === 'produksi') {
                    $namaM = strtoupper(trim($bagian));
                    $jenisHasil = isset($mesins[$namaM]) ? $mesins[$namaM]->jenis_hasil : 'core';

                    // Parse jenis kayu dari keterangan
                    $keterangan = $subItem['keterangan'] ?? '';
                    $parts = explode(' - ', $keterangan);
                    $namaKayu = count($parts) > 2 ? trim($parts[2]) : '';
                    $namaKayuUpper = strtoupper(trim($namaKayu));
                    $jenisKayuObj = $jenisKayus[$namaKayuUpper] ?? null;
                    $idJenisKayu = $jenisKayuObj ? $jenisKayuObj->id : null;

                    $ongkos = 0;
                    if ($idJenisKayu) {
                        $options = strtolower($jenisHasil) === 'f/b' ? ['faceback', 'face', 'back'] : ['core'];
                        foreach ($options as $opt) {
                            if (isset($hargaVeneerMap[$idJenisKayu][$opt])) {
                                $ongkos = $hargaVeneerMap[$idJenisKayu][$opt];
                                break;
                            }
                        }
                    }

                    // Fallback to legacy if no match found
                    if ($ongkos === 0) {
                        $ongkos = isset($mesins[$namaM]) ? (float)($mesins[$namaM]->ongkos_mesin ?? 0) : 0;
                    }

                    $harga  = $ongkos;
                    $jumlah = $volume !== null ? round((float)$volume * $ongkos, 4) : null;
                }

                // Khusus export Excel: harga pekerja di-hardcode 150.000
                if (($subItem['jenis_pihak'] ?? '') === 'karyawan') {
                    $harga  = 150_000;
                    $jumlah = 150_000; // 1 orang × 150.000
                }

                $mappedNoAkun = $noAkun;
                $mappedNamaAkun = $namaAkun;

                if ($noAkun === '115-01' || $noAkun === '115-02') {
                    $parts = explode(' - ', $subItem['keterangan'] ?? '');
                    $is130 = ($noAkun === '115-01');
                    $acc = LaporanKayuKeluarExport::getAccountDetails($parts[0] ?? '', $is130 ? 130 : 260);
                    $mappedNoAkun = $acc['no_akun'];
                    $mappedNamaAkun = $acc['mappedNamaAkun'] ?? $acc['nama_akun'];
                }

                if ($noAkun === '115-07' || $noAkun === '115-08') {
                    $isWHN = false;
                    if (request()) {
                        $host = request()->getHost();
                        if ($host === 'wahana.wijayaplywoods.com' || env('APP_COMPANY') === 'WHN') {
                            $isWHN = true;
                        }
                    }

                    $isSengon = (stripos($subItem['keterangan'] ?? '', 'sengon') !== false);
                    if ($noAkun === '115-07') {
                        // Veneer Basah F/B
                        if ($isWHN) {
                            $mappedNoAkun = $isSengon ? '1421.01' : '1422.01';
                            $mappedNamaAkun = $isSengon ? 'Veneer Basah 260 face/back sengon WHN' : 'Veneer Basah 260 face/back meranti WHN';
                        } else {
                            $mappedNoAkun = $isSengon ? '1421.00' : '1422.00';
                            $mappedNamaAkun = $isSengon ? 'Veneer Basah 260 face/back sengon WJY' : 'Veneer Basah 260 face/back meranti WJY';
                        }
                    } else {
                        // Veneer Basah CORE
                        if ($isWHN) {
                            $mappedNoAkun = $isSengon ? '1426.01' : '1427.01';
                            $mappedNamaAkun = $isSengon ? 'Veneer Basah 130 core sengon WHN' : 'Veneer Basah 130 core meranti WHN';
                        } else {
                            $mappedNoAkun = $isSengon ? '1426.00' : '1427.00';
                            $mappedNamaAkun = $isSengon ? 'Veneer Basah 130 core sengon WJY' : 'Veneer Basah 130 core meranti WJY';
                        }
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
                    'dk'         => (($subItem['jenis_pihak'] ?? '') === 'pemasok') ? 'k' : $mapDK,
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
                $isVeneer = in_array($g['no_akun'], ['115-07', '115-08', '1421.00', '1421.01', '1422.00', '1422.01', '1426.00', '1426.01', '1427.00', '1427.01']);
                $isHutangGaji = in_array($g['no_akun'], ['210-02', '2231.00']);
                $isWood = in_array($g['no_akun'], [
                    '115-01', '115-02', 
                    '1411.01', '1411.02', '1411.03', '1411.04', '1411.05', '1411.06', '1411.07', '1411.08',
                    '1413.01', '1413.03', '1413.04', '1413.05', '1413.06', '1413.07', '1413.08', '1413.09', '1413.10', '1413.11', '1413.12', '1413.13', '1414.00'
                ]);
                $isKayuKeluar = in_array($g['no_akun'], [
                    '1411.01', '1411.02', '1411.03', '1411.04', '1411.05', '1411.06', '1411.07', '1411.08',
                    '1413.01', '1413.03', '1413.04', '1413.05', '1413.06', '1413.07', '1413.08', '1413.09', '1413.10', '1413.11', '1413.12', '1413.13', '1414.00'
                ]);

                $rowHarga = 0.0;
                if ($isVeneer) {
                    $noAkunVal = $g['no_akun'] ?? '';
                    $namaAkunVal = strtolower($g['nama_akun'] ?? '');
                    
                    $dbHarga = $this->getHargaVeneerBasahDb($noAkunVal, $namaAkunVal);
                    if ($dbHarga > 0) {
                        $rowHarga = $dbHarga;
                    } else {
                        if ($noAkunVal === '1421.00' || $noAkunVal === '1421.01' || ($noAkunVal === '115-07' && str_contains($namaAkunVal, 'sengon'))) {
                            $rowHarga = 2700000.0;
                        } elseif ($noAkunVal === '1422.00' || $noAkunVal === '1422.01' || ($noAkunVal === '115-07' && str_contains($namaAkunVal, 'meranti'))) {
                            $rowHarga = 8000000.0;
                        } elseif ($noAkunVal === '1426.00' || $noAkunVal === '1426.01' || ($noAkunVal === '115-08' && str_contains($namaAkunVal, 'sengon'))) {
                            $rowHarga = 1700000.0;
                        } elseif ($noAkunVal === '1427.00' || $noAkunVal === '1427.01' || ($noAkunVal === '115-08' && str_contains($namaAkunVal, 'meranti'))) {
                            $rowHarga = 2100000.0;
                        } else {
                            if (str_contains($namaAkunVal, 'core')) {
                                $rowHarga = str_contains($namaAkunVal, 'sengon') ? 1700000.0 : 2100000.0;
                            } else {
                                $rowHarga = str_contains($namaAkunVal, 'sengon') ? 2700000.0 : 8000000.0;
                            }
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
                if ($isKayuKeluar) {
                    $rowTotal = (float)$g['jumlah'];
                } elseif ($g['has_vol'] && $g['volume'] !== null && $g['volume'] > 0) {
                    $rowTotal = round((float)$g['volume'], 4) * $rowHarga;
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

        $dateStr = Carbon::parse($this->tanggal)->format('Ymd');
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
            $tglVal = Carbon::parse($this->tanggal)->format('d-m-Y');
            foreach ($groupedRows as $g) {
                $isVeneer = in_array($g['no_akun'], ['115-07', '115-08', '1421.00', '1421.01', '1422.00', '1422.01', '1426.00', '1426.01', '1427.00', '1427.01']);
                $isHutangGaji = in_array($g['no_akun'], ['210-02', '2231.00']);
                $isKayuKeluar = in_array($g['no_akun'], [
                    '1411.01', '1411.02', '1411.03', '1411.04', '1411.05', '1411.06', '1411.07', '1411.08',
                    '1413.01', '1413.03', '1413.04', '1413.05', '1413.06', '1413.07', '1413.08', '1413.09', '1413.10', '1413.11', '1413.12', '1413.13', '1414.00'
                ]);

                // Format `Nama` (Col 7 / G)
                if ($isVeneer) {
                    $namaVal = 'kupasan (m - ' . strtolower($g['bagian']) . ')';
                } elseif ($isKayuKeluar) {
                    $namaVal = 'kayu keluar';
                } else {
                    $namaVal = 'kupasan';
                }

                // Format `hit kbk` (Col 10 / J)
                $hitKbkVal = '';
                if ($isVeneer || $isKayuKeluar) {
                    $hitKbkVal = 'm';
                } elseif ($isHutangGaji) {
                    $hitKbkVal = 'b';
                }

                $hargaVal = null;
                if ($isVeneer) {
                    $noAkunVal = $g['no_akun'] ?? '';
                    $namaAkunVal = strtolower($g['nama_akun'] ?? '');
                    
                    $dbHarga = $this->getHargaVeneerBasahDb($noAkunVal, $namaAkunVal);
                    if ($dbHarga > 0) {
                        $hargaVal = $dbHarga;
                    } else {
                        if ($noAkunVal === '1421.00' || $noAkunVal === '1421.01' || ($noAkunVal === '115-07' && str_contains($namaAkunVal, 'sengon'))) {
                            $hargaVal = 2700000;
                        } elseif ($noAkunVal === '1422.00' || $noAkunVal === '1422.01' || ($noAkunVal === '115-07' && str_contains($namaAkunVal, 'meranti'))) {
                            $hargaVal = 8000000;
                        } elseif ($noAkunVal === '1426.00' || $noAkunVal === '1426.01' || ($noAkunVal === '115-08' && str_contains($namaAkunVal, 'sengon'))) {
                            $hargaVal = 1700000;
                        } elseif ($noAkunVal === '1427.00' || $noAkunVal === '1427.01' || ($noAkunVal === '115-08' && str_contains($namaAkunVal, 'meranti'))) {
                            $hargaVal = 2100000;
                        } else {
                            if (str_contains($namaAkunVal, 'core')) {
                                $hargaVal = str_contains($namaAkunVal, 'sengon') ? 1700000 : 2100000;
                            } else {
                                $hargaVal = str_contains($namaAkunVal, 'sengon') ? 2700000 : 8000000;
                            }
                        }
                    }
                } elseif ($isHutangGaji) {
                    $hargaVal = 150000;
                } elseif ($isKayuKeluar) {
                    $hargaVal = $g['harga'];
                } else {
                    $hargaVal = $g['jumlah'];
                }

                // Calculate Total as an Excel formula referencing 'hit kbk' (Col J), Harga (Col M), M3 (Col L), and Banyak (Col K)
                $totalVal = "=IF(J{$currentRow}=\"m\",M{$currentRow}*L{$currentRow},IF(J{$currentRow}=\"b\",M{$currentRow}*K{$currentRow},M{$currentRow}))";

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
                    $g['has_vol'] ? round($g['volume'], 4) : null,      // 12. M3
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
        return 'jurnal produksi';
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
                $sheet->getColumnDimension('K')->setWidth(12); // Banyak
                $sheet->getColumnDimension('L')->setWidth(15); // M3
                $sheet->getColumnDimension('M')->setWidth(18); // Harga
                $sheet->getColumnDimension('N')->setWidth(18); // Total
            }
        ];
    }

    private function getHargaVeneerBasahDb(string $noAkun, string $namaAkun): float
    {
        $noAkunVal = trim($noAkun);
        $namaAkunVal = strtolower(trim($namaAkun));

        $isSengon = str_contains($namaAkunVal, 'sengon');
        $isMeranti = str_contains($namaAkunVal, 'meranti') || (!str_contains($namaAkunVal, 'sengon') && !str_contains($namaAkunVal, 'jabon') && !str_contains($namaAkunVal, 'mahoni'));
        
        $jns = $isSengon ? 'Sengon' : 'Meranti';
        $jenisKayu = \App\Models\JenisKayu::where('nama_kayu', $jns)->first();
        if (!$jenisKayu) {
            return 0.0;
        }

        // Determine if it's faceback or core
        $isCore = str_contains($namaAkunVal, 'core') 
            || $noAkunVal === '1426.00' 
            || $noAkunVal === '1426.01' 
            || $noAkunVal === '1427.00' 
            || $noAkunVal === '1427.01' 
            || ($noAkunVal === '115-08');

        $ukuranOptions = !$isCore
            ? ($jns === 'Sengon' ? ['faceback'] : ['face', 'back'])
            : ['core'];

        $kwOptions = array_map(function($opt) {
            return 'KW 1 - ' . ucfirst(str_replace('_', ' ', $opt));
        }, $ukuranOptions);

        $hargaVeneer = \App\Models\ReferensiHargaProduksi::where('id_jenis_kayu', $jenisKayu->id)
            ->where('jenis_barang', 'Veneer Basah')
            ->whereIn('kw', $kwOptions)
            ->first();

        return (float) ($hargaVeneer->harga ?? 0.0);
    }
}

class LaporanProduksiJurnalPenggunaanSheet extends DefaultValueBinder implements FromCollection, WithTitle, WithStyles, WithEvents, WithCustomValueBinder
{
    protected $tanggal;
    protected $titleRows = [];
    protected $headerRows = [];
    protected $dataRanges = [];

    public function bindValue(Cell $cell, $value)
    {
        if ($cell->getColumn() === 'D') {
            if (is_numeric($value)) {
                $cell->setValueExplicit((float)$value, DataType::TYPE_NUMERIC);
                $cell->getWorksheet()->getStyle($cell->getCoordinate())->getNumberFormat()->setFormatCode('0.00');
                return true;
            }
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
            $rows->push(['Tidak ada data penggunaan kayu untuk tanggal ini.']);
            return $rows;
        }

        $rawRows = [];

        foreach ($payload['jurnal_items'] as $item) {
            $noAkun   = $item['no_akun'];

            // We only look at Persediaan Kayu (115-01 / 115-02)
            if ($noAkun !== '115-01' && $noAkun !== '115-02') {
                continue;
            }

            foreach ($item['items'] as $subItem) {
                $parts = explode(' - ', $subItem['keterangan'] ?? '');
                $bagian = count($parts) > 1 ? trim($parts[1]) : '-';

                $is130 = ($noAkun === '115-01');
                $acc = LaporanKayuKeluarExport::getAccountDetails($parts[0] ?? '', $is130 ? 130 : 260);
                $mappedNoAkun = $acc['no_akun'];
                $mappedNamaAkun = $acc['nama_akun'];

                $lahanName = $subItem['nama_pihak'] ?? '';
                if (stripos($lahanName, 'Lahan ') === 0) {
                    $keteranganSpesifikasi = 'lahan ' . substr($lahanName, 6);
                } else {
                    $keteranganSpesifikasi = 'lahan ' . $lahanName;
                }

                $rawRows[] = [
                    'nama_akun'  => $mappedNamaAkun,
                    'no_akun'    => $mappedNoAkun,
                    'bagian'     => $bagian,
                    'keterangan' => $keteranganSpesifikasi,
                    'dk'         => 'k',
                    'tipe'       => 'b',
                    'banyak'     => $subItem['banyak'] !== null ? (float)$subItem['banyak'] : null,
                    'volume'     => $subItem['m3'] !== null ? (float)$subItem['m3'] : null,
                    'harga'      => $subItem['harga'] !== null ? (float)$subItem['harga'] : null,
                    'jumlah'     => $subItem['jumlah'] !== null ? (float)$subItem['jumlah'] : null,
                ];
            }
        }

        if (empty($rawRows)) {
            $rows->push(['Tidak ada data penggunaan kayu untuk tanggal ini.']);
            return $rows;
        }

        $grouped = [];
        foreach ($rawRows as $row) {
            $key = implode('|', [
                $row['no_akun'],
                $row['keterangan']
            ]);

            if (!isset($grouped[$key])) {
                $grouped[$key] = [
                    'nama_akun'  => $row['nama_akun'],
                    'no_akun'    => $row['no_akun'],
                    'bagian'     => $row['bagian'],
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

        $dateStr = Carbon::parse($this->tanggal)->format('Ymd');
        $currentRow = 1;

        // Title Row
        $noJurnal = 'ROT/' . $dateStr . '/KAYU_KELUAR';
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
        $tglVal = Carbon::parse($this->tanggal)->format('d-m-Y');
        foreach ($grouped as $g) {
            $totalVal = "=IF(J{$currentRow}=\"m\",M{$currentRow}*L{$currentRow},IF(J{$currentRow}=\"b\",M{$currentRow}*K{$currentRow},M{$currentRow}))";

            $rows->push([
                $g['nama_akun'],                                    // 1. Nama Akun
                $tglVal,                                            // 2. tgl
                '',                                                 // 3. jurnal
                $g['no_akun'],                                      // 4. No Akun
                '',                                                 // 5. No
                '',                                                 // 6. mm
                'kayu keluar',                                      // 7. Nama
                $g['keterangan'],                                   // 8. Keterangan
                'k',                                                // 9. map
                'm',                                                // 10. hit kbk
                $g['has_qty'] ? $g['banyak'] : null,                // 11. Banyak
                $g['has_vol'] ? round($g['volume'], 4) : null,      // 12. M3
                $g['harga'],                                        // 13. Harga
                $totalVal                                           // 14. Total
            ]);
            $currentRow++;
        }
        $dataEnd = $currentRow - 1;
        $this->dataRanges[] = ['start' => $dataStart, 'end' => $dataEnd];

        return $rows;
    }

    public function title(): string
    {
        return 'Penggunaan Kayu';
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
                $sheet->getColumnDimension('K')->setWidth(12); // Banyak
                $sheet->getColumnDimension('L')->setWidth(15); // M3
                $sheet->getColumnDimension('M')->setWidth(18); // Harga
                $sheet->getColumnDimension('N')->setWidth(18); // Total
            }
        ];
    }
}

class LaporanProduksiJurnalHargaAsliSheet extends DefaultValueBinder implements FromCollection, WithTitle, WithStyles, WithEvents, WithCustomValueBinder
{
    protected $tanggal;
    protected $titleRows = [];
    protected $headerRows = [];
    protected $dataRanges = [];

    public function bindValue(Cell $cell, $value)
    {
        if ($cell->getColumn() === 'D') {
            if (is_numeric($value)) {
                $cell->setValueExplicit((float)$value, DataType::TYPE_NUMERIC);
                $cell->getWorksheet()->getStyle($cell->getCoordinate())->getNumberFormat()->setFormatCode('0.00');
                return true;
            }
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
        $tgl = Carbon::parse($this->tanggal)->startOfDay();

        // =========================================================
        // 1. PRODUKSI HARI INI
        // =========================================================
        $produksiList = ProduksiRotary::with([
            'detailLahanRotary.lahan'
        ])->whereDate('tgl_produksi', $tgl)->get();

        if ($produksiList->isEmpty()) {
            $rows->push(['Tidak ada data penggunaan kayu untuk tanggal ini.']);
            return $rows;
        }

        // =========================================================
        // 2. AMBIL SEMUA ID LAHAN
        // =========================================================
        $lahanIds = [];

        foreach ($produksiList as $p) {
            foreach ($p->detailLahanRotary as $dl) {
                if ($dl->id_lahan) {
                    $lahanIds[] = $dl->id_lahan;
                }
            }
        }

        $lahanIds = array_unique($lahanIds);

        if (empty($lahanIds)) {
            $rows->push(['Tidak ada data penggunaan kayu untuk tanggal ini.']);
            return $rows;
        }

        // =========================================================
        // 3. DETAIL TURUSAN KAYU
        // =========================================================
        $details = DetailTurusanKayu::whereIn('lahan_id', $lahanIds)
            ->with([
                'jenisKayu',
                'kayuMasuk',
                'lahan'
            ])
            ->get();

        if ($details->isEmpty()) {
            $rows->push(['Tidak ada data penggunaan kayu untuk tanggal ini.']);
            return $rows;
        }

        // =========================================================
        // 4. GROUPING
        // MERGE BERDASARKAN:
        // - LAHAN
        // - JENIS KAYU
        //
        // TIDAK PEDULI:
        // - SERI
        // - GRADE
        // - DIAMETER
        // - HARGA
        // =========================================================
        $grouped = [];

        foreach ($details as $d) {

            $lahanCode = $d->lahan->kode_lahan ?? '-';
            $lahanName = $d->lahan->nama_lahan ?? '-';
            $jenisNama = $d->jenisKayu->nama_kayu ?? '-';

            // GROUP KEY
            $key = implode('|', [
                $d->lahan_id,
                $d->jenis_kayu_id,
            ]);

            if (!isset($grouped[$key])) {

                $grouped[$key] = [
                    'lahan_id'      => $d->lahan_id,
                    'jenis_kayu_id' => $d->jenis_kayu_id,

                    'lahan_code'    => $lahanCode,
                    'lahan_name'    => $lahanName,
                    'jenis_nama'    => $jenisNama,

                    'panjang'       => $d->panjang,

                    'banyak'        => 0,
                    'volume'        => 0,
                    'total_harga'   => 0,
                ];
            }

            $grouped[$key]['banyak'] += $d->kuantitas;

            $grouped[$key]['volume'] += $d->kubikasi;

            $grouped[$key]['total_harga'] +=
                ($d->harga * 1000) * $d->kubikasi;
        }

        // =========================================================
        // TITLE
        // =========================================================
        $dateStr = Carbon::parse($this->tanggal)->format('Ymd');
        $currentRow = 1;

        $noJurnal = 'ROT/' . $dateStr . '/KAYU_KELUAR_HARGA_ASLI';

        $rows->push([
            'No. Jurnal: ' . $noJurnal,
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            ''
        ]);

        $this->titleRows[] = $currentRow;
        $currentRow++;

        // =========================================================
        // HEADER
        // =========================================================
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

        // =========================================================
        // DATA
        // =========================================================
        $dataStart = $currentRow;

        $tglVal = Carbon::parse($this->tanggal)
            ->format('d-m-Y');

        foreach ($grouped as $g) {

            $acc = LaporanKayuKeluarExport::getAccountDetails($g['jenis_nama'] ?? '', $g['panjang']);
            $noAkun = $acc['no_akun'];
            $namaAkun = $acc['nama_akun'];

            // =====================================================
            // HARGA RATA-RATA PER M3
            // =====================================================
            $roundedVol = round($g['volume'], 4);
            $hargaPerM3 = $roundedVol > 0
                ? $g['total_harga'] / $roundedVol
                : 0;

            // =====================================================
            // KETERANGAN
            // =====================================================
            $keteranganSpec = sprintf(
                "Lahan %s - %s",
                $g['lahan_code'],
                $g['jenis_nama']
            );

            // =====================================================
            // PUSH ROW
            // =====================================================
            $totalVal = "=IF(J{$currentRow}=\"m\",M{$currentRow}*L{$currentRow},IF(J{$currentRow}=\"b\",M{$currentRow}*K{$currentRow},M{$currentRow}))";

            $rows->push([
                $namaAkun,                                         // A
                $tglVal,                                           // B
                '',                                                // C
                $noAkun,                                           // D
                '',                                                // E
                '',                                                // F
                'kayu keluar',                                     // G
                $keteranganSpec,                                   // H
                'k',                                               // I
                'm',                                                // J
                $g['banyak'] > 0 ? $g['banyak'] : null,            // K
                $roundedVol > 0 ? $roundedVol : null,              // L
                $hargaPerM3,                                       // M
                $totalVal                                          // N
            ]);

            $currentRow++;
        }

        $dataEnd = $currentRow - 1;

        $this->dataRanges[] = [
            'start' => $dataStart,
            'end' => $dataEnd
        ];

        return $rows;
    }

    public function title(): string
    {
        return 'Penggunaan Kayu Harga Asli';
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

                // =================================================
                // TITLE STYLE
                // =================================================
                foreach ($this->titleRows as $row) {

                    $sheet->mergeCells("A{$row}:N{$row}");

                    $sheet->getStyle("A{$row}:N{$row}")
                        ->applyFromArray([

                            'font' => [
                                'bold' => true,
                                'size' => 11,
                                'color' => [
                                    'argb' => 'FF1D2939'
                                ]
                            ],

                            'fill' => [
                                'fillType' => Fill::FILL_SOLID,
                                'startColor' => [
                                    'argb' => 'FFD2E4F0'
                                ]
                            ],

                            'borders' => [
                                'allBorders' => [
                                    'borderStyle' => Border::BORDER_THIN
                                ]
                            ]
                        ]);

                    $sheet->getRowDimension($row)
                        ->setRowHeight(25);
                }

                // =================================================
                // HEADER STYLE
                // =================================================
                foreach ($this->headerRows as $row) {

                    $sheet->getStyle("A{$row}:N{$row}")
                        ->applyFromArray([

                            'font' => [
                                'bold' => true,
                                'size' => 10
                            ],

                            'alignment' => [
                                'horizontal' => Alignment::HORIZONTAL_CENTER,
                                'vertical' => Alignment::VERTICAL_CENTER,
                            ],

                            'fill' => [
                                'fillType' => Fill::FILL_SOLID,
                                'startColor' => [
                                    'argb' => 'FFE5E8EB'
                                ]
                            ],

                            'borders' => [
                                'allBorders' => [
                                    'borderStyle' => Border::BORDER_THIN
                                ]
                            ]
                        ]);

                    $sheet->getRowDimension($row)
                        ->setRowHeight(25);
                }

                // =================================================
                // DATA STYLE
                // =================================================
                foreach ($this->dataRanges as $range) {

                    $start = $range['start'];
                    $end = $range['end'];

                    if ($start > $end) {
                        continue;
                    }

                    $sheet->getStyle("A{$start}:N{$end}")
                        ->applyFromArray([

                            'borders' => [
                                'allBorders' => [
                                    'borderStyle' => Border::BORDER_THIN
                                ]
                            ]
                        ]);

                    $sheet->getStyle("A{$start}:A{$end}")
                        ->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_LEFT);

                    $sheet->getStyle("B{$start}:F{$end}")
                        ->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_CENTER);

                    $sheet->getStyle("G{$start}:H{$end}")
                        ->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_LEFT);

                    $sheet->getStyle("I{$start}:J{$end}")
                        ->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_CENTER);

                    $sheet->getStyle("K{$start}:N{$end}")
                        ->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_RIGHT);

                    // FORMAT ANGKA
                    $sheet->getStyle("K{$start}:K{$end}")
                        ->getNumberFormat()
                        ->setFormatCode('#,##0');

                    $sheet->getStyle("L{$start}:L{$end}")
                        ->getNumberFormat()
                        ->setFormatCode('#,##0.0000');

                    $sheet->getStyle("M{$start}:M{$end}")
                        ->getNumberFormat()
                        ->setFormatCode('#,##0');

                    $sheet->getStyle("N{$start}:N{$end}")
                        ->getNumberFormat()
                        ->setFormatCode('#,##0');
                }

                // =================================================
                // COLUMN WIDTH
                // =================================================
                $sheet->getColumnDimension('A')->setWidth(25);
                $sheet->getColumnDimension('B')->setWidth(15);
                $sheet->getColumnDimension('C')->setWidth(12);
                $sheet->getColumnDimension('D')->setWidth(15);
                $sheet->getColumnDimension('E')->setWidth(10);
                $sheet->getColumnDimension('F')->setWidth(10);
                $sheet->getColumnDimension('G')->setWidth(20);
                $sheet->getColumnDimension('H')->setWidth(45);
                $sheet->getColumnDimension('I')->setWidth(10);
                $sheet->getColumnDimension('J')->setWidth(10);
                $sheet->getColumnDimension('K')->setWidth(12);
                $sheet->getColumnDimension('L')->setWidth(15);
                $sheet->getColumnDimension('M')->setWidth(18);
                $sheet->getColumnDimension('N')->setWidth(18);
            }
        ];
    }
}

class LaporanProduksiKayuHabisSheet extends DefaultValueBinder implements FromCollection, WithTitle, WithStyles, WithEvents, WithCustomValueBinder
{
    protected $tanggal;
    protected $titleRows = [];
    protected $headerRows = [];
    protected $dataRanges = [];

    public function bindValue(Cell $cell, $value)
    {
        if ($cell->getColumn() === 'D') {
            if (is_numeric($value)) {
                $cell->setValueExplicit((float)$value, DataType::TYPE_NUMERIC);
                $cell->getWorksheet()->getStyle($cell->getCoordinate())->getNumberFormat()->setFormatCode('0.00');
                return true;
            }
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
        $tgl = Carbon::parse($this->tanggal)->startOfDay();

        // 1. Fetch HppAverageLog records where stock goes to 0 on target date
        $records = HppAverageLog::with(['lahan', 'jenisKayu'])
            ->whereDate('tanggal', $tgl)
            ->where('tipe_transaksi', 'keluar')
            ->where('stok_batang_after', 0)
            ->orderBy('id', 'asc')
            ->get();

        if ($records->isEmpty()) {
            $rows->push(['Tidak ada data penggunaan kayu habis untuk tanggal ini.']);
            return $rows;
        }

        // 2. Title Row
        $dateStr = Carbon::parse($this->tanggal)->format('Ymd');
        $currentRow = 1;
        $noJurnal = 'ROT/' . $dateStr . '/KAYU_KELUAR';

        $rows->push([
            'No. Jurnal: ' . $noJurnal, '', '', '', '', '', '', '', '', '', '', '', '', ''
        ]);
        $this->titleRows[] = $currentRow;
        $currentRow++;

        // 3. Header Row
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

        // 4. Data Rows
        $dataStart = $currentRow;
        $tglVal = Carbon::parse($this->tanggal)->format('d-m-Y');

        $totalBanyak = 0;
        $totalM3 = 0;
        $totalHarga = 0;

        // Pre-calculate totals
        foreach ($records as $record) {
            $totalBanyak += ($record->total_batang > 0 ? $record->total_batang : 0);
            $totalM3 += ($record->total_kubikasi > 0 ? $record->total_kubikasi : 0);
            $totalHarga += $record->nilai_stok;
        }
        $totalM3 = round($totalM3, 4);

        // 1. Add Debit row first: HPP Triplek
        if (!$records->isEmpty()) {
            $totalValHpp = "=IF(J{$currentRow}=\"m\",M{$currentRow}*L{$currentRow},IF(J{$currentRow}=\"b\",M{$currentRow}*K{$currentRow},M{$currentRow}))";

            $rows->push([
                'HPP Triplek',                                                // 1. Nama Akun
                $tglVal,                                                      // 2. tgl
                '',                                                           // 3. jurnal
                '6111.00',                                                    // 4. No Akun
                '',                                                           // 5. No
                '',                                                           // 6. mm
                'kayu habis',                                                 // 7. Nama
                '',                                                           // 8. Keterangan
                'd',                                                          // 9. map
                '',                                                           // 10. hit kbk
                $totalBanyak > 0 ? $totalBanyak : null,                       // 11. Banyak
                $totalM3 > 0 ? $totalM3 : null,                               // 12. M3
                $totalHarga,                                                  // 13. Harga
                $totalValHpp                                                  // 14. Total
            ]);
            $currentRow++;
        }

        // 2. Add Credit rows second
        foreach ($records as $record) {
            $jenisNama = $record->jenisKayu?->nama_kayu ?? '-';
            $acc = LaporanKayuKeluarExport::getAccountDetails($jenisNama, $record->panjang);
            $noAkun = $acc['no_akun'];
            $namaAkun = $acc['nama_akun'];

            $keteranganSpec = "lahan " . ($record->lahan->kode_lahan ?? '-');

            $banyak = $record->total_batang > 0 ? $record->total_batang : 0;
            $m3 = $record->total_kubikasi > 0 ? round($record->total_kubikasi, 4) : 0;
            $totalStokValue = $record->nilai_stok;

            $totalVal = "=IF(J{$currentRow}=\"m\",M{$currentRow}*L{$currentRow},IF(J{$currentRow}=\"b\",M{$currentRow}*K{$currentRow},M{$currentRow}))";

            $rows->push([
                $namaAkun,                                                    // 1. Nama Akun
                $tglVal,                                                      // 2. tgl
                '',                                                           // 3. jurnal
                $noAkun,                                                      // 4. No Akun
                '',                                                           // 5. No
                '',                                                           // 6. mm
                'kayu keluar',                                                // 7. Nama
                $keteranganSpec,                                              // 8. Keterangan
                'k',                                                          // 9. map
                '',                                                           // 10. hit kbk
                $banyak > 0 ? $banyak : null,                                 // 11. Banyak
                $m3 > 0 ? $m3 : null,                                         // 12. M3
                $totalStokValue,                                              // 13. Harga
                $totalVal                                                     // 14. Total
            ]);

            $currentRow++;
        }

        $dataEnd = $currentRow - 1;
        $this->dataRanges[] = [
            'start' => $dataStart,
            'end' => $dataEnd
        ];

        return $rows;
    }

    public function title(): string
    {
        return 'jurnal produksi';
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
                        'font' => [
                            'bold' => true,
                            'size' => 11,
                            'color' => ['argb' => 'FF1D2939']
                        ],
                        'fill' => [
                            'fillType' => Fill::FILL_SOLID,
                            'startColor' => ['argb' => 'FFD2E4F0']
                        ],
                        'borders' => [
                            'allBorders' => ['borderStyle' => Border::BORDER_THIN]
                        ]
                    ]);
                    $sheet->getRowDimension($row)->setRowHeight(25);
                }

                // Style Header Rows
                foreach ($this->headerRows as $row) {
                    $sheet->getStyle("A{$row}:N{$row}")->applyFromArray([
                        'font' => [
                            'bold' => true,
                            'size' => 10
                        ],
                        'alignment' => [
                            'horizontal' => Alignment::HORIZONTAL_CENTER,
                            'vertical' => Alignment::VERTICAL_CENTER,
                        ],
                        'fill' => [
                            'fillType' => Fill::FILL_SOLID,
                            'startColor' => ['argb' => 'FFE5E8EB']
                        ],
                        'borders' => [
                            'allBorders' => ['borderStyle' => Border::BORDER_THIN]
                        ]
                    ]);
                    $sheet->getRowDimension($row)->setRowHeight(25);
                }

                // Style Data Rows
                foreach ($this->dataRanges as $range) {
                    $start = $range['start'];
                    $end = $range['end'];
                    if ($start > $end) continue;

                    $sheet->getStyle("A{$start}:N{$end}")->applyFromArray([
                        'borders' => [
                            'allBorders' => ['borderStyle' => Border::BORDER_THIN]
                        ]
                    ]);

                    $sheet->getStyle("A{$start}:A{$end}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                    $sheet->getStyle("B{$start}:F{$end}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle("G{$start}:H{$end}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                    $sheet->getStyle("I{$start}:J{$end}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle("K{$start}:N{$end}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

                    // Number formats
                    $sheet->getStyle("K{$start}:K{$end}")->getNumberFormat()->setFormatCode('#,##0');
                    $sheet->getStyle("L{$start}:L{$end}")->getNumberFormat()->setFormatCode('#,##0.0000');
                    $sheet->getStyle("M{$start}:N{$end}")->getNumberFormat()->setFormatCode('#,##0');
                }

                // Column Widths
                $sheet->getColumnDimension('A')->setWidth(25);
                $sheet->getColumnDimension('B')->setWidth(15);
                $sheet->getColumnDimension('C')->setWidth(10);
                $sheet->getColumnDimension('D')->setWidth(15);
                $sheet->getColumnDimension('E')->setWidth(10);
                $sheet->getColumnDimension('F')->setWidth(10); // mm
                $sheet->getColumnDimension('G')->setWidth(20); // Nama / 'kayu keluar' / 'kayu habis'
                $sheet->getColumnDimension('H')->setWidth(30); // Keterangan (e.g. 'lahan A')
                $sheet->getColumnDimension('I')->setWidth(10); // map
                $sheet->getColumnDimension('J')->setWidth(12); // hit kbk
                $sheet->getColumnDimension('K')->setWidth(12); // Banyak
                $sheet->getColumnDimension('L')->setWidth(15); // M3
                $sheet->getColumnDimension('M')->setWidth(18); // Harga
                $sheet->getColumnDimension('N')->setWidth(18); // Total
            }
        ];
    }
}
