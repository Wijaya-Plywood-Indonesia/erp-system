<?php

namespace App\Exports;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * Export Rekap Bulanan -- format meniru contoh
 * "8_Agustus_2026 - WAHANA.xlsx": baris 1 = tanggal (merge 2 kolom per
 * tanggal), baris 2 = sub-header ('jam kerja' / 'poin'), baris 3+ = data
 * per pegawai.
 *
 * SENGAJA pakai FromArray + WithEvents (bukan WithHeadings/WithMapping
 * seperti NewRekapAbsensiExport) karena header di sini 2 baris DAN
 * jumlah kolomnya dinamis (tergantung panjang rentang tanggal) --
 * WithHeadings cuma bisa 1 baris.
 *
 * CATATAN: kolom "Bonus" & "Tot Bonus" di contoh Excel belum ada aturan
 * perhitungannya yang dikonfirmasi -- untuk sekarang diisi 0 sebagai
 * placeholder. Ganti isinya begitu rumus bonusnya sudah jelas.
 */
class RekapBulananExport implements FromArray, WithEvents, WithTitle
{
    /**
     * Jumlah kolom "tetap" di depan (sebelum kolom per-tanggal):
     * ID, Nama, Tot Poin, Bonus, Tot Bonus.
     */
    protected const JUMLAH_KOLOM_TETAP = 5;

    protected int $originalPrecision;

    protected int $originalSerializePrecision;

    /**
     * @param  Collection  $rekap  hasil RekapBulananService::getRekap()
     * @param  array<int, string>  $periode  daftar tanggal (Y-m-d) sesuai urutan kolom
     */
    public function __construct(
        protected Collection $rekap,
        protected array $periode,
    ) {
        $this->originalPrecision = (int) ini_get('precision');
        $this->originalSerializePrecision = (int) ini_get('serialize_precision');

        ini_set('precision', 16);
        ini_set('serialize_precision', -1);
    }

    public function __destruct()
    {
        ini_set('precision', $this->originalPrecision);
        ini_set('serialize_precision', $this->originalSerializePrecision);
    }

    public function array(): array
    {
        $rows = [];

        // Baris 1: tanggal (hanya diisi di kolom pertama tiap pasangan --
        // kolom kedua dikosongkan, nanti di-merge di AfterSheet supaya
        // visualnya sama seperti file contoh).
        $barisTanggal = array_fill(0, self::JUMLAH_KOLOM_TETAP, '');
        foreach ($this->periode as $tanggal) {
            $barisTanggal[] = Carbon::parse($tanggal)->format('d/m/Y');
            $barisTanggal[] = '';
        }
        $rows[] = $barisTanggal;

        // Baris 2: sub-header.
        $barisSubHeader = ['ID', 'Nama', 'Tot Poin', 'Bonus', 'Tot Bonus'];
        foreach ($this->periode as $tanggal) {
            $barisSubHeader[] = 'jam kerja';
            $barisSubHeader[] = 'poin';
        }
        $rows[] = $barisSubHeader;

        // Baris data per pegawai.
        foreach ($this->rekap as $pegawai) {
            $baris = [
                $pegawai['kode_pegawai'] ?? '-',
                $pegawai['nama_pegawai'] ?? '-',
                $pegawai['total_poin'] ?? 0,
                0, // Bonus -- placeholder, belum ada rumusnya
                0, // Tot Bonus -- placeholder, belum ada rumusnya
            ];

            foreach ($this->periode as $tanggal) {
                $harian = $pegawai['harian'][$tanggal] ?? ['jam_kerja' => 0, 'poin' => 0];
                $baris[] = $harian['jam_kerja'];
                $baris[] = $harian['poin'];
            }

            $rows[] = $baris;
        }

        return $rows;
    }

    public function title(): string
    {
        $awal = $this->periode[0] ?? null;
        $akhir = $this->periode[count($this->periode) - 1] ?? null;

        if (!$awal || !$akhir) {
            return 'REKAP_BULANAN';
        }

        return 'REKAP_' . Carbon::parse($awal)->format('dmY') . '_' . Carbon::parse($akhir)->format('dmY');
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                $jumlahKolom = self::JUMLAH_KOLOM_TETAP + (count($this->periode) * 2);
                $lastColLetter = Coordinate::stringFromColumnIndex($jumlahKolom);
                $lastRow = 2 + $this->rekap->count();

                // Merge tiap pasangan kolom tanggal di baris 1.
                for ($i = 0; $i < count($this->periode); $i++) {
                    $kolomAwal = self::JUMLAH_KOLOM_TETAP + ($i * 2) + 1;
                    $kolomAkhir = $kolomAwal + 1;
                    $hurufAwal = Coordinate::stringFromColumnIndex($kolomAwal);
                    $hurufAkhir = Coordinate::stringFromColumnIndex($kolomAkhir);
                    $sheet->mergeCells("{$hurufAwal}1:{$hurufAkhir}1");
                }

                // Merge kolom tetap (A-E) supaya baris 1 & 2 nyatu jadi satu
                // header visual untuk kolom yang tidak punya "tanggal".
                for ($kolom = 1; $kolom <= self::JUMLAH_KOLOM_TETAP; $kolom++) {
                    $huruf = Coordinate::stringFromColumnIndex($kolom);
                    $sheet->mergeCells("{$huruf}1:{$huruf}2");
                }

                // Styling header (baris 1 & 2).
                $sheet->getStyle("A1:{$lastColLetter}2")->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '333333']],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                ]);

                // Border tipis untuk seluruh area data.
                $sheet->getStyle("A1:{$lastColLetter}{$lastRow}")->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['rgb' => 'AAAAAA'],
                        ],
                    ],
                    'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
                ]);

                // Rata tengah untuk semua kolom angka (mulai kolom C: Tot
                // Poin, Bonus, Tot Bonus, dan semua kolom tanggal).
                $sheet->getStyle("C3:{$lastColLetter}{$lastRow}")
                    ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // Nama pegawai rata kiri, biar gampang dibaca untuk nama panjang.
                $sheet->getStyle("B3:B{$lastRow}")
                    ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

                // Freeze kolom ID & Nama (A-B) + baris header (1-2), supaya
                // tetap kelihatan saat scroll ke kanan/bawah pada tabel yang
                // lebar (bisa >60 kolom untuk rentang 1 bulan).
                $sheet->freezePane('C3');

                $sheet->getColumnDimension('A')->setWidth(10);
                $sheet->getColumnDimension('B')->setWidth(28);
                $sheet->getColumnDimension('C')->setWidth(10);
                $sheet->getColumnDimension('D')->setWidth(10);
                $sheet->getColumnDimension('E')->setWidth(10);
                for ($kolom = self::JUMLAH_KOLOM_TETAP + 1; $kolom <= $jumlahKolom; $kolom++) {
                    $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($kolom))->setWidth(9);
                }
            },
        ];
    }
}