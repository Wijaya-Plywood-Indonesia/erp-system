<?php

namespace App\Exports;

use App\Filament\Pages\LaporanJoin\Queries\LoadLaporanJoin;
use App\Models\JenisKayu;
use App\Models\ReferensiHargaProduksi;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

// ============================================================
// MAIN EXPORT CLASS
// ============================================================
class LaporanJoinExport implements WithMultipleSheets
{
    public function __construct(
        protected array $detailData, // hasil JoinDataMap::make() -> 1 elemen per meja (pekerja[] + items[])
        protected string $tanggal     // format 'Y-m-d' (untuk query Sheet 2 & 3)
    ) {}

    public function sheets(): array
    {
        $rawCollection = LoadLaporanJoin::run($this->tanggal);

        return [
            new LaporanJoinDetailSheet($this->detailData),
            new LaporanJoinSummarySheet($rawCollection),
            new JurnalSheet($rawCollection),   // Sheet 3: Jurnal lama (COA lama, per jenis kayu)
            new JurnalSheetV2($rawCollection), // Sheet 4: Jurnal baru (COA general)
        ];
    }
}

// ============================================================
// SHEET 1: DETAIL PER MEJA
// ============================================================
class LaporanJoinDetailSheet implements FromCollection, WithHeadings, WithTitle
{
    protected Collection $data;

    public function __construct(array $detailData)
    {
        $this->data = collect($detailData);
    }

    public function collection()
    {
        $rows = collect();

        foreach ($this->data as $meja) {
            $pekerja = $meja['pekerja'] ?? [];
            $items = $meja['items'] ?? [];

            $rows->push(['MEJA / AREA',           $meja['nomor_meja'] ?? '-']);
            $rows->push(['TANGGAL PRODUKSI',       $meja['tanggal'] ?? '-']);
            $rows->push(['CAPAIAN GLOBAL TIM (%)', number_format($meja['capaian_global_persen'] ?? 0, 1, ',', '.')]);
            $rows->push(['POTONGAN TOTAL TIM',      'Rp '.number_format($meja['potongan_total_tim'] ?? 0)]);
            $rows->push([]);

            $rows->push(['DATA PEKERJA']);
            $rows->push([
                'ID Pegawai', 'Nama Lengkap', 'Jam Masuk', 'Jam Pulang',
                'Jam Aktual (jam)', 'Ijin', 'Potongan Target', 'Keterangan',
            ]);

            foreach ($pekerja as $p) {
                $potongan = (int) ($p['pot_target'] ?? 0);
                $rows->push([
                    $p['id'] ?? '-',
                    $p['nama'] ?? '-',
                    $p['jam_masuk'] ?? '-',
                    $p['jam_pulang'] ?? '-',
                    isset($p['jam_aktual_bersih']) ? number_format($p['jam_aktual_bersih'], 2, ',', '.') : '-',
                    $p['ijin'] ?? '-',
                    $potongan > 0 ? $potongan : '-',
                    $p['keterangan'] ?? '-',
                ]);
            }

            $totalPotongan = collect($pekerja)->sum('pot_target');
            $rows->push([
                'TOTAL', count($pekerja).' Orang', '', '', '', '',
                $totalPotongan > 0 ? $totalPotongan : '-', '',
            ]);

            $rows->push([]);

            $rows->push(['DATA BARANG DIKERJAKAN']);
            $rows->push([
                'Ukuran', 'Jenis Kayu', 'KW', 'Hasil', 'Target', 'Selisih', 'Capaian (%)',
            ]);

            foreach ($items as $item) {
                $rows->push([
                    $item['ukuran'] ?? '-',
                    $item['jenis_kayu'] ?? '-',
                    $item['kw'] ?? '-',
                    $item['hasil'] ?? 0,
                    ($item['has_target'] ?? false) ? number_format($item['target'], 2, ',', '.') : '-',
                    ($item['has_target'] ?? false) ? (($item['selisih'] >= 0 ? '+' : '').number_format($item['selisih'], 2, ',', '.')) : '-',
                    ($item['has_target'] ?? false) ? number_format($item['capaian_persen'], 1, ',', '.') : 'Target ?',
                ]);
            }

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
// SHEET 2: SUMMARY
// ============================================================
class LaporanJoinSummarySheet implements FromCollection, WithEvents, WithHeadings, WithTitle
{
    private array $totalRows = [];

    private array $firstRowOfGroup = [];

    public function __construct(protected $rawCollection) {}

    public function collection()
    {
        $rows = collect();
        $blocks = [];

        $grandTotalTotal = 0;
        $grandTotalByk = 0;

        foreach ($this->rawCollection as $produksi) {
            $tanggal = Carbon::parse($produksi->tanggal_produksi)->format('d-m-Y');

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
                        'nama' => strtoupper($namaBahanTerbaca),
                        'jumlah' => $jumlah > 0 ? $jumlah : '-',
                        'harga' => $hargaSatuan,
                        'total' => $jumlah * $hargaSatuan,
                    ];
                }
            } catch (\Exception $e) {
            }

            $jumlahPekerja = (int) $produksi->pegawaiJoint->count();
            $bahanRows[] = [
                'nama' => 'PEKERJA',
                'jumlah' => $jumlahPekerja > 0 ? $jumlahPekerja : '-',
                'harga' => 0,
                'total' => 0,
            ];

            $hasilGroups = $produksi->hasilJoint
                ->groupBy(fn ($h) => $h->id_ukuran.'|'.$h->kw);

            $hasilRows = [];
            foreach ($hasilGroups as $groupKey => $hasilItems) {
                $firstHasil = $hasilItems->first();
                $ukuranModel = $firstHasil->ukuran;
                $byk = (int) $hasilItems->sum('jumlah');
                $hasilRows[] = [
                    'p' => $ukuranModel->panjang ?? '',
                    'l' => $ukuranModel->lebar ?? '',
                    't' => $ukuranModel->tebal ?? '',
                    'byk' => $byk,
                    'kw' => $firstHasil->kw ?? '-',
                ];
            }

            $maxRows = max(count($bahanRows), count($hasilRows));
            $blockRows = [];
            $totalBahanForBlock = 0;
            $totalHasilForBlock = 0;

            for ($i = 0; $i < $maxRows; $i++) {
                $row = [
                    'tanggal' => ($i === 0) ? $tanggal : '',
                    'bahan_nama' => '',
                    'bahan_jumlah' => '',
                    'bahan_harga' => '',
                    'bahan_total' => '',
                    'p' => '',
                    'l' => '',
                    't' => '',
                    'byk' => '',
                    'kw' => '',
                ];

                if ($i < count($bahanRows)) {
                    $b = $bahanRows[$i];
                    $row['bahan_nama'] = $b['nama'];
                    $row['bahan_jumlah'] = $b['jumlah'];
                    $row['bahan_harga'] = $b['harga'] > 0 ? number_format($b['harga'], 3, '.', '') : '-';
                    $row['bahan_total'] = $b['total'] > 0 ? number_format($b['total'], 3, '.', '') : 0;
                    $totalBahanForBlock += $b['total'];
                }

                if ($i < count($hasilRows)) {
                    $h = $hasilRows[$i];
                    $row['p'] = $h['p'];
                    $row['l'] = $h['l'];
                    $row['t'] = $h['t'];
                    $row['byk'] = $h['byk'];
                    $row['kw'] = $h['kw'];
                    $totalHasilForBlock += $h['byk'];
                }

                $blockRows[] = $row;
            }

            $blocks[] = [
                'rows' => $blockRows,
                'totalBahan' => $totalBahanForBlock,
                'totalHasil' => $totalHasilForBlock,
            ];

            $grandTotalTotal += $totalBahanForBlock;
            $grandTotalByk += $totalHasilForBlock;
        }

        $rows->push([
            '', '', '', '',
            $grandTotalTotal > 0 ? number_format($grandTotalTotal, 3, '.', '') : 0,
            '', '', '',
            $grandTotalByk > 0 ? $grandTotalByk : 0,
            '',
        ]);

        $currentExcelRow = 3;
        foreach ($blocks as $block) {
            $this->firstRowOfGroup[] = $currentExcelRow;

            foreach ($block['rows'] as $row) {
                $rows->push([
                    $row['tanggal'], $row['bahan_nama'], $row['bahan_jumlah'], $row['bahan_harga'], $row['bahan_total'],
                    $row['p'], $row['l'], $row['t'], $row['byk'], $row['kw'],
                ]);
                $currentExcelRow++;
            }

            $this->totalRows[] = $currentExcelRow;
            $rows->push([
                '', 'TOTAL :', '', '',
                $block['totalBahan'] > 0 ? number_format($block['totalBahan'], 3, '.', '') : 0,
                '', '', '',
                $block['totalHasil'], '',
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
                $sheet = $event->sheet->getDelegate();
                $lastRow = $sheet->getHighestRow();

                $sheet->getStyle('A1:J1')->applyFromArray([
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['ARGB' => 'BDD7EE']],
                    'font' => ['bold' => true],
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                ]);

                $sheet->getStyle('A2:J2')->applyFromArray([
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['ARGB' => 'FFFF00']],
                    'font' => ['bold' => true],
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                ]);

                if ($lastRow >= 3) {
                    $sheet->getStyle("A3:J{$lastRow}")->applyFromArray([
                        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
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
// SHEET 3: JURNAL LAMA — COA LAMA, PER JENIS KAYU (TIDAK BERUBAH)
// ============================================================
class JurnalSheet implements FromArray, WithColumnFormatting, WithColumnWidths, WithStyles, WithTitle
{
    public function __construct(protected $rawCollection) {}

    public function title(): string
    {
        return 'jurnal produksi';
    }

    public function columnWidths(): array
    {
        return [
            'A' => 45, 'B' => 15, 'C' => 12, 'D' => 12, 'E' => 8, 'F' => 8,
            'G' => 15, 'H' => 45, 'I' => 8, 'J' => 8, 'K' => 14, 'L' => 16, 'M' => 16, 'N' => 22,
        ];
    }

    public function columnFormats(): array
    {
        return [
            'D' => '0.00',
            'K' => '#,##0',
            'L' => '#,##0.0000',
            'M' => '#,##0.00',
            'N' => '#,##0',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $lastRow = $sheet->getHighestRow();
        $sheet->getStyle('A1:N1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'name' => 'Calibri', 'size' => 11],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '9999FF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'FFFFFF']]],
        ]);

        if ($lastRow > 1) {
            $sheet->getStyle("A2:N{$lastRow}")->applyFromArray(['borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]]);
            $sheet->getStyle("D2:D{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("K2:N{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

            for ($row = 2; $row <= $lastRow; $row++) {
                $namaAkunVal = $sheet->getCell("A{$row}")->getValue();
                if ($namaAkunVal !== '' && $namaAkunVal !== null) {
                    $sheet->getCell("N{$row}")->setValue(
                        "=IF(J{$row}=\"m\",M{$row}*L{$row},IF(J{$row}=\"b\",M{$row}*K{$row},M{$row}))"
                    );
                }
            }
        }
    }

    private function normalizeJenis(string $jenis): string
    {
        return str_contains(strtolower(trim($jenis)), 'sengon') ? 'sengon' : 'meranti';
    }

    private function getHargaPatok(string $jenis, float $tebal, bool $isAf = false): int
    {
        $jns = $this->normalizeJenis($jenis);

        $dbHarga = $this->getHargaVeneerDb($jenis, $tebal, 'jadi', $isAf);
        if ($dbHarga > 0) {
            return $dbHarga;
        }

        if ($isAf) {
            return ($jns === 'sengon') ? 1500000 : 1800000;
        }

        $kelompok = ($tebal < 1) ? 'faceback' : 'core';
        $harga = [
            'sengon' => ['faceback' => 4000000, 'core' => 2250000],
            'meranti' => ['faceback' => 12500000, 'core' => 2800000],
        ];

        return $harga[$jns][$kelompok] ?? 0;
    }

    private function getHargaVeneerDb(string $jenis, float $tebal, string $tipeKualitas, bool $isAf = false): int
    {
        $jns = str_contains(strtolower(trim($jenis)), 'sengon') ? 'Sengon' : 'Meranti';
        $jenisKayu = JenisKayu::where('nama_kayu', $jns)->first();
        if (! $jenisKayu) {
            return 0;
        }

        if ($isAf) {
            $kelompok = ($tebal < 1) ? 'ppc_faceback' : 'ppc_core';
        } else {
            $kelompok = ($tebal < 1) ? 'faceback' : 'core';
        }

        $ukuranOptions = $kelompok === 'faceback'
            ? ($jns === 'Sengon' ? ['faceback'] : ['face', 'back'])
            : ($kelompok === 'ppc_faceback' ? ['ppc_faceback'] : [$kelompok]);

        $kwOptions = array_map(function ($opt) {
            return 'KW 1 - '.ucfirst(str_replace('_', ' ', $opt));
        }, $ukuranOptions);

        $tipeKualitasMap = [
            'basah' => 'Veneer Basah',
            'kering' => 'Veneer Kering',
            'jadi' => 'Veneer Jadi',
        ];
        $jenisBarang = $tipeKualitasMap[strtolower($tipeKualitas)] ?? 'Veneer Jadi';

        $hargaVeneer = ReferensiHargaProduksi::where('id_jenis_kayu', $jenisKayu->id)
            ->whereHas('kategoriBarang', function ($query) use ($jenisBarang) {
                $query->where('nama_kategori', $jenisBarang);
            })
            ->whereIn('nama', $kwOptions)
            ->first();

        if (! $hargaVeneer) {
            return 0;
        }

        return (int) $hargaVeneer->harga;
    }

    private function makeRow($namaAkun, $tgl, $noAkun, $keterangan, $map, $banyak, $m3, $harga, $total, $hitKbk = 'm'): array
    {
        return [
            $namaAkun, (string) $tgl, '', (string) $noAkun, '', '', 'nyambung', $keterangan,
            strtolower($map), strtolower($hitKbk), (float) $banyak, (float) $m3, (float) $harga, (float) $total,
        ];
    }

    public function array(): array
    {
        $rows = [];
        $rows[] = ['Nama Akun', 'tgl', 'jurnal', 'No Akun', 'No', 'mm', 'Nama', 'Keterangan', 'map', 'hit kbk', 'Banyak', 'M3', 'Harga', 'Total'];

        foreach ($this->rawCollection as $produksi) {
            $tglFormat = Carbon::parse($produksi->tanggal_produksi)->format('d-m-Y');
            $totalDebit = 0;
            $totalKredit = 0;
            $jurnalBlock = [];

            foreach ($produksi->hasilJoint as $hasil) {
                $ukuran = $hasil->ukuran;
                $jnsNorm = $this->normalizeJenis($hasil->jenisKayu->nama_kayu ?? '');
                $isAf = str_contains(strtolower($hasil->kw ?? ''), 'af');

                $noAkun = $isAf ? '1472.00' : ($jnsNorm === 'sengon' ? '1466.00' : '1467.00');
                $namaAkun = $isAf ? 'Veneer Jadi ppc '.strtolower(ucfirst($jnsNorm)).' WJY' : 'Veneer Jadi 130 core '.strtolower(ucfirst($jnsNorm)).' WJY';
                $keterangan = $isAf ? 'af '.strtolower($hasil->jenisKayu->nama_kayu ?? '').' '.$ukuran->panjang.' x '.$ukuran->lebar.' x '.$ukuran->tebal
                    : '130 core '.strtolower($hasil->jenisKayu->nama_kayu ?? '').' uk '.$ukuran->panjang.' x '.$ukuran->lebar.' x '.$ukuran->tebal;
                $m3 = ($ukuran->panjang * $ukuran->lebar * $ukuran->tebal * $hasil->jumlah) / 10000000;
                $hargaPatok = $this->getHargaPatok($jnsNorm, (float) $ukuran->tebal, $isAf);
                $totalValue = $m3 * $hargaPatok;

                $jurnalBlock[] = $this->makeRow($namaAkun, $tglFormat, $noAkun, $keterangan, 'd', $hasil->jumlah, $m3, $hargaPatok, $totalValue, 'm');
                $totalDebit += $totalValue;
            }

            foreach ($produksi->modalJoint as $modal) {
                $ukuran = $modal->ukuran;
                $jnsNorm = $this->normalizeJenis($modal->jenisKayu->nama_kayu ?? '');
                $isAf = str_contains(strtolower($modal->kw ?? ''), 'af');
                $noAkun = $isAf ? '1472.00' : ($jnsNorm === 'sengon' ? '1466.00' : '1467.00');
                $namaAkun = $isAf ? 'Veneer Jadi ppc '.strtolower(ucfirst($jnsNorm)).' WJY' : 'Veneer Jadi 130 core '.strtolower(ucfirst($jnsNorm)).' WJY';
                $keterangan = $isAf ? 'af '.strtolower($modal->jenisKayu->nama_kayu ?? '').' '.$ukuran->panjang.' x '.$ukuran->lebar.' x '.$ukuran->tebal
                    : '130 core '.strtolower($modal->jenisKayu->nama_kayu ?? '').' uk '.$ukuran->panjang.' x '.$ukuran->lebar.' x '.$ukuran->tebal;
                $m3 = ($ukuran->panjang * $ukuran->lebar * $ukuran->tebal * $modal->jumlah) / 10000000;
                $hargaPatok = $this->getHargaPatok($jnsNorm, (float) $ukuran->tebal, $isAf);
                $totalValue = $m3 * $hargaPatok;

                $jurnalBlock[] = $this->makeRow($namaAkun, $tglFormat, $noAkun, $keterangan, 'k', $modal->jumlah, $m3, $hargaPatok, $totalValue, 'm');
                $totalKredit += $totalValue;
            }

            foreach ($produksi->bahanProduksi as $bahan) {
                $jumlah = (float) ($bahan->jumlah ?? 0);
                if ($jumlah > 0) {
                    $namaBahanRaw = $bahan->nama_bahan ?? $bahan->nama_bahan_penolong ?? 'bahan';
                    $nama = strtolower(trim($namaBahanRaw));

                    $hargaH = 15000;
                    $akun = '1481.00';
                    $prefix = '';

                    if (str_contains($nama, 'aruki')) {
                        $hargaH = 6900;
                        $akun = '1507.63';
                        $prefix = 'Lem ';
                    } elseif (str_contains($nama, 'dover')) {
                        $hargaH = 6950;
                        $akun = '1507.64';
                        $prefix = 'Lem ';
                    } elseif (str_contains($nama, 'tepung')) {
                        $hargaH = 4500;
                        $akun = '1507.62';
                        $prefix = '';
                    }

                    $total = $hargaH * $jumlah;
                    $namaAkun = $prefix.ucfirst($nama).' WJY';

                    $jurnalBlock[] = $this->makeRow($namaAkun, $tglFormat, $akun, '', 'k', $jumlah, 0, $hargaH, $total, 'b');
                    $totalKredit += $total;
                }
            }

            $jmlPekerja = (int) $produksi->pegawaiJoint->count();
            if ($jmlPekerja > 0) {
                $jurnalBlock[] = $this->makeRow('Hutang Gaji', $tglFormat, '2231.00', '', 'k', $jmlPekerja, 0, 150000, ($jmlPekerja * 150000), 'b');
                $totalKredit += ($jmlPekerja * 150000);
            }

            $selisih = $totalDebit - $totalKredit;
            if (round($selisih, 2) != 0) {
                $jurnalBlock[] = $this->makeRow('hpp triplek', $tglFormat, '6111.00', '', 'd', 0, 0, abs($selisih), abs($selisih), '');
            }

            foreach ($jurnalBlock as $row) {
                $rows[] = $row;
            }
            $rows[] = array_fill(0, 14, '');
        }

        return $rows;
    }
}

// ============================================================
// SHEET 4: JURNAL BARU — COA GENERAL (TANPA SPLIT JENIS KAYU)
// ------------------------------------------------------------
// Perbedaan dari JurnalSheet (COA lama):
// - Veneer jadi TIDAK dipecah per jenis kayu (sengon/meranti), semua
//   masuk ke satu akun sesuai kelompok face-back / core / ppc.
// - Nomor & nama akun mengikuti daftar COA baru (1402.x, 2195.x, 5069.x).
// - Bahan penolong yang tidak dikenali (bukan lem/tepung/hardner/dst)
//   fallback ke akun umum 1402.0 Persediaan Bahan Baku.
// - Baris selisih debit-kredit pakai 5069.2 Selisih harga patok produksi.
// - Perhitungan harga patok / harga bahan / m3 tetap memakai logic yang
//   sama seperti JurnalSheet (tidak diubah), hanya akun & nama akun yang
//   di-general-kan.
// ============================================================
class JurnalSheetV2 implements FromArray, WithColumnFormatting, WithColumnWidths, WithStyles, WithTitle
{
    public function __construct(protected $rawCollection) {}

    public function title(): string
    {
        return 'jurnal produksi v2';
    }

    public function columnWidths(): array
    {
        return [
            'A' => 45, 'B' => 15, 'C' => 12, 'D' => 12, 'E' => 8, 'F' => 8,
            'G' => 15, 'H' => 45, 'I' => 8, 'J' => 8, 'K' => 14, 'L' => 16, 'M' => 16, 'N' => 22,
        ];
    }

    public function columnFormats(): array
    {
        return [
            'D' => '@',              // No Akun sebagai TEKS -> titik desimal tidak pernah dikonversi jadi koma
            'K' => '#,##0',
            'L' => '#,##0.0000',
            'M' => '#,##0.00',
            'N' => '#,##0',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $lastRow = $sheet->getHighestRow();
        $sheet->getStyle('A1:N1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'name' => 'Calibri', 'size' => 11],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '99CC99']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'FFFFFF']]],
        ]);

        if ($lastRow > 1) {
            $sheet->getStyle("A2:N{$lastRow}")->applyFromArray(['borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]]);
            $sheet->getStyle("D2:D{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("K2:N{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

            for ($row = 2; $row <= $lastRow; $row++) {
                // Paksa No Akun (kolom D) sebagai string eksplisit, supaya Excel
                // tidak auto-convert "1402.8" jadi angka lalu ditampilkan "1402,80".
                $noAkunVal = $sheet->getCell("D{$row}")->getValue();
                if ($noAkunVal !== null && $noAkunVal !== '') {
                    $sheet->getCell("D{$row}")->setValueExplicit((string) $noAkunVal, DataType::TYPE_STRING);
                }

                $namaAkunVal = $sheet->getCell("A{$row}")->getValue();
                if ($namaAkunVal !== '' && $namaAkunVal !== null) {
                    $sheet->getCell("N{$row}")->setValue(
                        "=IF(J{$row}=\"m\",M{$row}*L{$row},IF(J{$row}=\"b\",M{$row}*K{$row},M{$row}))"
                    );
                }
            }
        }
    }

    private function normalizeJenis(string $jenis): string
    {
        return str_contains(strtolower(trim($jenis)), 'sengon') ? 'sengon' : 'meranti';
    }

    // --- Logic harga tetap sama seperti JurnalSheet (tidak diubah) ---
    private function getHargaPatok(string $jenis, float $tebal, bool $isAf = false): int
    {
        $jns = $this->normalizeJenis($jenis);

        $dbHarga = $this->getHargaVeneerDb($jenis, $tebal, 'jadi', $isAf);
        if ($dbHarga > 0) {
            return $dbHarga;
        }

        if ($isAf) {
            return ($jns === 'sengon') ? 1500000 : 1800000;
        }

        $kelompok = ($tebal < 1) ? 'faceback' : 'core';
        $harga = [
            'sengon' => ['faceback' => 4000000, 'core' => 2250000],
            'meranti' => ['faceback' => 12500000, 'core' => 2800000],
        ];

        return $harga[$jns][$kelompok] ?? 0;
    }

    private function getHargaVeneerDb(string $jenis, float $tebal, string $tipeKualitas, bool $isAf = false): int
    {
        $jns = str_contains(strtolower(trim($jenis)), 'sengon') ? 'Sengon' : 'Meranti';
        $jenisKayu = JenisKayu::where('nama_kayu', $jns)->first();
        if (! $jenisKayu) {
            return 0;
        }

        if ($isAf) {
            $kelompok = ($tebal < 1) ? 'ppc_faceback' : 'ppc_core';
        } else {
            $kelompok = ($tebal < 1) ? 'faceback' : 'core';
        }

        $ukuranOptions = $kelompok === 'faceback'
            ? ($jns === 'Sengon' ? ['faceback'] : ['face', 'back'])
            : ($kelompok === 'ppc_faceback' ? ['ppc_faceback'] : [$kelompok]);

        $kwOptions = array_map(function ($opt) {
            return 'KW 1 - '.ucfirst(str_replace('_', ' ', $opt));
        }, $ukuranOptions);

        $tipeKualitasMap = [
            'basah' => 'Veneer Basah',
            'kering' => 'Veneer Kering',
            'jadi' => 'Veneer Jadi',
        ];
        $jenisBarang = $tipeKualitasMap[strtolower($tipeKualitas)] ?? 'Veneer Jadi';

        $hargaVeneer = ReferensiHargaProduksi::where('id_jenis_kayu', $jenisKayu->id)
            ->whereHas('kategoriBarang', function ($query) use ($jenisBarang) {
                $query->where('nama_kategori', $jenisBarang);
            })
            ->whereIn('nama', $kwOptions)
            ->first();

        if (! $hargaVeneer) {
            return 0;
        }

        return (int) $hargaVeneer->harga;
    }

    /**
     * Menentukan akun & nama akun Persediaan Veneer Jadi sesuai COA baru.
     * Sengon & meranti DIGABUNG -> hanya dibedakan face-back / core / ppc.
     */
    private function getAkunVeneerJadi(float $tebal, bool $isAf): array
    {
        if ($isAf) {
            return ['1402.18', 'Persediaan Veneer Jadi PPC'];
        }

        return ($tebal < 1)
            ? ['1402.7', 'Persediaan Veneer Jadi Face Back']
            : ['1402.8', 'Persediaan Veneer Jadi Core'];
    }

    /**
     * Menentukan akun bahan penolong sesuai COA baru.
     * Default fallback: 1402.0 Persediaan Bahan Baku (jika tidak match keyword manapun).
     */
    private function getAkunBahan(string $namaBahan): array
    {
        $nama = strtolower(trim($namaBahan));

        // Nama akun di sini HARUS sama persis (termasuk huruf besar/kecil)
        // dengan daftar COA baru yang diberikan.
        return match (true) {
            str_contains($nama, 'aruki'), str_contains($nama, 'dover'), str_contains($nama, 'lem') => ['1402.9', 'Persediaan Lem'],
            str_contains($nama, 'hardner') => ['1402.10', 'Persediaan Hardner'],
            str_contains($nama, 'staples') => ['1402.11', 'Persediaan Isi Staples'],
            str_contains($nama, 'warna') => ['1402.12', 'Persediaan pewarna'],
            str_contains($nama, 'tepung') => ['1402.13', 'Persediaan Tepung'],
            str_contains($nama, 'solasi') && str_contains($nama, 'coklat') => ['1402.14', 'Persediaan Solasi Coklat'],
            str_contains($nama, 'solasi') && str_contains($nama, 'putih') => ['1402.15', 'Persediaan Solasi Putih'],
            str_contains($nama, 'reeling') => ['1402.23', 'Persediaan Reeling Tape'],
            default => ['1402.0', 'Persediaan Bahan Baku'],
        };
    }

    // Harga satuan per bahan tetap sama seperti sebelumnya (default 15000,
    // override untuk aruki/dover/tepung). Silakan sesuaikan bila ada
    // harga bahan lain yang perlu di-hardcode juga.
    private function getHargaBahan(string $namaBahan): int
    {
        $nama = strtolower(trim($namaBahan));

        if (str_contains($nama, 'aruki')) {
            return 6900;
        }
        if (str_contains($nama, 'dover')) {
            return 6950;
        }
        if (str_contains($nama, 'tepung')) {
            return 4500;
        }

        return 15000;
    }

    private function makeRow($namaAkun, $tgl, $noAkun, $keterangan, $map, $banyak, $m3, $harga, $total, $hitKbk = 'm'): array
    {
        return [
            $namaAkun, (string) $tgl, '', (string) $noAkun, '', '', 'nyambung', $keterangan,
            strtolower($map), strtolower($hitKbk), (float) $banyak, (float) $m3, (float) $harga, (float) $total,
        ];
    }

    public function array(): array
    {
        $rows = [];
        $rows[] = ['Nama Akun', 'tgl', 'jurnal', 'No Akun', 'No', 'mm', 'Nama', 'Keterangan', 'map', 'hit kbk', 'Banyak', 'M3', 'Harga', 'Total'];

        foreach ($this->rawCollection as $produksi) {
            $tglFormat = Carbon::parse($produksi->tanggal_produksi)->format('d-m-Y');
            $totalDebit = 0;
            $totalKredit = 0;
            $jurnalBlock = [];

            // 1. DEBIT: Hasil (veneer jadi, akun digabung tanpa split jenis kayu)
            foreach ($produksi->hasilJoint as $hasil) {
                $ukuran = $hasil->ukuran;
                $jnsNorm = $this->normalizeJenis($hasil->jenisKayu->nama_kayu ?? '');
                $isAf = str_contains(strtolower($hasil->kw ?? ''), 'af');

                [$noAkun, $namaAkun] = $this->getAkunVeneerJadi((float) $ukuran->tebal, $isAf);
                $keterangan = ($isAf ? 'af ' : '130 ').strtolower($hasil->jenisKayu->nama_kayu ?? '').' uk '.$ukuran->panjang.' x '.$ukuran->lebar.' x '.$ukuran->tebal;

                $m3 = ($ukuran->panjang * $ukuran->lebar * $ukuran->tebal * $hasil->jumlah) / 10000000;
                $hargaPatok = $this->getHargaPatok($jnsNorm, (float) $ukuran->tebal, $isAf);
                $totalValue = $m3 * $hargaPatok;

                $jurnalBlock[] = $this->makeRow($namaAkun, $tglFormat, $noAkun, $keterangan, 'd', $hasil->jumlah, $m3, $hargaPatok, $totalValue, 'm');
                $totalDebit += $totalValue;
            }

            // 2. KREDIT: Modal (sama seperti hasil, akun digabung)
            foreach ($produksi->modalJoint as $modal) {
                $ukuran = $modal->ukuran;
                $jnsNorm = $this->normalizeJenis($modal->jenisKayu->nama_kayu ?? '');
                $isAf = str_contains(strtolower($modal->kw ?? ''), 'af');

                [$noAkun, $namaAkun] = $this->getAkunVeneerJadi((float) $ukuran->tebal, $isAf);
                $keterangan = ($isAf ? 'af ' : '130 ').strtolower($modal->jenisKayu->nama_kayu ?? '').' uk '.$ukuran->panjang.' x '.$ukuran->lebar.' x '.$ukuran->tebal;

                $m3 = ($ukuran->panjang * $ukuran->lebar * $ukuran->tebal * $modal->jumlah) / 10000000;
                $hargaPatok = $this->getHargaPatok($jnsNorm, (float) $ukuran->tebal, $isAf);
                $totalValue = $m3 * $hargaPatok;

                $jurnalBlock[] = $this->makeRow($namaAkun, $tglFormat, $noAkun, $keterangan, 'k', $modal->jumlah, $m3, $hargaPatok, $totalValue, 'm');
                $totalKredit += $totalValue;
            }

            // 3. KREDIT: Bahan penolong (akun sesuai COA baru, fallback 1402.0)
            foreach ($produksi->bahanProduksi as $bahan) {
                $jumlah = (float) ($bahan->jumlah ?? 0);
                if ($jumlah > 0) {
                    $namaBahanRaw = $bahan->nama_bahan ?? $bahan->nama_bahan_penolong ?? 'bahan';

                    [$akun, $namaAkun] = $this->getAkunBahan($namaBahanRaw);
                    $hargaH = $this->getHargaBahan($namaBahanRaw);
                    $total = $hargaH * $jumlah;

                    // Nama akun persis sama dengan COA; nama bahan asli ditaruh di Keterangan
                    $keterangan = ucfirst(strtolower(trim($namaBahanRaw)));

                    $jurnalBlock[] = $this->makeRow($namaAkun, $tglFormat, $akun, $keterangan, 'k', $jumlah, 0, $hargaH, $total, 'b');
                    $totalKredit += $total;
                }
            }

            // 4. KREDIT: Gaji -> 2195.1 Hutang Gaji
            $jmlPekerja = (int) $produksi->pegawaiJoint->count();
            if ($jmlPekerja > 0) {
                $jurnalBlock[] = $this->makeRow('Hutang Gaji', $tglFormat, '2195.1', '', 'k', $jmlPekerja, 0, 150000, ($jmlPekerja * 150000), 'b');
                $totalKredit += ($jmlPekerja * 150000);
            }

            // 5. Selisih debit-kredit -> 5069.2 Selisih harga patok produksi
            $selisih = $totalDebit - $totalKredit;
            if (round($selisih, 2) != 0) {
                $jurnalBlock[] = $this->makeRow('Selisih harga patok produksi', $tglFormat, '5069.2', '', 'd', 0, 0, abs($selisih), abs($selisih), '');
            }

            foreach ($jurnalBlock as $row) {
                $rows[] = $row;
            }
            $rows[] = array_fill(0, 14, '');
        }

        return $rows;
    }
}
