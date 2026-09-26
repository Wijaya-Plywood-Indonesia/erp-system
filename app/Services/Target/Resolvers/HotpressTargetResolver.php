<?php

namespace App\Services\Target\Resolvers;

use App\Models\KategoriBarang;
use App\Models\Mesin;
use App\Models\Target;

/**
 * Resolver target Hotpress. Mendukung DUA pola sekaligus, tanpa mengubah
 * perilaku satu sama lain:
 *
 *  - Pola WIJAYA: target per ukuran PERSIS (panjang x lebar x tebal),
 *    dibedakan jenis kayu, tanpa kategori/grade. Fallback ke ukuran
 *    wildcard 0x0x0 kalau ukuran spesifik tidak ada.
 *
 *  - Pola WAHANA: target per TEBAL (ukuran 0x0xtebal), dibedakan
 *    kategori barang (Platform vs Plywood/lainnya), jenis kayu, dan
 *    grade (UTY, AJ, Nebali, dst). Barang Platform HANYA boleh
 *    memakai target berkategori Platform; barang Triplek/Plywood
 *    TIDAK BOLEH memakai target berkategori Platform.
 *
 * Urutan pencarian: ukuran persis dulu (Wijaya), lalu tebal+kategori+
 * kayu+grade (Wahana), lalu wildcard 0x0x0 sesuai kategori, lalu
 * wildcard 0x0x0 apa saja (fallback terakhir, sama seperti kode lama).
 */
class HotpressTargetResolver
{
    /**
     * @param  string  $tipeBarang  'platform' atau 'triplek'
     */
    public function resolve(
        ?int $idUkuranExact,
        float|string|null $tebal,
        ?int $idJenisKayu,
        ?string $grade,
        string $tipeBarang
    ): ?Target {
        $mesinIds = $this->idMesinHotpress();
        if (empty($mesinIds)) {
            return null;
        }

        $gradeLower = ($grade !== null && trim($grade) !== '') ? strtolower(trim($grade)) : null;
        $idKategoriPlatform = $this->idKategoriPlatform();

        $baseQuery = fn () => Target::query()->whereIn('id_mesin', $mesinIds);

        // Filter kategori: Platform hanya boleh kategori Platform;
        // Triplek/Plywood TIDAK BOLEH kategori Platform.
        $withKategoriFilter = function ($q) use ($tipeBarang, $idKategoriPlatform) {
            if (! $idKategoriPlatform) {
                return $q;
            }

            if ($tipeBarang === 'platform') {
                return $q->where('id_kategori_barang', $idKategoriPlatform);
            }

            return $q->where(function ($qq) use ($idKategoriPlatform) {
                $qq->whereNull('id_kategori_barang')
                    ->orWhere('id_kategori_barang', '!=', $idKategoriPlatform);
            });
        };

        // 1. Pola WIJAYA: ukuran persis + jenis kayu
        if ($idUkuranExact) {
            $q = $baseQuery()->where('id_ukuran', $idUkuranExact);
            if ($idJenisKayu) {
                $q->where('id_jenis_kayu', $idJenisKayu);
            }
            $target = $q->orderByDesc('id')->first();
            if ($target) {
                return $target;
            }
        }

        // 2. Pola WAHANA: tebal (ukuran 0x0xtebal) + kategori + kayu + grade
        if ($tebal !== null && $tebal !== '') {
            $tebalStr = number_format((float) $tebal, 2, '.', '');
            $byTebal = fn () => $withKategoriFilter(
                $baseQuery()->whereHas('ukuranModel', fn ($q) => $q
                    ->where('panjang', 0)->where('lebar', 0)
                    ->whereRaw('ROUND(tebal, 2) = ?', [$tebalStr]))
            );

            // 2a. kayu + grade
            if ($gradeLower && $idJenisKayu) {
                $target = $byTebal()
                    ->where('id_jenis_kayu', $idJenisKayu)
                    ->whereRaw('LOWER(grade) = ?', [$gradeLower])
                    ->orderByDesc('id')->first();
                if ($target) {
                    return $target;
                }
            }

            // 2b. grade saja (kayu apa saja)
            if ($gradeLower) {
                $target = $byTebal()
                    ->whereRaw('LOWER(grade) = ?', [$gradeLower])
                    ->orderByDesc('id')->first();
                if ($target) {
                    return $target;
                }
            }

            // 2c. kayu tanpa grade
            if ($idJenisKayu) {
                $target = $byTebal()
                    ->where('id_jenis_kayu', $idJenisKayu)
                    ->where(fn ($q) => $q->whereNull('grade')->orWhere('grade', ''))
                    ->orderByDesc('id')->first();
                if ($target) {
                    return $target;
                }
            }

            // 2d. tanpa kayu, tanpa grade (generik per tebal+kategori)
            $target = $byTebal()
                ->where(fn ($q) => $q->whereNull('grade')->orWhere('grade', ''))
                ->orderByDesc('id')->first();
            if ($target) {
                return $target;
            }
        }

        // 3. Wildcard 0x0x0 sesuai kategori
        $wildcard = $withKategoriFilter(
            $baseQuery()->whereHas('ukuranModel', fn ($q) => $q
                ->where('panjang', 0)->where('lebar', 0)->where('tebal', 0))
        )->orderByDesc('id')->first();
        if ($wildcard) {
            return $wildcard;
        }

        // 4. Wildcard 0x0x0 apa saja (fallback terakhir, sama seperti kode lama)
        return $baseQuery()
            ->whereHas('ukuranModel', fn ($q) => $q
                ->where('panjang', 0)->where('lebar', 0)->where('tebal', 0))
            ->orderByDesc('id')->first();
    }

    private function idMesinHotpress(): array
    {
        $ids = Mesin::join('kategori_mesins', 'mesins.kategori_mesin_id', '=', 'kategori_mesins.id')
            ->where('kategori_mesins.nama_kategori_mesin', 'HOTPRESS')
            ->pluck('mesins.id')->toArray();

        return ! empty($ids) ? $ids : [13, 26, 27, 28];
    }

    private function idKategoriPlatform(): ?int
    {
        $id = KategoriBarang::whereRaw('LOWER(nama_kategori) = ?', ['platform'])->value('id');

        return $id ? (int) $id : null;
    }
}