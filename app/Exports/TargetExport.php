<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Export data Target ke Excel.
 *
 * Layout kolom (baris 1 = judul, baris 2 = header, data mulai baris 3):
 *  A Mesin | B P | C L | D T | E Shift | F KW | G Jenis | H Jenis Barang
 *  I Target | J Org | K Jam | L Menit | M Target/Jam | N Target/Menit
 *  O Target/Org | P Gaji | Q Pot/Lembar
 *
 * Kolom hitungan (Menit, Target/Jam, Target/Menit, Target/Org, Pot/Lembar)
 * ditulis sebagai rumus Excel, jadi kalau user mengubah Target / Org / Jam /
 * Gaji di file Excel, hasilnya ikut terhitung ulang.
 */
class TargetExport implements
    FromCollection,
    WithHeadings,
    WithMapping,
    WithStyles,
    WithColumnFormatting,
    WithEvents,
    WithTitle,
    ShouldAutoSize
{
    private const HEADER_ROW = 2;
    private const FIRST_DATA_ROW = 3;
    private const LAST_COL = 'Q';

    private int $row = self::FIRST_DATA_ROW - 1;

    /**
     * @param Collection $targets Koleksi model Target (relasi sudah di-eager load).
     */
    public function __construct(private Collection $targets)
    {
    }

    public function title(): string
    {
        return 'Target';
    }

    /**
     * Urutan kelompok mesin di Excel. Dicocokkan dengan potongan nama mesin
     * (huruf besar, tanpa spasi). Mesin yang tidak cocok dengan daftar ini
     * ditaruh paling bawah, urut abjad. Ubah/tambah daftar ini kalau perlu.
     *
     * Rotary -> Dryer (Press Dryer, Kedi/Bongkar) -> lainnya.
     */
    private const URUTAN_MESIN = [
        // Rotary
        'SPINDLE', 'SPINDLESS', 'MERANTI', 'SANJI', 'YUE',
        // Dryer
        'PRESSDRYER', 'DRYER', 'BONGKAR', 'KEDI',
        // Lanjutan
        'HOTPRESS',
    ];

    private function urutanMesin(?string $nama): int
    {
        $nama = strtoupper(str_replace(' ', '', (string) $nama));

        foreach (self::URUTAN_MESIN as $i => $kunci) {
            if (str_contains($nama, $kunci)) {
                return $i;
            }
        }

        return count(self::URUTAN_MESIN);
    }

    public function collection(): Collection
    {
        return $this->targets
            ->sortBy([
                fn ($a, $b) => $this->urutanMesin($a->mesin?->nama_mesin) <=> $this->urutanMesin($b->mesin?->nama_mesin),
                fn ($a, $b) => strcasecmp($a->mesin?->nama_mesin ?? '', $b->mesin?->nama_mesin ?? ''),
                fn ($a, $b) => ($b->ukuranModel?->panjang ?? 0) <=> ($a->ukuranModel?->panjang ?? 0),
                fn ($a, $b) => ($a->ukuranModel?->tebal ?? 0) <=> ($b->ukuranModel?->tebal ?? 0),
                fn ($a, $b) => strcmp((string) $a->grade, (string) $b->grade),
            ])
            ->values();
    }

    public function headings(): array
    {
        // Baris 1 hanya judul (diisi di AfterSheet), header kolom di baris 2.
        return [
            ['DATA TARGET PRODUKSI'],
            [
                'Mesin', 'P', 'L', 'T', 'Shift', 'KW', 'Jenis', 'Jenis Barang',
                'Target', 'Org', 'Jam', 'Menit', 'Target/Jam', 'Target/Menit',
                'Target/Org', 'Gaji', 'Pot/Lembar',
            ],
        ];
    }

    /**
     * @param \App\Models\Target $t
     */
    public function map($t): array
    {
        $r = ++$this->row;

        return [
            $t->mesin?->nama_mesin ?? '-',
            $t->ukuranModel?->panjang,
            $t->ukuranModel?->lebar,
            $t->ukuranModel?->tebal,
            $t->shift ? strtoupper($t->shift) : '',
            $t->grade ?? '',
            $t->jenisKayu?->kode_kayu ?? '',
            $t->kategoriBarang?->nama_kategori ?? '',
            (float) $t->target,
            (int) $t->orang,
            (int) $t->jam,
            "=K{$r}*60",
            "=IFERROR(I{$r}/K{$r},0)",
            "=IFERROR(I{$r}/L{$r},0)",
            "=IFERROR(I{$r}/J{$r},0)",
            (float) $t->gaji,
            "=IFERROR(P{$r}/O{$r},0)",
        ];
    }

    public function columnFormats(): array
    {
        return [
            'B' => '0.##',
            'C' => '0.##',
            'D' => '0.##',
            'I' => '#,##0',
            'L' => '0',
            'M' => '#,##0',
            'N' => '#,##0',
            'O' => '#,##0',
            'P' => '#,##0',
            'Q' => '#,##0',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $header = self::HEADER_ROW;
                $last = max($this->row, self::FIRST_DATA_ROW);
                $lastCol = self::LAST_COL;

                // Judul
                $sheet->mergeCells("A1:{$lastCol}1");
                $sheet->getStyle('A1')->applyFromArray([
                    'font' => ['bold' => true, 'size' => 14],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT],
                ]);

                // Header kolom
                $sheet->getStyle("A{$header}:{$lastCol}{$header}")->applyFromArray([
                    'font' => ['bold' => true],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => 'D9E1F2'],
                    ],
                ]);

                // Border seluruh tabel
                $sheet->getStyle("A{$header}:{$lastCol}{$last}")->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['rgb' => 'BFBFBF'],
                        ],
                    ],
                ]);

                // Kolom Target/Org diberi warna hijau muda (sorotan utama)
                $sheet->getStyle("O" . self::FIRST_DATA_ROW . ":O{$last}")->applyFromArray([
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => 'E2EFDA'],
                    ],
                    'font' => ['bold' => true],
                ]);

                // Kolom teks pendek rata tengah
                $sheet->getStyle("B" . self::FIRST_DATA_ROW . ":H{$last}")
                    ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("A" . self::FIRST_DATA_ROW . ":A{$last}")
                    ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

                // Filter + freeze header & kolom Mesin
                $sheet->setAutoFilter("A{$header}:{$lastCol}{$last}");
                $sheet->freezePane('B' . self::FIRST_DATA_ROW);
            },
        ];
    }
}