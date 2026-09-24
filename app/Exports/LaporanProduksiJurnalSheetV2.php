<?php

namespace App\Exports;

use App\Models\JenisKayu;
use App\Models\KategoriBarang;
use App\Models\Mesin;
use App\Models\ReferensiHargaProduksi;
use App\Services\Akuntansi\RotaryJurnalService;
use App\Services\CoaAliasService;
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
use Throwable;

// ============================================================
// SHEET: JURNAL PRODUKSI ROTARY — COA GENERAL (V2)
//
// Perubahan dari versi sebelumnya:
// - Semua nomor & nama akun diambil dari App\Services\CoaAliasService
//   (getAkunVeneerBasah / getAkunGaji / getAkunHpp / getAkunKayuMasuk /
//   mapAkunLama). Tidak ada lagi '1402.3', '2195.1', '5069.2' hardcode.
// - Item pemasok (kayu) tidak lagi di-skip: dipetakan lewat
//   getAkunKayuMasuk(), sesuai TODO lama.
// - Nomor akun lama yang tidak dikenali dilewatkan ke mapAkunLama()
//   sebagai jaring pengaman.
//
// Daftarkan di LaporanProduksiExport::sheets():
//     new LaporanProduksiJurnalSheetV2($this->tanggal),
// ============================================================
class LaporanProduksiJurnalSheetV2 extends DefaultValueBinder implements FromCollection, WithCustomValueBinder, WithEvents, WithStyles, WithTitle
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
    // DATABASE REFERENCE HELPERS
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
                } elseif ($jenisPihak === 'bahan_penolong') {
                    // Bahan penolong (reeling tape, solasi, dll)
                    // Akun sudah di-set oleh RotaryJurnalService (dari ReferensiHargaProduksi / BAHAN_PENOLONG_MAP)
                    // Harga & jumlah tidak di-override — diambil dari BahanPenolongProduksi
                    $bagian = $subItem['nama_pihak'] ?? '-';
                    $keteranganSpesifikasi = $subItem['nama_barang'] ?? '-';
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
                            $urlApi = rtrim(config('services.akuntansi.url', 'http://localhost:8080'), '/') . '/api/barang/resolve-kayu';
                            $responseApi = \Illuminate\Support\Facades\Http::withoutVerifying()->timeout(5)->get($urlApi, [
                                'ukuran' => $panjang,
                                'jenis_kayu' => $jenisKayu,
                            ]);
                            if ($responseApi->successful()) {
                                $subItem['id_barang'] = $responseApi->json('id_barang');
                            }
                        } catch (\Throwable $e) {
                            \Illuminate\Support\Facades\Log::warning("Gagal resolve id_barang kayu: " . $e->getMessage());
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

                // Bahan penolong: harga & jumlah sudah benar dari payload (tidak di-override)
                // $harga = harga_satuan dari BahanPenolongProduksi
                // $jumlah = nilai_total (harga_satuan × kuantitas)

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
                    $row['no_akun'], $row['keterangan'], $row['dk'], $row['tipe'], $row['nama_akun'], $row['id_barang'] ?? ''
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
                    $totalVal, $g['id_barang'] ?? null,
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
