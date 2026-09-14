<?php

namespace App\Exports;

use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * SHEET BARU (tambahan) -- COA general, TIDAK menggantikan
 * LaporanJurnalKayuMasukSheet2 (sheet lama, COA WHN/WJY per jenis kayu).
 *
 * Perbedaan dari sheet lama:
 * - Tidak ada split WHN vs WJY.
 * - Meranti/rijek/baloan/lunak/keras digabung jadi 2 akun umum
 *   berdasarkan panjang: 1402.1 (130) / 1402.2 (selain 130).
 * - Logcore tetap akun sendiri: 1402.21 (130) / 1402.22 (selain 130).
 * - Hutang ongkos turun kayu -> 2195.2 (sesuai COA baru), bukan 2400.01.
 * - Kolom No Akun dipaksa jadi teks supaya titik tidak berubah jadi koma.
 */
class LaporanJurnalKayuMasukSheet2New extends DefaultValueBinder implements FromCollection, WithCustomValueBinder, WithStyles, WithTitle
{
    public function bindValue(Cell $cell, $value)
    {
        // Kolom D (No Akun) HARUS selalu jadi teks, apapun isinya.
        // Jangan biarkan is_numeric() men-convert "1402.1" jadi float lalu
        // diformat '0.00' -- itu yang bikin Excel menampilkannya sebagai
        // "1402,10" (desimal lokal Indonesia pakai koma) padahal aslinya titik.
        if ($cell->getColumn() === 'D') {
            $cell->setValueExplicit((string) $value, DataType::TYPE_STRING);

            return true;
        }

        return parent::bindValue($cell, $value);
    }

    protected array $jurnalTables;

    public function __construct(array $jurnalTables)
    {
        $this->jurnalTables = $jurnalTables;
    }

    /**
     * COA baru (general).
     */
    public function getAccountDetails(string $jenisKayuNama, $panjang): array
    {
        $jenis = strtolower(trim($jenisKayuNama));
        $panjang = (int) $panjang;

        $isLogCore = str_contains($jenis, 'log core') || str_contains($jenis, 'core');

        if ($isLogCore) {
            if ($panjang === 130) {
                return ['no_akun' => '1402.21', 'nama_akun' => 'Persediaan Logcore 130'];
            }

            return ['no_akun' => '1402.22', 'nama_akun' => 'Persediaan Logcore 260'];
        }

        if ($panjang === 130) {
            return ['no_akun' => '1402.1', 'nama_akun' => 'Persediaan kayu 130'];
        }

        return ['no_akun' => '1402.2', 'nama_akun' => 'Persediaan kayu 260'];
    }

    public function collection()
    {
        $flatRows = [];
        $currentRow = 1;

        foreach ($this->jurnalTables as $table) {
            $noJurnal = 'MASUK/'.Carbon::parse($table['tgl_kayu_masuk'])->format('Ymd').'/'.$table['no_nota'];
            $tglVal = Carbon::parse($table['tgl_kayu_masuk'])->format('d/m/Y');

            // Table Header Block
            $flatRows[] = ['No. Jurnal: '.$noJurnal, '', '', '', '', '', '', '', '', '', '', '', '', ''];
            $currentRow++;

            // Column Headers
            $flatRows[] = [
                'Nama Akun', 'tgl', 'jur', 'No Akun', 'No', 'mm',
                'Nama Suplier', 'Lahan', 'm', 'hit kbk', 'Banyak', 'M3', 'Harga', 'Total',
            ];
            $currentRow++;

            // 1. Add Debit entries from groups
            foreach ($table['groups'] as $group) {
                $jenisKayuNama = $group['jenis'] ?? '';
                $panjang = $group['panjang'] ?? 130;
                $kodeLahan = $group['kode_lahan'] ?? '';

                if (empty($jenisKayuNama) && ! empty($group['header'])) {
                    $header = $group['header'];

                    if (preg_match('/(\d+)\s*cm/', $header, $matches)) {
                        $panjang = (int) $matches[1];
                    }

                    if (preg_match('/cm\s+(.+?)\s+\(/', $header, $matches)) {
                        $jenisKayuNama = trim($matches[1]);
                    } elseif (preg_match('/cm\s+(.+)$/', $header, $matches)) {
                        $jenisKayuNama = trim($matches[1]);
                    }
                }

                if (empty($kodeLahan) && ! empty($group['header'])) {
                    $tokens = preg_split('/\s+/', trim($group['header']));
                    if (! empty($tokens)) {
                        $kodeLahan = $tokens[0];
                    }
                }

                $acc = $this->getAccountDetails($jenisKayuNama, $panjang);

                $hargaVal = $group['total_harga'];
                $totalVal = "=IF(J{$currentRow}=\"m\",M{$currentRow}*L{$currentRow},IF(J{$currentRow}=\"b\",M{$currentRow}*K{$currentRow},M{$currentRow}))";

                $flatRows[] = [
                    $acc['nama_akun'],
                    $tglVal,
                    '',
                    $acc['no_akun'],
                    $table['seri'],
                    '',
                    $table['nama_supplier'],
                    $kodeLahan,
                    'd',
                    null,
                    $group['total_batang'],
                    $group['total_kubikasi'],
                    $hargaVal,
                    $totalVal,
                ];
                $currentRow++;
            }

            // 2. Add Credit Row 1: hutang ongkos turun kayu -> 2195.2 (COA baru)
            $totalValRow1 = "=IF(J{$currentRow}=\"m\",M{$currentRow}*L{$currentRow},IF(J{$currentRow}=\"b\",M{$currentRow}*K{$currentRow},M{$currentRow}))";
            $flatRows[] = [
                'Hutang ongkos turun kayu',
                $tglVal, '', '2195.2', $table['seri'], '', $table['nama_supplier'], '',
                'k', '', '', '', $table['selisih'], $totalValRow1,
            ];
            $currentRow++;

            // 3. Add Credit Row 2: pendapatan
            $flatRows[] = [
                'pendapatan',
                $tglVal, '', '4000.00', $table['seri'], '', $table['nama_supplier'], '',
                'k', '', '', '', '', '',
            ];
            $currentRow++;

            // 4. Add Credit Row 3: Kas Mut
            $totalValKasMut = "=IF(J{$currentRow}=\"m\",M{$currentRow}*L{$currentRow},IF(J{$currentRow}=\"b\",M{$currentRow}*K{$currentRow},M{$currentRow}))";
            $flatRows[] = [
                'Kas Mut',
                $tglVal, '', '1111.00', $table['seri'], '', $table['nama_supplier'], '',
                'k', '', $table['totalBatang'], $table['totalKubikasi'], $table['hargaFinal'], $totalValKasMut,
            ];
            $currentRow++;

            // Spacer Rows between multiple tables
            $flatRows[] = ['', '', '', '', '', '', '', '', '', '', '', '', '', ''];
            $currentRow++;
            $flatRows[] = ['', '', '', '', '', '', '', '', '', '', '', '', '', ''];
            $currentRow++;
        }

        return collect($flatRows);
    }

    public function title(): string
    {
        return 'jurnal produksi v2';
    }

    public function styles(Worksheet $sheet)
    {
        $highestRow = $sheet->getHighestRow();

        for ($row = 1; $row <= $highestRow; $row++) {
            $cellValue = $sheet->getCell("A{$row}")->getValue();

            if (str_starts_with((string) $cellValue, 'No. Jurnal:')) {
                $sheet->mergeCells("A{$row}:N{$row}");
                $sheet->getStyle("A{$row}:N{$row}")->applyFromArray([
                    'font' => ['bold' => true, 'size' => 11, 'color' => ['argb' => 'FF1D2939']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFD2E4F0']],
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                ]);
            } elseif ($cellValue === 'Nama Akun') {
                $sheet->getStyle("A{$row}:N{$row}")->applyFromArray([
                    'font' => ['bold' => true, 'size' => 10],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFE5E8EB']],
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                ]);
            } elseif (! empty($cellValue)) {
                $sheet->getStyle("A{$row}:N{$row}")->applyFromArray([
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                ]);

                $sheet->getStyle("B{$row}:F{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("G{$row}:H{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                $sheet->getStyle("I{$row}:J{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("K{$row}:N{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

                $sheet->getStyle("K{$row}")->getNumberFormat()->setFormatCode('#,##0');
                $sheet->getStyle("L{$row}")->getNumberFormat()->setFormatCode('#,##0.0000');
                $sheet->getStyle("M{$row}:N{$row}")->getNumberFormat()->setFormatCode('#,##0');
            }
        }

        $sheet->getColumnDimension('A')->setWidth(25);
        $sheet->getColumnDimension('B')->setWidth(15);
        $sheet->getColumnDimension('C')->setWidth(10);
        $sheet->getColumnDimension('D')->setWidth(15);
        $sheet->getColumnDimension('E')->setWidth(10);
        $sheet->getColumnDimension('F')->setWidth(10);
        $sheet->getColumnDimension('G')->setWidth(25);
        $sheet->getColumnDimension('H')->setWidth(10);
        $sheet->getColumnDimension('I')->setWidth(10);
        $sheet->getColumnDimension('J')->setWidth(12);
        $sheet->getColumnDimension('K')->setWidth(12);
        $sheet->getColumnDimension('L')->setWidth(15);
        $sheet->getColumnDimension('M')->setWidth(18);
        $sheet->getColumnDimension('N')->setWidth(18);

        return [];
    }
}
