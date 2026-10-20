<?php

namespace App\Exports;

use App\Models\NotaBarangKeluar;
use App\Models\NotaBarangMasuk;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class RekapNotaExport implements FromArray, WithColumnFormatting, WithColumnWidths, WithEvents, WithHeadings, WithStyles, WithTitle
{
    private const FMT_TANGGAL = 'dd\-mm\-yyyy';

    private const FMT_ANGKA = '_(* #,##0_);_(* \(#,##0\);_(* "-"??_);_(@_)';

    private const FMT_M3 = '_(* #,##0.0000_);_(* \(#,##0.0000\);_(* "-"??_);_(@_)';

    /** @param 'bk'|'bm' $jenis */
    public function __construct(
        protected string $jenis,
        protected string $tanggal,
        protected ?int $nomorAwal = null,
    ) {}

    /** Nama sheet = nama bulan huruf besar, seperti file contoh. */
    public function title(): string
    {
        return strtoupper(
            Carbon::parse($this->tanggal)->locale('id')->translatedFormat('F')
        );
    }

    public function headings(): array
    {
        return [
            'NAMA AKUN', 'TANGGAL', 'No', 'NO.AKUN', 'NO', 'mm',
            'Nama', 'Keterangan', 'Map', 'Hit', 'Banyak', 'm3', 'Harga', 'Total',
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 36.4, 'B' => 10.2, 'C' => 6.5, 'D' => 9.5, 'E' => 13.2,
            'F' => 6.5,  'G' => 19.2, 'H' => 78.9, 'I' => 4.4, 'J' => 3.5,
            'K' => 8.7,  'L' => 11.3, 'M' => 14.2, 'N' => 14.2,
        ];
    }

    public function columnFormats(): array
    {
        return [
            'B' => self::FMT_TANGGAL,
            'K' => self::FMT_ANGKA,
            'L' => self::FMT_M3,
            'M' => self::FMT_ANGKA,
            'N' => self::FMT_ANGKA,
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->getParent()->getDefaultStyle()->getFont()->setName('Calibri')->setSize(11);

        $sheet->getStyle('A1:N1')->applyFromArray([
            'font' => ['name' => 'Calibri', 'size' => 11],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['argb' => config('akun_jurnal.warna_header', 'FFFFC6C6')],
            ],
        ]);

        return [];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $sheet->freezePane('A2');
                $sheet->setAutoFilter('A1:N'.max($sheet->getHighestRow(), 2));
            },
        ];
    }

    public function array(): array
    {
        $isBk = $this->jenis === 'bk';
        $prefixes = $isBk ? ['BK', 'BKL'] : ['BM', 'BML'];

        $query = $isBk ? NotaBarangKeluar::query() : NotaBarangMasuk::query();

        $notas = $query
            ->with(['detail', 'mutasi.details.ukuran', 'mutasi.details.jenisKayu'])
            ->whereDate('tanggal', $this->tanggal)
            ->orderBy('id')
            ->get()
            ->filter(fn ($nota) => in_array($this->prefix($nota), $prefixes, true));

        $noJurnal = $this->nomorAwal ?? 1;
        $rows = [];

        foreach ($notas as $nota) {
            $akun = config('akun_jurnal.'.strtolower($this->prefix($nota)), []);

            foreach ($this->items($nota) as $item) {
                $rows[] = $this->row($nota, $noJurnal, $akun['debit'] ?? [], 'd', $item);
                $rows[] = $this->row($nota, $noJurnal, $akun['kredit'] ?? [], 'k', $item);
            }

            $noJurnal++; // satu nomor per nota
        }

        return $rows;
    }

    protected function prefix($nota): string
    {
        preg_match('/^[A-Za-z]+/', trim((string) $nota->no_nota), $m);

        return strtoupper($m[0] ?? '');
    }

    protected function items($nota): array
    {
        if ($nota->mutasi && $nota->mutasi->details->isNotEmpty()) {
            return $nota->mutasi->details->map(function ($d) use ($nota) {
                $qty = (float) $d->qty;
                $harga = (float) $d->harga;

                return [
                    'mm' => data_get($d, 'ukuran.nama'),
                    'nama' => trim(($d->tipe_veneer ?? '').' '.data_get($d, 'jenisKayu.nama', '').' KW '.($d->kw ?? '')),
                    'keterangan' => $nota->mutasi->keterangan ?? null,
                    'hit' => 'm',
                    'qty' => $qty,
                    'm3' => (float) $d->m3,
                    'harga' => $harga,
                    'total' => round($qty * $harga),
                ];
            })->all();
        }

        return $nota->detail->map(function ($d) {
            $qty = (float) $d->jumlah;
            $harga = (float) ($d->getAttributes()['harga'] ?? 0);

            return [
                'mm' => null,
                'nama' => $d->nama_barang,
                'keterangan' => trim(($d->keterangan ?? '').' '.($d->satuan ? "@{$d->satuan}" : '')),
                'hit' => 'b',
                'qty' => $qty,
                'm3' => null,
                'harga' => $harga,
                'total' => round($qty * $harga),
            ];
        })->all();
    }

    protected function row($nota, int $noJurnal, array $akun, string $map, array $item): array
    {
        $kode = $akun['kode'] ?? null;

        return [
            $akun['nama'] ?? null,                                              // A NAMA AKUN
            $nota->tanggal ? ExcelDate::dateTimeToExcel($nota->tanggal) : null, // B TANGGAL
            $noJurnal,                                                          // C No
            is_numeric($kode) ? (float) $kode : $kode,                          // D NO.AKUN
            $nota->no_nota,                                                     // E NO
            $item['mm'],                                                        // F mm
            $nota->tujuan_nota,                                                 // G Nama
            $item['keterangan'] ?: $item['nama'],                               // H Keterangan
            $map,                                                               // I Map (d/k)
            $item['hit'],                                                       // J Hit (b/m)
            $item['qty'],                                                       // K Banyak
            $item['m3'],                                                        // L m3
            $item['harga'],                                                     // M Harga
            $item['total'],                                                     // N Total
        ];
    }
}
