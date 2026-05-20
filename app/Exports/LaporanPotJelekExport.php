<?php

namespace App\Exports;

use App\Models\ProduksiPotJelek;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class LaporanPotJelekExport implements WithMultipleSheets
{
    protected $data;
    protected $tanggal;

    public function __construct(array $dataProduksi, $tanggal = null)
    {
        $this->data = $dataProduksi;
        $this->tanggal = $tanggal;
    }

    public function sheets(): array
    {
        return [
            new LaporanPotJelekDetailSheet($this->data),
            new LaporanPotJelekRekapSheet($this->tanggal),
        ];
    }
}

class LaporanPotJelekDetailSheet implements FromCollection, WithHeadings, WithTitle, ShouldAutoSize
{
    protected Collection $data;

    public function __construct(array $dataProduksi)
    {
        $this->data = collect($dataProduksi);
    }

    public function collection()
    {
        $rows = collect();
        foreach ($this->data as $item) {
            $rows->push(['PEGAWAI', $item['kode_nama']]);
            $rows->push(['TANGGAL PRODUKSI', $item['tanggal']]);
            $rows->push([]);
            $rows->push(['KODE UKURAN', 'HASIL', 'JAM MASUK', 'JAM PULANG', 'POTONGAN TARGET', 'IJIN', 'KETERANGAN / KENDALA']);
            foreach ($item['rincian'] as $index => $detail) {
                $rows->push([
                    $detail['ukuran_lengkap'], $detail['jumlah'],
                    $index === 0 ? $item['jam_masuk'] : '',
                    $index === 0 ? $item['jam_pulang'] : '',
                    $index === 0 ? ($item['pot_target'] > 0 ? 'Rp ' . number_format($item['pot_target']) : '-') : '',
                    $index === 0 ? $item['ijin'] : '',
                    $index === 0 ? $item['keterangan'] : '',
                ]);
            }
            $rows->push(['TOTAL', $item['hasil'], 'TARGET: ' . number_format($item['target']), 'SELISIH: ' . ($item['selisih'] >= 0 ? '+' : '') . number_format($item['selisih']), 'DIPOTONG: ' . ($item['pot_target'] > 0 ? 'Rp ' . number_format($item['pot_target']) : '0'), '', '']);
            $rows->push([]); $rows->push([]);
        }
        return $rows;
    }

    public function headings(): array { return []; }
    public function title(): string { return 'Detail Per Pekerja'; }
}

class LaporanPotJelekRekapSheet implements FromCollection, WithHeadings, WithStyles, WithEvents, WithTitle
{
    protected $tanggal;
    protected $mergeData = [];

    public function __construct($tanggal)
    {
        $this->tanggal = $tanggal;
    }

    public function collection()
    {
        $produksis = ProduksiPotJelek::with([
            'detailBarangDikerjakanPotJelek.ukuran',
            'detailBarangDikerjakanPotJelek.jenisKayu',
            'pegawaiPotJelek'
        ])
            ->whereDate('tanggal_produksi', $this->tanggal)
            ->get();

        $rows = collect();
        $currentRow = 2;
        $this->mergeData = [];

        foreach ($produksis as $index => $produksi) {
            $tgl = Carbon::parse($produksi->tanggal_produksi)->format('d-M');
            $totalPekerja = $produksi->pegawaiPotJelek()->whereNotNull('id_pegawai')->distinct('id_pegawai')->count('id_pegawai');
            
            $details = $produksi->detailBarangDikerjakanPotJelek->groupBy(function($item) {
                $u = $item->ukuran;
                $jRaw = strtolower($item->jenisKayu->nama_kayu ?? '');
                $jenis = $item->jenisKayu->kode_kayu ?? '-';
                if (str_contains($jRaw, 'sengon')) $jenis = 'afs';
                elseif (str_contains($jRaw, 'jabon')) $jenis = 'afj';
                return ($u->panjang ?? 0) . '|' . ($u->lebar ?? 0) . '|' . ($u->tebal ?? 0) . '|' . $jenis;
            });

            if ($details->count() > 0) {
                $this->mergeData[] = ['start' => $currentRow, 'end' => $currentRow + $details->count() - 1];
                foreach ($details as $key => $items) {
                    [$p, $l, $t, $jenis] = explode('|', $key);
                    $p = str_replace('.', ',', (string)(float)$p); $l = str_replace('.', ',', (string)(float)$l); $t = str_replace('.', ',', (string)(float)$t);
                    $rows->push([
                        'tanggal' => $tgl, 'p' => $p, 'l' => $l, 't' => $t, 'jenis' => $jenis, 'byk' => $items->sum('tinggi') ?: '', 'ttl_pkj' => $totalPekerja,
                    ]);
                    $currentRow++;
                }
                if ($index < $produksis->count() - 1) { $rows->push(['', '', '', '', '', '', '']); $currentRow++; }
            }
        }
        return $rows;
    }

    public function headings(): array { return ['Tanggal', 'p', 'l', 't', 'jenis', 'byk', 'TTL PKJ']; }

    public function styles(Worksheet $sheet)
    {
        return [1 => ['font' => ['bold' => true, 'name' => 'Times New Roman'], 'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]]];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate(); $peachColor = 'F9CB9C';
                $sheet->getStyle('A1:G1')->applyFromArray(['borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]], 'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER], 'font' => ['bold' => true, 'size' => 12]]);
                foreach ($this->mergeData as $range) {
                    $start = $range['start']; $end = $range['end'];
                    if ($start < $end) { $sheet->mergeCells("A{$start}:A{$end}"); $sheet->mergeCells("G{$start}:G{$end}"); }
                    $sheet->getStyle("A{$start}:G{$end}")->applyFromArray(['borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]], 'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER], 'font' => ['name' => 'Times New Roman']]);
                    $sheet->getStyle("A{$start}:A{$end}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($peachColor);
                    $sheet->getStyle("E{$start}:E{$end}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($peachColor);
                    $sheet->getStyle("G{$start}:G{$end}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($peachColor);
                    $sheet->getStyle("A{$start}:A{$end}")->getFont()->setBold(true);
                    $sheet->getStyle("G{$start}:G{$end}")->getFont()->setBold(true);
                }
                $sheet->getRowDimension(1)->setRowHeight(35);
                $sheet->getColumnDimension('A')->setWidth(15); $sheet->getColumnDimension('B')->setWidth(8); $sheet->getColumnDimension('C')->setWidth(8); $sheet->getColumnDimension('D')->setWidth(8); $sheet->getColumnDimension('E')->setWidth(12); $sheet->getColumnDimension('F')->setWidth(12); $sheet->getColumnDimension('G')->setWidth(12);
            },
        ];
    }
    public function title(): string { return 'Rekap Produksi Custom'; }
}
