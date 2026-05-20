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
use App\Filament\Pages\LaporanJoin\Queries\LoadLaporanJoin;

// ============================================================
// MAIN EXPORT CLASS
// ============================================================
class LaporanJoinExport implements WithMultipleSheets
{
    public function __construct(
        protected array  $detailData, // flat array dari JoinDataMap (Sheet 1)
        protected string $tanggal     // format 'Y-m-d' (untuk query Sheet 2)
    ) {}

    public function sheets(): array
    {
        // Sheet 2 query ulang langsung ke DB supaya data pasti fresh
        $rawCollection = LoadLaporanJoin::run($this->tanggal);

        return [
            new LaporanJoinDetailSheet($this->detailData),
            new LaporanJoinSummarySheet($rawCollection),
        ];
    }
}

// ============================================================
// SHEET 1: DETAIL PER MEJA (logika lama)
// ============================================================
class LaporanJoinDetailSheet implements FromCollection, WithHeadings, WithTitle
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
            $first   = $items->first();
            $pekerja = $first['pekerja'] ?? [];
            $target  = (int) $first['target'];
            $hasil   = (int) $first['hasil'];
            $selisih = (int) $first['selisih'];

            $rows->push(['MEJA / AREA',       $first['nomor_meja']]);
            $rows->push(['UKURAN',             $first['ukuran']]);
            $rows->push(['JENIS BARANG',       $first['jenis_kayu'] ?? '-']);
            $rows->push(['GRADE / KW',         $first['kw']]);
            $rows->push(['TANGGAL PRODUKSI',   $first['tanggal']]);
            $rows->push([]);

            $rows->push([
                'ID PEGAWAI',
                'Nama Lengkap',
                'Jam Masuk',
                'Jam Pulang',
                'Ijin',
                'Potongan Target',
                'Keterangan',
                '',
                'Target Harian',
                'Hasil Produksi',
                'Selisih',
            ]);

            foreach ($pekerja as $p) {
                $potongan = (int) ($p['pot_target'] ?? 0);
                $rows->push([
                    $p['id']         ?? '-',
                    $p['nama']       ?? '-',
                    $p['jam_masuk']  ?? '-',
                    $p['jam_pulang'] ?? '-',
                    $p['ijin']       ?? '-',
                    $potongan > 0 ? $potongan : '-',
                    $p['keterangan'] ?? '-',
                    '',
                    $target,
                    $hasil,
                    $selisih >= 0 ? '+' . $selisih : $selisih,
                ]);
            }

            $totalPotongan = collect($pekerja)->sum('pot_target');
            $rows->push([
                'TOTAL',
                count($pekerja) . ' Orang',
                '',
                '',
                '',
                $totalPotongan > 0 ? $totalPotongan : '-',
                '',
                '',
                $target,
                $hasil,
                $selisih >= 0 ? '+' . $selisih : $selisih,
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
// SHEET 2: SUMMARY — Layout per blok produksi
//
// Kolom: A=Tgl | B=BAHAN | C=BANYAK | D=HARGA | E=TOTAL | F=p | G=l | H=t | I=byk | J=kw
//
// Struktur per blok:
//   [Tgl] [LEM PAI]    [23] [5.467]   [125.741] [130] [68] [3.7] [1200] [af]
//   [   ] [HDR]        [-]  [1.000]   [0]        [  ] [  ] [   ] [    ] [  ]
//   [   ] [TEPUNG BGS] [5]  [4.995]   [24.975]   [  ] [  ] [   ] [    ] [  ]
//   [   ] [PEKERJA]    [6]  [115.000] [690.000]  [  ] [  ] [   ] [    ] [  ]
//   [   ] [TOTAL :]    [ ]  [       ] [840.716]  [  ] [  ] [   ] [1200] [  ]
// ============================================================
class LaporanJoinSummarySheet implements FromCollection, WithHeadings, WithTitle, WithEvents
{
    private array $totalRows       = [];
    private array $firstRowOfGroup = [];

    public function __construct(protected $rawCollection) {}

    public function collection()
    {
        $rows      = collect();
        $allGroups = [];

        // =======================================================
        // STEP 1: Build groups per produksi → per modalJoint (ukuran + kw)
        // Mengikuti logika JoinDataMap: hasilJoint difilter by id_ukuran
        // =======================================================
        foreach ($this->rawCollection as $produksi) {
            $tanggal = Carbon::parse($produksi->tanggal_produksi)->format('d/m/Y');

            // Bahan
            $bahanRows = [];
            try {
                foreach ($produksi->bahanProduksi ?? collect() as $bahan) {
                    $hargaSatuan = (float) (
                        $bahan->harga
                        ?? $bahan->bahanPenolong?->harga
                        ?? $bahan->bahan?->harga
                        ?? 0
                    );
                    $jumlah = (float) ($bahan->jumlah ?? 0);

                    $bahanRows[] = [
                        'nama'   => strtoupper($bahan->nama_bahan ?? '-'),
                        'jumlah' => $jumlah > 0 ? $jumlah : '-',
                        'harga'  => $hargaSatuan,
                        'total'  => $jumlah * $hargaSatuan,
                    ];
                }
            } catch (\Exception $e) {
            }

            $jumlahPekerja = (int) $produksi->pegawaiJoint->count();
            $bahanRows[] = [
                'nama'   => 'PEKERJA',
                'jumlah' => $jumlahPekerja > 0 ? $jumlahPekerja : '-',
                'harga'  => 0,
                'total'  => 0,
            ];

            // ✅ Langsung group hasilJoint per id_ukuran + kw
            $hasilGroups = $produksi->hasilJoint
                ->groupBy(fn($h) => $h->id_ukuran . '|' . $h->kw);

            foreach ($hasilGroups as $groupKey => $hasilItems) {
                $firstHasil  = $hasilItems->first();
                $ukuranModel = $firstHasil->ukuran; // relasi sudah di-load

                $byk = (int) $hasilItems->sum('jumlah');

                $allGroups[] = [
                    'tanggal' => $tanggal,
                    'p'       => $ukuranModel->panjang ?? '',
                    'l'       => $ukuranModel->lebar   ?? '',
                    't'       => $ukuranModel->tebal   ?? '',
                    'byk'     => $byk,
                    'kw'      => $firstHasil->kw ?? '-',
                    'bahan'   => $bahanRows,
                ];
            }
        }

        // =======================================================
        // STEP 2: Grand Total (Row 2)
        // =======================================================
        $grandTotalByk   = collect($allGroups)->sum('byk');
        $grandTotalTotal = collect($allGroups)->sum(
            fn($g) => collect($g['bahan'])->sum('total')
        );

        $rows->push([
            '',  // A
            '',  // B
            '',  // C
            '',  // D
            $grandTotalTotal > 0 ? number_format($grandTotalTotal, 3, '.', '') : 0, // E
            '',  // F - p
            '',  // G - l
            '',  // H - t
            $grandTotalByk > 0 ? $grandTotalByk : 0, // I - byk
            '',  // J - kw
        ]);

        // =======================================================
        // STEP 3: Push baris per grup
        // Row 1 = heading, Row 2 = grand total, data mulai Row 3
        // =======================================================
        $currentExcelRow = 3;

        foreach ($allGroups as $group) {
            $this->firstRowOfGroup[] = $currentExcelRow;

            foreach ($group['bahan'] as $i => $bahan) {
                $isFirst = ($i === 0);

                $rows->push([
                    $isFirst ? $group['tanggal'] : '',  // A - Tgl
                    $bahan['nama'],                      // B - BAHAN
                    $bahan['jumlah'],                    // C - BANYAK
                    $bahan['harga'] > 0
                        ? number_format($bahan['harga'], 3, '.', '')
                        : '-',                           // D - HARGA
                    $bahan['total'] > 0
                        ? number_format($bahan['total'], 3, '.', '')
                        : 0,                             // E - TOTAL
                    $isFirst ? $group['p'] : '',         // F - p
                    $isFirst ? $group['l'] : '',         // G - l
                    $isFirst ? $group['t'] : '',         // H - t
                    $isFirst ? $group['byk'] : '',       // I - byk
                    $isFirst ? $group['kw'] : '',        // J - kw
                ]);

                $currentExcelRow++;
            }

            // Baris TOTAL per grup
            $groupTotal            = collect($group['bahan'])->sum('total');
            $this->totalRows[]     = $currentExcelRow;

            $rows->push([
                '',        // A
                'TOTAL :', // B
                '',        // C
                '',        // D
                $groupTotal > 0 ? number_format($groupTotal, 3, '.', '') : 0, // E
                '',        // F
                '',        // G
                '',        // H
                $group['byk'], // I - byk diulang di baris TOTAL
                '',        // J
            ]);

            $currentExcelRow++;
        }

        return $rows;
    }

    // Kembali ke 10 kolom A-J (p, l, t terpisah lagi sesuai komentar asli)
    public function headings(): array
    {
        return ['Tgl', 'BAHAN', 'BANYAK', 'HARGA', 'TOTAL', 'p', 'l', 't', 'byk', 'kw'];
    }

    public function title(): string
    {
        return 'Summary Join';
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet   = $event->sheet->getDelegate();
                $lastRow = $sheet->getHighestRow();

                // Row 1: Header biru
                $sheet->getStyle('A1:J1')->applyFromArray([
                    'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['ARGB' => 'BDD7EE']],
                    'font'      => ['bold' => true],
                    'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                ]);

                // Row 2: Grand Total kuning terang
                $sheet->getStyle('A2:J2')->applyFromArray([
                    'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['ARGB' => 'FFFF00']],
                    'font'      => ['bold' => true],
                    'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                ]);

                // Row 3+: Border semua data
                if ($lastRow >= 3) {
                    $sheet->getStyle("A3:J{$lastRow}")->applyFromArray([
                        'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                    ]);
                }

                // Baris TOTAL tiap grup → kuning muda + bold
                foreach ($this->totalRows as $rowNum) {
                    $sheet->getStyle("A{$rowNum}:J{$rowNum}")->applyFromArray([
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['ARGB' => 'FFF2CC']],
                        'font' => ['bold' => true],
                    ]);
                }

                // Kolom A (Tgl) & B (BAHAN) → rata kiri
                $sheet->getStyle("A3:A{$lastRow}")
                    ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                $sheet->getStyle("B3:B{$lastRow}")
                    ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

                // Auto-size semua kolom
                foreach (range('A', 'J') as $col) {
                    $sheet->getColumnDimension($col)->setAutoSize(true);
                }
            },
        ];
    }
}
