<?php

namespace App\Exports\Sheets;

use App\Models\ProduksiHp;
use App\Models\ReferensiHargaProduksi;
use App\Models\JenisKayu;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithMapping;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use Carbon\Carbon;

class LaporanProduksiHotPressJurnalSheet implements FromArray, WithTitle, WithColumnWidths, WithStyles, WithMapping
{
    protected string $tanggal;
    protected string $domain;
    protected int $rowIndex = 0;

    protected ?int $idKayuSengon  = null;
    protected ?int $idKayuMeranti = null;

    // Cache agar tidak query DB berulang
    private array $kayuCache     = [];
    private array $kategoriCache = [];
    private array $gradeCache    = [];

    public function __construct(string $tanggal, string $domain)
    {
        $this->tanggal = $tanggal;
        $this->domain  = $domain;
    }

    public function title(): string
    {
        return 'jurnal produksi';
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
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $lastRow = $sheet->getHighestRow();
        $sheet->getStyle("A1:N{$lastRow}")->applyFromArray([
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']]],
        ]);
        $sheet->getStyle('A1:N1')->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1F4E79']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getStyle("D2:D{$lastRow}")->getNumberFormat()->setFormatCode('@');
        $sheet->getStyle("L2:L{$lastRow}")->getNumberFormat()->setFormatCode('0.0000');
        $sheet->getStyle("M2:N{$lastRow}")->getNumberFormat()->setFormatCode('#,##0');
        $sheet->getRowDimension(1)->setRowHeight(20);

        return [];
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
        $shiftStr  = strtolower(trim($shift));
        if (str_contains($namaLower, '1')) return "hp 1 {$shiftStr}";
        if (str_contains($namaLower, '2')) return "hp 2 {$shiftStr}";
        if (str_contains($namaLower, '3')) return "hp 3 {$shiftStr}";
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
        if (in_array($g, ['1', '2'])) return 'face';
        if (in_array($g, ['3', '4'])) return 'back';
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
                    if (is_string($val) && str_contains(strtolower($val), $key)) return true;
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
        if (!$namaJenisBarang) return null;
        return $this->getIdKayuByNama(strtolower(trim($namaJenisBarang)));
    }

    /**
     * Ambil id kategori barang berdasarkan nama (dengan cache).
     * PENTING: kolom di tabel kategori_barang adalah `nama_kategori`, bukan `nama`.
     */
    private function getIdKategoriBarang(string $namaKategori): ?int
    {
        $key = strtolower(trim($namaKategori));
        if (array_key_exists($key, $this->kategoriCache)) {
            return $this->kategoriCache[$key];
        }
        try {
            $kategori = \App\Models\KategoriBarang::whereRaw("LOWER(nama_kategori) LIKE ?", ["%{$key}%"])->first();
            return $this->kategoriCache[$key] = $kategori?->id;
        } catch (\Throwable $e) {
            return $this->kategoriCache[$key] = null;
        }
    }

    /**
     * Ambil id grade berdasarkan nama (dengan cache).
     * Dipakai untuk filter plywood & platform yang pencocokannya via id_grade.
     *
     * Grade dari form produksi bisa berupa string seperti 'better', 'better local',
     * 'uty', 'af', dll. Kolom di tabel grade perlu dicek nama kolomnya — asumsi `nama_grade`.
     * Pencocokan pakai LIKE agar variasi 'lokal'/'local' tetap ketemu.
     */
    private function getIdGrade(string $namaGrade): ?int
    {
        $key = strtolower(trim($namaGrade));
        // Normalisasi lokal <-> local agar keduanya ketemu
        $keyLocal = str_replace('lokal', 'local', $key);
        $keyLokal = str_replace('local', 'lokal', $key);

        $cacheKey = $key;
        if (array_key_exists($cacheKey, $this->gradeCache)) {
            return $this->gradeCache[$cacheKey];
        }

        try {
            $grade = \App\Models\Grade::where(function ($q) use ($key, $keyLocal, $keyLokal) {
                $q->whereRaw("LOWER(nama_grade) = ?", [$key])
                  ->orWhereRaw("LOWER(nama_grade) = ?", [$keyLocal])
                  ->orWhereRaw("LOWER(nama_grade) = ?", [$keyLokal])
                  ->orWhereRaw("LOWER(nama_grade) LIKE ?", ["%{$key}%"]);
            })->first();

            return $this->gradeCache[$cacheKey] = $grade?->id;
        } catch (\Throwable $e) {
            return $this->gradeCache[$cacheKey] = null;
        }
    }

    // =========================================================================
    // FILTER HELPERS — STRICT (tidak ketemu → UNKNOWN)
    // =========================================================================

    /**
     * Filter domain: cocok suffix → pakai.
     * Hanya dipakai untuk plywood & platform.
     */
    private function filterByDomain(\Illuminate\Support\Collection $c): \Illuminate\Support\Collection
    {
        if ($c->isEmpty()) return $c;
        $suffix   = $this->domainSuffix();
        $filtered = $c->filter(function ($item) use ($suffix) {
            $namaRef  = strtolower($item->nama ?? '');
            $namaAkun = strtolower($item->subAnakAkun->kode_sub_anak_akun ?? '');
            foreach ($suffix as $s) {
                if (str_contains($namaRef, $s) || str_contains($namaAkun, $s)) return true;
            }
            return false;
        });
        return $filtered->isNotEmpty() ? $filtered : $c;
    }

    /**
     * Filter bahan penolong: preferensi nama GENERIC (tanpa suffix wjy/whn/mth).
     */
    private function filterGenericFirst(\Illuminate\Support\Collection $c): \Illuminate\Support\Collection
    {
        if ($c->isEmpty()) return $c;
        $allSuffix = ['wjy', 'whn', 'mth'];
        $generic   = $c->filter(function ($item) use ($allSuffix) {
            $namaRef = strtolower($item->nama ?? '');
            foreach ($allSuffix as $s) {
                if (str_contains($namaRef, $s)) return false;
            }
            return true;
        });
        return $generic->isNotEmpty() ? $generic : $c;
    }

    /**
     * Filter ukuran STRICT:
     * - Cocok id_ukuran spesifik → pakai
     * - Cari mirror ukuran (p x l dibalik) → pakai
     * - Fallback ke id_ukuran NULL (referensi generic)
     * - Tidak ada keduanya → return kosong → UNKNOWN
     */
    private function filterByUkuran(\Illuminate\Support\Collection $c, ?int $idUkuran): \Illuminate\Support\Collection
    {
        if ($c->isEmpty() || !$idUkuran) return $c;

        $exact = $c->filter(fn($i) => $i->id_ukuran == $idUkuran);
        if ($exact->isNotEmpty()) return $exact;

        try {
            $ukuran = \App\Models\Ukuran::find($idUkuran);
            if ($ukuran) {
                $idMirror = \App\Models\Ukuran::where('tebal', $ukuran->tebal)
                    ->where(
                        fn($q) => $q
                            ->where(fn($q2) => $q2->where('panjang', $ukuran->panjang)->where('lebar', $ukuran->lebar))
                            ->orWhere(fn($q2) => $q2->where('panjang', $ukuran->lebar)->where('lebar', $ukuran->panjang))
                    )->pluck('id');

                $mirror = $c->filter(fn($i) => $idMirror->contains($i->id_ukuran));
                if ($mirror->isNotEmpty()) return $mirror;
            }
        } catch (\Throwable $e) {
            // Lanjut ke fallback
        }

        return $c->filter(fn($i) => is_null($i->id_ukuran));
    }

    /**
     * Filter kayu STRICT:
     * - Cocok id_jenis_kayu → pakai
     * - Fallback ke id_jenis_kayu NULL (referensi generic)
     * - Tidak ada keduanya → return kosong → UNKNOWN
     */
    private function filterByKayu(\Illuminate\Support\Collection $c, ?int $idJenisKayu): \Illuminate\Support\Collection
    {
        if ($c->isEmpty()) return $c;

        // Jika tidak ada id kayu yang diberikan, kembalikan semua (tidak difilter)
        if (!$idJenisKayu) return $c;

        // Prioritas 1: cocok exact
        $filtered = $c->filter(fn($i) => $i->id_jenis_kayu == $idJenisKayu);
        if ($filtered->isNotEmpty()) return $filtered;

        // Prioritas 2: fallback ke referensi generic (id_jenis_kayu NULL)
        $generic = $c->filter(fn($i) => is_null($i->id_jenis_kayu));
        if ($generic->isNotEmpty()) return $generic;

        // Tidak ada yang cocok sama sekali → return kosong → UNKNOWN
        // JANGAN return semua ($c) karena bisa salah ambil referensi dari kayu lain
        return collect();
    }

    /**
     * Filter grade via id_grade (menggantikan filter string kw lama).
     *
     * Alur:
     * 1. Lookup id_grade dari nama grade yang diberikan
     * 2. Cocokkan ke kolom id_grade di tabel referensi
     * 3. Fallback ke id_grade NULL (referensi generic tanpa grade spesifik)
     * 4. Tidak ada keduanya → return kosong → UNKNOWN
     *
     * Kenapa id_grade bukan kw_min/kw_max:
     * Grade plywood/platform berupa string ('better', 'uty', 'af') yang tidak
     * bisa direpresentasikan sebagai integer range — kolom id_grade dipakai
     * sebagai foreign key ke tabel grade, berbeda dengan veneer yang pakai
     * kw_min/kw_max integer untuk kw 1-4.
     */
    private function filterByGrade(\Illuminate\Support\Collection $c, ?string $grade, ?int $idGrade = null): \Illuminate\Support\Collection
    {
        if ($c->isEmpty()) return $c;

        // Prioritas 1: id grade langsung dari barangSetengahJadi (paling akurat).
        // Penting karena tabel grade punya nama duplikat (mis. 'BETTER' id 44
        // untuk plywood dan id 51 untuk platform) — lookup by nama bisa salah ambil.
        if ($idGrade) {
            $filtered = $c->filter(fn($i) => $i->id_grade == $idGrade);
            if ($filtered->isNotEmpty()) return $filtered;
        }

        // Prioritas 2: lookup by nama (untuk pemanggil yang hanya punya string,
        // mis. bahan hotpress dari mutasi keluar yang cuma punya kw_grade string)
        if ($grade) {
            $idByNama = $this->getIdGrade($grade);
            if ($idByNama) {
                $filtered = $c->filter(fn($i) => $i->id_grade == $idByNama);
                if ($filtered->isNotEmpty()) return $filtered;
            }
        }

        // Tidak ada info grade sama sekali → jangan difilter
        if (!$idGrade && !$grade) return $c;

        // Fallback ke referensi generic (id_grade NULL)
        return $c->filter(fn($i) => is_null($i->id_grade));
    }

    // =========================================================================
    // BASE QUERY per tipe barang — sekarang pakai id_kategori_barang
    // =========================================================================

    /**
     * Query base referensi berdasarkan id_kategori_barang.
     * Menggantikan filter string jenis_barang yang lama.
     *
     * Mapping nama kategori ke id (dari tabel kategori_barang):
     *   'plywood'  → id kategori 'Plywood Mentah' atau 'Plywood'
     *   'platform' → id kategori 'Platform'
     *   'barang'   → id kategori 'Barang' (bahan penolong & veneer)
     *   'afalan'   → id kategori 'Veneer Afalan'
     */
    private function baseQuery(string $tipe): \Illuminate\Database\Eloquent\Builder
    {
        $q         = ReferensiHargaProduksi::with('subAnakAkun');
        $tipeLower = strtolower(trim($tipe));

        if (in_array($tipeLower, ['triplek', 'plywood'])) {
            // Plywood mentah: id_kategori_barang untuk 'Plywood Mentah'
            $idKategori = $this->getIdKategoriBarang('Plywood Mentah');
            if ($idKategori) {
                $q->where('id_kategori_barang', $idKategori);
            } else {
                // Fallback: cari 'Plywood' biasa
                $idKategori = $this->getIdKategoriBarang('Plywood');
                if ($idKategori) $q->where('id_kategori_barang', $idKategori);
            }
        } elseif ($tipeLower === 'platform') {
            $idKategori = $this->getIdKategoriBarang('Platform');
            if ($idKategori) $q->where('id_kategori_barang', $idKategori);
        } elseif ($tipeLower === 'bahan') {
            // Bahan hotpress: HANYA veneer basah/kering/jadi
            // JANGAN include kategori 'Bahan' (bahan penolong) di sini,
            // karena nanti veneer jenis kayu non-sengon/meranti (jabon, pinus, dll)
            // bisa nyasar ke bahan penolong yang id_jenis_kayu-nya null.
            // Bahan penolong dicari terpisah via fetchReferensiPenolong().
            $idVeneerBasah  = $this->getIdKategoriBarang('Veneer Basah');
            $idVeneerKering = $this->getIdKategoriBarang('Veneer Kering');
            $idVeneerJadi   = $this->getIdKategoriBarang('Veneer Jadi');
            $ids = array_filter([$idVeneerBasah, $idVeneerKering, $idVeneerJadi]);
            if (!empty($ids)) $q->whereIn('id_kategori_barang', $ids);
        } elseif ($tipeLower === 'afalan') {
            $idKategori = $this->getIdKategoriBarang('Veneer Afalan');
            if ($idKategori) $q->where('id_kategori_barang', $idKategori);
        } elseif ($tipeLower === 'barang_penolong') {
            // Nama kategori bahan penolong di DB adalah 'Bahan', bukan 'Barang'
            $idKategori = $this->getIdKategoriBarang('Bahan');
            if ($idKategori) $q->where('id_kategori_barang', $idKategori);
        }

        return $q;
    }

    // =========================================================================
    // PENCARIAN REFERENSI
    // =========================================================================

    /**
     * Fetch referensi umum (triplek, platform, bahan/veneer).
     * Filter chain STRICT — tidak ketemu → return null → UNKNOWN.
     *
     * Untuk plywood & platform: filter via id_grade (bukan kw_min/kw_max).
     * Untuk veneer/bahan: tidak pakai id_grade.
     */
    private function fetchReferensi(string $tipe, ?int $idUkuran, ?int $idJenisKayu, ?string $grade, ?int $idGrade = null): ?ReferensiHargaProduksi
    {
        $all = $this->baseQuery($tipe)->get();
        if ($all->isEmpty()) return null;

        $results = $this->filterByUkuran($all, $idUkuran);
        if ($results->isEmpty()) return null;

        $results = $this->filterByKayu($results, $idJenisKayu);
        if ($results->isEmpty()) return null;

        // Plywood & platform: filter via id_grade
        // Veneer/bahan: tidak perlu filter grade (dibedakan oleh kategori + ukuran + kayu)
        $tipeLower = strtolower(trim($tipe));
        if (in_array($tipeLower, ['triplek', 'plywood', 'platform'])) {
            $results = $this->filterByGrade($results, $grade, $idGrade);
            if ($results->isEmpty()) return null;

            $results = $this->filterByDomain($results);
        }

        return $results->first();
    }

    /**
     * Khusus AF: sengon → cari afalan sengon; selain sengon → cari afalan meranti.
     * Untuk afalan tidak ada filter grade maupun kw — cukup kategori + kayu.
     */
    private function fetchReferensiAfalan(?int $idJenisKayu): ?ReferensiHargaProduksi
    {
        $idSengon  = $this->getIdSengon();
        $idMeranti = $this->getIdMeranti();

        $isSengon     = $idJenisKayu && $idJenisKayu === $idSengon;
        $idKayuLookup = $isSengon ? $idSengon : $idMeranti;

        $all = $this->baseQuery('afalan')->get();
        if ($all->isEmpty()) return null;

        $byKayu = $all->filter(fn($i) => $i->id_jenis_kayu == $idKayuLookup);
        $pool   = $byKayu->isNotEmpty() ? $byKayu : $all;

        return $pool->first();
    }

    private function resolveRefBahan(?int $idUkuran, ?int $idJenisKayu, ?string $grade): ?ReferensiHargaProduksi
    {
        if ($grade && $this->isAf($grade)) {
            return $this->fetchReferensiAfalan($idJenisKayu);
        }

        // Aturan harga veneer: sengon ikut sengon, selain sengon (meranti, jabon,
        // waru, mahoni, dll) ikut referensi meranti. Keterangan di jurnal tetap
        // menampilkan jenis kayu aslinya — hanya lookup harga/akun yang dipetakan.
        $idSengon     = $this->getIdSengon();
        $idMeranti    = $this->getIdMeranti();
        $idKayuLookup = ($idJenisKayu && $idJenisKayu === $idSengon) ? $idSengon : $idMeranti;

        return $this->fetchReferensi('bahan', $idUkuran, $idKayuLookup, $grade);
    }

    private function aliasBahanPenolong(): array
    {
        return [
            'hdr'          => 'hadner',
            'isi_steples'  => 'staples',
            'isi steples'  => 'staples',
            'tepung_bgs'   => 'tepung',
            'tepung bgs'   => 'tepung',
            'solasi_putih' => 'isolasi putih',
            'solasi putih' => 'isolasi putih',
            'solasi'       => 'isolasi',
        ];
    }

    /**
     * Fetch referensi bahan penolong dari kategori 'Bahan'.
     * Pencarian multi-tahap:
     *   Tahap 1: nama lengkap / LIKE
     *   Tahap 2: alias mapping
     *   Tahap 3: per kata (terpanjang dulu, min 3 huruf)
     * Tidak ketemu → return null → UNKNOWN
     */
    private function fetchReferensiPenolong(string $namaBahan): ?ReferensiHargaProduksi
    {
        $namaLower = strtolower(trim($namaBahan));
        $namaClean = str_replace('_', ' ', $namaLower);

        // Nama kategori bahan penolong di DB adalah 'Bahan', bukan 'Barang'
        $idKategori = $this->getIdKategoriBarang('Bahan');
        if (!$idKategori) return null;

        // Tahap 1: full match / LIKE dalam kategori 'Bahan'
        $results = ReferensiHargaProduksi::with('subAnakAkun')
            ->where('id_kategori_barang', $idKategori)
            ->where(
                fn($q) => $q
                    ->whereRaw("LOWER(nama) = ?", [$namaLower])
                    ->orWhereRaw("REPLACE(LOWER(nama), '_', ' ') = ?", [$namaClean])
                    ->orWhereRaw("LOWER(nama) LIKE ?", ["%{$namaClean}%"])
                    ->orWhereRaw("REPLACE(LOWER(nama), '_', ' ') LIKE ?", ["%{$namaClean}%"])
            )->get();

        if ($results->isNotEmpty()) {
            return $this->filterGenericFirst($results)->first();
        }

        // Tahap 2: alias mapping
        $alias      = $this->aliasBahanPenolong();
        $cariDengan = $alias[$namaLower] ?? $alias[$namaClean] ?? null;

        if ($cariDengan) {
            $byAlias = ReferensiHargaProduksi::with('subAnakAkun')
                ->where('id_kategori_barang', $idKategori)
                ->whereRaw("LOWER(nama) LIKE ?", ["%{$cariDengan}%"])
                ->get();
            if ($byAlias->isNotEmpty()) {
                return $this->filterGenericFirst($byAlias)->first();
            }
        }

        // Tahap 3: per kata (terpanjang dulu, min 3 huruf)
        $kata = array_filter(explode(' ', $namaClean), fn($k) => strlen($k) >= 3);
        usort($kata, fn($a, $b) => strlen($b) - strlen($a));

        foreach ($kata as $k) {
            $byKata = ReferensiHargaProduksi::with('subAnakAkun')
                ->where('id_kategori_barang', $idKategori)
                ->whereRaw("LOWER(nama) LIKE ?", ["%{$k}%"])
                ->get();
            if ($byKata->isNotEmpty()) {
                return $this->filterGenericFirst($byKata)->first();
            }
        }

        return null;
    }

    // =========================================================================
    // EXTRACT AKUN
    // =========================================================================

    private function extractAkun(?ReferensiHargaProduksi $ref): array
    {
        if (!$ref) {
            return ['UNKNOWN', 'UNKNOWN', 0.0];
        }

        if (!$ref->relationLoaded('subAnakAkun')) {
            $ref->load('subAnakAkun');
        }

        $sub = $ref->subAnakAkun;

        if (!$sub) {
            \Illuminate\Support\Facades\Log::warning("ReferensiHargaProduksi id={$ref->id} tidak punya subAnakAkun");
            return ['UNKNOWN', 'UNKNOWN', (float) $ref->harga];
        }

        $namaAkun = trim($sub->nama_sub_anak_akun ?? '');
        $noAkun   = trim($sub->kode_sub_anak_akun ?? '');

        if ($namaAkun === '') $namaAkun = 'UNKNOWN';
        if ($noAkun   === '') $noAkun   = 'UNKNOWN';

        return [$namaAkun, $noAkun, (float) $ref->harga];
    }

    // =========================================================================
    // AKUN HPP & GAJI
    // =========================================================================

    private function getAkunHpp(): array
    {
        $ext = $this->isWhn() ? '01' : '00';
        return ['nama' => 'hpp', 'no' => "6111.{$ext}"];
    }

    private function getAkunGaji(): array
    {
        $ext = $this->isWhn() ? '01' : '00';
        return [
            'biaya'  => ['nama' => 'Biaya Gaji Plywood', 'no' => "5114.{$ext}"],
            'hutang' => ['nama' => 'Hutang Gaji',        'no' => '2231.00'],
        ];
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
        $total = null
    ): array {
        return [
            $namaAkun,
            $tgl,
            '',
            "\t" . $noAkun,
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
        ];
    }

    // =========================================================================
    // MAIN: array()
    // =========================================================================
    public function array(): array
    {
        $rows   = [];
        $rows[] = ['Nama Akun', 'tgl', 'jurnal', 'No Akun', 'No', 'mm', 'Nama', 'Keterangan', 'map', 'hit kbk', 'Banyak', 'M3', 'Harga', 'Total'];

        $produksis = ProduksiHp::with([
            'triplekHasilHp.mesin',
            'triplekHasilHp.barangSetengahJadi.jenisBarang',
            'triplekHasilHp.barangSetengahJadi.ukuran',
            'triplekHasilHp.barangSetengahJadi.grade',
            'platformHasilHp.mesin',
            'platformHasilHp.barangSetengahJadi.jenisBarang',
            'platformHasilHp.barangSetengahJadi.ukuran',
            'platformHasilHp.barangSetengahJadi.grade',
            // Bahan hotpress: struktur baru via mutasi keluar palet
            'bahanHotpress.mutasiKeluarPalet.mutasiKeluar.jenisKayu',
            // Fallback untuk data lama yang masih pakai barangSetengahJadi
            'bahanHotpress.barangSetengahJadi.jenisBarang',
            'bahanHotpress.barangSetengahJadi.ukuran',
            'bahanHotpress.barangSetengahJadi.grade',
            'bahanPenolongHp',
            'detailPegawaiHp',
        ])->whereDate('tanggal_produksi', $this->tanggal)->get();

        if ($produksis->isEmpty()) return $rows;

        $tglStr             = Carbon::parse($this->tanggal)->format('d-m-Y');
        $hargaPegawaiMaster = 150000;

        foreach ($produksis as $prod) {
            $shiftStr     = $prod->shift ?? 'pagi';
            $hasilByMesin = [];

            foreach ($prod->triplekHasilHp  as $t) $hasilByMesin[$t->id_mesin][] = ['tipe' => 'triplek',  'data' => $t];
            foreach ($prod->platformHasilHp as $p) $hasilByMesin[$p->id_mesin][] = ['tipe' => 'platform', 'data' => $p];

            uasort($hasilByMesin, fn($a, $b) => strcmp(
                strtolower($a[0]['data']->mesin->nama_mesin ?? ''),
                strtolower($b[0]['data']->mesin->nama_mesin ?? '')
            ));

            // Tentukan HP3
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
                if (!$hp3Id) $hp3Id = array_key_last($hasilByMesin);
            }

            $jumlahPekerja = $prod->detailPegawaiHp->count();

            foreach ($hasilByMesin as $mId => $items) {
                $namaMesinAsli    = $items[0]['data']->mesin->nama_mesin ?? 'HOTPRESS 1';
                $namaMesinSingkat = $this->getNamaMesinSingkat($namaMesinAsli, $shiftStr);

                $totalHargaProdukHp3   = 0;
                $totalHargaBahanGlobal = 0;
                $totalProdHp1Hp2       = 0;

                // =======================================================
                // 1. HASIL PRODUKSI (DEBIT)
                // =======================================================
                foreach ($items as $item) {
                    $tipe        = $item['tipe'];
                    $data        = $item['data'];
                    $u           = $data->barangSetengahJadi->ukuran ?? null;
                    $idUkuran    = $u?->id;
                    $jk          = $data->barangSetengahJadi->jenisBarang->nama_jenis_barang ?? 'sengon';
                    $idJenisKayu = $this->resolveIdJenisKayu($jk);
                    $grStr       = $data->barangSetengahJadi->grade->nama_grade ?? '';
                    $idGrade     = $data->barangSetengahJadi->grade->id ?? null;
                    $tebal       = $u?->tebal ?? 0;
                    $banyak      = $data->isi ?? 0;
                    $m3Round     = $u ? round(($u->panjang * $u->lebar * $tebal * $banyak) / 10_000_000, 4) : 0;

                    $ref = $this->fetchReferensi($tipe, $idUkuran, $idJenisKayu, $grStr, $idGrade);
                    [$akunNama, $akunNo, $hargaHpp] = $this->extractAkun($ref);

                    // Keterangan: nama dari referensi, fallback ke nama manual jika UNKNOWN
                    if ($ref) {
                        $keterangan = $ref->nama;
                    } else {
                        $tebalInt   = (int) $tebal;
                        $jkSingkat  = strtolower(substr($jk, 0, 1));
                        $kwStr      = strtolower($grStr);
                        $keterangan = "{$tebalInt}{$jkSingkat} {$kwStr} MTH [UNKNOWN - cek master data]";
                    }

                    $rows[] = $this->makeRow($akunNama, $akunNo, $tglStr, $namaMesinSingkat, $keterangan, 'd', 'b', $banyak, $m3Round, $hargaHpp);

                    $totalProdValue = round($banyak * $hargaHpp, 0);
                    if ($mId != $hp3Id) {
                        $totalProdHp1Hp2 += $totalProdValue;
                    } else {
                        $totalHargaProdukHp3 += $totalProdValue;
                    }
                }

                // KREDIT HPP untuk HP1 & HP2 (taruh langsung setelah debit masing-masing mesin)
                if ($mId != $hp3Id && $totalProdHp1Hp2 > 0) {
                    $hpp    = $this->getAkunHpp();
                    $rows[] = $this->makeRow($hpp['nama'], $hpp['no'], $tglStr, $namaMesinSingkat, '', 'k', '', '', '', round($totalProdHp1Hp2, 0));
                }

                // =======================================================
                // 2. BIAYA HP3 (KREDIT) — taruh setelah semua debit HP3
                // =======================================================
                if ($mId == $hp3Id) {

                    // --- BAHAN HOTPRESS (veneer + platform sebagai bahan) ---
                    $veneerMap = [];

                    foreach ($prod->bahanHotpress as $bahan) {
                        // Relasi mutasiKeluarPalet.mutasiKeluar.jenisKayu sudah di-eager load
                        // di query utama — tidak perlu query manual per baris (hindari N+1)
                        $mutasiHeader = $bahan->mutasiKeluarPalet?->mutasiKeluar;
                        $bsj          = $bahan->barangSetengahJadi ?? null;

                        if ($mutasiHeader) {
                            // Struktur baru: data dari VeneerJadiMutasiKeluar
                            $p           = (float) ($mutasiHeader->panjang ?? 0);
                            $l           = (float) ($mutasiHeader->lebar   ?? 0);
                            $tebal       = (float) ($mutasiHeader->tebal   ?? 0);
                            $idJenisKayu = $mutasiHeader->id_jenis_kayu ?? null;
                            $jkAsli      = $mutasiHeader->jenisKayu?->nama_kayu ?? 'sengon';
                            $grAsli      = (string) ($mutasiHeader->kw_grade ?? '');
                            $idUkuran    = null;
                        } elseif ($bsj) {
                            // Fallback: data dari barangSetengahJadi (struktur lama)
                            $u           = $bsj->ukuran ?? null;
                            $idUkuran    = $u?->id;
                            $p           = (float) ($u?->panjang ?? 0);
                            $l           = (float) ($u?->lebar   ?? 0);
                            $tebal       = (float) ($u?->tebal   ?? 0);
                            $jkAsli      = $bsj->jenisBarang->nama_jenis_barang ?? 'sengon';
                            $idJenisKayu = $this->resolveIdJenisKayu($jkAsli);
                            $grAsli      = $bsj->grade->nama_grade ?? '';
                        } else {
                            continue; // Tidak ada data → skip
                        }

                        $banyak = $bahan->isi ?? 0;

                        // Parsing keterangan kelebihan/kehilangan.
                        // Format baru: "+ 5 kelebihan" / "- 3 kehilangan" → angka pada
                        // keterangan adalah jumlah penyesuaian TERPISAH, sedangkan `isi`
                        // tetap pemakaian normal. Jadi satu baris bahan bisa menghasilkan
                        // dua entri jurnal: normal (k) + penyesuaian (d untuk kelebihan).
                        // Format lama: "kelebihan" tanpa angka → seluruh isi dianggap
                        // kelebihan (perilaku lama dipertahankan).
                        $ketBahan = strtolower(trim($bahan->ket ?? ''));
                        $entries  = [];

                        // Terima dua urutan penulisan:
                        //   "+5 kelebihan" / "5 lembar kelebihan"  (angka dulu)
                        //   "kelebihan +5" / "kelebihan 5 lembar"  (kata dulu)
                        $polaAngkaDulu = '/([+-]?\s*\d+)\s*(?:lembar|lbr|pcs|ply)?\s*(kelebihan|kehilangan)/';
                        $polaKataDulu  = '/(kelebihan|kehilangan)\s*([+-]?\s*\d+)/';

                        $jumlahAdj = null;
                        $kataAdj   = null;

                        if (preg_match($polaAngkaDulu, $ketBahan, $m)) {
                            $jumlahAdj = (int) preg_replace('/\s+/', '', $m[1]);
                            $kataAdj   = $m[2];
                        } elseif (preg_match($polaKataDulu, $ketBahan, $m)) {
                            $kataAdj   = $m[1];
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

                        if (empty($entries)) continue;

                        // m3 per lembar — dipakai untuk menghitung m3 tiap entri
                        $m3PerLembar = ($p * $l * $tebal) / 10_000_000;

                        $isAf       = $this->isAf($grAsli);
                        $isPlatform = !$isAf && $this->isPlatformGrade($grAsli);

                        $ref = $isPlatform
                            ? $this->fetchReferensi('platform', $idUkuran, $idJenisKayu, $grAsli)
                            : $this->resolveRefBahan($idUkuran, $idJenisKayu, $grAsli);

                        [$akunNama, $akunNo, $hargaBahan] = $this->extractAkun($ref);

                        if ($isPlatform) {
                            $tipeVeneer = 'Platform';
                            $ketDasar   = $ref?->nama ?? "Platform {$jkAsli} uk {$tebal} [UNKNOWN - cek master data]";
                        } else {
                            $tipeVeneer = $isAf ? 'AF' : (($tebal < 1) ? '260 F/B' : '130 Core');
                            $ketDasar   = "{$tipeVeneer} {$jkAsli} uk {$tebal}" . ($ref ? '' : ' [UNKNOWN - cek master data]');
                        }

                        $katGrade = $this->kategoriGrade($grAsli);

                        foreach ($entries as $entry) {
                            $statusKey  = $entry['status'];
                            $banyakItem = $entry['banyak'];
                            $m3Item     = $m3PerLembar * $banyakItem;

                            $ketVeneer = $ketDasar;
                            if ($statusKey === 'lebih')  $ketVeneer .= ' // kelebihan';
                            if ($statusKey === 'hilang') $ketVeneer .= ' // kehilangan';

                            $groupKey = "{$akunNo}_{$tipeVeneer}_{$tebal}_{$p}x{$l}_{$katGrade}_{$statusKey}";

                            if (!isset($veneerMap[$groupKey])) {
                                $veneerMap[$groupKey] = [
                                    'akun_nama'   => $akunNama,
                                    'akun_no'     => $akunNo,
                                    'ket'         => $ketVeneer,
                                    'banyak'      => 0,
                                    'm3'          => 0.0,
                                    'harga'       => $hargaBahan,
                                    'map'         => $statusKey === 'lebih' ? 'd' : 'k',
                                    'is_platform' => $isPlatform,
                                    'status'      => $statusKey,
                                ];
                            }
                            $veneerMap[$groupKey]['banyak'] += $banyakItem;
                            $veneerMap[$groupKey]['m3']     += $m3Item;
                        }
                    }

                    // Urutkan: normal → kelebihan → kehilangan
                    $veneerNormal = array_filter($veneerMap, fn($v) => $v['status'] === 'normal');
                    $veneerLebih  = array_filter($veneerMap, fn($v) => $v['status'] === 'lebih');
                    $veneerHilang = array_filter($veneerMap, fn($v) => $v['status'] === 'hilang');
                    $veneerUrut   = array_merge($veneerNormal, $veneerLebih, $veneerHilang);

                    foreach ($veneerUrut as $v) {
                        $hitKbk  = $v['is_platform'] ? 'b' : 'm';
                        $m3Round = round($v['m3'], 4);

                        $nilai = $v['is_platform']
                            ? round($v['banyak'] * $v['harga'], 0)
                            : round($m3Round * $v['harga'], 0);

                        // Baris kelebihan bermap 'd' (debit) — nilainya MENGURANGI
                        // total biaya bahan, bukan menambah. Kalau ikut ditambah,
                        // jurnal tidak balance sebesar 2x nilai baris tersebut.
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
                            $v['harga']
                        );
                    }

                    // --- BAHAN PENOLONG ---
                    foreach ($prod->bahanPenolongHp as $penolong) {
                        $namaBahanLower = strtolower(trim($penolong->nama_bahan));

                        if (
                            str_contains($namaBahanLower, 'kalsium') ||
                            str_contains($namaBahanLower, 'semen')   ||
                            str_contains($namaBahanLower, 'pvac')
                        ) continue;

                        $banyak = $penolong->jumlah;
                        $ref    = $this->fetchReferensiPenolong($penolong->nama_bahan);
                        [$akunNama, $akunNo, $hargaPenolong] = $this->extractAkun($ref);

                        $ketPenolong = $ref?->nama ?? "{$penolong->nama_bahan} [UNKNOWN - cek master data]";

                        $totalHargaBahanGlobal += round($banyak * $hargaPenolong, 0);
                        $rows[] = $this->makeRow($akunNama, $akunNo, $tglStr, $namaMesinSingkat, $ketPenolong, 'k', 'b', $banyak, '', $hargaPenolong);
                    }

                    // --- HUTANG GAJI ---
                    if ($jumlahPekerja > 0) {
                        $akunGaji  = $this->getAkunGaji();
                        $totalGaji = round($jumlahPekerja * $hargaPegawaiMaster, 0);
                        $totalHargaBahanGlobal += $totalGaji;
                        $rows[] = $this->makeRow($akunGaji['hutang']['nama'], $akunGaji['hutang']['no'], $tglStr, $namaMesinSingkat, '', 'k', 'b', $jumlahPekerja, '', $hargaPegawaiMaster);
                    }

                    // --- HPP PENYEIMBANG HP3 — selalu paling bawah sendiri ---
                    $nilaiHppHp3 = $totalHargaBahanGlobal - $totalHargaProdukHp3;
                    if (round(abs($nilaiHppHp3), 0) != 0) {
                        $hpp    = $this->getAkunHpp();
                        $mapHpp = $nilaiHppHp3 > 0 ? 'd' : 'k';
                        $rows[] = $this->makeRow($hpp['nama'], $hpp['no'], $tglStr, $namaMesinSingkat, '', $mapHpp, '', '', '', round(abs($nilaiHppHp3), 0));
                    }
                }
            }

            $rows[] = array_fill(0, 14, '');
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

        $r       = $this->rowIndex;
        $row[13] = "=ROUND(IF(J{$r}=\"m\", M{$r}*L{$r}, IF(J{$r}=\"b\", M{$r}*K{$r}, M{$r})), 0)";

        return $row;
    }
}