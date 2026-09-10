<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class LaporanPilihVeneerExport implements FromCollection, WithEvents, WithTitle
{
    protected array $laporan;
    protected array $tableRanges = [];

    public function __construct(array $laporan, $tanggal = null)
    {
        $this->laporan = $laporan;
    }

    public function collection(): Collection
    {
        $rows = collect();
        $this->tableRanges = [];

        if (empty($this->laporan)) {
            $rows->push(['Tidak ada data pilih veneer untuk tanggal ini.']);
            return $rows;
        }

        $currentRow = 0;

        foreach ($this->laporan as $table) {
            $detailProduksi = $table['detail_produksi'] ?? [];
            $rekapPekerja = $table['rekap_pekerja'] ?? [];

            if (empty($rekapPekerja) && empty($detailProduksi)) {
                continue;
            }

            $labelGrup = strtoupper($table['nomor_meja'] ?? 'PILIH VENEER');

            // --- Judul grup ---
            $rows->push([$labelGrup]);
            $currentRow++;

            $totalHasil = 0;
            $targetParts = [];
            foreach ($detailProduksi as $prod) {
                $totalHasil += (float) ($prod['hasil'] ?? 0);
                $label = $prod['ukuran'] ?? 'Ukuran';
                if (!empty($prod['kw']) && $prod['kw'] !== '-') {
                    $label .= ' KW ' . $prod['kw'];
                }
                $targetVal = (float) ($prod['target'] ?? 0);
                $targetParts[] = 'Target ' . $label . ': ' . number_format($targetVal, 0, ',', '.') . ' pcs';
            }

            $pencapaianRasio = $table['pencapaian_global'] ?? null;
            $pencapaianPersen = $pencapaianRasio !== null ? $pencapaianRasio * 100 : null;
            $pencapaianText = $pencapaianPersen !== null
                ? number_format($pencapaianPersen, 1, ',', '.').'%'
                : '-';

            $infoParts = [];
            $infoParts[] = 'Hasil Aktual: ' . number_format($totalHasil, 0, ',', '.') . ' pcs';
            foreach ($targetParts as $tp) {
                $infoParts[] = $tp;
            }
            $infoParts[] = 'Pencapaian: ' . $pencapaianText;

            // Jika tidak mencapai target (< 100%), tampilkan selisih salah satu produk saja
            if ($pencapaianRasio !== null && $pencapaianRasio < 1.0) {
                $firstProd = $detailProduksi[0] ?? null;
                if ($firstProd) {
                    $label = $firstProd['ukuran'] ?? 'Ukuran';
                    if (!empty($firstProd['kw']) && $firstProd['kw'] !== '-') {
                        $label .= ' KW ' . $firstProd['kw'];
                    }
                    $targetVal = (float) ($firstProd['target'] ?? 0);
                    $shortageRatio = 1.0 - $pencapaianRasio;
                    $selisihPcs = round($shortageRatio * $targetVal);
                    $infoParts[] = 'Selisih: -' . number_format($selisihPcs, 0, ',', '.') . ' pcs ' . $label;
                }
            }

            $rows->push([
                implode('   |   ', $infoParts),
            ]);
            $infoRow = $currentRow + 1;
            $currentRow++;

            // --- Baris info jam ---
            $totalMenit = 0;
            $jumlahPekerja = count($rekapPekerja);
            foreach ($rekapPekerja as $p) {
                $jamKerjaStr = $p['jam_kerja'] ?? '0 jam';
                $jamNum = (float) str_replace(' jam', '', $jamKerjaStr);
                $totalMenit += $jamNum * 60;
            }
            $totalJamAktual = $totalMenit / 60;
            $rataJamPerOrang = $jumlahPekerja > 0 ? ($totalJamAktual / $jumlahPekerja) : 0;

            $rows->push([
                'Jumlah Pekerja: '.$jumlahPekerja.' orang'
                    .'   |   Jam Aktual Total: '.number_format($totalJamAktual, 1, ',', '.').' jam'
                    .'   |   Rata-rata: '.number_format($rataJamPerOrang, 1, ',', '.').' jam/orang',
            ]);
            $jamInfoRow = $currentRow + 1;
            $currentRow++;

            // --- Header ---
            $headerRow = $currentRow + 1;
            $rows->push(['No', 'Kode Pegawai', 'Nama', 'Jam Masuk', 'Jam Pulang', 'Ijin', 'Keterangan', 'Potongan Gaji (Rp)']);
            $currentRow++;

            // --- Data per pegawai ---
            $startRow = $currentRow + 1;
            $no = 1;
            foreach ($rekapPekerja as $p) {
                $rows->push([
                    $no++,
                    $p['id'] ?? '-',
                    $p['nama'] ?? '-',
                    $p['jam_masuk'] ?? '-',
                    $p['jam_pulang'] ?? '-',
                    $p['ijin'] ?? '-',
                    $p['keterangan'] ?? '-',
                    (int) ($p['pot_target'] ?? 0),
                ]);
                $currentRow++;
            }
            $endRow = $currentRow;

            // --- Total ---
            $totalRow = $currentRow + 1;
            $rows->push([
                'TOTAL',
                '',
                $jumlahPekerja.' pekerja',
                '', '', '', '',
                $endRow >= $startRow ? "=SUM(H{$startRow}:H{$endRow})" : 0,
            ]);
            $currentRow++;

            $this->tableRanges[] = [
                'info' => $infoRow,
                'jam_info' => $jamInfoRow,
                'header' => $headerRow,
                'start' => $startRow,
                'end' => $endRow,
                'total' => $totalRow,
            ];

            // Baris kosong pemisah antar grup/meja
            $rows->push(array_fill(0, 8, ''));
            $currentRow++;
        }

        if ($rows->isEmpty()) {
            $rows->push(['Tidak ada data pilih veneer untuk tanggal ini.']);
        }

        return $rows;
    }

    public function title(): string
    {
        return 'Potongan';
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                $sheet->getColumnDimension('A')->setWidth(10);
                $sheet->getColumnDimension('B')->setWidth(16);
                $sheet->getColumnDimension('C')->setWidth(28);
                $sheet->getColumnDimension('D')->setWidth(12);
                $sheet->getColumnDimension('E')->setWidth(12);
                $sheet->getColumnDimension('F')->setWidth(10);
                $sheet->getColumnDimension('G')->setWidth(30);
                $sheet->getColumnDimension('H')->setWidth(18);

                foreach ($this->tableRanges as $range) {
                    $infoRow = $range['info'] ?? null;
                    $jamInfoRow = $range['jam_info'] ?? null;
                    $headerRow = $range['header'];
                    $startRow = $range['start'];
                    $endRow = $range['end'];
                    $totalRow = $range['total'];

                    if ($infoRow) {
                        $sheet->mergeCells("A{$infoRow}:H{$infoRow}");
                        $sheet->getStyle("A{$infoRow}:H{$infoRow}")->applyFromArray([
                            'font' => ['italic' => true, 'size' => 10, 'color' => ['argb' => 'FF334155']],
                            'fill' => [
                                'fillType' => Fill::FILL_SOLID,
                                'startColor' => ['argb' => 'FFFFF7ED'],
                            ],
                            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
                        ]);
                        $sheet->getRowDimension($infoRow)->setRowHeight(24);
                    }

                    if ($jamInfoRow) {
                        $sheet->mergeCells("A{$jamInfoRow}:H{$jamInfoRow}");
                        $sheet->getStyle("A{$jamInfoRow}:H{$jamInfoRow}")->applyFromArray([
                            'font' => ['italic' => true, 'size' => 10, 'color' => ['argb' => 'FF334155']],
                            'fill' => [
                                'fillType' => Fill::FILL_SOLID,
                                'startColor' => ['argb' => 'FFEFF6FF'],
                            ],
                            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
                        ]);
                        $sheet->getRowDimension($jamInfoRow)->setRowHeight(18);
                    }

                    // Border seluruh tabel
                    $sheet->getStyle("A{$headerRow}:H{$totalRow}")->applyFromArray([
                        'borders' => [
                            'allBorders' => [
                                'borderStyle' => Border::BORDER_THIN,
                                'color' => ['argb' => 'FFCBD5E1'],
                            ],
                        ],
                    ]);

                    // Header style
                    $sheet->getStyle("A{$headerRow}:H{$headerRow}")->applyFromArray([
                        'font' => ['bold' => true, 'color' => ['argb' => 'FF1E293B']],
                        'fill' => [
                            'fillType' => Fill::FILL_SOLID,
                            'startColor' => ['argb' => 'FFE2E8F0'],
                        ],
                        'alignment' => [
                            'horizontal' => Alignment::HORIZONTAL_CENTER,
                            'vertical' => Alignment::VERTICAL_CENTER,
                        ],
                    ]);

                    // Total row style
                    $sheet->getStyle("A{$totalRow}:H{$totalRow}")->applyFromArray([
                        'font' => ['bold' => true, 'color' => ['argb' => 'FF1E293B']],
                        'fill' => [
                            'fillType' => Fill::FILL_SOLID,
                            'startColor' => ['argb' => 'FFF1F5F9'],
                        ],
                    ]);

                    // Alignment data
                    if ($startRow <= $endRow) {
                        $sheet->getStyle("A{$startRow}:A{$endRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                        $sheet->getStyle("B{$startRow}:B{$endRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                        $sheet->getStyle("D{$startRow}:F{$endRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                        $sheet->getStyle("H{$startRow}:H{$totalRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                        $sheet->getStyle("H{$startRow}:H{$totalRow}")->getNumberFormat()->setFormatCode('#,##0');
                    }

                    // Judul grup (3 baris di atas header: judul, info hasil/target, info jam) — merge & bold
                    $titleRow = $headerRow - 3;
                    $sheet->mergeCells("A{$titleRow}:H{$titleRow}");
                    $sheet->getStyle("A{$titleRow}:H{$titleRow}")->applyFromArray([
                        'font' => ['bold' => true, 'size' => 12, 'color' => ['argb' => 'FFFFFFFF']],
                        'fill' => [
                            'fillType' => Fill::FILL_SOLID,
                            'startColor' => ['argb' => 'FF2F5597'],
                        ],
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
                    ]);
                    $sheet->getRowDimension($titleRow)->setRowHeight(22);
                }
            },
        ];
    }
}
