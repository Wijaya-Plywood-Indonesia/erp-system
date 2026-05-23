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
use App\Filament\Pages\LaporanRepairs\Queries\LoadLaporanRepairs;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

// ============================================================
// MAIN EXPORT CLASS
// ============================================================
class LaporanRepairExport implements WithMultipleSheets
{
    public function __construct(
        protected array  $detailData, // Array hasil RepairDataMap (untuk Sheet 1)
        protected string $tanggal     // String tanggal format 'Y-m-d' (untuk query Sheet 2)
    ) {}

    public function sheets(): array
    {
        // Sheet 2 query langsung ke DB, tidak lewat transformer!
        $rawCollection = LoadLaporanRepairs::run($this->tanggal);

        return [
            new LaporanRepairDetailSheet($this->detailData),
            new LaporanRepairSummarySheet($rawCollection),
            new JurnalSheet($rawCollection),
        ];
    }
}

// ============================================================
// SHEET 1: DETAIL PER MEJA (UPDATE: TAMBAH KOLOM KETERANGAN)
// ============================================================
class LaporanRepairDetailSheet implements FromCollection, WithHeadings, WithTitle
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
            $first        = $items->first();
            $targetPerJam = $first['jam_kerja'] > 0
                ? round($first['target'] / $first['jam_kerja'], 2)
                : 0;
            $pekerja      = $first['pekerja'] ?? [];

            $rows->push(['MEJA',        $first['nomor_meja']]);
            $rows->push(['UKURAN',      $first['ukuran']]);
            $rows->push(['JENIS KAYU',  $first['jenis_kayu']]);
            $rows->push(['KW',          $first['kw']]);
            $rows->push(['TANGGAL',     $first['tanggal']]);
            $rows->push([]);

            // 🚀 UPDATE HEADER TABEL: Menambahkan Keterangan Hasil & Kerja di samping Keterangan Absen lama
            $rows->push([
                'ID',
                'Nama',
                'Masuk',
                'Pulang',
                'Ijin',
                'Potongan Target',
                'Keterangan Absen',
                'Keterangan Hasil', // 👈 Kolom Baru
                'Keterangan Kerja', // 👈 Kolom Baru
                '',
                'Target Harian',
                'Jam Kerja',
                'Target / Jam',
                'Hasil',
                'Selisih'
            ]);

            foreach ($pekerja as $p) {
                $rows->push([
                    $p['id'] ?? '-',
                    $p['nama'] ?? '-',
                    $p['jam_masuk'] ?? '-',
                    $p['jam_pulang'] ?? '-',
                    $p['ijin'] ?? '-',
                    ($p['pot_target'] ?? 0) > 0 ? $p['pot_target'] : '-',
                    $p['keterangan'] ?? '-',       // Ini Keterangan Absen bawaan array Anda
                    $p['keterangan_hasil'] ?? '—', // 👈 Diambil langsung dari mapping data hasil pekerja
                    $p['keterangan_kerja'] ?? '—', // 👈 Diambil langsung dari mapping data rencana kerja pekerja
                    '',
                    $first['target'],
                    $first['jam_kerja'],
                    $targetPerJam,
                    $first['hasil'],
                    $first['selisih'] >= 0 ? '+' . $first['selisih'] : $first['selisih'],
                ]);
            }

            $totalPotongan = collect($pekerja)->sum('pot_target');
            $rows->push([
                'TOTAL',
                '',
                '',
                '',
                '',
                $totalPotongan,
                '',
                '', // Kosongkan kolom baru untuk baris TOTAL
                '', // Kosongkan kolom baru untuk baris TOTAL
                '',
                $first['target'],
                $first['jam_kerja'],
                $targetPerJam,
                $first['hasil'],
                $first['selisih'] >= 0 ? '+' . $first['selisih'] : $first['selisih'],
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
// SHEET 2: SUMMARY — Bersih Seperti Semula
// ============================================================
class LaporanRepairSummarySheet implements FromCollection, WithHeadings, WithTitle, WithEvents
{
    private array $summary = [];

    private const MASTER_KW = ['1', '2', '3', '4', 'af'];

    public function __construct(protected $rawCollection)
    {
        $this->buildSummary();
    }

    private function buildSummary(): void
    {
        foreach ($this->rawCollection as $produksi) {
            $tanggal = Carbon::parse($produksi->tanggal)->format('d M');

            foreach ($produksi->modalRepairs as $modal) {
                $p = (float) ($modal->ukuran->panjang ?? 0);
                $l = (float) ($modal->ukuran->lebar   ?? 0);
                $t = (float) ($modal->ukuran->tebal   ?? 0);
                $jenis = strtoupper($modal->jenisKayu->kode_kayu ?? substr($modal->jenisKayu->nama_kayu ?? '-', 0, 1));
                $kwData = strtolower(trim($modal->kw ?? ''));

                $key = "{$jenis}|{$tanggal}|{$p}|{$l}|{$t}|{$kwData}";

                if (!isset($this->summary[$key])) {
                    $this->summary[$key] = [
                        'tanggal'     => $tanggal,
                        'p'           => $p,
                        'l'           => $l,
                        't'           => $t,
                        'jenis'       => $jenis,
                        'current_kw'  => $kwData,
                        'pekerja_ids' => [],
                    ];

                    foreach (self::MASTER_KW as $mKw) {
                        $this->summary[$key]['kw_' . $mKw] = 0;
                    }
                }

                $hasilModal = 0;
                foreach ($produksi->rencanaPegawais as $rp) {
                    if (!$rp->pegawai) continue;

                    $hasilIndividu = (int) $rp->rencanaRepairs
                        ->where('id_modal_repair', $modal->id)
                        ->flatMap->hasilRepairs
                        ->sum('jumlah');

                    if ($hasilIndividu > 0) {
                        $hasilModal += $hasilIndividu;
                        $this->summary[$key]['pekerja_ids'][] = $rp->pegawai->id;
                    }
                }

                if ($kwData !== '' && $hasilModal > 0) {
                    if (in_array($kwData, self::MASTER_KW)) {
                        $this->summary[$key]['kw_' . $kwData] += $hasilModal;
                    }
                }
            }
        }

        ksort($this->summary);
    }

    public function collection()
    {
        $rows = collect();
        $dataStart = 3;
        $totalMasterKw = count(self::MASTER_KW);
        $lastRow = $dataStart + count($this->summary) - 1;

        // Row 2: Grand Total
        $grandRow = ['', '', '', '', ''];
        for ($i = 0; $i < $totalMasterKw; $i++) {
            $colLetter = Coordinate::stringFromColumnIndex(6 + $i);
            $grandRow[] = "=SUM({$colLetter}{$dataStart}:{$colLetter}{$lastRow})";
        }

        $ttlPkjCol = Coordinate::stringFromColumnIndex(6 + $totalMasterKw);
        $grandRow[] = "=SUM({$ttlPkjCol}{$dataStart}:{$ttlPkjCol}{$lastRow})";

        $rows->push($grandRow);

        // Row 3+: Data Rows
        foreach ($this->summary as $s) {
            $row = [$s['tanggal'], $s['p'], $s['l'], $s['t'], $s['jenis']];

            foreach (self::MASTER_KW as $mKw) {
                $val = $s['kw_' . $mKw] ?? 0;
                $row[] = $val > 0 ? $val : '';
            }

            $uniquePekerja = count(array_unique($s['pekerja_ids']));
            $row[] = $uniquePekerja > 0 ? $uniquePekerja : '';
            $rows->push($row);
        }

        return $rows;
    }

    public function headings(): array
    {
        $heads = ['Tanggal', 'p', 'l', 't', 'jenis'];
        foreach (self::MASTER_KW as $mKw) {
            $heads[] = 'KW ' . strtoupper($mKw);
        }
        $heads[] = 'TTL PKJ';
        return $heads;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $lastCol = $sheet->getHighestColumn();
                $lastRow = $sheet->getHighestRow();

                // Style Header & Grand Total
                foreach (['1', '2'] as $rowNum) {
                    $color = ($rowNum == '1') ? 'BDD7EE' : 'FFFF00';
                    $sheet->getStyle("A{$rowNum}:{$lastCol}{$rowNum}")->applyFromArray([
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['ARGB' => $color]],
                        'font' => ['bold' => true],
                        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                    ]);
                }

                if ($lastRow >= 3) {
                    $sheet->getStyle("A3:{$lastCol}{$lastRow}")->applyFromArray([
                        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                    ]);
                }

                foreach (range('A', $lastCol) as $col) {
                    $sheet->getColumnDimension($col)->setAutoSize(true);
                }
            },
        ];
    }

    public function title(): string
    {
        return 'Summary Produksi';
    }
}

// ============================================================
// SHEET 3: JURNAL — REPAIR TEMPLATE (MENIRU STRUKTUR JOIN)
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
            'D' => '0.00',         // No Akun sebagai Teks/Desimal Terkunci agar .00 tidak hilang
            'K' => '#,##0',        // Banyak
            'L' => '#,##0.0000',   // M3: 4 desimal
            'M' => '#,##0.00',     // Harga
            'N' => '#,##0',        // Total
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $lastRow = $sheet->getHighestRow();
        $sheet->getStyle('A1:N1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'name' => 'Calibri', 'size' => 11],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '9999FF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'FFFFFF']]]
        ]);

        if ($lastRow > 1) {
            $sheet->getStyle("A2:N{$lastRow}")->applyFromArray(['borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]]);
            $sheet->getStyle("D2:D{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("B2:G{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("I2:J{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("K2:N{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        }
    }

    private function normalizeJenis(string $jenis): string
    {
        return str_contains(strtolower(trim($jenis)), 'sengon') ? 'sengon' : 'meranti';
    }

    private function getHargaPatok(string $jenis, float $tebal, bool $isAf = false): int
    {
        $jns = $this->normalizeJenis($jenis);
        // Jika PCC (AF), gunakan harga khusus sesuai master rule
        if ($isAf) {
            return ($jns === 'sengon') ? 1500000 : 1800000;
        }
        $kelompok = ($tebal < 1) ? 'faceback' : 'core';
        $harga = [
            'sengon' => ['faceback' => 2800000, 'core' => 2250000],
            'meranti' => ['faceback' => 2800000, 'core' => 2800000],
        ];
        return $harga[$jns][$kelompok] ?? 0;
    }

    private function makeRow($namaAkun, $tgl, $noAkun, $keterangan, $map, $banyak, $m3, $harga, $total, $hitKbk = 'm'): array
    {
        return [
            $namaAkun,
            (string)$tgl,
            '',
            (string)$noAkun,
            '',
            '',
            'tembel', // Nama aktivitas disesuaikan dengan konteks repair (tembel)
            $keterangan,
            strtolower($map),
            strtolower($hitKbk),
            (float) $banyak,
            (float) $m3,
            (float) $harga,
            (float) $total
        ];
    }

    public function array(): array
    {
        $rows = [];
        $rows[] = ['Nama Akun', 'tgl', 'jurnal', 'No Akun', 'No', 'mm', 'Nama', 'Keterangan', 'map', 'hit kbk', 'Banyak', 'M3', 'Harga', 'Total'];

        foreach ($this->rawCollection as $produksi) {
            $tglFormat = Carbon::parse($produksi->tanggal)->format('d-m-Y');
            $totalDebit = 0;
            $totalKredit = 0;
            $jurnalBlock = [];

            // ============================================================
            // 1. DEBIT: Hasil Repair (Barang Jadi) - SESUAI STRUKTUR RENCANA
            // ============================================================
            foreach ($produksi->hasilRepairs as $hasil) {
                // 1. Tarik jembatan rencana repair-nya
                $rencanaRepair = $hasil->rencanaRepair;

                // 2. Tarik modal repair melalui relasi 'modalRepairs' (sesuai nama fungsi di model Anda)
                $modal = $rencanaRepair?->modalRepairs;

                // 🚀 PROTEKSI: Jika jembatan rencana atau modalnya tidak ketemu/null, skip agar tidak crash
                if (!$modal || !$modal->ukuran || !$modal->jenisKayu) {
                    continue;
                }

                $ukuran = $modal->ukuran;
                $namaKayuAsli = $modal->jenisKayu->nama_kayu ?? '';
                $jnsNorm = $this->normalizeJenis($namaKayuAsli);

                // Cek status AF/PCC bisa dari RencanaRepair (kolom kw) atau dari ModalRepair asli
                $kwStatus = strtolower(($rencanaRepair->kw ?? $modal->kw) ?? '');
                $isAf = str_contains($kwStatus, 'af');

                // Amankan nilai dimensi ukuran kayu
                $panjang = (float)($ukuran->panjang ?? 0);
                $lebar   = (float)($ukuran->lebar ?? 0);
                $tebal   = (float)($ukuran->tebal ?? 0);

                // Hitung Volume (M3) berdasarkan Jumlah dari tabel HASIL REPAIR
                $m3 = ($panjang * $lebar * $tebal * $hasil->jumlah) / 10000000;

                // Ambil harga patok berdasarkan jenis kayu & tebal
                $hargaPatok = $this->getHargaPatok($jnsNorm, $tebal, $isAf);
                $totalValue = $m3 * $hargaPatok;

                // Penentuan No Akun & Nama Akun untuk DEBIT (Hasil Jadi)
                $noAkun = $isAf ? '1472.00' : ($jnsNorm === 'sengon' ? '1466.00' : '1467.00');

                // Format teks nama akun (Capitalized pada nama jenis kayu)
                $namaAkun = $isAf
                    ? "Veneer Jadi ppc " . strtolower(ucfirst($jnsNorm)) . " WJY"
                    : "Veneer Jadi 130 core " . strtolower(ucfirst($jnsNorm)) . " WJY";

                // Format keterangan mengikuti Excel asli (130 Core [jenis] uk [tebal] atau af [jenis] [dimensi])
                $keterangan = $isAf
                    ? "af " . strtolower($namaKayuAsli) . " " . $panjang . " x " . $lebar . " x " . $tebal
                    : "130 Core " . strtolower($namaKayuAsli) . " uk " . $panjang . " x " . $lebar . " x " . $tebal;

                // Masukkan ke array Jurnal (m = Hasil/Sortimen/Modal)
                $jurnalBlock[] = $this->makeRow($namaAkun, $tglFormat, $noAkun, $keterangan, 'd', $hasil->jumlah, $m3, $hargaPatok, $totalValue, 'm');
                $totalDebit += $totalValue;
            }

            // ============================================================
            // 2. KREDIT: Modal Repair (Bahan Mentah Keluar) - ANTI CRASH
            // ============================================================
            foreach ($produksi->modalRepairs as $modal) {
                // 🚀 PROTEKSI 1: Jika relasi ukuran atau jenis kayu null, skip data ini agar tidak crash
                if (!$modal->ukuran || !$modal->jenisKayu) {
                    continue;
                }

                $ukuran = $modal->ukuran;
                $namaKayuAsli = $modal->jenisKayu->nama_kayu ?? '';
                $jnsNorm = $this->normalizeJenis($namaKayuAsli);
                $isAf = str_contains(strtolower($modal->kw ?? ''), 'af');

                // Amankan nilai dimensi
                $panjang = (float)($ukuran->panjang ?? 0);
                $lebar   = (float)($ukuran->lebar ?? 0);
                $tebal   = (float)($ukuran->tebal ?? 0);

                $m3 = ($panjang * $lebar * $tebal * $modal->jumlah) / 10000000;
                $hargaPatok = $this->getHargaPatok($jnsNorm, $tebal, $isAf);
                $totalValue = $m3 * $hargaPatok;

                // Penentuan No Akun & Nama Akun
                $noAkun = $isAf ? '1472.00' : ($jnsNorm === 'sengon' ? '1441.00' : '1447.00');

                // 🚀 PENYESUAIAN TEKS: Mengikuti gambar (Veneer Kering 130 core [Capitalized] WJY)
                $namaAkun = $isAf
                    ? "Veneer Jadi ppc " . strtolower(ucfirst($jnsNorm)) . " WJY"
                    : "Veneer Kering 130 core " . strtolower(ucfirst($jnsNorm)) . " WJY";

                // 🚀 PENYESUAIAN KETERANGAN: Mengikuti gambar (130 Core [Nama Kayu] uk [Tebal])
                $keterangan = $isAf
                    ? "af " . strtolower($namaKayuAsli) . " " . $panjang . " x " . $lebar . " x " . $tebal
                    : "130 core " . strtolower($namaKayuAsli) . " uk " . $panjang . " x " . $lebar . " x " . $tebal;

                // Jika ada catatan kehilangan seperti di gambar (misal ada kolom status/keterangan di modal)
                if (str_contains(strtolower($modal->keterangan ?? ''), 'kehilangan')) {
                    $keterangan .= " // kehilangan";
                }

                $jurnalBlock[] = $this->makeRow($namaAkun, $tglFormat, $noAkun, $keterangan, 'k', $modal->jumlah, $m3, $hargaPatok, $totalValue, 'm');
                $totalKredit += $totalValue;
            }
            // ============================================================
            // 4. KREDIT: Gaji Pegawai Repair (Membaca RencanaPegawai)
            // ============================================================
            $jmlPekerja = (int)$produksi->rencanaPegawais->count();
            if ($jmlPekerja > 0) {
                $totalGaji = $jmlPekerja * 150000;
                $jurnalBlock[] = $this->makeRow('Hutang Gaji', $tglFormat, '2231.00', '', 'k', $jmlPekerja, 0, 150000, $totalGaji, 'b');
                $totalKredit += $totalGaji;
            }

            // ============================================================
            // 5. DEBIT/PENYEIMBANG: HPP Repair
            // ============================================================
            $selisih = $totalDebit - $totalKredit;
            if (round($selisih, 2) != 0) {
                $jurnalBlock[] = $this->makeRow('hpp triplek', $tglFormat, '6111.00', '', 'd', 0, 0, abs($selisih), abs($selisih), 'm');
            }

            // Konsolidasi ke output sheet utama
            foreach ($jurnalBlock as $row) {
                $rows[] = $row;
            }
            $rows[] = array_fill(0, 14, ''); // Baris kosong pembatas antar tanggal produksi
        }
        return $rows;
    }
}
