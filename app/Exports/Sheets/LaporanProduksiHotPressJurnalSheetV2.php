<?php

namespace App\Exports\Sheets;

use App\Models\Grade;
use App\Models\JenisKayu;
use App\Models\KategoriBarang;
use App\Models\ProduksiHp;
use App\Models\ReferensiHargaProduksi;
use App\Models\Ukuran;
use App\Services\CoaAliasService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

// ============================================================
// FILE INI TIDAK MENGUBAH LaporanProduksiHotPressJurnalSheet.php
// SAMA SEKALI. Ini COPY dengan:
// - Nama class baru: LaporanProduksiHotPressJurnalSheetV2
// - Judul sheet baru: 'jurnal produksi v2'
// - Akun Hutang Gaji: 2231.00 -> 2195.1
// - Akun HPP/selisih penyeimbang: 6111.00 / 6111.01 (whn) ->
//   DISATUKAN jadi 5069.2 "Selisih harga patok produksi" untuk semua domain
//   (COA baru tidak membedakan wjy/whn untuk akun ini).
// - Akun veneer yang dipakai sebagai BAHAN hotpress (bukan platform, bukan
//   hasil produksi/triplek) di-alias dinamis ke COA baru lewat
//   CoaAliasService, sama seperti JurnalSheetV2 (dryer) & JurnalRepairSheetV2.
// - Akun Platform SEKARANG DI-ALIAS ke COA baru lewat CoaAliasService:
//     * Platform hasil produksi HP (output)                    -> Platform Jadi   (1403.3)
//     * Platform sebagai bahan masuk HP (input, belum diproses) -> Platform Mentah (1403.1)
// - Akun Triplek HASIL PRODUKSI SEKARANG DI-ALIAS ke COA baru lewat
//   CoaAliasService::extractAkunTriplek() -> disatukan jadi satu akun
//   "Persediaan Triplek Mentah" (1403.2) untuk semua ukuran/grade (kode
//   lama beda-beda per varian, mis. 1506.32 / 1506.33).
// - Akun Bahan Penolong SEKARANG DI-ALIAS ke COA baru lewat
//   CoaAliasService::extractAkunPenolong() (dicocokkan dari nama bahan
//   asli): Lem->1402.9, Hardner->1402.10, Isi Staples->1402.11,
//   Pewarna->1402.12, Tepung->1402.13, Solasi Coklat->1402.14,
//   Solasi Putih->1402.15.
// - Kolom "No Akun" (D) dipaksa jadi TEKS eksplisit (bukan numerik),
//   supaya kode akun seperti "1402.5" tidak pernah dipaksa 2 digit /
//   diubah titik jadi koma oleh Excel.
// - NEW: Kolom "ID Barang" (O) sekarang diisi dengan hasil resolve dari
//   API eksternal /api/barang/resolve-veneer, MENGIKUTI POLA yang sama
//   dipakai di JurnalSheetV2 (dryer) & JurnalRepairSheetV2 (repair).
//   PENTING — resolver ini HANYA diterapkan untuk baris BAHAN VENEER
//   (veneer masuk sebagai bahan hotpress, bukan platform) karena
//   endpoint /api/barang/resolve-veneer secara kontrak hanya mengenal
//   parameter veneer (jenis_veneer, bagian, jenis_kayu, ketebalan,
//   ukuran, kw). Baris TRIPLEK dan PLATFORM (baik sebagai hasil
//   produksi maupun sebagai bahan masuk) TIDAK di-resolve di sini dan
//   ID Barang-nya dibiarkan null, karena keduanya bukan item veneer dan
//   belum ada endpoint resolver terpisah untuk triplek/platform yang
//   dikonfirmasi. Kalau nanti ada endpoint resolve-triplek atau
//   resolve-platform, tinggal tambahkan pemanggilan serupa di titik
//   yang ditandai "// TODO: resolver triplek/platform" di bawah.
//
// NOTE REFACTOR: Logic ALIAS AKUN COA BARU (aliasAkunBaru, extractAkunVeneer,
// extractAkunPlatform, extractAkunTriplek, extractAkunPenolong, extractAkunRaw,
// getAkunHpp, getAkunGaji) sudah
// DIPINDAH ke App\Services\CoaAliasService supaya bisa dipakai bersama oleh
// JurnalSheetV2 (dryer) & JurnalRepairSheetV2. Sheet ini sekarang hanya
// memanggil service tsb lewat $this->coaAlias.
//
// Cara pakai: daftarkan class ini di sheets() milik
// LaporanProduksiHotPressExport TANPA menghapus sheet lama, misalnya:
//
//     public function sheets(): array
//     {
//         return [
//             new LaporanProduksiHotPressSheetPekerja($this->tanggal),
//             new LaporanProduksiHotPressRekapSheet($this->tanggal),
//             new LaporanProduksiHotPressDetailSheet($this->data),
//             new LaporanProduksiHotPressJurnalSheet($this->tanggal, $this->domain),   // lama, tidak diubah
//             new LaporanProduksiHotPressJurnalSheetV2($this->tanggal, $this->domain), // baru
//         ];
//     }
// ============================================================
class LaporanProduksiHotPressJurnalSheetV2 implements FromArray, WithColumnWidths, WithEvents, WithMapping, WithStyles, WithTitle
{
    protected string $tanggal;

    protected string $domain;

    protected int $rowIndex = 0;

    protected ?int $idKayuSengon = null;

    protected ?int $idKayuMeranti = null;

    protected CoaAliasService $coaAlias;

    // Cache agar tidak query DB berulang
    private array $kayuCache = [];

    private array $kategoriCache = [];

    private array $gradeCache = [];

    /**
     * Cache hasil resolve id_barang dari API eksternal, dikunci per
     * kombinasi parameter (jenis_veneer|bagian|jenis_kayu|ketebalan|
     * ukuran|kw) supaya tidak memanggil API berkali-kali untuk
     * kombinasi yang sama dalam satu laporan.
     */
    private array $idBarangCache = [];

    public function __construct(string $tanggal, string $domain, ?CoaAliasService $coaAlias = null)
    {
        $this->tanggal = $tanggal;
        $this->domain = $domain;
        $this->coaAlias = $coaAlias ?? app(CoaAliasService::class);
    }

    public function title(): string
    {
        return 'jurnal produksi v2';
    }

    public function columnWidths(): array
    {
        return [
            'A' => 42,
            'B' => 15,
            'C' => 10,
            'D' => 12,
            'E' => 10,
            'F' => 10,
            'G' => 18,
            'H' => 42,
            'I' => 6,
            'J' => 10,
            'K' => 10,
            'L' => 15,
            'M' => 15,
            'N' => 20,
            'O' => 12,
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $lastRow = $sheet->getHighestRow();
        $sheet->getStyle("A1:O{$lastRow}")->applyFromArray([
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']]],
        ]);
        $sheet->getStyle('A1:O1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '2E7D4F']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);

        // Kolom D (No Akun) diformat sebagai TEKS, bukan numerik — supaya
        // kode akun seperti "1402.5" tidak dipaksa 2 digit di belakang
        // titik / diubah jadi koma oleh Excel locale Indonesia.
        $sheet->getStyle("D2:D{$lastRow}")->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);

        $sheet->getStyle("L2:L{$lastRow}")->getNumberFormat()->setFormatCode('0.0000');
        $sheet->getStyle("M2:N{$lastRow}")->getNumberFormat()->setFormatCode('#,##0');
        $sheet->getStyle("O2:O{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getRowDimension(1)->setRowHeight(20);

        return [];
    }

    /**
     * Paksa kolom "No Akun" (D) disimpan sebagai STRING eksplisit di level
     * cell, bukan cuma diformat tampilan sebagai teks. Sama seperti
     * JurnalSheetV2 (dryer) & JurnalRepairSheetV2 — mencegah PhpSpreadsheet
     * auto-detect nilai seperti "1402.5" sebagai float, yang akan membuat
     * Excel menampilkannya dengan koma sebagai pemisah desimal & dipaksa
     * 2 digit di belakang titik.
     *
     * Catatan: kode akun di file ini ditulis dengan prefix "\t" (tab) di
     * makeRow() untuk mencegah Excel auto-format sebagai angka — tab
     * tersebut kita hapus di sini sebelum menyimpan ulang sebagai string
     * eksplisit, supaya nilai yang tampil bersih (tanpa tab).
     */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $lastRow = $sheet->getHighestRow();

                for ($row = 2; $row <= $lastRow; $row++) {
                    $cell = $sheet->getCell("D{$row}");
                    $value = $cell->getValue();

                    if ($value === null || $value === '') {
                        continue;
                    }

                    $cleanValue = ltrim((string) $value, "\t");
                    $sheet->setCellValueExplicit("D{$row}", $cleanValue, DataType::TYPE_STRING);
                }
            },
        ];
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    private function isWhn(): bool
    {
        return str_contains(strtolower($this->domain), 'wahana');
    }

    private function getNamaMesinSingkat(string $namaMesin, string $shift): string
    {
        $namaLower = strtolower($namaMesin);
        $shiftStr = strtolower(trim($shift));
        if (str_contains($namaLower, '1')) {
            return "hp 1 {$shiftStr}";
        }
        if (str_contains($namaLower, '2')) {
            return "hp 2 {$shiftStr}";
        }
        if (str_contains($namaLower, '3')) {
            return "hp 3 {$shiftStr}";
        }

        return "hp 1 {$shiftStr}";
    }

    private function domainSuffix(): array
    {
        return $this->isWhn() ? ['mth', 'whn'] : ['wjy'];
    }

    private function isAf(string $grade): bool
    {
        return str_contains(strtolower(trim($grade)), 'af');
    }

    private function isPlatformGrade(string $grade): bool
    {
        $g = strtolower(trim($grade));

        return in_array($g, [
            'better',
            'better local',
            'better lokal',
            'better local mth',
            'better lokal mth',
        ]);
    }

    private function kategoriGrade(string $grade): string
    {
        $g = strtolower(trim($grade));
        if (in_array($g, ['1', '2'])) {
            return 'face';
        }
        if (in_array($g, ['3', '4'])) {
            return 'back';
        }

        return $g;
    }

    // =========================================================================
    // DATABASE LOOKUP HELPERS (dengan cache)
    // =========================================================================

    private function getIdKayuByNama(string $namaLike): ?int
    {
        $key = strtolower(trim($namaLike));
        if (array_key_exists($key, $this->kayuCache)) {
            return $this->kayuCache[$key];
        }

        $kolom = ['nama', 'nama_kayu', 'nama_jenis_kayu', 'jenis_kayu'];
        foreach ($kolom as $col) {
            try {
                $kayu = JenisKayu::whereRaw("LOWER({$col}) LIKE ?", ["%{$key}%"])->first();
                if ($kayu) {
                    return $this->kayuCache[$key] = $kayu->id;
                }
            } catch (\Throwable $e) {
                continue;
            }
        }

        // Fallback: cari di semua kolom
        try {
            $semua = JenisKayu::all();
            $found = $semua->first(function ($item) use ($key) {
                foreach ($item->getAttributes() as $val) {
                    if (is_string($val) && str_contains(strtolower($val), $key)) {
                        return true;
                    }
                }

                return false;
            });

            return $this->kayuCache[$key] = $found?->id;
        } catch (\Throwable $e) {
            return $this->kayuCache[$key] = null;
        }
    }

    private function getIdSengon(): ?int
    {
        if ($this->idKayuSengon === null) {
            $this->idKayuSengon = $this->getIdKayuByNama('sengon') ?? 0;
        }

        return $this->idKayuSengon ?: null;
    }

    private function getIdMeranti(): ?int
    {
        if ($this->idKayuMeranti === null) {
            $this->idKayuMeranti = $this->getIdKayuByNama('meranti') ?? 0;
        }

        return $this->idKayuMeranti ?: null;
    }

    private function resolveIdJenisKayu(?string $namaJenisBarang): ?int
    {
        if (! $namaJenisBarang) {
            return null;
        }

        return $this->getIdKayuByNama(strtolower(trim($namaJenisBarang)));
    }

    private function getIdKategoriBarang(string $namaKategori): ?int
    {
        $key = strtolower(trim($namaKategori));
        if (array_key_exists($key, $this->kategoriCache)) {
            return $this->kategoriCache[$key];
        }
        try {
            $kategori = KategoriBarang::whereRaw('LOWER(nama_kategori) LIKE ?', ["%{$key}%"])->first();

            return $this->kategoriCache[$key] = $kategori?->id;
        } catch (\Throwable $e) {
            return $this->kategoriCache[$key] = null;
        }
    }

    private function getIdGrade(string $namaGrade): ?int
    {
        $key = strtolower(trim($namaGrade));
        $keyLocal = str_replace('lokal', 'local', $key);
        $keyLokal = str_replace('local', 'lokal', $key);

        $cacheKey = $key;
        if (array_key_exists($cacheKey, $this->gradeCache)) {
            return $this->gradeCache[$cacheKey];
        }

        try {
            $grade = Grade::where(function ($q) use ($key, $keyLocal, $keyLokal) {
                $q->whereRaw('LOWER(nama_grade) = ?', [$key])
                    ->orWhereRaw('LOWER(nama_grade) = ?', [$keyLocal])
                    ->orWhereRaw('LOWER(nama_grade) = ?', [$keyLokal])
                    ->orWhereRaw('LOWER(nama_grade) LIKE ?', ["%{$key}%"]);
            })->first();

            return $this->gradeCache[$cacheKey] = $grade?->id;
        } catch (\Throwable $e) {
            return $this->gradeCache[$cacheKey] = null;
        }
    }

    // =========================================================================
    // FILTER HELPERS — STRICT (tidak ketemu → UNKNOWN)
    // =========================================================================

    private function filterByDomain(Collection $c): Collection
    {
        if ($c->isEmpty()) {
            return $c;
        }
        $suffix = $this->domainSuffix();
        $filtered = $c->filter(function ($item) use ($suffix) {
            $namaRef = strtolower($item->nama ?? '');
            $namaAkun = strtolower($item->subAnakAkun->kode_sub_anak_akun ?? '');
            foreach ($suffix as $s) {
                if (str_contains($namaRef, $s) || str_contains($namaAkun, $s)) {
                    return true;
                }
            }

            return false;
        });

        return $filtered->isNotEmpty() ? $filtered : $c;
    }

    private function filterGenericFirst(Collection $c): Collection
    {
        if ($c->isEmpty()) {
            return $c;
        }
        $allSuffix = ['wjy', 'whn', 'mth'];
        $generic = $c->filter(function ($item) use ($allSuffix) {
            $namaRef = strtolower($item->nama ?? '');
            foreach ($allSuffix as $s) {
                if (str_contains($namaRef, $s)) {
                    return false;
                }
            }

            return true;
        });

        return $generic->isNotEmpty() ? $generic : $c;
    }

    private function filterByUkuran(Collection $c, ?int $idUkuran): Collection
    {
        if ($c->isEmpty() || ! $idUkuran) {
            return $c;
        }

        $exact = $c->filter(fn ($i) => $i->id_ukuran == $idUkuran);
        if ($exact->isNotEmpty()) {
            return $exact;
        }

        try {
            $ukuran = Ukuran::find($idUkuran);
            if ($ukuran) {
                $idMirror = Ukuran::where('tebal', $ukuran->tebal)
                    ->where(
                        fn ($q) => $q
                            ->where(fn ($q2) => $q2->where('panjang', $ukuran->panjang)->where('lebar', $ukuran->lebar))
                            ->orWhere(fn ($q2) => $q2->where('panjang', $ukuran->lebar)->where('lebar', $ukuran->panjang))
                    )->pluck('id');

                $mirror = $c->filter(fn ($i) => $idMirror->contains($i->id_ukuran));
                if ($mirror->isNotEmpty()) {
                    return $mirror;
                }
            }
        } catch (\Throwable $e) {
            // Lanjut ke fallback
        }

        return $c->filter(fn ($i) => is_null($i->id_ukuran));
    }

    private function filterByKayu(Collection $c, ?int $idJenisKayu): Collection
    {
        if ($c->isEmpty()) {
            return $c;
        }
        if (! $idJenisKayu) {
            return $c;
        }

        $filtered = $c->filter(fn ($i) => $i->id_jenis_kayu == $idJenisKayu);
        if ($filtered->isNotEmpty()) {
            return $filtered;
        }

        $generic = $c->filter(fn ($i) => is_null($i->id_jenis_kayu));
        if ($generic->isNotEmpty()) {
            return $generic;
        }

        return collect();
    }

    private function filterByGrade(Collection $c, ?string $grade, ?int $idGrade = null): Collection
    {
        if ($c->isEmpty()) {
            return $c;
        }

        if ($idGrade) {
            $filtered = $c->filter(fn ($i) => $i->id_grade == $idGrade);
            if ($filtered->isNotEmpty()) {
                return $filtered;
            }
        }

        if ($grade) {
            $idByNama = $this->getIdGrade($grade);
            if ($idByNama) {
                $filtered = $c->filter(fn ($i) => $i->id_grade == $idByNama);
                if ($filtered->isNotEmpty()) {
                    return $filtered;
                }
            }
        }

        if (! $idGrade && ! $grade) {
            return $c;
        }

        return $c->filter(fn ($i) => is_null($i->id_grade));
    }

    // =========================================================================
    // BASE QUERY per tipe barang
    // =========================================================================

    private function baseQuery(string $tipe): Builder
    {
        $q = ReferensiHargaProduksi::with('subAnakAkun');
        $tipeLower = strtolower(trim($tipe));

        if (in_array($tipeLower, ['triplek', 'plywood'])) {
            $idKategori = $this->getIdKategoriBarang('Plywood Mentah');
            if ($idKategori) {
                $q->where('id_kategori_barang', $idKategori);
            } else {
                $idKategori = $this->getIdKategoriBarang('Plywood');
                if ($idKategori) {
                    $q->where('id_kategori_barang', $idKategori);
                }
            }
        } elseif ($tipeLower === 'platform') {
            $idKategori = $this->getIdKategoriBarang('Platform');
            if ($idKategori) {
                $q->where('id_kategori_barang', $idKategori);
            }
        } elseif ($tipeLower === 'bahan') {
            $idVeneerBasah = $this->getIdKategoriBarang('Veneer Basah');
            $idVeneerKering = $this->getIdKategoriBarang('Veneer Kering');
            $idVeneerJadi = $this->getIdKategoriBarang('Veneer Jadi');
            $ids = array_filter([$idVeneerBasah, $idVeneerKering, $idVeneerJadi]);
            if (! empty($ids)) {
                $q->whereIn('id_kategori_barang', $ids);
            }
        } elseif ($tipeLower === 'afalan') {
            $idKategori = $this->getIdKategoriBarang('Veneer Afalan');
            if ($idKategori) {
                $q->where('id_kategori_barang', $idKategori);
            }
        } elseif ($tipeLower === 'barang_penolong') {
            $idKategori = $this->getIdKategoriBarang('Bahan');
            if ($idKategori) {
                $q->where('id_kategori_barang', $idKategori);
            }
        }

        return $q;
    }

    // =========================================================================
    // PENCARIAN REFERENSI
    // =========================================================================

    private function fetchReferensi(string $tipe, ?int $idUkuran, ?int $idJenisKayu, ?string $grade, ?int $idGrade = null): ?ReferensiHargaProduksi
    {
        $all = $this->baseQuery($tipe)->get();
        if ($all->isEmpty()) {
            return null;
        }

        $results = $this->filterByUkuran($all, $idUkuran);
        if ($results->isEmpty()) {
            return null;
        }

        $results = $this->filterByKayu($results, $idJenisKayu);
        if ($results->isEmpty()) {
            return null;
        }

        $tipeLower = strtolower(trim($tipe));
        if (in_array($tipeLower, ['triplek', 'plywood', 'platform'])) {
            $results = $this->filterByGrade($results, $grade, $idGrade);
            if ($results->isEmpty()) {
                return null;
            }

            $results = $this->filterByDomain($results);
        }

        return $results->first();
    }

    private function fetchReferensiAfalan(?int $idJenisKayu): ?ReferensiHargaProduksi
    {
        $idSengon = $this->getIdSengon();
        $idMeranti = $this->getIdMeranti();

        $isSengon = $idJenisKayu && $idJenisKayu === $idSengon;
        $idKayuLookup = $isSengon ? $idSengon : $idMeranti;

        $all = $this->baseQuery('afalan')->get();
        if ($all->isEmpty()) {
            return null;
        }

        $byKayu = $all->filter(fn ($i) => $i->id_jenis_kayu == $idKayuLookup);
        $pool = $byKayu->isNotEmpty() ? $byKayu : $all;

        return $pool->first();
    }

    private function resolveRefBahan(?int $idUkuran, ?int $idJenisKayu, ?string $grade): ?ReferensiHargaProduksi
    {
        if ($grade && $this->isAf($grade)) {
            return $this->fetchReferensiAfalan($idJenisKayu);
        }

        $idSengon = $this->getIdSengon();
        $idMeranti = $this->getIdMeranti();
        $idKayuLookup = ($idJenisKayu && $idJenisKayu === $idSengon) ? $idSengon : $idMeranti;

        return $this->fetchReferensi('bahan', $idUkuran, $idKayuLookup, $grade);
    }

    private function aliasBahanPenolong(): array
    {
        return [
            'hdr' => 'hadner',
            'isi_steples' => 'staples',
            'isi steples' => 'staples',
            'tepung_bgs' => 'tepung',
            'tepung bgs' => 'tepung',
            'solasi_putih' => 'isolasi putih',
            'solasi putih' => 'isolasi putih',
            'solasi' => 'isolasi',
        ];
    }

    private function fetchReferensiPenolong(string $namaBahan): ?ReferensiHargaProduksi
    {
        $namaLower = strtolower(trim($namaBahan));
        $namaClean = str_replace('_', ' ', $namaLower);

        $idKategori = $this->getIdKategoriBarang('Bahan');
        if (! $idKategori) {
            return null;
        }

        $results = ReferensiHargaProduksi::with('subAnakAkun')
            ->where('id_kategori_barang', $idKategori)
            ->where(
                fn ($q) => $q
                    ->whereRaw('LOWER(nama) = ?', [$namaLower])
                    ->orWhereRaw("REPLACE(LOWER(nama), '_', ' ') = ?", [$namaClean])
                    ->orWhereRaw('LOWER(nama) LIKE ?', ["%{$namaClean}%"])
                    ->orWhereRaw("REPLACE(LOWER(nama), '_', ' ') LIKE ?", ["%{$namaClean}%"])
            )->get();

        if ($results->isNotEmpty()) {
            return $this->filterGenericFirst($results)->first();
        }

        $alias = $this->aliasBahanPenolong();
        $cariDengan = $alias[$namaLower] ?? $alias[$namaClean] ?? null;

        if ($cariDengan) {
            $byAlias = ReferensiHargaProduksi::with('subAnakAkun')
                ->where('id_kategori_barang', $idKategori)
                ->whereRaw('LOWER(nama) LIKE ?', ["%{$cariDengan}%"])
                ->get();
            if ($byAlias->isNotEmpty()) {
                return $this->filterGenericFirst($byAlias)->first();
            }
        }

        $kata = array_filter(explode(' ', $namaClean), fn ($k) => strlen($k) >= 3);
        usort($kata, fn ($a, $b) => strlen($b) - strlen($a));

        foreach ($kata as $k) {
            $byKata = ReferensiHargaProduksi::with('subAnakAkun')
                ->where('id_kategori_barang', $idKategori)
                ->whereRaw('LOWER(nama) LIKE ?', ["%{$k}%"])
                ->get();
            if ($byKata->isNotEmpty()) {
                return $this->filterGenericFirst($byKata)->first();
            }
        }

        return null;
    }

    /**
     * Resolve id_barang dari API eksternal /api/barang/resolve-veneer,
     * dengan cache per kombinasi parameter supaya tidak memanggil API
     * berkali-kali untuk kombinasi ukuran/jenis/kw yang identik dalam
     * satu laporan. Mengembalikan null (bukan melempar exception) kalau
     * API gagal/timeout, supaya proses export tidak gagal total hanya
     * karena resolver down.
     *
     * HANYA dipakai untuk baris VENEER (bahan masuk hotpress). Triplek
     * dan Platform tidak memakai resolver ini — lihat catatan di header
     * file.
     *
     * @param  string  $jenisVeneer  'Veneer Basah' | 'Veneer Kering' | 'Veneer Jadi' | 'Veneer Afalan'
     * @param  string  $bagian  'Face Back' | 'Core' | 'PPC'
     */
    private function resolveIdBarang(
        string $jenisVeneer,
        string $bagian,
        string $jenisKayu,
        float $tebal,
        float $panjang,
        float $lebar,
        string $kw
    ): ?int {
        $ukuran = $panjang.'x'.$lebar;

        $cacheKey = implode('|', [
            $jenisVeneer,
            $bagian,
            strtolower(trim($jenisKayu)),
            $tebal,
            $ukuran,
            strtolower(trim($kw)),
        ]);

        if (array_key_exists($cacheKey, $this->idBarangCache)) {
            return $this->idBarangCache[$cacheKey];
        }

        $urlApi = rtrim(config('services.akuntansi.url', 'http://localhost:8080'), '/').'/api/barang/resolve-veneer';

        try {
            $response = Http::withoutVerifying()->timeout(10)->get($urlApi, [
                'jenis_veneer' => $jenisVeneer,
                'bagian' => $bagian,
                'jenis_kayu' => $jenisKayu,
                'ketebalan' => $tebal,
                'ukuran' => $ukuran,
                'kw' => $kw,
            ]);

            $idBarang = $response->json('id_barang');
        } catch (\Throwable $e) {
            $idBarang = null;
        }

        return $this->idBarangCache[$cacheKey] = $idBarang;
    }

    /**
     * Menentukan nama kategori "jenis_veneer" untuk dikirim ke resolver,
     * berdasarkan status AF dan grade bahan veneer. Mengikuti pola
     * kategori yang sudah dipakai baseQuery('bahan') / fetchReferensiAfalan().
     */
    private function jenisVeneerUntukResolver(bool $isAf, float $tebal): string
    {
        if ($isAf) {
            return 'Veneer Afalan';
        }

        // Bahan masuk hotpress secara umum berstatus "kering" (belum
        // dipress jadi triplek/platform) — sama seperti asumsi yang
        // dipakai resolveRefBahan()/fetchReferensi('bahan', ...).
        return 'Veneer Kering';
    }

    // =========================================================================
    // ROW BUILDER
    // =========================================================================

    private function makeRow(
        string $namaAkun,
        string $noAkun,
        string $tgl,
        string $namaProduksi,
        string $ket,
        string $map,
        string $hitKbk,
        $banyak,
        $m3,
        $harga,
        $total = null,
        $idBarang = null
    ): array {
        return [
            $namaAkun,
            $tgl,
            '',
            "\t".$noAkun,
            '',
            '',
            $namaProduksi,
            $ket,
            $map,
            $hitKbk,
            $banyak,
            $m3,
            $harga,
            $total,
            $idBarang,
        ];
    }

    // =========================================================================
    // MAIN: array()
    // =========================================================================
    public function array(): array
    {
        $rows = [];
        $rows[] = ['Nama Akun', 'tgl', 'jurnal', 'No Akun', 'No', 'mm', 'Nama', 'Keterangan', 'map', 'hit kbk', 'Banyak', 'M3', 'Harga', 'Total', 'ID Barang'];

        $produksis = ProduksiHp::with([
            'triplekHasilHp.mesin',
            'triplekHasilHp.barangSetengahJadi.jenisBarang',
            'triplekHasilHp.barangSetengahJadi.ukuran',
            'triplekHasilHp.barangSetengahJadi.grade',
            'platformHasilHp.mesin',
            'platformHasilHp.barangSetengahJadi.jenisBarang',
            'platformHasilHp.barangSetengahJadi.ukuran',
            'platformHasilHp.barangSetengahJadi.grade',
            'bahanHotpress.mutasiKeluarPalet.mutasiKeluar.jenisKayu',
            'bahanHotpress.barangSetengahJadi.jenisBarang',
            'bahanHotpress.barangSetengahJadi.ukuran',
            'bahanHotpress.barangSetengahJadi.grade',
            'bahanPenolongHp',
            'detailPegawaiHp',
        ])->whereDate('tanggal_produksi', $this->tanggal)->get();

        if ($produksis->isEmpty()) {
            return $rows;
        }

        $tglStr = Carbon::parse($this->tanggal)->format('d-m-Y');
        $hargaPegawaiMaster = 150000;

        foreach ($produksis as $prod) {
            $shiftStr = $prod->shift ?? 'pagi';
            $hasilByMesin = [];

            foreach ($prod->triplekHasilHp as $t) {
                $hasilByMesin[$t->id_mesin][] = ['tipe' => 'triplek',  'data' => $t];
            }
            foreach ($prod->platformHasilHp as $p) {
                $hasilByMesin[$p->id_mesin][] = ['tipe' => 'platform', 'data' => $p];
            }

            uasort($hasilByMesin, fn ($a, $b) => strcmp(
                strtolower($a[0]['data']->mesin->nama_mesin ?? ''),
                strtolower($b[0]['data']->mesin->nama_mesin ?? '')
            ));

            $jumlahMesin = count($hasilByMesin);
            $hp3Id = null;
            if ($jumlahMesin === 1) {
                $hp3Id = array_key_first($hasilByMesin);
            } else {
                foreach ($hasilByMesin as $mId => $items) {
                    if (str_contains(strtolower($items[0]['data']->mesin->nama_mesin ?? ''), '3')) {
                        $hp3Id = $mId;
                        break;
                    }
                }
                if (! $hp3Id) {
                    $hp3Id = array_key_last($hasilByMesin);
                }
            }

            $jumlahPekerja = $prod->detailPegawaiHp->count();

            foreach ($hasilByMesin as $mId => $items) {
                $namaMesinAsli = $items[0]['data']->mesin->nama_mesin ?? 'HOTPRESS 1';
                $namaMesinSingkat = $this->getNamaMesinSingkat($namaMesinAsli, $shiftStr);

                $totalHargaProdukHp3 = 0;
                $totalHargaBahanGlobal = 0;
                $totalProdHp1Hp2 = 0;

                // =======================================================
                // 1. HASIL PRODUKSI (DEBIT)
                //    - Triplek: akun RAW (belum ada mapping COA baru)
                //    - Platform: akun DI-ALIAS ke COA baru (Platform Jadi)
                //    ID Barang: TIDAK di-resolve di sini — lihat catatan
                //    header file (resolver hanya untuk bahan veneer).
                // =======================================================
                foreach ($items as $item) {
                    $tipe = $item['tipe'];
                    $data = $item['data'];
                    $u = $data->barangSetengahJadi->ukuran ?? null;
                    $idUkuran = $u?->id;
                    $jk = $data->barangSetengahJadi->jenisBarang->nama_jenis_barang ?? 'sengon';
                    $idJenisKayu = $this->resolveIdJenisKayu($jk);
                    $grStr = $data->barangSetengahJadi->grade->nama_grade ?? '';
                    $idGrade = $data->barangSetengahJadi->grade->id ?? null;
                    $tebal = $u?->tebal ?? 0;
                    $banyak = $data->isi ?? 0;
                    $m3Round = $u ? round(($u->panjang * $u->lebar * $tebal * $banyak) / 10_000_000, 4) : 0;

                    $ref = $this->fetchReferensi($tipe, $idUkuran, $idJenisKayu, $grStr, $idGrade);

                    [$akunNama, $akunNo, $hargaHpp] = ($tipe === 'platform')
                        ? $this->coaAlias->extractAkunPlatform($ref, true)   // hasil produksi -> Platform Jadi
                        : $this->coaAlias->extractAkunTriplek($ref);         // hasil produksi -> Triplek Mentah (1403.2)

                    if ($ref) {
                        $keterangan = $ref->nama;
                    } else {
                        $tebalInt = (int) $tebal;
                        $jkSingkat = strtolower(substr($jk, 0, 1));
                        $kwStr = strtolower($grStr);
                        $keterangan = "{$tebalInt}{$jkSingkat} {$kwStr} MTH [UNKNOWN - cek master data]";
                    }

                    // TODO: resolver triplek/platform — belum ada endpoint
                    // resolve-triplek / resolve-platform yang dikonfirmasi,
                    // jadi ID Barang dibiarkan null untuk baris hasil produksi.
                    $rows[] = $this->makeRow($akunNama, $akunNo, $tglStr, $namaMesinSingkat, $keterangan, 'd', 'b', $banyak, $m3Round, $hargaHpp, null, null);

                    $totalProdValue = round($banyak * $hargaHpp, 0);
                    if ($mId != $hp3Id) {
                        $totalProdHp1Hp2 += $totalProdValue;
                    } else {
                        $totalHargaProdukHp3 += $totalProdValue;
                    }
                }

                if ($mId != $hp3Id && $totalProdHp1Hp2 > 0) {
                    $hpp = $this->coaAlias->getAkunHpp();
                    $rows[] = $this->makeRow($hpp['nama'], $hpp['no'], $tglStr, $namaMesinSingkat, '', 'k', '', '', '', round($totalProdHp1Hp2, 0));
                }

                // =======================================================
                // 2. BIAYA HP3 (KREDIT)
                // =======================================================
                if ($mId == $hp3Id) {

                    // --- BAHAN HOTPRESS (veneer di-alias, platform di-alias ke Platform Mentah) ---
                    $veneerMap = [];

                    foreach ($prod->bahanHotpress as $bahan) {
                        $mutasiHeader = $bahan->mutasiKeluarPalet?->mutasiKeluar;
                        $bsj = $bahan->barangSetengahJadi ?? null;

                        if ($mutasiHeader) {
                            $p = (float) ($mutasiHeader->panjang ?? 0);
                            $l = (float) ($mutasiHeader->lebar ?? 0);
                            $tebal = (float) ($mutasiHeader->tebal ?? 0);
                            $idJenisKayu = $mutasiHeader->id_jenis_kayu ?? null;
                            $jkAsli = $mutasiHeader->jenisKayu?->nama_kayu ?? 'sengon';
                            $grAsli = (string) ($mutasiHeader->kw_grade ?? '');
                            $idUkuran = null;
                        } elseif ($bsj) {
                            $u = $bsj->ukuran ?? null;
                            $idUkuran = $u?->id;
                            $p = (float) ($u?->panjang ?? 0);
                            $l = (float) ($u?->lebar ?? 0);
                            $tebal = (float) ($u?->tebal ?? 0);
                            $jkAsli = $bsj->jenisBarang->nama_jenis_barang ?? 'sengon';
                            $idJenisKayu = $this->resolveIdJenisKayu($jkAsli);
                            $grAsli = $bsj->grade->nama_grade ?? '';
                        } else {
                            continue;
                        }

                        $banyak = $bahan->isi ?? 0;

                        $ketBahan = strtolower(trim($bahan->ket ?? ''));
                        $entries = [];

                        $polaAngkaDulu = '/([+-]?\s*\d+)\s*(?:lembar|lbr|pcs|ply)?\s*(kelebihan|kehilangan)/';
                        $polaKataDulu = '/(kelebihan|kehilangan)\s*([+-]?\s*\d+)/';

                        $jumlahAdj = null;
                        $kataAdj = null;

                        if (preg_match($polaAngkaDulu, $ketBahan, $m)) {
                            $jumlahAdj = (int) preg_replace('/\s+/', '', $m[1]);
                            $kataAdj = $m[2];
                        } elseif (preg_match($polaKataDulu, $ketBahan, $m)) {
                            $kataAdj = $m[1];
                            $jumlahAdj = (int) preg_replace('/\s+/', '', $m[2]);
                        }

                        if ($kataAdj !== null) {
                            $statusAdj = $kataAdj === 'kelebihan' ? 'lebih' : 'hilang';

                            if ($banyak != 0) {
                                $entries[] = ['banyak' => $banyak, 'status' => 'normal'];
                            }
                            if ($jumlahAdj != 0) {
                                $entries[] = ['banyak' => abs($jumlahAdj), 'status' => $statusAdj];
                            }
                        } else {
                            $statusLama = str_contains($ketBahan, 'kelebihan')
                                ? 'lebih'
                                : (str_contains($ketBahan, 'kehilangan') ? 'hilang' : 'normal');
                            $entries[] = ['banyak' => $banyak, 'status' => $statusLama];
                        }

                        if (empty($entries)) {
                            continue;
                        }

                        $m3PerLembar = ($p * $l * $tebal) / 10_000_000;

                        $isAf = $this->isAf($grAsli);
                        $isPlatform = ! $isAf && $this->isPlatformGrade($grAsli);

                        $ref = $isPlatform
                            ? $this->fetchReferensi('platform', $idUkuran, $idJenisKayu, $grAsli)
                            : $this->resolveRefBahan($idUkuran, $idJenisKayu, $grAsli);

                        // Platform: akun DI-ALIAS ke COA baru -> Platform Mentah (bahan masuk).
                        // Veneer (basah/kering/jadi/af): akun DI-ALIAS ke COA baru.
                        [$akunNama, $akunNo, $hargaBahan] = $isPlatform
                            ? $this->coaAlias->extractAkunPlatform($ref, false)   // bahan masuk HP -> Platform Mentah
                            : $this->coaAlias->extractAkunVeneer($ref);

                        if ($isPlatform) {
                            $tipeVeneer = 'Platform';
                            $ketDasar = $ref?->nama ?? "Platform {$jkAsli} uk {$tebal} [UNKNOWN - cek master data]";
                        } else {
                            $tipeVeneer = $isAf ? 'AF' : (($tebal < 1) ? '260 F/B' : '130 Core');
                            $ketDasar = "{$tipeVeneer} {$jkAsli} uk {$tebal}".($ref ? '' : ' [UNKNOWN - cek master data]');
                        }

                        $katGrade = $this->kategoriGrade($grAsli);

                        // NEW: resolve id_barang untuk bahan VENEER (bukan
                        // platform). Parameter "bagian" mengikuti pola yang
                        // sama seperti JurnalSheetV2/JurnalRepairSheetV2:
                        // AF -> PPC, tebal < 1 -> Face Back, selain itu Core.
                        $idBarangBahan = null;
                        if (! $isPlatform) {
                            $bagianResolver = $isAf ? 'PPC' : (($tebal < 1) ? 'Face Back' : 'Core');
                            $jenisVeneerResolver = $this->jenisVeneerUntukResolver($isAf, $tebal);
                            $idBarangBahan = $this->resolveIdBarang(
                                $jenisVeneerResolver,
                                $bagianResolver,
                                $jkAsli,
                                $tebal,
                                $p,
                                $l,
                                $grAsli
                            );
                        }

                        foreach ($entries as $entry) {
                            $statusKey = $entry['status'];
                            $banyakItem = $entry['banyak'];
                            $m3Item = $m3PerLembar * $banyakItem;

                            $ketVeneer = $ketDasar;
                            if ($statusKey === 'lebih') {
                                $ketVeneer .= ' // kelebihan';
                            }
                            if ($statusKey === 'hilang') {
                                $ketVeneer .= ' // kehilangan';
                            }

                            $groupKey = "{$akunNo}_{$tipeVeneer}_{$tebal}_{$p}x{$l}_{$katGrade}_{$statusKey}";

                            if (! isset($veneerMap[$groupKey])) {
                                $veneerMap[$groupKey] = [
                                    'akun_nama' => $akunNama,
                                    'akun_no' => $akunNo,
                                    'ket' => $ketVeneer,
                                    'banyak' => 0,
                                    'm3' => 0.0,
                                    'harga' => $hargaBahan,
                                    'map' => $statusKey === 'lebih' ? 'd' : 'k',
                                    'is_platform' => $isPlatform,
                                    'status' => $statusKey,
                                    'id_barang' => $idBarangBahan,
                                ];
                            }
                            $veneerMap[$groupKey]['banyak'] += $banyakItem;
                            $veneerMap[$groupKey]['m3'] += $m3Item;
                        }
                    }

                    $veneerNormal = array_filter($veneerMap, fn ($v) => $v['status'] === 'normal');
                    $veneerLebih = array_filter($veneerMap, fn ($v) => $v['status'] === 'lebih');
                    $veneerHilang = array_filter($veneerMap, fn ($v) => $v['status'] === 'hilang');
                    $veneerUrut = array_merge($veneerNormal, $veneerLebih, $veneerHilang);

                    foreach ($veneerUrut as $v) {
                        $hitKbk = $v['is_platform'] ? 'b' : 'm';
                        $m3Round = round($v['m3'], 4);

                        $nilai = $v['is_platform']
                            ? round($v['banyak'] * $v['harga'], 0)
                            : round($m3Round * $v['harga'], 0);

                        $totalHargaBahanGlobal += ($v['map'] === 'd') ? -$nilai : $nilai;

                        $rows[] = $this->makeRow(
                            $v['akun_nama'],
                            $v['akun_no'],
                            $tglStr,
                            $namaMesinSingkat,
                            $v['ket'],
                            $v['map'],
                            $hitKbk,
                            $v['banyak'],
                            $m3Round,
                            $v['harga'],
                            null,
                            $v['id_barang']
                        );
                    }

                    // --- BAHAN PENOLONG (akun RAW, tidak diubah; ID Barang null) ---
                    foreach ($prod->bahanPenolongHp as $penolong) {
                        $namaBahanLower = strtolower(trim($penolong->nama_bahan));

                        if (
                            str_contains($namaBahanLower, 'kalsium') ||
                            str_contains($namaBahanLower, 'semen') ||
                            str_contains($namaBahanLower, 'pvac')
                        ) {
                            continue;
                        }

                        $banyak = $penolong->jumlah;
                        $ref = $this->fetchReferensiPenolong($penolong->nama_bahan);
                        [$akunNama, $akunNo, $hargaPenolong] = $this->coaAlias->extractAkunPenolong($ref, $penolong->nama_bahan);

                        $ketPenolong = $ref?->nama ?? "{$penolong->nama_bahan} [UNKNOWN - cek master data]";

                        $totalHargaBahanGlobal += round($banyak * $hargaPenolong, 0);
                        $rows[] = $this->makeRow($akunNama, $akunNo, $tglStr, $namaMesinSingkat, $ketPenolong, 'k', 'b', $banyak, '', $hargaPenolong);
                    }

                    // --- HUTANG GAJI — 2231.00 -> 2195.1 (ID Barang null) ---
                    if ($jumlahPekerja > 0) {
                        $akunGaji = $this->coaAlias->getAkunGaji($this->isWhn());
                        $totalGaji = round($jumlahPekerja * $hargaPegawaiMaster, 0);
                        $totalHargaBahanGlobal += $totalGaji;
                        $rows[] = $this->makeRow($akunGaji['hutang']['nama'], $akunGaji['hutang']['no'], $tglStr, $namaMesinSingkat, '', 'k', 'b', $jumlahPekerja, '', $hargaPegawaiMaster);
                    }

                    // --- HPP PENYEIMBANG HP3 — 6111.xx -> 5069.2 (ID Barang null) ---
                    $nilaiHppHp3 = $totalHargaBahanGlobal - $totalHargaProdukHp3;
                    if (round(abs($nilaiHppHp3), 0) != 0) {
                        $hpp = $this->coaAlias->getAkunHpp();
                        $mapHpp = $nilaiHppHp3 > 0 ? 'd' : 'k';
                        $rows[] = $this->makeRow($hpp['nama'], $hpp['no'], $tglStr, $namaMesinSingkat, '', $mapHpp, '', '', '', round(abs($nilaiHppHp3), 0));
                    }
                }
            }

            $rows[] = array_fill(0, 15, '');
        }

        return $rows;
    }

    // =========================================================================
    // MAP: inject formula Excel untuk kolom N (Total)
    // =========================================================================

    public function map($row): array
    {
        $this->rowIndex++;

        if ($this->rowIndex === 1 || implode('', (array) $row) === '') {
            return $row;
        }

        $r = $this->rowIndex;
        $row[13] = "=ROUND(IF(J{$r}=\"m\", M{$r}*L{$r}, IF(J{$r}=\"b\", M{$r}*K{$r}, M{$r})), 0)";

        return $row;
    }
}
