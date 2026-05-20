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