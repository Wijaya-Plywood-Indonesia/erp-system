<?php

namespace App\Exports;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use App\Filament\Pages\LaporanJoin\Queries\LoadLaporanJoin;
use Illuminate\Support\Facades\Log;

// ============================================================
// MAIN EXPORT CLASS
// ============================================================
class LaporanJoinExport implements WithMultipleSheets
{
    public function __construct(
        protected array  $detailData, // flat array dari JoinDataMap (Sheet 1)
        protected string $tanggal     // format 'Y-m-d' (untuk query Sheet 2 & 3)
    ) {}

    public function sheets(): array
    {
        $rawCollection = LoadLaporanJoin::run($this->tanggal);

        return [
            new LaporanJoinDetailSheet($this->detailData),
            new LaporanJoinSummarySheet($rawCollection),
            new JurnalSheet($rawCollection), // Sheet 3: Jurnal Akuntansi dengan Hardcode Bahan Penolong
        ];
    }
}

// ============================================================
// SHEET 1: DETAIL PER MEJA (Logika bawaan Anda)
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
// SHEET 2: SUMMARY (Logika bawaan Anda)
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

        foreach ($this->rawCollection as $produksi) {
            $tanggal = Carbon::parse($produksi->tanggal_produksi)->format('dd-mm-yy');

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

                    $namaBahanTerbaca = $bahan->nama_bahan_penolong ?? $bahan->nama_bahan ?? '-';

                    $bahanRows[] = [
                        'nama'   => strtoupper($namaBahanTerbaca),
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

            $hasilGroups = $produksi->hasilJoint
                ->groupBy(fn($h) => $h->id_ukuran . '|' . $h->kw);

            foreach ($hasilGroups as $groupKey => $hasilItems) {
                $firstHasil  = $hasilItems->first();
                $ukuranModel = $firstHasil->ukuran;

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

        $grandTotalByk   = collect($allGroups)->sum('byk');
        $grandTotalTotal = collect($allGroups)->sum(
            fn($g) => collect($g['bahan'])->sum('total')
        );

        $rows->push([
            '',
            '',
            '',
            '',
            $grandTotalTotal > 0 ? number_format($grandTotalTotal, 3, '.', '') : 0,
            '',
            '',
            '',
            $grandTotalByk > 0 ? $grandTotalByk : 0,
            '',
        ]);

        $currentExcelRow = 3;

        foreach ($allGroups as $group) {
            $this->firstRowOfGroup[] = $currentExcelRow;

            foreach ($group['bahan'] as $i => $bahan) {
                $isFirst = ($i === 0);

                $rows->push([
                    $isFirst ? $group['tanggal'] : '',
                    $bahan['nama'],
                    $bahan['jumlah'],
                    $bahan['harga'] > 0 ? number_format($bahan['harga'], 3, '.', '') : '-',
                    $bahan['total'] > 0 ? number_format($bahan['total'], 3, '.', '') : 0,
                    $isFirst ? $group['p'] : '',
                    $isFirst ? $group['l'] : '',
                    $isFirst ? $group['t'] : '',
                    $isFirst ? $group['byk'] : '',
                    $isFirst ? $group['kw'] : '',
                ]);

                $currentExcelRow++;
            }

            $groupTotal            = collect($group['bahan'])->sum('total');
            $this->totalRows[]     = $currentExcelRow;

            $rows->push([
                '',
                'TOTAL :',
                '',
                '',
                $groupTotal > 0 ? number_format($groupTotal, 3, '.', '') : 0,
                '',
                '',
                '',
                $group['byk'],
                '',
            ]);

            $currentExcelRow++;
        }

        return $rows;
    }

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

                $sheet->getStyle('A1:J1')->applyFromArray([
                    'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['ARGB' => 'BDD7EE']],
                    'font'      => ['bold' => true],
                    'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                ]);

                $sheet->getStyle('A2:J2')->applyFromArray([
                    'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['ARGB' => 'FFFF00']],
                    'font'      => ['bold' => true],
                    'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                ]);

                if ($lastRow >= 3) {
                    $sheet->getStyle("A3:J{$lastRow}")->applyFromArray([
                        'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                    ]);
                }

                foreach ($this->totalRows as $rowNum) {
                    $sheet->getStyle("A{$rowNum}:J{$rowNum}")->applyFromArray([
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['ARGB' => 'FFF2CC']],
                        'font' => ['bold' => true],
                    ]);
                }

                $sheet->getStyle("A3:A{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                $sheet->getStyle("B3:B{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

                foreach (range('A', 'J') as $col) {
                    $sheet->getColumnDimension($col)->setAutoSize(true);
                }
            },
        ];
    }
}

// ============================================================
// SHEET 3: JURNAL — KODIFIKASI KOMA KUSTOM & LOCK DESIMAL
// ============================================================
class JurnalSheet implements FromArray, WithTitle, WithColumnWidths, WithStyles, WithColumnFormatting
{
    public function __construct(protected $rawCollection) {}

    public function title(): string
    {
        return 'Jurnal';
    }

    public function columnWidths(): array
    {
        return [
            'A' => 45, // Nama Akun
            'B' => 15, // tgl
            'C' => 12, // jurnal
            'D' => 12, // No Akun
            'E' => 8,  // No
            'F' => 8,  // mm
            'G' => 15, // Nama
            'H' => 45, // Keterangan
            'I' => 8,  // map
            'J' => 8,  // hit kbk
            'K' => 14, // Banyak
            'L' => 16, // M3
            'M' => 16, // Harga
            'N' => 22, // Total
        ];
    }

    public function columnFormats(): array
    {
        return [
            'K' => '#,##0',        // Banyak -> Langsung angka bulat murni (Tanpa ,00)
            'L' => '#,##0.0000',   // M3 -> Template Fix: 4 angka di belakang koma desimal
            'M' => '#,##0',        // Harga -> Langsung angka bulat murni (Tanpa ,00)
            'N' => '#,##0',        // Total -> Langsung angka bulat murni (Tanpa ,00)
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $lastRow = $sheet->getHighestRow();

        $sheet->getStyle('A1:N1')->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'], // Teks Putih Bersih
                'name' => 'Calibri',
                'size' => 11
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '9999FF'] // Warna Biru Khas Excel Modern (Steel Blue Accent)
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true // Otomatis turun baris jika teks kolom panjang
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'FFFFFF'] // Sekat putih antar kolom header agar terlihat clean
                ]
            ]
        ]);

        if ($lastRow > 1) {
            $sheet->getStyle("A2:N{$lastRow}")->applyFromArray([
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
            ]);
            $sheet->getStyle("D2:D{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("B2:G{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("I2:J{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("K2:N{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        }
    }

    private function normalizeJenis(string $jenis): string
    {
        $jns = strtolower(trim($jenis));
        return str_contains($jns, 'sengon') ? 'sengon' : 'meranti';
    }

    private function getHargaPatok(string $jenis, float $tebal): int
    {
        $jns = $this->normalizeJenis($jenis);
        $kelompok = ($tebal < 1) ? 'faceback' : 'core';

        $harga = [
            'sengon' => [
                'faceback' => 4000000,
                'core'     => 2250000,
            ],
            'meranti' => [
                'faceback' => 12500000,
                'core'     => 2800000,
            ]
        ];

        return $harga[$jns][$kelompok] ?? 0;
    }

    // 🚀 DITAMBAHKAN PARAMETER RUMUS DINAMIS $hitKbk DENGAN FORMAT DEFAULT 'm'
    private function makeRow($namaAkun, $tgl, $noAnakAkunKoma, $keterangan, $map, $banyak, $m3, $harga, $total, $hitKbk = 'm'): array
    {
        return [
            $namaAkun,                // A: Nama Akun
            $tgl,                     // B: tgl
            '',                       // C: jurnal
            (string) $noAnakAkunKoma, // D: No Akun (Teks murni format koma)
            '',                       // E: No
            '',                       // F: mm
            'nyambung',               // G: Nama
            $keterangan,              // H: Keterangan
            strtolower($map),         // I: map
            strtolower($hitKbk),      // J: hit kbk (Dinamis: m / b)
            $banyak > 0 ? (float) $banyak : 0,
            $m3 > 0     ? (float) $m3 : 0,
            $harga > 0  ? (float) $harga : 0,
            $total > 0  ? (float) $total : 0,
        ];
    }

    public function array(): array
    {
        $rows = [];
        $rows[] = ['Nama Akun', 'tgl', 'jurnal', 'No Akun', 'No', 'mm', 'Nama', 'Keterangan', 'map', 'hit kbk', 'Banyak', 'M3', 'Harga', 'Total'];

        foreach ($this->rawCollection as $produksi) {
            $tglFormat = Carbon::parse($produksi->tanggal_produksi)->format('dd-mm-yy');

            $totalDebit  = 0;
            $totalKredit = 0;
            $jurnalBlock = [];

            // ============================================================
            // 1. DEBIT: HasilJoint (Veneer Jadi)
            // ============================================================
            foreach ($produksi->hasilJoint as $hasil) {
                $ukuran = $hasil->ukuran;
                $jenisNama = $hasil->jenisKayu->nama_kayu ?? 'meranti';
                $jnsNorm = $this->normalizeJenis($jenisNama);

                $tebal = (float)($ukuran->tebal ?? 0);
                $tipeUkuran = ($tebal < 1) ? '260 face/back' : '130 core';

                $p = (float)($ukuran->panjang ?? 0);
                $l = (float)($ukuran->lebar ?? 0);
                $t = $tebal;
                $m3 = $p * $l * $t * $hasil->jumlah / 10000000;

                $hargaPatok = $this->getHargaPatok($jenisNama, $tebal);

                // 🚀 RUMUS EVALUASI: hit kbk == m -> m3 * harga
                $totalValue = $m3 * $hargaPatok;

                $noAkunKoma = ($jnsNorm === 'sengon') ? '1466,00' : '1467,00';
                $namaAkun = "Veneer Jadi {$jnsNorm} WJY";
                $keterangan = "130 core " . strtolower($jenisNama) . " {$ukuran->panjang} x {$ukuran->lebar} x {$ukuran->tebal}";

                // Sisi Hasil Jadi tetap hit kbk = 'm'
                $jurnalBlock[] = $this->makeRow($namaAkun, $tglFormat, $noAkunKoma, $keterangan, 'd', $hasil->jumlah, $m3, $hargaPatok, $totalValue, 'm');

                $totalDebit += $totalValue;
            }

            // ============================================================
            // 2. KREDIT: ModalJoint (Veneer Sortimen)
            // ============================================================
            foreach ($produksi->modalJoint as $modal) {
                $ukuran = $modal->ukuran;
                $jenisNama = $modal->jenisKayu->nama_kayu ?? 'meranti';
                $jnsNorm = $this->normalizeJenis($jenisNama);

                $tebal = (float)($ukuran->tebal ?? 0);
                $tipeUkuran = ($tebal < 1) ? '260 face/back' : '130 core';

                $p = (float)($ukuran->panjang ?? 0);
                $l = (float)($ukuran->lebar ?? 0);
                $t = $tebal;
                $m3 = $p * $l * $t * $modal->jumlah / 10000000;

                $hargaPatok = $this->getHargaPatok($jenisNama, $tebal);

                // 🚀 RUMUS EVALUASI: hit kbk == m -> m3 * harga
                $totalValue = $m3 * $hargaPatok;

                $noAkunKoma = ($jnsNorm === 'sengon') ? '1416,00' : '1417,00';
                $namaAkun = "Veneer Jadi {$jnsNorm} WJY";
                $keterangan = "130 core " . strtolower($jenisNama) . " {$ukuran->panjang} x {$ukuran->lebar} x {$ukuran->tebal}";

                // Sisi Sortimen tetap hit kbk = 'm'
                $jurnalBlock[] = $this->makeRow($namaAkun, $tglFormat, $noAkunKoma, $keterangan, 'k', $modal->jumlah, $m3, $hargaPatok, $totalValue, 'm');

                $totalKredit += $totalValue;
            }

            // ============================================================
            // 3. KREDIT: Bahan Pembantu / Lem Tepung (HARDCODED HARGA & NO AKUN)
            // ============================================================
            foreach ($produksi->bahanProduksi as $bahan) {
                $jumlahBahan = (float)($bahan->jumlah ?? 0);

                if ($jumlahBahan > 0) {
                    $namaBahanRaw = $bahan->nama_bahan ?? $bahan->nama_bahan_penolong ?? 'bahan';
                    $namaBahanLower = strtolower(trim($namaBahanRaw));

                    $hargaHardcode = 15000;
                    $noAkunKomaBahan = '1481,00';
                    $prefixLem = '';

                    if (str_contains($namaBahanLower, 'aruki')) {
                        $hargaHardcode = 152900;
                        $noAkunKomaBahan = '1507,63';
                        $prefixLem = 'Lem ';
                    } elseif (str_contains($namaBahanLower, 'dover') || str_contains($namaBahanLower, 'lem')) {
                        $hargaHardcode = 152900;
                        $noAkunKomaBahan = '1507,64';
                        $prefixLem = 'Lem ';
                    } elseif (str_contains($namaBahanLower, 'tepung')) {
                        $hargaHardcode = 18000;
                        $noAkunKomaBahan = '1507,62';
                        $prefixLem = '';
                    }

                    // 🚀 RUMUS EVALUASI: hit kbk == b -> banyak * harga
                    $totalBahan = $hargaHardcode * $jumlahBahan;

                    $labelDisplayBahan = $prefixLem . ucfirst($namaBahanLower) . ' WJY';

                    // Mapping hit kbk dialihkan menjadi 'b' sesuai instruksi Anda
                    $jurnalBlock[] = $this->makeRow(
                        $labelDisplayBahan,
                        $tglFormat,
                        $noAkunKomaBahan,
                        '',
                        'k',
                        $jumlahBahan,
                        0,
                        $hargaHardcode,
                        $totalBahan,
                        'b'
                    );
                    $totalKredit += $totalBahan;
                }
            }

            // ============================================================
            // 4. KREDIT: Upah Gaji Borongan (PegawaiJoint)
            // ============================================================
            $jumlahPekerja = (int)$produksi->pegawaiJoint->count();
            if ($jumlahPekerja > 0) {
                // 🚀 RUMUS EVALUASI: hit kbk == b -> banyak * harga (Pekerja * 150.000)
                $totalGaji = $jumlahPekerja * 150000;

                // Mapping hit kbk dialihkan menjadi 'b' sesuai instruksi Anda
                $jurnalBlock[] = $this->makeRow('Hutang Gaji', $tglFormat, '2400,00', '', 'k', $jumlahPekerja, 0, 150000, $totalGaji, 'b');

                $totalKredit += $totalGaji;
            }

            // ============================================================
            // 5. PENYEIMBANG BALANCE: HPP Produksi Jointer (Sisa Masuk ke Debet)
            // ============================================================
            // 🚀 RUMUS EVALUASI: Sisa selisih (Debet - Kredit) langsung dipaksa masuk ke HPP Debet ('d')
            $selisihHpp = $totalDebit - $totalKredit;
            if (round($selisihHpp, 2) != 0) {
                $jurnalBlock[] = $this->makeRow(
                    'hpp produksi jointer',
                    $tglFormat,
                    '6112,00',
                    '',
                    'd', // Dipaksa selalu masuk ke sisi debet sesuai permintaan
                    0,
                    0,
                    abs($selisihHpp),
                    abs($selisihHpp),
                    'm'
                );
            }

            foreach ($jurnalBlock as $row) {
                $rows[] = $row;
            }

            $rows[] = array_fill(0, 14, '');
        }

        return $rows;
    }
}
