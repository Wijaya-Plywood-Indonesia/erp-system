<?php

namespace App\Exports\Sheets;

use App\Models\BahanPenolongProduksi;
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
use Illuminate\Support\Facades\Log;
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
// - Akun veneer bahan hotpress, platform (hasil & bahan), triplek hasil
//   produksi, dan bahan penolong di-alias ke COA baru lewat
//   App\Services\CoaAliasService.
// - HARGA bahan penolong diambil dari model BahanPenolongProduksi
//   (kategori_produksi = 'hot_press'); fallback ke harga alias referensi.
// - Kolom "No Akun" (D) dipaksa TEKS eksplisit.
// - Kolom "ID Barang" (O) diisi dari API akuntansi:
//     * Bahan veneer          -> /api/barang/resolve-veneer
//     * Triplek / Platform    -> /api/barang/resolve-plywood
//     * Bahan penolong        -> /api/barang/resolve-penolong
//   Baris tanpa barang (Hutang Gaji, Selisih/HPP) = null (memang tidak
//   punya ID barang).
//
// PERUBAHAN TERBARU:
// - SUMBER BAHAN TRIPLEK (bahan_hotpress.sumber = 'triplek'): sekarang
//   dibaca dari relasi mutasiKeluarTriplek.mutasiKeluar (stok Triplek JADI).
//   Sebelumnya baris ini jatuh ke `continue` dan tidak masuk jurnal.
// - TAHAP MENTAH / JADI pada referensi_harga_produksi:
//     * HASIL produksi hotpress (triplek & platform) -> referensi MENTAH
//     * BAHAN dari mutasi keluar platform / triplek   -> referensi JADI
//     * BAHAN lewat barang setengah jadi (legacy)     -> referensi MENTAH
//   Kategori JADI ("Plywood Jadi", "Platform Jadi") dicari dengan nama
//   PERSIS (getIdKategoriTahap) supaya tidak tertukar dengan kategori
//   mentah lewat LIKE. Kalau varian JADI belum ada di master, otomatis
//   fallback ke referensi MENTAH + Log::warning + keterangan ditandai
//   "[ref JADI belum ada, pakai MENTAH]".
// - FIX klasifikasi: grade "better" tidak lagi membuat triplek dianggap
//   platform (isPlatformGrade hanya berlaku untuk sumber non-triplek).
// - resolve-plywood sekarang mengirim parameter `tahap` (mentah|jadi).
//   PASTIKAN endpoint akuntansi membacanya.
// - AKUN PLATFORM DIBALIK (koreksi alur akuntansi):
//     * Platform HASIL produksi hotpress -> Persediaan Platform MENTAH (1403.1)
//       (extractAkunPlatform($ref, false))
//     * Platform BAHAN hotpress          -> Persediaan Platform JADI (1403.3)
//       (extractAkunPlatform($ref, true))
//   Sebelumnya terbalik. Nilai debit/kredit tidak berubah, hanya akunnya.
// - FIX Repair: di master JenisKayu, "Repair" tercatat sebagai JENIS KAYU,
//   padahal di master barang akuntansi "Repair" adalah GRADE
//   (mis. "... M 244x122x0.3mm grade Repair"). Sebelum memanggil resolver,
//   bahan veneer yang jenis kayunya "Repair" dinormalisasi lewat
//   normalisasiParamVeneerResolver(): jenis_kayu -> REPAIR_KAYU_DASAR,
//   kw -> 'Repair'. Keterangan jurnal TIDAK berubah.
// - Jenis veneer untuk Repair diatur konstanta JENIS_VENEER_REPAIR.
// - Semua resolver API sekarang mencatat Log::warning kalau id_barang
//   tidak ketemu (berisi nama_barang_format yang dicari + parameter),
//   supaya kegagalan tidak lagi senyap.
// ============================================================
class LaporanProduksiHotPressJurnalSheetV2 implements FromArray, WithColumnWidths, WithEvents, WithMapping, WithStyles, WithTitle
{
    /**
     * Kayu dasar untuk veneer Repair saat dikirim ke resolver.
     * Di master barang, Repair yang ada memakai kode 'M' (Meranti).
     * Ubah jika ada Repair dari kayu lain.
     */
    private const REPAIR_KAYU_DASAR = 'Meranti';

    /**
     * Jenis veneer untuk Repair saat dikirim ke resolver.
     * Stok veneer Repair di hotpress berasal dari tabel veneer_jadi_mutasi_keluars
     * (hasil proses repair yang sudah selesai), sehingga akunnya adalah Veneer Jadi.
     * Barang yang dipakai: id 1858 =
     * 'Veneer Jadi Face Back M 244x122x0.3mm grade Repair' di akuntansi.
     */
    private const JENIS_VENEER_REPAIR = 'Veneer Jadi';

    /**
     * Tahap barang untuk referensi_harga_produksi (triplek & platform).
     */
    private const TAHAP_MENTAH = 'mentah';

    private const TAHAP_JADI = 'jadi';

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

    private array $gradeKategoriCache = [];

    private array $ukuranDimCache = [];

    /**
     * Cache harga bahan penolong dari model BahanPenolongProduksi
     * (kategori_produksi = 'hot_press'). Key = nama_bahan_penolong lowercase.
     *
     * @var array<string, float>
     */
    private array $bahanPenolongCache = [];

    /**
     * Cache hasil resolve id_barang dari API eksternal.
     */
    private array $idBarangCache = [];

    public function __construct(string $tanggal, string $domain, ?CoaAliasService $coaAlias = null)
    {
        $this->tanggal = $tanggal;
        $this->domain = $domain;
        $this->coaAlias = $coaAlias ?? app(CoaAliasService::class);

        // Cache semua bahan penolong kategori hot_press dari DB, key = lowercase nama
        BahanPenolongProduksi::where('kategori_produksi', 'hot_press')
            ->get()
            ->each(function (BahanPenolongProduksi $b) {
                $this->bahanPenolongCache[strtolower(trim($b->nama_bahan_penolong))] = (float) ($b->harga ?? 0);
            });
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

        // Kolom D (No Akun) diformat sebagai TEKS, bukan numerik.
        $sheet->getStyle("D2:D{$lastRow}")->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);

        $sheet->getStyle("L2:L{$lastRow}")->getNumberFormat()->setFormatCode('0.0000');
        $sheet->getStyle("M2:N{$lastRow}")->getNumberFormat()->setFormatCode('#,##0');
        $sheet->getStyle("O2:O{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getRowDimension(1)->setRowHeight(20);

        return [];
    }

    /**
     * Paksa kolom "No Akun" (D) disimpan sebagai STRING eksplisit di level
     * cell. Prefix "\t" dari makeRow() dihapus di sini sebelum disimpan
     * ulang sebagai string eksplisit.
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

    private function isRepair(string $jenisKayu): bool
    {
        return str_contains(strtolower(trim($jenisKayu)), 'repair');
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

    /**
     * Nama jenis kayu dari header mutasi keluar yang relasinya tidak seragam
     * antar-model (platform: jenisBarang, veneer: jenisKayu). Mencoba relasi
     * yang ADA di model; kalau tidak ada satupun, dicatat ke log.
     */
    private function namaJenisDariHeader($header): string
    {
        foreach (['jenisBarang' => 'nama_jenis_barang', 'jenisKayu' => 'nama_kayu'] as $relasi => $kolom) {
            if (method_exists($header, $relasi)) {
                $nama = $header->{$relasi}?->{$kolom};
                if ($nama) {
                    return (string) $nama;
                }
            }
        }

        Log::warning('HotPress V2: jenis kayu tidak ditemukan di header mutasi keluar, dipakai default sengon', [
            'model' => get_class($header),
            'id' => $header->getKey(),
        ]);

        return 'sengon';
    }

    private function resolveIdJenisKayu(?string $namaJenisBarang): ?int
    {
        if (! $namaJenisBarang) {
            return null;
        }

        return $this->getIdKayuByNama(strtolower(trim($namaJenisBarang)));
    }

    /**
     * Cari id grade di SATU kategori berdasarkan NAMA grade
     * (toleran ejaan local/lokal).
     */
    private function getIdGradeByKategori(string $namaGrade, ?int $idKategori): ?int
    {
        $key = strtolower(trim($namaGrade));
        if ($key === '' || ! $idKategori) {
            return null;
        }

        $cacheKey = "{$idKategori}|{$key}";
        if (array_key_exists($cacheKey, $this->gradeKategoriCache)) {
            return $this->gradeKategoriCache[$cacheKey];
        }

        try {
            $keyLocal = str_replace('lokal', 'local', $key);
            $keyLokal = str_replace('local', 'lokal', $key);

            $grade = Grade::where('id_kategori_barang', $idKategori)
                ->where(function ($q) use ($key, $keyLocal, $keyLokal) {
                    $q->whereRaw('LOWER(nama_grade) = ?', [$key])
                        ->orWhereRaw('LOWER(nama_grade) = ?', [$keyLocal])
                        ->orWhereRaw('LOWER(nama_grade) = ?', [$keyLokal]);
                })->first();

            return $this->gradeKategoriCache[$cacheKey] = $grade?->id;
        } catch (\Throwable $e) {
            return $this->gradeKategoriCache[$cacheKey] = null;
        }
    }

    /**
     * Id grade untuk kategori dasar ('Platform' | 'Plywood') pada tahap tertentu.
     * Untuk tahap JADI: coba grade milik kategori JADI dulu, lalu grade
     * kategori MENTAH (kalau grade dipakai bersama antar-tahap).
     */
    private function getIdGradeTahap(string $namaGrade, string $dasar, string $tahap): ?int
    {
        $id = $this->getIdGradeByKategori($namaGrade, $this->getIdKategoriTahap($dasar, $tahap));
        if ($id || $tahap !== self::TAHAP_JADI) {
            return $id;
        }

        return $this->getIdGradeByKategori($namaGrade, $this->getIdKategoriTahap($dasar, self::TAHAP_MENTAH));
    }

    /**
     * Cari id ukuran dari panjang x lebar x tebal (urutan panjang/lebar
     * boleh tertukar).
     */
    private function resolveIdUkuranByDimensi(float $p, float $l, float $t): ?int
    {
        if ($p <= 0 || $l <= 0 || $t <= 0) {
            return null;
        }

        $key = "{$p}x{$l}x{$t}";
        if (array_key_exists($key, $this->ukuranDimCache)) {
            return $this->ukuranDimCache[$key];
        }

        try {
            $u = Ukuran::where('panjang', $p)->where('lebar', $l)->where('tebal', $t)->first()
                ?? Ukuran::where('panjang', $l)->where('lebar', $p)->where('tebal', $t)->first();

            return $this->ukuranDimCache[$key] = $u?->id;
        } catch (\Throwable $e) {
            return $this->ukuranDimCache[$key] = null;
        }
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

    /**
     * Id kategori per tahap. $dasar = 'Plywood' | 'Platform'.
     *  - JADI  : nama PERSIS "{dasar} Jadi".
     *  - MENTAH: nama persis "{dasar} Mentah", lalu persis "{dasar}", lalu
     *            LIKE yang TIDAK mengandung "jadi".
     * Nama persis dipakai supaya "Platform Jadi" tidak ikut tertangkap saat
     * mencari kategori mentah (dan sebaliknya).
     */
    private function getIdKategoriTahap(string $dasar, string $tahap): ?int
    {
        $dasarL = strtolower(trim($dasar));
        $cacheKey = "tahap|{$dasarL}|{$tahap}";
        if (array_key_exists($cacheKey, $this->kategoriCache)) {
            return $this->kategoriCache[$cacheKey];
        }

        try {
            $kandidat = $tahap === self::TAHAP_JADI ? ["{$dasarL} jadi"] : ["{$dasarL} mentah", $dasarL];

            foreach ($kandidat as $nama) {
                $k = KategoriBarang::whereRaw('LOWER(TRIM(nama_kategori)) = ?', [$nama])->first();
                if ($k) {
                    return $this->kategoriCache[$cacheKey] = $k->id;
                }
            }

            if ($tahap !== self::TAHAP_JADI) {
                $k = KategoriBarang::whereRaw('LOWER(nama_kategori) LIKE ?', ["%{$dasarL}%"])
                    ->whereRaw('LOWER(nama_kategori) NOT LIKE ?', ['%jadi%'])
                    ->first();
                if ($k) {
                    return $this->kategoriCache[$cacheKey] = $k->id;
                }
            }

            return $this->kategoriCache[$cacheKey] = null;
        } catch (\Throwable $e) {
            return $this->kategoriCache[$cacheKey] = null;
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

    /**
     * Ambil harga bahan penolong dari model BahanPenolongProduksi
     * (kategori hot_press). Exact match dulu, lalu fuzzy (saling mengandung).
     * Return null jika tidak ketemu atau harga belum diisi (0).
     */
    private function getHargaBahanPenolong(string $namaBahan): ?float
    {
        $nama = strtolower(trim($namaBahan));

        if ($nama === '') {
            return null;
        }

        if (isset($this->bahanPenolongCache[$nama]) && $this->bahanPenolongCache[$nama] > 0) {
            return $this->bahanPenolongCache[$nama];
        }

        foreach ($this->bahanPenolongCache as $key => $harga) {
            if ($harga > 0 && $key !== '' && (str_contains($nama, $key) || str_contains($key, $nama))) {
                return $harga;
            }
        }

        return null;
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

        if ($filtered->isNotEmpty()) {
            return $filtered;
        }

        // Tidak ada penanda domain eksplisit (mis. varian JADI yang namanya tanpa "MTH",
        // karena MTH = mentah). Buang saja referensi yang bertanda domain LAIN.
        $domainLain = $this->isWhn() ? ['wjy'] : ['whn'];
        $tanpaDomainLain = $c->filter(function ($item) use ($domainLain) {
            $namaRef = strtolower($item->nama ?? '');
            foreach ($domainLain as $s) {
                if (str_contains($namaRef, $s)) {
                    return false;
                }
            }

            return true;
        });

        return $tanpaDomainLain->isNotEmpty() ? $tanpaDomainLain : $c;
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

    private function baseQuery(string $tipe, string $tahap = self::TAHAP_MENTAH): Builder
    {
        $q = ReferensiHargaProduksi::with('subAnakAkun');
        $tipeLower = strtolower(trim($tipe));

        if (in_array($tipeLower, ['triplek', 'plywood', 'platform'])) {
            $dasar = $tipeLower === 'platform' ? 'Platform' : 'Plywood';
            $idKategori = $this->getIdKategoriTahap($dasar, $tahap);

            if ($idKategori) {
                $q->where('id_kategori_barang', $idKategori);
            } elseif ($tahap === self::TAHAP_JADI) {
                // Kategori JADI belum ada -> kosongkan hasil supaya fallback ke MENTAH jalan.
                $q->whereRaw('1 = 0');
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

    private function fetchReferensi(string $tipe, ?int $idUkuran, ?int $idJenisKayu, ?string $grade, ?int $idGrade = null, string $tahap = self::TAHAP_MENTAH): ?ReferensiHargaProduksi
    {
        $all = $this->baseQuery($tipe, $tahap)->get();
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

    /**
     * Cari referensi sesuai tahap. Kalau varian JADI belum ada di master,
     * fallback ke MENTAH (jurnal tetap jalan) tetapi ditandai + di-log.
     *
     * @return array{0: ?ReferensiHargaProduksi, 1: bool} [ref, fallbackKeMentah]
     */
    private function fetchReferensiTahap(string $tipe, string $tahap, ?int $idUkuran, ?int $idJenisKayu, ?string $grade, ?int $idGrade = null): array
    {
        $ref = $this->fetchReferensi($tipe, $idUkuran, $idJenisKayu, $grade, $idGrade, $tahap);
        if ($ref || $tahap === self::TAHAP_MENTAH) {
            return [$ref, false];
        }

        $ref = $this->fetchReferensi($tipe, $idUkuran, $idJenisKayu, $grade, $idGrade, self::TAHAP_MENTAH);

        Log::warning("HotPress V2: referensi {$tipe} JADI tidak ditemukan, fallback ke MENTAH", [
            'tanggal' => $this->tanggal,
            'id_ukuran' => $idUkuran,
            'id_jenis_kayu' => $idJenisKayu,
            'grade' => $grade,
            'fallback_ditemukan' => (bool) $ref,
        ]);

        return [$ref, (bool) $ref];
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

    // =========================================================================
    // AKUN TRIPLEK (bahan, tahap mentah/jadi)
    // =========================================================================

    /**
     * Akun triplek untuk BAHAN hotpress. Butuh CoaAliasService::extractAkunTriplek
     * yang menerima parameter kedua ($jadi). Kalau belum, akun yang keluar
     * tetap Triplek Mentah -> dicatat ke log supaya tidak salah akun diam-diam.
     */
    private function extractAkunTriplekBahan(?ReferensiHargaProduksi $ref, bool $jadi): array
    {
        if ($jadi) {
            try {
                $jumlahParam = (new \ReflectionMethod($this->coaAlias, 'extractAkunTriplek'))->getNumberOfParameters();
                if ($jumlahParam < 2) {
                    Log::warning('HotPress V2: CoaAliasService::extractAkunTriplek belum mendukung parameter $jadi; bahan triplek JADI memakai akun Triplek Mentah.');
                }
            } catch (\Throwable $e) {
                // abaikan, lanjut
            }
        }

        return $this->coaAlias->extractAkunTriplek($ref, $jadi);
    }

    // =========================================================================
    // RESOLVER ID BARANG (API akuntansi)
    // =========================================================================

    /**
     * Panggil endpoint resolver akuntansi dan kembalikan id_barang (atau null).
     * Kalau id_barang tidak ketemu / request gagal, dicatat ke log beserta
     * nama_barang_format yang dicari server, supaya mudah ditelusuri.
     */
    private function callResolver(string $endpoint, array $params): ?int
    {
        $urlApi = rtrim(config('services.akuntansi.url', 'http://localhost:8080'), '/').'/api/barang/'.$endpoint;

        try {
            $response = Http::withoutVerifying()->timeout(10)->get($urlApi, $params);
            $idBarang = $response->json('id_barang');

            if (! $idBarang) {
                Log::warning("HotPress V2: resolver {$endpoint} tidak menemukan barang", [
                    'tanggal' => $this->tanggal,
                    'params' => $params,
                    'status' => $response->status(),
                    'nama_barang_format' => $response->json('nama_barang_format'),
                    'message' => $response->json('message'),
                ]);
            }

            return $idBarang ? (int) $idBarang : null;
        } catch (\Throwable $e) {
            Log::warning("HotPress V2: resolver {$endpoint} gagal dipanggil", [
                'tanggal' => $this->tanggal,
                'params' => $params,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Normalisasi parameter veneer sebelum dikirim ke resolver.
     *
     * Di master JenisKayu, "Repair" tercatat sebagai jenis kayu, sedangkan
     * di master barang akuntansi "Repair" adalah GRADE (mis.
     * "Veneer Basah Face Back M 244x122x0.3mm grade Repair").
     *
     * @return array{0: string, 1: string, 2: string} [jenisVeneer, jenisKayu, kw]
     */
    private function normalisasiParamVeneerResolver(
        string $jenisVeneer,
        string $jenisKayu,
        string $kw
    ): array {
        if ($this->isRepair($jenisKayu)) {
            return [self::JENIS_VENEER_REPAIR, self::REPAIR_KAYU_DASAR, 'Repair'];
        }

        return [$jenisVeneer, $jenisKayu, $kw];
    }

    /**
     * Resolve id_barang dari API /api/barang/resolve-veneer, dengan cache
     * per kombinasi parameter. HANYA dipakai untuk baris VENEER.
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
        [$jenisVeneer, $jenisKayu, $kw] = $this->normalisasiParamVeneerResolver($jenisVeneer, $jenisKayu, $kw);

        $ukuran = $panjang.'x'.$lebar;

        $cacheKey = implode('|', [
            'veneer',
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

        $idBarang = $this->callResolver('resolve-veneer', [
            'jenis_veneer' => $jenisVeneer,
            'bagian' => $bagian,
            'jenis_kayu' => $jenisKayu,
            'ketebalan' => $tebal,
            'ukuran' => $ukuran,
            'kw' => $kw,
        ]);

        return $this->idBarangCache[$cacheKey] = $idBarang;
    }

    /**
     * Resolve id_barang untuk Triplek / Platform dari /api/barang/resolve-plywood
     *
     * @param  string  $tipe  'triplek' | 'platform'
     * @param  string|null  $namaKeterangan  mis. "12m better MTH", "platform 12 better MTH"
     * @param  string  $tahap  'mentah' | 'jadi' (dikirim ke API sebagai `tahap`)
     */
    private function resolveIdBarangPlywood(
        string $tipe,
        ?string $namaKeterangan,
        float $tebal,
        string $jenisKayu = '',
        string $grade = '',
        string $tahap = self::TAHAP_MENTAH
    ): ?int {
        $cacheKey = implode('|', [
            'plywood',
            $tipe,
            $tahap,
            strtolower(trim((string) $namaKeterangan)),
            $tebal,
            strtolower(trim($jenisKayu)),
            strtolower(trim($grade)),
        ]);

        if (array_key_exists($cacheKey, $this->idBarangCache)) {
            return $this->idBarangCache[$cacheKey];
        }

        $idBarang = $this->callResolver('resolve-plywood', [
            'tipe' => $tipe,
            'nama' => $namaKeterangan,
            'tebal' => $tebal,
            'jenis_kayu' => $jenisKayu,
            'grade' => $grade,
            'tahap' => $tahap,
        ]);

        // Fallback jika tidak ketemu karena ejaan "local" vs "lokal"
        if (! $idBarang && $namaKeterangan && (str_contains(strtolower($namaKeterangan), 'local') || str_contains(strtolower($namaKeterangan), 'lokal'))) {
            $namaAlt = str_contains(strtolower($namaKeterangan), 'local')
                ? str_ireplace('local', 'lokal', $namaKeterangan)
                : str_ireplace('lokal', 'local', $namaKeterangan);
            $gradeAlt = str_contains(strtolower($grade), 'local')
                ? str_ireplace('local', 'lokal', $grade)
                : str_ireplace('lokal', 'local', $grade);

            $idBarang = $this->callResolver('resolve-plywood', [
                'tipe' => $tipe,
                'nama' => $namaAlt,
                'tebal' => $tebal,
                'jenis_kayu' => $jenisKayu,
                'grade' => $gradeAlt,
                'tahap' => $tahap,
            ]);
        }

        return $this->idBarangCache[$cacheKey] = $idBarang;
    }

    /**
     * Resolve id_barang untuk Bahan Penolong dari /api/barang/resolve-penolong
     */
    private function resolveIdBarangPenolong(string $namaBahan): ?int
    {
        $domainStr = $this->isWhn() ? 'whn' : 'wjy';
        $cacheKey = 'penolong|'.strtolower(trim($namaBahan)).'|'.$domainStr;

        if (array_key_exists($cacheKey, $this->idBarangCache)) {
            return $this->idBarangCache[$cacheKey];
        }

        $idBarang = $this->callResolver('resolve-penolong', [
            'nama' => $namaBahan,
            'domain' => $domainStr,
        ]);

        return $this->idBarangCache[$cacheKey] = $idBarang;
    }

    /**
     * Menentukan nama kategori "jenis_veneer" untuk dikirim ke resolver.
     * (Untuk Repair, nilainya di-override di normalisasiParamVeneerResolver.)
     */
    private function jenisVeneerUntukResolver(bool $isAf, float $tebal): string
    {
        if ($isAf) {
            return 'Veneer Afalan';
        }

        // Bahan masuk hotpress secara umum berstatus "kering".
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
            'bahanHotpress.mutasiKeluarPlatform.mutasiKeluar.jenisBarang',
            'bahanHotpress.mutasiKeluarTriplek.mutasiKeluar',
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
                // 1. HASIL PRODUKSI (DEBIT) — referensi tahap MENTAH
                //    - Triplek: Triplek Mentah (1403.2)
                //    - Platform: Platform MENTAH (1403.1)
                //    ID Barang via resolve-plywood.
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

                    $ref = $this->fetchReferensi($tipe, $idUkuran, $idJenisKayu, $grStr, $idGrade, self::TAHAP_MENTAH);

                    [$akunNama, $akunNo, $hargaHpp] = ($tipe === 'platform')
                        ? $this->coaAlias->extractAkunPlatform($ref, false)  // hasil produksi -> Platform Mentah (1403.1)
                        : $this->coaAlias->extractAkunTriplek($ref);         // hasil produksi -> Triplek Mentah (1403.2)

                    if ($ref) {
                        $keterangan = $ref->nama;
                    } else {
                        $tebalInt = (int) $tebal;
                        $jkSingkat = strtolower(substr($jk, 0, 1));
                        $kwStr = strtolower($grStr);
                        $keterangan = "{$tebalInt}{$jkSingkat} {$kwStr} MTH [UNKNOWN - cek master data]";
                    }

                    $idBarangProduksi = $this->resolveIdBarangPlywood(
                        $tipe,
                        $keterangan,
                        $tebal,
                        $jk,
                        $grStr,
                        self::TAHAP_MENTAH
                    );

                    $rows[] = $this->makeRow($akunNama, $akunNo, $tglStr, $namaMesinSingkat, $keterangan, 'd', 'b', $banyak, $m3Round, $hargaHpp, null, $idBarangProduksi);

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

                    // --- BAHAN HOTPRESS ---
                    //   veneer  -> alias COA baru
                    //   platform-> Platform JADI (referensi tahap JADI bila dari mutasi keluar platform)
                    //   triplek -> Triplek JADI  (referensi tahap JADI, dari mutasi keluar triplek)
                    $veneerMap = [];

                    foreach ($prod->bahanHotpress as $bahan) {
                        $mutasiHeader = $bahan->mutasiKeluarPalet?->mutasiKeluar;
                        $mutasiPlatformHeader = $bahan->mutasiKeluarPlatform?->mutasiKeluar;
                        $mutasiTriplekHeader = $bahan->mutasiKeluarTriplek?->mutasiKeluar;
                        $bsj = $bahan->barangSetengahJadi ?? null;
                        $idGradeRef = null;
                        $tahapBahan = self::TAHAP_MENTAH; // legacy (lewat barang setengah jadi) tetap seperti dulu

                        if ($mutasiHeader) {
                            $p = (float) ($mutasiHeader->panjang ?? 0);
                            $l = (float) ($mutasiHeader->lebar ?? 0);
                            $tebal = (float) ($mutasiHeader->tebal ?? 0);
                            $idJenisKayu = $mutasiHeader->id_jenis_kayu ?? null;
                            $jkAsli = $mutasiHeader->jenisKayu?->nama_kayu ?? 'sengon';
                            $grAsli = (string) ($mutasiHeader->kw_grade ?? '');
                            $idUkuran = null;
                        } elseif ($mutasiPlatformHeader) {
                            // Bahan platform dari gudang platform jadi (sumber = platform).
                            $p = (float) ($mutasiPlatformHeader->panjang ?? 0);
                            $l = (float) ($mutasiPlatformHeader->lebar ?? 0);
                            $tebal = (float) ($mutasiPlatformHeader->tebal ?? 0);
                            $jkAsli = $mutasiPlatformHeader->jenisBarang?->nama_jenis_barang ?? 'sengon';
                            $idJenisKayu = $this->resolveIdJenisKayu($jkAsli);
                            $grAsli = (string) ($mutasiPlatformHeader->kw_grade ?? '');
                            $idUkuran = $this->resolveIdUkuranByDimensi($p, $l, $tebal);
                            $tahapBahan = self::TAHAP_JADI;
                            $idGradeRef = $this->getIdGradeTahap($grAsli, 'Platform', $tahapBahan);
                        } elseif ($mutasiTriplekHeader) {
                            // Bahan triplek dari gudang triplek jadi (sumber = triplek).
                            $p = (float) ($mutasiTriplekHeader->panjang ?? 0);
                            $l = (float) ($mutasiTriplekHeader->lebar ?? 0);
                            $tebal = (float) ($mutasiTriplekHeader->tebal ?? 0);
                            $jkAsli = $this->namaJenisDariHeader($mutasiTriplekHeader);
                            $idJenisKayu = $this->resolveIdJenisKayu($jkAsli);
                            $grAsli = (string) ($mutasiTriplekHeader->kw_grade ?? '');
                            $idUkuran = $this->resolveIdUkuranByDimensi($p, $l, $tebal);
                            $tahapBahan = self::TAHAP_JADI;
                            $idGradeRef = $this->getIdGradeTahap($grAsli, 'Plywood', $tahapBahan);
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

                        // Triplek dicek PERTAMA: grade "better" milik triplek tidak boleh
                        // dianggap platform oleh isPlatformGrade().
                        $isTriplek = $mutasiTriplekHeader !== null || $bahan->sumber === 'triplek';
                        $isPlatform = ! $isTriplek
                            && (($bahan->sumber === 'platform') || (! $isAf && $this->isPlatformGrade($grAsli)));

                        $fallbackMentah = false;

                        if ($isTriplek) {
                            [$ref, $fallbackMentah] = $this->fetchReferensiTahap('triplek', $tahapBahan, $idUkuran, $idJenisKayu, $grAsli, $idGradeRef);
                        } elseif ($isPlatform) {
                            [$ref, $fallbackMentah] = $this->fetchReferensiTahap('platform', $tahapBahan, $idUkuran, $idJenisKayu, $grAsli, $idGradeRef);
                        } else {
                            $ref = $this->resolveRefBahan($idUkuran, $idJenisKayu, $grAsli);
                        }

                        $isJadi = $tahapBahan === self::TAHAP_JADI;

                        // Bahan: Triplek/Platform -> akun JADI (sumber mutasi keluar);
                        // Veneer -> alias COA baru.
                        if ($isTriplek) {
                            [$akunNama, $akunNo, $hargaBahan] = $this->extractAkunTriplekBahan($ref, $isJadi);
                        } elseif ($isPlatform) {
                            [$akunNama, $akunNo, $hargaBahan] = $this->coaAlias->extractAkunPlatform($ref, true);
                        } else {
                            [$akunNama, $akunNo, $hargaBahan] = $this->coaAlias->extractAkunVeneer($ref);
                        }

                        $labelFallback = $fallbackMentah ? ' [ref JADI belum ada, pakai MENTAH]' : '';

                        if ($isTriplek) {
                            $tipeVeneer = 'Triplek';
                            $ketDasar = ($ref?->nama ?? "Triplek {$jkAsli} uk {$tebal} [UNKNOWN - cek master data]").$labelFallback;
                        } elseif ($isPlatform) {
                            $tipeVeneer = 'Platform';
                            $ketDasar = ($ref?->nama ?? "Platform {$jkAsli} uk {$tebal} [UNKNOWN - cek master data]").$labelFallback;
                        } else {
                            $tipeVeneer = $isAf ? 'AF' : (($tebal < 1) ? '260 F/B' : '130 Core');
                            $ketDasar = "{$tipeVeneer} {$jkAsli} uk {$tebal}".($ref ? '' : ' [UNKNOWN - cek master data]');
                        }

                        $katGrade = $this->kategoriGrade($grAsli);

                        // Resolve id_barang untuk bahan VENEER / PLATFORM / TRIPLEK
                        $idBarangBahan = null;
                        if ($isTriplek) {
                            $idBarangBahan = $this->resolveIdBarangPlywood(
                                'triplek',
                                $ref?->nama ?? $ketDasar,
                                $tebal,
                                $jkAsli,
                                $grAsli,
                                $tahapBahan
                            );
                        } elseif ($isPlatform) {
                            $idBarangBahan = $this->resolveIdBarangPlywood(
                                'platform',
                                $ref?->nama ?? $ketDasar,
                                $tebal,
                                $jkAsli,
                                $grAsli,
                                $tahapBahan
                            );
                        } else {
                            $bagianResolver = $isAf ? 'PPC' : (($tebal < 1) ? 'Face Back' : 'Core');
                            $jenisVeneerResolver = $this->jenisVeneerUntukResolver($isAf, $tebal);
                            // Normalisasi Repair (jenis kayu "Repair" -> kayu dasar + grade Repair)
                            // terjadi di dalam resolveIdBarang().
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
                                    // Triplek & platform dihitung per lembar (banyak x harga)
                                    'per_lembar' => $isPlatform || $isTriplek,
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
                        $hitKbk = $v['per_lembar'] ? 'b' : 'm';
                        $m3Round = round($v['m3'], 4);

                        $nilai = $v['per_lembar']
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

                    // --- BAHAN PENOLONG ---
                    // Akun & nama akun dari CoaAliasService; HARGA dari model
                    // BahanPenolongProduksi (kategori hot_press).
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
                        [$akunNama, $akunNo, $hargaAlias] = $this->coaAlias->extractAkunPenolong($ref, $penolong->nama_bahan);

                        // Harga dari model BahanPenolongProduksi; fallback ke harga
                        // alias/referensi hanya jika harga di model belum diisi.
                        $hargaModel = $this->getHargaBahanPenolong($penolong->nama_bahan);
                        $hargaPenolong = $hargaModel ?? (float) $hargaAlias;

                        $ketPenolong = $ref?->nama ?? "{$penolong->nama_bahan} [UNKNOWN - cek master data]";
                        if ($hargaModel === null) {
                            $ketPenolong .= ' [harga bahan penolong belum diisi]';
                        }

                        $idBarangPenolong = $this->resolveIdBarangPenolong($penolong->nama_bahan);

                        $totalHargaBahanGlobal += round($banyak * $hargaPenolong, 0);
                        $rows[] = $this->makeRow($akunNama, $akunNo, $tglStr, $namaMesinSingkat, $ketPenolong, 'k', 'b', $banyak, '', $hargaPenolong, null, $idBarangPenolong);
                    }

                    // --- HUTANG GAJI — 2231.00 -> 2195.1 (ID Barang null, memang tidak ada) ---
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
