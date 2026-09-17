<?php

namespace App\Exports;

use App\Models\DetailTurusanKayu;
use App\Models\HppAverageLog;
use App\Models\JenisKayu;
use App\Models\Mesin;
use App\Models\ProduksiRotary;
use App\Models\ReferensiHargaProduksi;
use App\Services\Akuntansi\RotaryJurnalService;
use App\Services\CoaAliasService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
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
// FILE INI TIDAK MENGUBAH LaporanKayuKeluarExport.php SAMA SEKALI.
// Ini murni COPY dari sheet-sheet jurnal yang ada, dengan:
// - Nama class baru (akhiran V2)
// - Judul sheet baru (title() beda dari aslinya)
// - No. Jurnal baru (ditambah suffix _V2 supaya gampang dibedakan)
// - Mapping akun mengikuti COA baru (1402.x / 2195.1 / 5069.2)
//
// NOTE REFACTOR: Semua akun COA baru (kayu keluar, veneer basah, hutang
// gaji, selisih HPP) sekarang diambil dari App\Services\CoaAliasService,
// menggantikan LaporanKayuKeluarExportV2Helper yang lama, supaya satu
// sumber kebenaran bersama sheet v2 lain (dryer/hotpress/repair/kedi/
// kayu masuk).
//
// Cara pakai: daftarkan salah satu/semua class di bawah ini ke
// method sheets() milik LaporanKayuKeluarExport (atau export lain
// yang kamu pakai), TANPA menghapus sheet lama, misalnya:
//
//     public function sheets(): array
//     {
//         return [
//             new LaporanProduksiKayuHabisSheet($this->tanggal),   // lama, tidak diubah
//             new LaporanProduksiKayuHabisSheetV2($this->tanggal), // baru
//         ];
//     }
// ============================================================

// ============================================================
// COPY dari LaporanProduksiJurnalGabungSheet -> versi COA baru
// ============================================================
class LaporanProduksiJurnalGabungSheetV2 extends DefaultValueBinder implements FromCollection, WithCustomValueBinder, WithEvents, WithStyles, WithTitle
{
    protected $tanggal;

    protected $titleRows = [];

    protected $headerRows = [];

    protected $dataRanges = [];

    protected CoaAliasService $coaAlias;

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

    public function __construct($tanggal, ?CoaAliasService $coaAlias = null)
    {
        $this->tanggal = $tanggal;
        $this->coaAlias = $coaAlias ?? app(CoaAliasService::class);
    }

    // =========================================================================
    // DATABASE REFERENCE HELPERS (sama seperti SheetV2)
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
            idJenisKayu: $idJenisKayu,
            idKategoriBarang: $idKategoriBarang,
            kw: null,
            tebal: $tebalRepresentatif,
        );

        return (float) ($ref->harga ?? 0.0);
    }

    private function hargaVeneerBasah(bool $isCore): float
    {
        // Sengon/meranti sudah digabung di COA baru -> pakai "Meranti"
        // sebagai jenis representatif untuk cari referensi harga.
        $dbHarga = $this->getHargaVeneerBasahDb('Meranti', $isCore);

        return $dbHarga > 0 ? $dbHarga : ($isCore ? 2100000.0 : 8000000.0);
    }

    // =========================================================================
    // HELPER KLASIFIKASI AKUN (semua lewat CoaAliasService)
    // =========================================================================

    private function isVeneerBasah(string $noAkun): bool
    {
        return in_array($noAkun, $this->coaAlias->nomorAkunVeneerBasah(), true);
    }

    private function isVeneerCore(string $noAkun): bool
    {
        [$noCore] = $this->coaAlias->getAkunVeneerBasah(true);

        return $noAkun === $noCore;
    }

    private function isHutangGaji(string $noAkun): bool
    {
        return in_array($noAkun, $this->coaAlias->nomorAkunHutangGaji(), true);
    }

    private function isKayu(string $noAkun): bool
    {
        return in_array($noAkun, $this->coaAlias->nomorAkunKayu(), true);
    }

    // =========================================================================
    // COLLECTION
    // =========================================================================

    public function collection()
    {
        $rows = collect();
        $service = new RotaryJurnalService;
        $payload = $service->buildJurnalPayloadPreview($this->tanggal);

        if (! $payload || empty($payload['jurnal_items'])) {
            $rows->push(['Tidak ada data jurnal produksi untuk tanggal ini.']);

            return $rows;
        }

        $akunHppNo = $this->coaAlias->getAkunHpp()['no'];
        $rawRows = [];
        $mesins = Mesin::all()->keyBy(fn ($m) => strtoupper(trim($m->nama_mesin)));

        foreach ($payload['jurnal_items'] as $item) {
            $namaAkun = $item['nama_akun'];
            $noAkun = $item['no_akun'];
            $mapDK = $item['map'];

            // 510-01 (HPP lama) tetap di-skip: selisih dihitung ulang di bawah
            if ($noAkun === '510-01') {
                continue;
            }

            foreach ($item['items'] as $subItem) {
                $bagian = '-';
                $keteranganSpesifikasi = $subItem['keterangan'] ?? '-';
                $jenisPihak = $subItem['jenis_pihak'] ?? '';
                $dkOverride = null;

                // ---- Mapping akun default: lewat alias service ----
                $mapped = $this->coaAlias->mapAkunLama($noAkun, $namaAkun);
                $mappedNoAkun = $mapped['no'];
                $mappedNamaAkun = $mapped['nama'];

                if ($jenisPihak === 'produksi') {
                    $bagian = $subItem['nama_pihak'] ?? '-';
                    if (($subItem['nama_barang'] ?? '') !== 'Mesin' && ($subItem['nama_barang'] ?? '') !== '-') {
                        $keteranganSpesifikasi = $subItem['nama_barang'] ?? '-';
                    } else {
                        $keteranganSpesifikasi = ($subItem['keterangan'] ?? '').' ('.($subItem['ukuran'] ?? '').')';
                    }
                } elseif ($jenisPihak === 'karyawan') {
                    $parts = explode(' - ', $subItem['keterangan'] ?? '');
                    $bagian = count($parts) > 1 ? trim($parts[1]) : '-';
                    $keteranganSpesifikasi = '';
                } elseif ($jenisPihak === 'pemasok') {
                    // Dulu di-skip. Sekarang dipetakan ke akun kayu COA baru.
                    $parts = explode(' - ', $subItem['keterangan'] ?? '');
                    $bagian = count($parts) > 1 ? trim($parts[1]) : '-';

                    $panjang = ($noAkun === '115-01') ? 130 : 260;
                    $jenisKayu = $parts[0] ?? '';
                    $akunKayu = $this->coaAlias->getAkunKayuMasuk($jenisKayu, $panjang);
                    $mappedNoAkun = $akunKayu['no'];
                    $mappedNamaAkun = $akunKayu['nama'];

                    $lahanName = $subItem['nama_pihak'] ?? '';
                    $lahanLabel = stripos($lahanName, 'Lahan ') === 0 ? substr($lahanName, 6) : $lahanName;
                    // Nama jenis kayu asli tetap ditulis di Keterangan
                    $keteranganSpesifikasi = 'lahan '.$lahanLabel.' - '.($jenisKayu !== '' ? $jenisKayu : '-');
                    $dkOverride = 'k';

                    if (empty($subItem['id_barang'])) {
                        try {
                            $urlApi = rtrim(config('services.akuntansi.url', 'http://localhost:8080'), '/').'/api/barang/resolve-kayu';
                            $responseApi = Http::withoutVerifying()->timeout(5)->get($urlApi, [
                                'ukuran' => $panjang,
                                'jenis_kayu' => $jenisKayu,
                            ]);
                            if ($responseApi->successful()) {
                                $subItem['id_barang'] = $responseApi->json('id_barang');
                            }
                        } catch (\Throwable $e) {
                            Log::warning('Gagal resolve id_barang kayu: '.$e->getMessage());
                        }
                    }
                } else {
                    $bagian = '-';
                    $keteranganSpesifikasi = $subItem['keterangan'] ?? '-';
                }

                $tipe = $jenisPihak === 'produksi' ? 'm' : 'b';

                $banyak = $subItem['banyak'];
                if ($jenisPihak === 'karyawan') {
                    $banyak = 1;
                }

                $volume = $subItem['m3'];
                $harga = $subItem['harga'];
                $jumlah = $subItem['jumlah'];

                if ($jenisPihak === 'produksi') {
                    $namaM = strtoupper(trim($bagian));
                    $jenisHasil = isset($mesins[$namaM]) ? $mesins[$namaM]->jenis_hasil : 'core';
                    $isCore = strtolower($jenisHasil) !== 'f/b';

                    $keterangan = $subItem['keterangan'] ?? '';
                    $partsK = explode(' - ', $keterangan);
                    $namaKayu = count($partsK) > 2 ? trim($partsK[2]) : '';

                    $ongkos = $namaKayu !== ''
                        ? $this->getHargaVeneerBasahDb($namaKayu, $isCore)
                        : 0.0;

                    if ($ongkos === 0.0) {
                        $ongkos = $this->getHargaVeneerBasahDb('Meranti', $isCore);
                    }
                    if ($ongkos === 0.0) {
                        $ongkos = isset($mesins[$namaM]) ? (float) ($mesins[$namaM]->ongkos_mesin ?? 0) : 0;
                    }

                    $harga = $ongkos;
                    $jumlah = $volume !== null ? round((float) $volume * $ongkos, 4) : null;

                    [$mappedNoAkun, $mappedNamaAkun] = $this->coaAlias->getAkunVeneerBasah($isCore);
                }

                if ($jenisPihak === 'karyawan') {
                    $akunGaji = $this->coaAlias->getAkunGaji(false);
                    $harga = 150_000;
                    $jumlah = 150_000;
                    $mappedNoAkun = $akunGaji['hutang']['no'];
                    $mappedNamaAkun = $akunGaji['hutang']['nama'];
                }

                $rawRows[] = [
                    'nama_akun' => $mappedNamaAkun,
                    'no_akun' => $mappedNoAkun,
                    'bagian' => $bagian,
                    'keterangan' => $keteranganSpesifikasi,
                    'dk' => $dkOverride ?? $mapDK,
                    'tipe' => $tipe,
                    'banyak' => $banyak !== null ? (float) $banyak : null,
                    'volume' => $volume !== null ? (float) $volume : null,
                    'harga' => $harga !== null ? (float) $harga : null,
                    'jumlah' => $jumlah !== null ? (float) $jumlah : null,
                    'id_barang' => $subItem['id_barang'] ?? null,
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
                    $row['no_akun'], $row['keterangan'], $row['dk'], $row['tipe'], $row['nama_akun'], $row['id_barang'] ?? '',
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
                        'id_barang' => $row['id_barang'],
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
                $noAkunG = (string) $g['no_akun'];
                $isVeneer = $this->isVeneerBasah($noAkunG);
                $isHutangGaji = $this->isHutangGaji($noAkunG);
                $isKayu = $this->isKayu($noAkunG);

                if ($isVeneer) {
                    $rowHarga = $this->hargaVeneerBasah($this->isVeneerCore($noAkunG));
                } elseif ($isHutangGaji) {
                    $rowHarga = 150000.0;
                } elseif ($isKayu) {
                    $rowHarga = (float) ($g['harga'] ?? 0.0);
                } else {
                    $rowHarga = (float) ($g['harga'] ?? 0.0);
                }

                if ($isKayu) {
                    $rowTotal = round((float) $g['jumlah'], 0);
                } elseif ($g['has_vol'] && $g['volume'] !== null && $g['volume'] > 0) {
                    $rowTotal = round(round((float) $g['volume'], 4) * $rowHarga, 0);
                } elseif ($g['has_qty'] && $g['banyak'] !== null && $g['banyak'] > 0) {
                    $rowTotal = round((float) $g['banyak'] * $rowHarga, 0);
                } else {
                    $rowTotal = round($rowHarga, 0);
                }

                if ($g['dk'] === 'd') {
                    $totalDebit += $rowTotal;
                } else {
                    $totalKredit += $rowTotal;
                }
            }

            // Selisih untuk menyeimbangkan (D/K dinamis)
            $selisih = $totalDebit - $totalKredit;
            if ($selisih != 0) {
                $akunHpp = $this->coaAlias->getAkunHpp();
                $grouped[] = [
                    'nama_akun' => $akunHpp['nama'],
                    'no_akun' => $akunHpp['no'],
                    'bagian' => $machine,
                    'keterangan' => '',
                    'dk' => $selisih > 0 ? 'k' : 'd',
                    'tipe' => 'b',
                    'banyak' => null,
                    'volume' => null,
                    'harga' => null,
                    'jumlah' => abs($selisih),
                    'has_qty' => false,
                    'has_vol' => false,
                    'id_barang' => null,
                ];
            }

            $machineTables[$machine] = $grouped;
        }

        $dateStr = Carbon::parse($this->tanggal)->format('Ymd');
        $currentRow = 1;

        foreach ($machineTables as $machine => $groupedRows) {
            $noJurnal = 'ROT/'.$dateStr.'/'.strtoupper(str_replace(' ', '', $machine)).'_V2';
            $rows->push(['No. Jurnal: '.$noJurnal, '', '', '', '', '', '', '', '', '', '', '', '', '']);
            $this->titleRows[] = $currentRow;
            $currentRow++;

            $rows->push(['Nama Akun', 'tgl', 'jurnal', 'No Akun', 'No', 'mm', 'Nama', 'Keterangan', 'map', 'hit kbk', 'Banyak', 'M3', 'Harga', 'Total', 'ID Barang']);
            $this->headerRows[] = $currentRow;
            $currentRow++;

            $dataStart = $currentRow;
            $tglVal = Carbon::parse($this->tanggal)->format('d-m-Y');

            foreach ($groupedRows as $g) {
                $noAkunG = (string) $g['no_akun'];
                $isVeneer = $this->isVeneerBasah($noAkunG);
                $isHutangGaji = $this->isHutangGaji($noAkunG);
                $isKayu = $this->isKayu($noAkunG);
                $isHpp = $noAkunG === $akunHppNo;

                if ($isVeneer) {
                    $namaVal = 'kupasan (m - '.strtolower($g['bagian']).')';
                } elseif ($isKayu) {
                    $namaVal = 'kayu keluar';
                } else {
                    $namaVal = 'kupasan';
                }

                $hitKbkVal = '';
                if ($isVeneer || $isKayu) {
                    $hitKbkVal = 'm';
                } elseif ($isHutangGaji) {
                    $hitKbkVal = 'b';
                }

                if ($isVeneer) {
                    $hargaVal = $this->hargaVeneerBasah($this->isVeneerCore($noAkunG));
                } elseif ($isHutangGaji) {
                    $hargaVal = 150000;
                } elseif ($isKayu) {
                    $hargaVal = $g['harga'];
                } elseif ($isHpp) {
                    $hargaVal = $g['jumlah'];
                } else {
                    $hargaVal = $g['jumlah'];
                }

                $totalVal = "=ROUND(IF(J{$currentRow}=\"m\",M{$currentRow}*L{$currentRow},IF(J{$currentRow}=\"b\",M{$currentRow}*K{$currentRow},M{$currentRow})), 0)";

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
                    $g['id_barang'] ?? null,
                ]);
                $currentRow++;
            }

            $dataEnd = $currentRow - 1;
            $this->dataRanges[] = ['start' => $dataStart, 'end' => $dataEnd];

            $rows->push(['', '', '', '', '', '', '', '', '', '', '', '', '', '', '']);
            $rows->push(['', '', '', '', '', '', '', '', '', '', '', '', '', '', '']);
            $currentRow += 2;
        }

        return $rows;
    }

    public function title(): string
    {
        return 'jurnal produksi gabung v2';
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
                    $sheet->mergeCells("A{$row}:O{$row}");
                    $sheet->getStyle("A{$row}:O{$row}")->applyFromArray([
                        'font' => ['bold' => true, 'size' => 11, 'color' => ['argb' => 'FF1D2939']],
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFD2F0DA']],
                        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                    ]);
                    $sheet->getRowDimension($row)->setRowHeight(25);
                }

                foreach ($this->headerRows as $row) {
                    $sheet->getStyle("A{$row}:O{$row}")->applyFromArray([
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

                    $sheet->getStyle("A{$start}:O{$end}")->applyFromArray([
                        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                    ]);

                    $sheet->getStyle("A{$start}:A{$end}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                    $sheet->getStyle("B{$start}:F{$end}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle("G{$start}:H{$end}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                    $sheet->getStyle("I{$start}:J{$end}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle("K{$start}:O{$end}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

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
                $sheet->getColumnDimension('K')->setWidth(12);
                $sheet->getColumnDimension('L')->setWidth(15);
                $sheet->getColumnDimension('M')->setWidth(18);
                $sheet->getColumnDimension('N')->setWidth(18);
                $sheet->getColumnDimension('O')->setWidth(15);
                $sheet->getColumnDimension('O')->setVisible(false);
            },
        ];
    }
}

// ============================================================
// COPY dari LaporanProduksiJurnalPenggunaanSheet -> versi COA baru
// ============================================================
class LaporanProduksiJurnalPenggunaanSheetV2 extends DefaultValueBinder implements FromCollection, WithCustomValueBinder, WithEvents, WithStyles, WithTitle
{
    protected $tanggal;

    protected $titleRows = [];

    protected $headerRows = [];

    protected $dataRanges = [];

    protected CoaAliasService $coaAlias;

    public function bindValue(Cell $cell, $value)
    {
        if ($cell->getColumn() === 'D') {
            $cell->setValueExplicit((string) $value, DataType::TYPE_STRING);

            return true;
        }

        return parent::bindValue($cell, $value);
    }

    public function __construct($tanggal, ?CoaAliasService $coaAlias = null)
    {
        $this->tanggal = $tanggal;
        $this->coaAlias = $coaAlias ?? app(CoaAliasService::class);
    }

    public function collection()
    {
        $rows = collect();
        $service = new RotaryJurnalService;
        $payload = $service->buildJurnalPayloadPreview($this->tanggal);

        if (! $payload || empty($payload['jurnal_items'])) {
            $rows->push(['Tidak ada data penggunaan kayu untuk tanggal ini.']);

            return $rows;
        }

        $rawRows = [];

        foreach ($payload['jurnal_items'] as $item) {
            $noAkun = $item['no_akun'];

            if ($noAkun !== '115-01' && $noAkun !== '115-02') {
                continue;
            }

            foreach ($item['items'] as $subItem) {
                $parts = explode(' - ', $subItem['keterangan'] ?? '');
                $bagian = count($parts) > 1 ? trim($parts[1]) : '-';

                $is130 = ($noAkun === '115-01');
                $panjang = $is130 ? 130 : 260;
                $jenisKayu = $parts[0] ?? '';
                $akunKayu = $this->coaAlias->getAkunKayuMasuk($jenisKayu, $panjang);

                $lahanName = $subItem['nama_pihak'] ?? '';
                $lahanLabel = stripos($lahanName, 'Lahan ') === 0 ? substr($lahanName, 6) : $lahanName;
                // nama jenis kayu tetap disimpan di Keterangan walau akun sudah digabung
                $keteranganSpesifikasi = 'lahan '.$lahanLabel.' - '.($jenisKayu !== '' ? $jenisKayu : '-');

                if (empty($subItem['id_barang'])) {
                    try {
                        $urlApi = rtrim(config('services.akuntansi.url', 'http://localhost:8080'), '/').'/api/barang/resolve-kayu';
                        $responseApi = Http::withoutVerifying()->timeout(5)->get($urlApi, [
                            'ukuran' => $panjang,
                            'jenis_kayu' => $jenisKayu,
                        ]);
                        if ($responseApi->successful()) {
                            $subItem['id_barang'] = $responseApi->json('id_barang');
                        }
                    } catch (\Throwable $e) {
                        Log::warning('Gagal resolve id_barang kayu: '.$e->getMessage());
                    }
                }

                $rawRows[] = [
                    'nama_akun' => $akunKayu['nama'],
                    'no_akun' => $akunKayu['no'],
                    'bagian' => $bagian,
                    'keterangan' => $keteranganSpesifikasi,
                    'dk' => 'k',
                    'tipe' => 'b',
                    'banyak' => $subItem['banyak'] !== null ? (float) $subItem['banyak'] : null,
                    'volume' => $subItem['m3'] !== null ? (float) $subItem['m3'] : null,
                    'harga' => $subItem['harga'] !== null ? (float) $subItem['harga'] : null,
                    'jumlah' => $subItem['jumlah'] !== null ? (float) $subItem['jumlah'] : null,
                    'id_barang' => $subItem['id_barang'] ?? null,
                ];
            }
        }

        if (empty($rawRows)) {
            $rows->push(['Tidak ada data penggunaan kayu untuk tanggal ini.']);

            return $rows;
        }

        $grouped = [];
        foreach ($rawRows as $row) {
            $key = implode('|', [$row['no_akun'], $row['keterangan'], $row['id_barang'] ?? '']);

            if (! isset($grouped[$key])) {
                $grouped[$key] = [
                    'nama_akun' => $row['nama_akun'],
                    'no_akun' => $row['no_akun'],
                    'bagian' => $row['bagian'],
                    'keterangan' => $row['keterangan'],
                    'dk' => $row['dk'],
                    'tipe' => $row['tipe'],
                    'banyak' => 0.0,
                    'volume' => 0.0,
                    'harga' => $row['harga'],
                    'jumlah' => 0.0,
                    'has_qty' => $row['banyak'] !== null,
                    'has_vol' => $row['volume'] !== null,
                    'id_barang' => $row['id_barang'] ?? null,
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

        $dateStr = Carbon::parse($this->tanggal)->format('Ymd');
        $currentRow = 1;

        $noJurnal = 'ROT/'.$dateStr.'/KAYU_KELUAR_V2';
        $rows->push(['No. Jurnal: '.$noJurnal, '', '', '', '', '', '', '', '', '', '', '', '', '']);
        $this->titleRows[] = $currentRow;
        $currentRow++;

        $rows->push(['Nama Akun', 'tgl', 'jurnal', 'No Akun', 'No', 'mm', 'Nama', 'Keterangan', 'map', 'hit kbk', 'Banyak', 'M3', 'Harga', 'Total', 'ID Barang']);
        $this->headerRows[] = $currentRow;
        $currentRow++;

        $dataStart = $currentRow;
        $tglVal = Carbon::parse($this->tanggal)->format('d-m-Y');
        foreach ($grouped as $g) {
            $totalVal = "=IF(J{$currentRow}=\"m\",M{$currentRow}*L{$currentRow},IF(J{$currentRow}=\"b\",M{$currentRow}*K{$currentRow},M{$currentRow}))";

            $rows->push([
                $g['nama_akun'], $tglVal, '', $g['no_akun'], '', '', 'kayu keluar', $g['keterangan'],
                'k', 'm', $g['has_qty'] ? $g['banyak'] : null, $g['has_vol'] ? round($g['volume'], 4) : null,
                $g['harga'], $totalVal, $g['id_barang'] ?? null,
            ]);
            $currentRow++;
        }
        $dataEnd = $currentRow - 1;
        $this->dataRanges[] = ['start' => $dataStart, 'end' => $dataEnd];

        return $rows;
    }

    public function title(): string
    {
        return 'Penggunaan Kayu v2';
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
                    $sheet->mergeCells("A{$row}:O{$row}");
                    $sheet->getStyle("A{$row}:O{$row}")->applyFromArray([
                        'font' => ['bold' => true, 'size' => 11, 'color' => ['argb' => 'FF1D2939']],
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFD2F0DA']],
                        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                    ]);
                    $sheet->getRowDimension($row)->setRowHeight(25);
                }

                foreach ($this->headerRows as $row) {
                    $sheet->getStyle("A{$row}:O{$row}")->applyFromArray([
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

                    $sheet->getStyle("A{$start}:O{$end}")->applyFromArray(['borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]]);
                    $sheet->getStyle("A{$start}:A{$end}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                    $sheet->getStyle("B{$start}:F{$end}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle("G{$start}:H{$end}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                    $sheet->getStyle("I{$start}:J{$end}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle("K{$start}:O{$end}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

                    $sheet->getStyle("K{$start}:K{$end}")->getNumberFormat()->setFormatCode('#,##0');
                    $sheet->getStyle("L{$start}:L{$end}")->getNumberFormat()->setFormatCode('#,##0.0000');
                    $sheet->getStyle("M{$start}:M{$end}")->getNumberFormat()->setFormatCode('#,##0');
                    $sheet->getStyle("N{$start}:N{$end}")->getNumberFormat()->setFormatCode('#,##0');
                }

                $sheet->getColumnDimension('A')->setWidth(25);
                $sheet->getColumnDimension('B')->setWidth(15);
                $sheet->getColumnDimension('C')->setWidth(12);
                $sheet->getColumnDimension('D')->setWidth(12);
                $sheet->getColumnDimension('E')->setWidth(10);
                $sheet->getColumnDimension('F')->setWidth(10);
                $sheet->getColumnDimension('G')->setWidth(25);
                $sheet->getColumnDimension('H')->setWidth(40);
                $sheet->getColumnDimension('I')->setWidth(10);
                $sheet->getColumnDimension('J')->setWidth(10);
                $sheet->getColumnDimension('K')->setWidth(12);
                $sheet->getColumnDimension('L')->setWidth(15);
                $sheet->getColumnDimension('M')->setWidth(18);
                $sheet->getColumnDimension('N')->setWidth(18);
                $sheet->getColumnDimension('O')->setWidth(15);
                $sheet->getColumnDimension('O')->setVisible(false);
            },
        ];
    }
}

// ============================================================
// COPY dari LaporanProduksiJurnalHargaAsliSheet -> versi COA baru
// ============================================================
class LaporanProduksiJurnalHargaAsliSheetV2 extends DefaultValueBinder implements FromCollection, WithCustomValueBinder, WithEvents, WithStyles, WithTitle
{
    protected $tanggal;

    protected $titleRows = [];

    protected $headerRows = [];

    protected $dataRanges = [];

    protected CoaAliasService $coaAlias;

    public function bindValue(Cell $cell, $value)
    {
        if ($cell->getColumn() === 'D') {
            $cell->setValueExplicit((string) $value, DataType::TYPE_STRING);

            return true;
        }

        return parent::bindValue($cell, $value);
    }

    public function __construct($tanggal, ?CoaAliasService $coaAlias = null)
    {
        $this->tanggal = $tanggal;
        $this->coaAlias = $coaAlias ?? app(CoaAliasService::class);
    }

    public function collection()
    {
        $rows = collect();
        $tgl = Carbon::parse($this->tanggal)->startOfDay();

        $produksiList = ProduksiRotary::with(['detailLahanRotary.lahan'])
            ->whereDate('tgl_produksi', $tgl)->get();

        if ($produksiList->isEmpty()) {
            $rows->push(['Tidak ada data penggunaan kayu untuk tanggal ini.']);

            return $rows;
        }

        $lahanIds = [];
        foreach ($produksiList as $p) {
            foreach ($p->detailLahanRotary as $dl) {
                if ($dl->id_lahan) {
                    $lahanIds[] = $dl->id_lahan;
                }
            }
        }
        $lahanIds = array_unique($lahanIds);

        if (empty($lahanIds)) {
            $rows->push(['Tidak ada data penggunaan kayu untuk tanggal ini.']);

            return $rows;
        }

        $details = DetailTurusanKayu::whereIn('lahan_id', $lahanIds)
            ->with(['jenisKayu', 'kayuMasuk', 'lahan'])
            ->get();

        if ($details->isEmpty()) {
            $rows->push(['Tidak ada data penggunaan kayu untuk tanggal ini.']);

            return $rows;
        }

        $grouped = [];
        foreach ($details as $d) {
            $lahanCode = $d->lahan->kode_lahan ?? '-';
            $lahanName = $d->lahan->nama_lahan ?? '-';
            $jenisNama = $d->jenisKayu->nama_kayu ?? '-';

            $key = implode('|', [$d->lahan_id, $d->jenis_kayu_id]);

            if (! isset($grouped[$key])) {
                $grouped[$key] = [
                    'lahan_id' => $d->lahan_id,
                    'jenis_kayu_id' => $d->jenis_kayu_id,
                    'lahan_code' => $lahanCode,
                    'lahan_name' => $lahanName,
                    'jenis_nama' => $jenisNama,
                    'panjang' => $d->panjang,
                    'banyak' => 0,
                    'volume' => 0,
                    'total_harga' => 0,
                ];
            }

            $grouped[$key]['banyak'] += $d->kuantitas;
            $grouped[$key]['volume'] += $d->kubikasi;
            $grouped[$key]['total_harga'] += ($d->harga * 1000) * $d->kubikasi;
        }

        $dateStr = Carbon::parse($this->tanggal)->format('Ymd');
        $currentRow = 1;
        $noJurnal = 'ROT/'.$dateStr.'/KAYU_KELUAR_HARGA_ASLI_V2';

        $rows->push(['No. Jurnal: '.$noJurnal, '', '', '', '', '', '', '', '', '', '', '', '', '']);
        $this->titleRows[] = $currentRow;
        $currentRow++;

        $rows->push(['Nama Akun', 'tgl', 'jurnal', 'No Akun', 'No', 'mm', 'Nama', 'Keterangan', 'map', 'hit kbk', 'Banyak', 'M3', 'Harga', 'Total', 'ID Barang']);
        $this->headerRows[] = $currentRow;
        $currentRow++;

        $dataStart = $currentRow;
        $tglVal = Carbon::parse($this->tanggal)->format('d-m-Y');

        foreach ($grouped as $g) {
            $akunKayu = $this->coaAlias->getAkunKayuMasuk($g['jenis_nama'] ?? '', $g['panjang']);
            $noAkun = $akunKayu['no'];
            $namaAkun = $akunKayu['nama'];

            $roundedVol = round($g['volume'], 4);
            $hargaPerM3 = $roundedVol > 0 ? $g['total_harga'] / $roundedVol : 0;

            $keteranganSpec = sprintf('Lahan %s - %s', $g['lahan_code'], $g['jenis_nama']);

            $totalVal = "=IF(J{$currentRow}=\"m\",M{$currentRow}*L{$currentRow},IF(J{$currentRow}=\"b\",M{$currentRow}*K{$currentRow},M{$currentRow}))";

            $rows->push([
                $namaAkun, $tglVal, '', $noAkun, '', '', 'kayu keluar', $keteranganSpec,
                'k', 'm', $g['banyak'] > 0 ? $g['banyak'] : null, $roundedVol > 0 ? $roundedVol : null,
                $hargaPerM3, $totalVal, null,
            ]);
            $currentRow++;
        }

        $dataEnd = $currentRow - 1;
        $this->dataRanges[] = ['start' => $dataStart, 'end' => $dataEnd];

        return $rows;
    }

    public function title(): string
    {
        return 'Penggunaan Kayu Harga Asli v2';
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
                    $sheet->mergeCells("A{$row}:O{$row}");
                    $sheet->getStyle("A{$row}:O{$row}")->applyFromArray([
                        'font' => ['bold' => true, 'size' => 11, 'color' => ['argb' => 'FF1D2939']],
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFD2F0DA']],
                        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                    ]);
                    $sheet->getRowDimension($row)->setRowHeight(25);
                }

                foreach ($this->headerRows as $row) {
                    $sheet->getStyle("A{$row}:O{$row}")->applyFromArray([
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

                    $sheet->getStyle("A{$start}:O{$end}")->applyFromArray(['borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]]);
                    $sheet->getStyle("A{$start}:A{$end}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                    $sheet->getStyle("B{$start}:F{$end}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle("G{$start}:H{$end}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                    $sheet->getStyle("I{$start}:J{$end}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle("K{$start}:O{$end}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

                    $sheet->getStyle("K{$start}:K{$end}")->getNumberFormat()->setFormatCode('#,##0');
                    $sheet->getStyle("L{$start}:L{$end}")->getNumberFormat()->setFormatCode('#,##0.0000');
                    $sheet->getStyle("M{$start}:M{$end}")->getNumberFormat()->setFormatCode('#,##0');
                    $sheet->getStyle("N{$start}:N{$end}")->getNumberFormat()->setFormatCode('#,##0');
                }

                $sheet->getColumnDimension('A')->setWidth(25);
                $sheet->getColumnDimension('B')->setWidth(15);
                $sheet->getColumnDimension('C')->setWidth(12);
                $sheet->getColumnDimension('D')->setWidth(12);
                $sheet->getColumnDimension('E')->setWidth(10);
                $sheet->getColumnDimension('F')->setWidth(10);
                $sheet->getColumnDimension('G')->setWidth(20);
                $sheet->getColumnDimension('H')->setWidth(45);
                $sheet->getColumnDimension('I')->setWidth(10);
                $sheet->getColumnDimension('J')->setWidth(10);
                $sheet->getColumnDimension('K')->setWidth(12);
                $sheet->getColumnDimension('L')->setWidth(15);
                $sheet->getColumnDimension('M')->setWidth(18);
                $sheet->getColumnDimension('N')->setWidth(18);
                $sheet->getColumnDimension('O')->setWidth(15);
                $sheet->getColumnDimension('O')->setVisible(false);
            },
        ];
    }
}

// ============================================================
// COPY dari LaporanProduksiKayuHabisSheet -> versi COA baru
// ============================================================
class LaporanProduksiKayuHabisSheetV2 extends DefaultValueBinder implements FromCollection, WithCustomValueBinder, WithEvents, WithStyles, WithTitle
{
    protected $tanggal;

    protected $titleRows = [];

    protected $headerRows = [];

    protected $dataRanges = [];

    protected CoaAliasService $coaAlias;

    public function bindValue(Cell $cell, $value)
    {
        if ($cell->getColumn() === 'D') {
            $cell->setValueExplicit((string) $value, DataType::TYPE_STRING);

            return true;
        }

        return parent::bindValue($cell, $value);
    }

    public function __construct($tanggal, ?CoaAliasService $coaAlias = null)
    {
        $this->tanggal = $tanggal;
        $this->coaAlias = $coaAlias ?? app(CoaAliasService::class);
    }

    public function collection()
    {
        $rows = collect();
        $tgl = Carbon::parse($this->tanggal)->startOfDay();

        $records = HppAverageLog::with(['lahan', 'jenisKayu'])
            ->whereDate('tanggal', $tgl)
            ->where('tipe_transaksi', 'keluar')
            ->where('stok_batang_after', 0)
            ->orderBy('id', 'asc')
            ->get();

        if ($records->isEmpty()) {
            $rows->push(['Tidak ada data penggunaan kayu habis untuk tanggal ini.']);

            return $rows;
        }

        $dateStr = Carbon::parse($this->tanggal)->format('Ymd');
        $currentRow = 1;
        $noJurnal = 'ROT/'.$dateStr.'/KAYU_KELUAR_V2';

        $rows->push(['No. Jurnal: '.$noJurnal, '', '', '', '', '', '', '', '', '', '', '', '', '']);
        $this->titleRows[] = $currentRow;
        $currentRow++;

        $rows->push(['Nama Akun', 'tgl', 'jurnal', 'No Akun', 'No', 'mm', 'Nama', 'Keterangan', 'map', 'hit kbk', 'Banyak', 'M3', 'Harga', 'Total', 'ID Barang']);
        $this->headerRows[] = $currentRow;
        $currentRow++;

        $dataStart = $currentRow;
        $tglVal = Carbon::parse($this->tanggal)->format('d-m-Y');

        $totalBanyak = 0;
        $totalM3 = 0;
        $totalHarga = 0;

        foreach ($records as $record) {
            $totalBanyak += ($record->total_batang > 0 ? $record->total_batang : 0);
            $totalM3 += ($record->total_kubikasi > 0 ? $record->total_kubikasi : 0);
            $totalHarga += $record->nilai_stok;
        }
        $totalM3 = round($totalM3, 4);

        if (! $records->isEmpty()) {
            $totalValHpp = "=IF(J{$currentRow}=\"m\",M{$currentRow}*L{$currentRow},IF(J{$currentRow}=\"b\",M{$currentRow}*K{$currentRow},M{$currentRow}))";

            // Selisih/HPP dipetakan ke 5069.2 (akun DE) untuk v2, tetap sisi debit
            $akunHpp = $this->coaAlias->getAkunHpp();
            $rows->push([
                $akunHpp['nama'], $tglVal, '', $akunHpp['no'], '', '', 'kayu habis', '',
                'd', '', $totalBanyak > 0 ? $totalBanyak : null, $totalM3 > 0 ? $totalM3 : null,
                $totalHarga, $totalValHpp, null,
            ]);
            $currentRow++;
        }

        foreach ($records as $record) {
            $jenisNama = $record->jenisKayu?->nama_kayu ?? '-';
            $akunKayu = $this->coaAlias->getAkunKayuMasuk($jenisNama, $record->panjang);
            $noAkun = $akunKayu['no'];
            $namaAkun = $akunKayu['nama'];

            // nama jenis kayu asli tetap ditulis di keterangan
            $keteranganSpec = 'lahan '.($record->lahan->kode_lahan ?? '-').' - '.$jenisNama;

            $banyak = $record->total_batang > 0 ? $record->total_batang : 0;
            $m3 = $record->total_kubikasi > 0 ? round($record->total_kubikasi, 4) : 0;
            $totalStokValue = $record->nilai_stok;

            $totalVal = "=IF(J{$currentRow}=\"m\",M{$currentRow}*L{$currentRow},IF(J{$currentRow}=\"b\",M{$currentRow}*K{$currentRow},M{$currentRow}))";

            $rows->push([
                $namaAkun, $tglVal, '', $noAkun, '', '', 'kayu keluar', $keteranganSpec,
                'k', '', $banyak > 0 ? $banyak : null, $m3 > 0 ? $m3 : null, $totalStokValue, $totalVal, null,
            ]);

            $currentRow++;
        }

        $dataEnd = $currentRow - 1;
        $this->dataRanges[] = ['start' => $dataStart, 'end' => $dataEnd];

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
                    $sheet->mergeCells("A{$row}:O{$row}");
                    $sheet->getStyle("A{$row}:O{$row}")->applyFromArray([
                        'font' => ['bold' => true, 'size' => 11, 'color' => ['argb' => 'FF1D2939']],
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFD2F0DA']],
                        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                    ]);
                    $sheet->getRowDimension($row)->setRowHeight(25);
                }

                foreach ($this->headerRows as $row) {
                    $sheet->getStyle("A{$row}:O{$row}")->applyFromArray([
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

                    $sheet->getStyle("A{$start}:O{$end}")->applyFromArray(['borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]]);
                    $sheet->getStyle("A{$start}:A{$end}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                    $sheet->getStyle("B{$start}:F{$end}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle("G{$start}:H{$end}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                    $sheet->getStyle("I{$start}:J{$end}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle("K{$start}:O{$end}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

                    $sheet->getStyle("K{$start}:K{$end}")->getNumberFormat()->setFormatCode('#,##0');
                    $sheet->getStyle("L{$start}:L{$end}")->getNumberFormat()->setFormatCode('#,##0.0000');
                    $sheet->getStyle("M{$start}:O{$end}")->getNumberFormat()->setFormatCode('#,##0');
                }

                $sheet->getColumnDimension('A')->setWidth(28);
                $sheet->getColumnDimension('B')->setWidth(15);
                $sheet->getColumnDimension('C')->setWidth(10);
                $sheet->getColumnDimension('D')->setWidth(12);
                $sheet->getColumnDimension('E')->setWidth(10);
                $sheet->getColumnDimension('F')->setWidth(10);
                $sheet->getColumnDimension('G')->setWidth(20);
                $sheet->getColumnDimension('H')->setWidth(30);
                $sheet->getColumnDimension('I')->setWidth(10);
                $sheet->getColumnDimension('J')->setWidth(12);
                $sheet->getColumnDimension('K')->setWidth(12);
                $sheet->getColumnDimension('L')->setWidth(15);
                $sheet->getColumnDimension('M')->setWidth(18);
                $sheet->getColumnDimension('N')->setWidth(18);
                $sheet->getColumnDimension('O')->setWidth(15);
                $sheet->getColumnDimension('O')->setVisible(false);
            },
        ];
    }
}
