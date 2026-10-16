<?php

namespace App\Services\Akuntansi\Produksi;

/**
 * Mengubah baris sheet "jurnal produksi v2" (hasil ->array() class *SheetV2)
 * menjadi struktur jurnal untuk API akuntansi.
 *
 * Kelas ini murni (tanpa DB/HTTP) supaya mudah diuji dan dipakai semua
 * jenis produksi.
 *
 * Layout kolom sheet (0-based):
 *   0 Nama Akun | 1 tgl (d-m-Y) | 2 jurnal | 3 No Akun | 4 No | 5 mm | 6 Nama
 *   7 Keterangan | 8 map (d/k) | 9 hit kbk (m/b) | 10 Banyak | 11 M3
 *   12 Harga | 13 Total | 14 ID Barang
 *
 * Satu "jurnal" = kumpulan baris berurutan; baris kosong memisahkan jurnal.
 * Baris pertama (header) dilewati.
 */
final class JurnalSheetRows
{
    /**
     * @param  array<int, array>  $sheetRows  hasil ->array() sheet V2 (termasuk baris header)
     * @param  string  $tanggal  Y-m-d, dipakai kalau kolom tgl kosong
     * @param  callable  $noDokumenFor  fn(array $barisPertama, int $urutan): string
     * @return array<int, array{no_dokumen:string, rows:array}>
     */
    public static function toJurnals(array $sheetRows, string $tanggal, callable $noDokumenFor): array
    {
        $jurnals = [];
        $grup = [];

        $tutup = function () use (&$grup, &$jurnals, $noDokumenFor) {
            if ($grup) {
                $jurnals[] = [
                    'no_dokumen' => $noDokumenFor($grup[0], count($jurnals)),
                    'rows' => $grup,
                ];
                $grup = [];
            }
        };

        foreach ($sheetRows as $i => $row) {
            $row = array_values((array) $row);

            if ($i === 0 && strtolower(trim((string) ($row[0] ?? ''))) === 'nama akun') {
                continue; // header
            }

            if (self::kosong($row)) {
                $tutup();

                continue;
            }

            $map = strtolower(trim((string) ($row[8] ?? '')));
            $noAkun = trim((string) ($row[3] ?? ''));
            if ($noAkun === '' || ! in_array($map, ['d', 'k'], true)) {
                continue;
            }

            $hit = strtolower(trim((string) ($row[9] ?? '')));
            $hit = in_array($hit, ['m', 'b'], true) ? $hit : null;

            $banyak = self::angka($row[10] ?? null);
            $m3 = self::angka($row[11] ?? null);
            $harga = self::angka($row[12] ?? null) ?? 0.0;

            $idBarang = $row[14] ?? null;
            $idBarang = (is_numeric($idBarang) && (int) $idBarang > 0) ? (int) $idBarang : null;

            $grup[] = [
                'no_akun' => $noAkun,
                'nama_akun' => trim((string) ($row[0] ?? '')),
                'tgl' => self::tanggal($row[1] ?? null, $tanggal),
                'map' => $map,
                'nama' => trim((string) ($row[6] ?? '')),
                'keterangan' => trim((string) ($row[7] ?? '')),
                'hit_kbk' => $hit,
                'banyak' => $banyak,
                'm3' => $m3,
                'harga' => $harga,
                'total' => self::total($hit, $banyak, $m3, $harga),
                'id_barang' => $idBarang,
            ];
        }

        $tutup();

        return $jurnals;
    }

    /**
     * Rumus total yang sama dengan kolom N di Excel (lihat map() di class sheet V2):
     *   =ROUND(IF(hit="m", Harga*M3, IF(hit="b", Harga*Banyak, Harga)), 0)
     */
    public static function total(?string $hit, ?float $banyak, ?float $m3, float $harga): float
    {
        $nilai = match ($hit) {
            'm' => $harga * ($m3 ?? 0.0),
            'b' => $harga * ($banyak ?? 0.0),
            default => $harga,
        };

        return round($nilai, 0);
    }

    private static function kosong(array $row): bool
    {
        foreach ($row as $c) {
            if ($c !== null && trim((string) $c) !== '') {
                return false;
            }
        }

        return true;
    }

    private static function angka(mixed $v): ?float
    {
        if ($v === null || $v === '') {
            return null;
        }

        return is_numeric($v) ? (float) $v : null;
    }

    private static function tanggal(mixed $v, string $default): string
    {
        $s = trim((string) $v);
        if ($s === '') {
            return $default;
        }

        foreach (['d-m-Y', 'd/m/Y', 'Y-m-d'] as $fmt) {
            $dt = \DateTime::createFromFormat('!'.$fmt, $s);
            if ($dt && $dt->format($fmt) === $s) {
                return $dt->format('Y-m-d');
            }
        }

        return $default;
    }
}
