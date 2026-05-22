<?php

namespace App\Exports;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use App\Filament\Pages\LaporanRepairs\Queries\LoadLaporanRepairs;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

// ============================================================
// MAIN EXPORT CLASS
// ============================================================
class LaporanRepairExport implements WithMultipleSheets
{
    public function __construct(
        protected array  $detailData, // Array hasil RepairDataMap (untuk Sheet 1)
        protected string $tanggal     // String tanggal format 'Y-m-d' (untuk query Sheet 2)
    ) {}

    public function sheets(): array
    {
        // Sheet 2 query langsung ke DB, tidak lewat transformer!
        $rawCollection = LoadLaporanRepairs::run($this->tanggal);

        return [
            new LaporanRepairDetailSheet($this->detailData),
            new LaporanRepairSummarySheet($rawCollection),
        ];
    }
}

// ============================================================
// SHEET 1: DETAIL PER MEJA (UPDATE: TAMBAH KOLOM KETERANGAN)
// ============================================================
class LaporanRepairDetailSheet implements FromCollection, WithHeadings, WithTitle
{
    protected Collection $data;

    public function __construct(array $detailData)
    {
        $this->data = collect($detailData)
            ->groupBy(fn($item) => $item['nomor_meja'] . '|' . $item['kode_ukuran']);
    }

    public function collection()
    {
        $rows = collect();
        foreach ($this->data as $groupKey => $items) {
            $first        = $items->first();
            $targetPerJam = $first['jam_kerja'] > 0
                ? round($first['target'] / $first['jam_kerja'], 2)
                : 0;
            $pekerja      = $first['pekerja'] ?? [];

            $rows->push(['MEJA',        $first['nomor_meja']]);
            $rows->push(['UKURAN',      $first['ukuran']]);
            $rows->push(['JENIS KAYU',  $first['jenis_kayu']]);
            $rows->push(['KW',          $first['kw']]);
            $rows->push(['TANGGAL',     $first['tanggal']]);
            $rows->push([]);

            // 🚀 UPDATE HEADER TABEL: Menambahkan Keterangan Hasil & Kerja di samping Keterangan Absen lama
            $rows->push([
                'ID',
                'Nama',
                'Masuk',
                'Pulang',
                'Ijin',
                'Potongan Target',
                'Keterangan Absen',
                'Keterangan Hasil', // 👈 Kolom Baru
                'Keterangan Kerja', // 👈 Kolom Baru
                '',
                'Target Harian',
                'Jam Kerja',
                'Target / Jam',
                'Hasil',
                'Selisih'
            ]);

            foreach ($pekerja as $p) {
                $rows->push([
                    $p['id'] ?? '-',
                    $p['nama'] ?? '-',
                    $p['jam_masuk'] ?? '-',
                    $p['jam_pulang'] ?? '-',
                    $p['ijin'] ?? '-',
                    ($p['pot_target'] ?? 0) > 0 ? $p['pot_target'] : '-',
                    $p['keterangan'] ?? '-',       // Ini Keterangan Absen bawaan array Anda
                    $p['keterangan_hasil'] ?? '—', // 👈 Diambil langsung dari mapping data hasil pekerja
                    $p['keterangan_kerja'] ?? '—', // 👈 Diambil langsung dari mapping data rencana kerja pekerja
                    '',
                    $first['target'],
                    $first['jam_kerja'],
                    $targetPerJam,
                    $first['hasil'],
                    $first['selisih'] >= 0 ? '+' . $first['selisih'] : $first['selisih'],
                ]);
            }

            $totalPotongan = collect($pekerja)->sum('pot_target');
            $rows->push([
                'TOTAL',
                '',
                '',
                '',
                '',
                $totalPotongan,
                '',
                '', // Kosongkan kolom baru untuk baris TOTAL
                '', // Kosongkan kolom baru untuk baris TOTAL
                '',
                $first['target'],
                $first['jam_kerja'],
                $targetPerJam,
                $first['hasil'],
                $first['selisih'] >= 0 ? '+' . $first['selisih'] : $first['selisih'],
            ]);

            $rows->push([]);
            $rows->push([]);
        }

        return $rows;
    }

    public function headings(): array
    {
        return [];
    }
    public function title(): string
    {
        return 'Detail Per Meja';
    }
}

// ============================================================
// SHEET 2: SUMMARY — Bersih Seperti Semula
// ============================================================
class LaporanRepairSummarySheet implements FromCollection, WithHeadings, WithTitle, WithEvents
{
    private array $summary = [];

    private const MASTER_KW = ['1', '2', '3', '4', 'af'];

    public function __construct(protected $rawCollection)
    {
        $this->buildSummary();
    }

    private function buildSummary(): void
    {
        foreach ($this->rawCollection as $produksi) {
            $tanggal = Carbon::parse($produksi->tanggal)->format('d M');

            foreach ($produksi->modalRepairs as $modal) {
                $p = (float) ($modal->ukuran->panjang ?? 0);
                $l = (float) ($modal->ukuran->lebar   ?? 0);
                $t = (float) ($modal->ukuran->tebal   ?? 0);
                $jenis = strtoupper($modal->jenisKayu->kode_kayu ?? substr($modal->jenisKayu->nama_kayu ?? '-', 0, 1));
                $kwData = strtolower(trim($modal->kw ?? ''));

                $key = "{$jenis}|{$tanggal}|{$p}|{$l}|{$t}|{$kwData}";

                if (!isset($this->summary[$key])) {
                    $this->summary[$key] = [
                        'tanggal'     => $tanggal,
                        'p'           => $p,
                        'l'           => $l,
                        't'           => $t,
                        'jenis'       => $jenis,
                        'current_kw'  => $kwData,
                        'pekerja_ids' => [],
                    ];

                    foreach (self::MASTER_KW as $mKw) {
                        $this->summary[$key]['kw_' . $mKw] = 0;
                    }
                }

                $hasilModal = 0;
                foreach ($produksi->rencanaPegawais as $rp) {
                    if (!$rp->pegawai) continue;

                    $hasilIndividu = (int) $rp->rencanaRepairs
                        ->where('id_modal_repair', $modal->id)
                        ->flatMap->hasilRepairs
                        ->sum('jumlah');

                    if ($hasilIndividu > 0) {
                        $hasilModal += $hasilIndividu;
                        $this->summary[$key]['pekerja_ids'][] = $rp->pegawai->id;
                    }
                }

                if ($kwData !== '' && $hasilModal > 0) {
                    if (in_array($kwData, self::MASTER_KW)) {
                        $this->summary[$key]['kw_' . $kwData] += $hasilModal;
                    }
                }
            }
        }

        ksort($this->summary);
    }

    public function collection()
    {
        $rows = collect();
        $dataStart = 3;
        $totalMasterKw = count(self::MASTER_KW);
        $lastRow = $dataStart + count($this->summary) - 1;

        // Row 2: Grand Total
        $grandRow = ['', '', '', '', ''];
        for ($i = 0; $i < $totalMasterKw; $i++) {
            $colLetter = Coordinate::stringFromColumnIndex(6 + $i);
            $grandRow[] = "=SUM({$colLetter}{$dataStart}:{$colLetter}{$lastRow})";
        }

        $ttlPkjCol = Coordinate::stringFromColumnIndex(6 + $totalMasterKw);
        $grandRow[] = "=SUM({$ttlPkjCol}{$dataStart}:{$ttlPkjCol}{$lastRow})";

        $rows->push($grandRow);

        // Row 3+: Data Rows
        foreach ($this->summary as $s) {
            $row = [$s['tanggal'], $s['p'], $s['l'], $s['t'], $s['jenis']];

            foreach (self::MASTER_KW as $mKw) {
                $val = $s['kw_' . $mKw] ?? 0;
                $row[] = $val > 0 ? $val : '';
            }

            $uniquePekerja = count(array_unique($s['pekerja_ids']));
            $row[] = $uniquePekerja > 0 ? $uniquePekerja : '';
            $rows->push($row);
        }

        return $rows;
    }

    public function headings(): array
    {
        $heads = ['Tanggal', 'p', 'l', 't', 'jenis'];
        foreach (self::MASTER_KW as $mKw) {
            $heads[] = 'KW ' . strtoupper($mKw);
        }
        $heads[] = 'TTL PKJ';
        return $heads;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $lastCol = $sheet->getHighestColumn();
                $lastRow = $sheet->getHighestRow();

                // Style Header & Grand Total
                foreach (['1', '2'] as $rowNum) {
                    $color = ($rowNum == '1') ? 'BDD7EE' : 'FFFF00';
                    $sheet->getStyle("A{$rowNum}:{$lastCol}{$rowNum}")->applyFromArray([
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['ARGB' => $color]],
                        'font' => ['bold' => true],
                        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                    ]);
                }

                if ($lastRow >= 3) {
                    $sheet->getStyle("A3:{$lastCol}{$lastRow}")->applyFromArray([
                        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                    ]);
                }

                foreach (range('A', $lastCol) as $col) {
                    $sheet->getColumnDimension($col)->setAutoSize(true);
                }
            },
        ];
    }

    public function title(): string
    {
        return 'Summary Produksi';
    }
}
