<?php

namespace App\Exports;

use App\Models\JenisKayu;
use App\Models\KategoriBarang;
use App\Models\Mesin;
use App\Models\ReferensiHargaProduksi;
use App\Services\Akuntansi\RotaryJurnalService;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

// ============================================================
// TAMBAHKAN class ini ke file LaporanProduksiExport.php (Rotary)
// dan daftarkan di LaporanProduksiExport::sheets():
//
//     return [
//         new LaporanProduksiDetailSheet($data),
//         new LaporanProduksiRekapSheet($this->tanggal),
//         new LaporanProduksiJurnalSheet($this->tanggal),
//         new LaporanProduksiJurnalSheetV2($this->tanggal), // <-- baru
//     ];
//
// ============================================================
// SHEET: JURNAL BARU — COA GENERAL (mengikuti pola JurnalSheetV2
// pada LaporanJoinExport):
// - Veneer Basah digabung (tanpa split sengon/meranti/WHN), hanya
//   dibedakan Face/Back vs Core.
// - Nomor & nama akun mengikuti COA baru (1402.x, 2195.x, 5069.x).
// - Selisih debit-kredit -> 5069.2 Selisih harga patok produksi
//   (akun ini DE di COA baru, jadi ditaruh di sisi debit, sama
//   seperti pola di JurnalSheetV2 milik LaporanJoinExport).
// - Akun kayu/logcore (115-01/02, 1411.xx, 1413.xx, 1414.00) BELUM
//   dipetakan karena item pemasok sudah di-skip lebih awal di
//   collection() -> kemungkinan besar tidak pernah muncul di sini.
//   Kalau ternyata masih kepakai, tinggal tambahkan case di
//   getAkunKayu() (lihat TODO di bawah) lalu panggil dari titik
//   yang sama seperti isWood pada versi lama.
// ============================================================
class LaporanProduksiJurnalSheetV2 extends DefaultValueBinder implements FromCollection, WithCustomValueBinder, WithEvents, WithStyles, WithTitle
{
    protected $tanggal;

    protected $titleRows = [];

    protected $headerRows = [];

    protected $dataRanges = [];

    // Cache agar tidak query DB berulang
    private array $kayuCache = [];

    private array $kategoriCache = [];

    public function bindValue(Cell $cell, $value)
    {
        if ($cell->getColumn() === 'D') {
            // No Akun sebagai TEKS -> "1402.3" tidak dikonversi jadi angka/koma
            $cell->setValueExplicit((string) $value, DataType::TYPE_STRING);

            return true;
        }

        return parent::bindValue($cell, $value);
    }

    public function __construct($tanggal)
    {
        $this->tanggal = $tanggal;
    }

    // =========================================================================
    // DATABASE REFERENCE HELPERS (identik dengan LaporanProduksiJurnalSheet)
    // =========================================================================

    private function getIdKayuByNama(string $namaKayu): ?int
    {
        $key = strtolower(trim($namaKayu));
        if (! array_key_exists($key, $this->kayuCache)) {
            $kayu = JenisKayu::where('nama_kayu', $namaKayu)->first();
            $this->kayuCache[$key] = $kayu?->id;
        }

        return $this->kayuCache[$key];
    }

    private function getIdKategoriBarang(string $namaKategori): ?int
    {
        $key = strtolower(trim($namaKategori));
        if (! array_key_exists($key, $this->kategoriCache)) {
            try {
                $kategori = KategoriBarang::whereRaw('LOWER(nama_kategori) LIKE ?', ["%{$key}%"])->first();
                $this->kategoriCache[$key] = $kategori?->id;
            } catch (Throwable $e) {
                $this->kategoriCache[$key] = null;
            }
        }

        return $this->kategoriCache[$key];
    }

    private function getHargaVeneerBasahDb(string $jenisKayu, bool $isCore): float
    {
        $idJenisKayu = $this->getIdKayuByNama($jenisKayu);
        $idKategoriBarang = $this->getIdKategoriBarang('veneer basah');

        if (! $idJenisKayu || ! $idKategoriBarang) {
            return 0.0;
        }

        $tebalRepresentatif = $isCore ? 1.5 : 0.5;

        $ref = ReferensiHargaProduksi::findReferensi(
            idJenisKayu      : $idJenisKayu,
            idKategoriBarang : $idKategoriBarang,
            kw               : null,
            tebal            : $tebalRepresentatif,
        );

        return (float) ($ref->harga ?? 0.0);
    }

    /**
     * Akun Persediaan Veneer Basah sesuai COA baru.
     * Sengon & meranti DIGABUNG -> hanya dibedakan Face/Back vs Core.
     */
    private function getAkunVeneerBasah(bool $isCore): array
    {
        return $isCore
            ? ['1402.4', 'Persediaan Veneer Basah Core']
            : ['1402.3', 'Persediaan Veneer Basah Face Back'];
    }

    // TODO: jika akun kayu (115-01/02, 1411.xx, 1413.xx, 1414.00) ternyata
    // masih muncul di data, tambahkan mapping ke COA baru di sini, misalnya:
    // private function getAkunKayu(string $noAkunLama): array
    // {
    //     return match ($noAkunLama) {
    //         '115-01', '1411.01' => ['1402.1', 'Persediaan kayu 130'],
    //         '115-02', '1411.02' => ['1402.2', 'Persediaan kayu 260'],
    //         default => [$noAkunLama, $noAkunLama],
    //     };
    // }

    public function collection()
    {
        $rows = collect();
        $service = new RotaryJurnalService;
        $payload = $service->buildJurnalPayloadPreview($this->tanggal);

        if (! $payload || empty($payload['jurnal_items'])) {
            $rows->push(['Tidak ada data jurnal produksi untuk tanggal ini.']);

            return $rows;
        }

        $rawRows = [];
        $mesins = Mesin::all()->keyBy(fn ($m) => strtoupper(trim($m->nama_mesin)));

        foreach ($payload['jurnal_items'] as $item) {
            $namaAkun = $item['nama_akun'];
            $noAkun = $item['no_akun'];
            $mapDK = $item['map'];

            if ($noAkun === '510-01') {
                continue;
            }

            foreach ($item['items'] as $subItem) {
                $bagian = '-';
                $keteranganSpesifikasi = $subItem['keterangan'] ?? '-';

                if (($subItem['jenis_pihak'] ?? '') === 'produksi') {
                    $bagian = $subItem['nama_pihak'] ?? '-';
                    if (($subItem['nama_barang'] ?? '') !== 'Mesin' && ($subItem['nama_barang'] ?? '') !== '-') {
                        $keteranganSpesifikasi = $subItem['nama_barang'] ?? '-';
                    } else {
                        $keteranganSpesifikasi = ($subItem['keterangan'] ?? '').' ('.($subItem['ukuran'] ?? '').')';
                    }
                } elseif (($subItem['jenis_pihak'] ?? '') === 'karyawan') {
                    $parts = explode(' - ', $subItem['keterangan'] ?? '');
                    $bagian = count($parts) > 1 ? trim($parts[1]) : '-';
                    $keteranganSpesifikasi = '';
                } elseif (($subItem['jenis_pihak'] ?? '') === 'pemasok') {
                    continue;
                } else {
                    $bagian = '-';
                    $keteranganSpesifikasi = $subItem['keterangan'] ?? '-';
                }

                $tipe = ($subItem['jenis_pihak'] ?? '') === 'produksi' ? 'm' : 'b';

                $banyak = $subItem['banyak'];
                if (($subItem['jenis_pihak'] ?? '') === 'karyawan') {
                    $banyak = 1;
                }

                $volume = $subItem['m3'];
                $harga = $subItem['harga'];
                $jumlah = $subItem['jumlah'];

                if (($subItem['jenis_pihak'] ?? '') === 'produksi') {
                    $namaM = strtoupper(trim($bagian));
                    $jenisHasil = isset($mesins[$namaM]) ? $mesins[$namaM]->jenis_hasil : 'core';

                    $keterangan = $subItem['keterangan'] ?? '';
                    $parts = explode(' - ', $keterangan);
                    $namaKayu = count($parts) > 2 ? trim($parts[2]) : '';

                    $isCore = strtolower($jenisHasil) !== 'f/b';
                    $ongkos = $this->getHargaVeneerBasahDb($namaKayu, $isCore);

                    if ($ongkos === 0.0) {
                        $ongkos = isset($mesins[$namaM]) ? (float) ($mesins[$namaM]->ongkos_mesin ?? 0) : 0;
                    }

                    $harga = $ongkos;
                    $jumlah = $volume !== null ? round((float) $volume * $ongkos, 4) : null;
                }

                if (($subItem['jenis_pihak'] ?? '') === 'karyawan') {
                    $harga = 150_000;
                    $jumlah = 150_000;
                }

                // --- Mapping akun ke COA baru (menggantikan blok isWHN lama) ---
                $mappedNoAkun = $noAkun;
                $mappedNamaAkun = $namaAkun;

                if (in_array($noAkun, ['115-07', '1421.00', '1421.01', '1422.00', '1422.01'], true)) {
                    [$mappedNoAkun, $mappedNamaAkun] = $this->getAkunVeneerBasah(false);
                } elseif (in_array($noAkun, ['115-08', '1426.00', '1426.01', '1427.00', '1427.01'], true)) {
                    [$mappedNoAkun, $mappedNamaAkun] = $this->getAkunVeneerBasah(true);
                } elseif (in_array($noAkun, ['210-02', '2231.00'], true)) {
                    $mappedNoAkun = '2195.1';
                    $mappedNamaAkun = 'Hutang Gaji';
                }
                // TODO: tambahkan branch untuk akun kayu/logcore di sini bila diperlukan
                // (lihat getAkunKayu() di atas).

                $rawRows[] = [
                    'nama_akun' => $mappedNamaAkun,
                    'no_akun' => $mappedNoAkun,
                    'bagian' => $bagian,
                    'keterangan' => $keteranganSpesifikasi,
                    'dk' => $mapDK,
                    'tipe' => $tipe,
                    'banyak' => $banyak !== null ? (float) $banyak : null,
                    'volume' => $volume !== null ? (float) $volume : null,
                    'harga' => $harga !== null ? (float) $harga : null,
                    'jumlah' => $jumlah !== null ? (float) $jumlah : null,
                ];
            }
        }

        $rawRowsByMachine = [];
        foreach ($rawRows as $row) {
            $machine = $row['bagian'];
            if ($machine === '-') {
                continue;
            }
            $rawRowsByMachine[$machine][] = $row;
        }

        $machineTables = [];
        foreach ($rawRowsByMachine as $machine => $rowsOfMachine) {
            $grouped = [];
            $totalDebit = 0.0;
            $totalKredit = 0.0;

            foreach ($rowsOfMachine as $row) {
                $key = implode('|', [
                    $row['no_akun'], $row['keterangan'], $row['dk'], $row['tipe'], $row['nama_akun'],
                ]);

                if (! isset($grouped[$key])) {
                    $grouped[$key] = [
                        'nama_akun' => $row['nama_akun'],
                        'no_akun' => $row['no_akun'],
                        'bagian' => $machine,
                        'keterangan' => $row['keterangan'],
                        'dk' => $row['dk'],
                        'tipe' => $row['tipe'],
                        'banyak' => 0.0,
                        'volume' => 0.0,
                        'harga' => $row['harga'],
                        'jumlah' => 0.0,
                        'has_qty' => $row['banyak'] !== null,
                        'has_vol' => $row['volume'] !== null,
                    ];
                }

                if ($row['banyak'] !== null) {
                    $grouped[$key]['banyak'] += $row['banyak'];
                    $grouped[$key]['has_qty'] = true;
                }
                if ($row['volume'] !== null) {
                    $grouped[$key]['volume'] += $row['volume'];
                    $grouped[$key]['has_vol'] = true;
                }
                if ($row['jumlah'] !== null) {
                    $grouped[$key]['jumlah'] += $row['jumlah'];
                }
            }

            foreach ($grouped as $g) {
                $isVeneer = in_array($g['no_akun'], ['1402.3', '1402.4'], true);
                $isHutangGaji = $g['no_akun'] === '2195.1';

                $rowHarga = 0.0;
                if ($isVeneer) {
                    $isCoreVal = $g['no_akun'] === '1402.4';
                    $namaAkunVal = strtolower($g['nama_akun'] ?? '');
                    // Sengon/meranti sudah digabung -> pakai default harga
                    // referensi dengan jenis kayu "Meranti" sbg representatif
                    // jika DB tidak menemukan by jenis spesifik. Sesuaikan bila
                    // referensi hargamu memang membedakan per jenis kayu.
                    $dbHarga = $this->getHargaVeneerBasahDb('Meranti', $isCoreVal);
                    $rowHarga = $dbHarga > 0 ? $dbHarga : ($isCoreVal ? 2100000.0 : 8000000.0);
                } elseif ($isHutangGaji) {
                    $rowHarga = 150000.0;
                } else {
                    $rowHarga = (float) ($g['harga'] ?? 0.0);
                }

                $rowTotal = 0.0;
                if ($g['has_vol'] && $g['volume'] !== null && $g['volume'] > 0) {
                    $rowTotal = round((float) $g['volume'], 4) * $rowHarga;
                } elseif ($g['has_qty'] && $g['banyak'] !== null && $g['banyak'] > 0) {
                    $rowTotal = (float) $g['banyak'] * $rowHarga;
                } else {
                    $rowTotal = $rowHarga;
                }

                if ($g['dk'] === 'd') {
                    $totalDebit += $rowTotal;
                } else {
                    $totalKredit += $rowTotal;
                }
            }

            // Selisih -> 5069.2 Selisih harga patok produksi (akun DE -> debit)
            $selisih = round($totalDebit - $totalKredit, 2);
            if ($selisih != 0) {
                $grouped[] = [
                    'nama_akun' => 'Selisih harga patok produksi',
                    'no_akun' => '5069.2',
                    'bagian' => $machine,
                    'keterangan' => '',
                    'dk' => 'd',
                    'tipe' => 'b',
                    'banyak' => null,
                    'volume' => null,
                    'harga' => null,
                    'jumlah' => abs($selisih),
                    'has_qty' => false,
                    'has_vol' => false,
                ];
            }

            $machineTables[$machine] = $grouped;
        }

        $dateStr = Carbon::parse($this->tanggal)->format('Ymd');
        $currentRow = 1;

        foreach ($machineTables as $machine => $groupedRows) {
            $noJurnal = 'ROT/'.$dateStr.'/'.strtoupper(str_replace(' ', '', $machine));
            $rows->push(['No. Jurnal: '.$noJurnal, '', '', '', '', '', '', '', '', '', '', '', '', '']);
            $this->titleRows[] = $currentRow;
            $currentRow++;

            $rows->push(['Nama Akun', 'tgl', 'jurnal', 'No Akun', 'No', 'mm', 'Nama', 'Keterangan', 'map', 'hit kbk', 'Banyak', 'M3', 'Harga', 'Total']);
            $this->headerRows[] = $currentRow;
            $currentRow++;

            $dataStart = $currentRow;
            $tglVal = Carbon::parse($this->tanggal)->format('d-m-Y');

            foreach ($groupedRows as $g) {
                $isVeneer = in_array($g['no_akun'], ['1402.3', '1402.4'], true);
                $isHutangGaji = $g['no_akun'] === '2195.1';

                $namaVal = $isVeneer ? 'kupasan (m - '.strtolower($g['bagian']).')' : 'kupasan';

                $hitKbkVal = '';
                if ($isVeneer) {
                    $hitKbkVal = 'm';
                } elseif ($isHutangGaji) {
                    $hitKbkVal = 'b';
                }

                $hargaVal = null;
                if ($isVeneer) {
                    $isCoreVal = $g['no_akun'] === '1402.4';
                    $dbHarga = $this->getHargaVeneerBasahDb('Meranti', $isCoreVal);
                    $hargaVal = $dbHarga > 0 ? $dbHarga : ($isCoreVal ? 2100000 : 8000000);
                } elseif ($isHutangGaji) {
                    $hargaVal = 150000;
                } else {
                    $hargaVal = $g['jumlah'];
                }

                $totalVal = "=IF(J{$currentRow}=\"m\",M{$currentRow}*L{$currentRow},IF(J{$currentRow}=\"b\",M{$currentRow}*K{$currentRow},M{$currentRow}))";

                $rows->push([
                    $g['nama_akun'],
                    $tglVal,
                    '',
                    $g['no_akun'],
                    '',
                    '',
                    $namaVal,
                    $g['keterangan'],
                    $g['dk'],
                    $hitKbkVal,
                    $g['has_qty'] ? $g['banyak'] : null,
                    $g['has_vol'] ? round($g['volume'], 4) : null,
                    $hargaVal,
                    $totalVal,
                ]);
                $currentRow++;
            }

            $dataEnd = $currentRow - 1;
            $this->dataRanges[] = ['start' => $dataStart, 'end' => $dataEnd];

            $rows->push(['', '', '', '', '', '', '', '', '', '', '', '', '', '']);
            $rows->push(['', '', '', '', '', '', '', '', '', '', '', '', '', '']);
            $currentRow += 2;
        }

        return $rows;
    }

    public function title(): string
    {
        return 'jurnal produksi v2';
    }

    public function styles(Worksheet $sheet)
    {
        return [];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                foreach ($this->titleRows as $row) {
                    $sheet->mergeCells("A{$row}:N{$row}");
                    $sheet->getStyle("A{$row}:N{$row}")->applyFromArray([
                        'font' => ['bold' => true, 'size' => 11, 'color' => ['argb' => 'FF1D2939']],
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFD2F0DA']],
                        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                    ]);
                    $sheet->getRowDimension($row)->setRowHeight(25);
                }

                foreach ($this->headerRows as $row) {
                    $sheet->getStyle("A{$row}:N{$row}")->applyFromArray([
                        'font' => ['bold' => true, 'size' => 10],
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFE5E8EB']],
                        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                    ]);
                    $sheet->getRowDimension($row)->setRowHeight(25);
                }

                foreach ($this->dataRanges as $range) {
                    $start = $range['start'];
                    $end = $range['end'];
                    if ($start > $end) {
                        continue;
                    }

                    $sheet->getStyle("A{$start}:N{$end}")->applyFromArray([
                        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                    ]);

                    $sheet->getStyle("A{$start}:A{$end}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                    $sheet->getStyle("B{$start}:F{$end}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle("G{$start}:H{$end}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                    $sheet->getStyle("I{$start}:J{$end}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle("K{$start}:N{$end}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

                    $sheet->getStyle("K{$start}:K{$end}")->getNumberFormat()->setFormatCode('#,##0');
                    $sheet->getStyle("L{$start}:L{$end}")->getNumberFormat()->setFormatCode('#,##0.0000');
                    $sheet->getStyle("M{$start}:M{$end}")->getNumberFormat()->setFormatCode('#,##0');
                    $sheet->getStyle("N{$start}:N{$end}")->getNumberFormat()->setFormatCode('#,##0');
                }

                $sheet->getColumnDimension('A')->setWidth(30);
                $sheet->getColumnDimension('B')->setWidth(15);
                $sheet->getColumnDimension('C')->setWidth(12);
                $sheet->getColumnDimension('D')->setWidth(12);
                $sheet->getColumnDimension('E')->setWidth(10);
                $sheet->getColumnDimension('F')->setWidth(10);
                $sheet->getColumnDimension('G')->setWidth(30);
                $sheet->getColumnDimension('H')->setWidth(40);
                $sheet->getColumnDimension('I')->setWidth(10);
                $sheet->getColumnDimension('J')->setWidth(10);
                $sheet->getColumnDimension('N')->setWidth(18);
            },
        ];
    }
}
