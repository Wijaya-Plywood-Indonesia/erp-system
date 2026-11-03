<?php

namespace App\Services\Akuntansi\Produksi;

use App\Services\CoaAliasService;

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
     * @return array<int, array{no_dokumen:string, rows:array, akun_tidak_valid:array}>
     */
    public static function toJurnals(array $sheetRows, string $tanggal, callable $noDokumenFor): array
    {
        $coa = app(CoaAliasService::class);
        $jurnals = [];
        $grup = [];

        $tutup = function () use (&$grup, &$jurnals, $noDokumenFor, $coa) {
            if ($grup) {
                $rows = self::seimbangkan($grup);

                // Kumpulkan semua akun yang tidak dikenali COA baru:
                // mencakup 'UNKNOWN' dan nomor akun lama yang tidak termapping.
                $akunTidakValid = array_values(array_unique(
                    array_map(
                        fn ($r) => $r['keterangan'] !== ''
                            ? "{$r['no_akun']} ({$r['keterangan']})"
                            : $r['no_akun'],
                        array_filter($rows, fn ($r) => ! $coa->isNomorAkunValid($r['no_akun']))
                    )
                ));

                $jurnals[] = [
                    'no_dokumen' => $noDokumenFor($grup[0], count($jurnals)),
                    'rows' => $rows,
                    'akun_tidak_valid' => $akunTidakValid,
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

            // Kolom "No" (mis. no seri kayu masuk). Hanya ikut kalau terisi.
            $no = trim((string) ($row[4] ?? ''));

            $baris = [
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

            if ($no !== '' && $no !== '-') {
                $baris['no'] = $no;
            }

            $grup[] = $baris;
        }

        $tutup();

        return $jurnals;
    }

    /** Selisih pembulatan (rupiah) yang masih boleh diserap baris selisih patok. */
    public const TOLERANSI_PEMBULATAN = 50;

    /**
     * Sebagian sheet (mis. Repair) menghitung baris selisih dari angka
     * yang BELUM dibulatkan, sedangkan tiap baris ditulis dibulatkan, jadi
     * debit dan kredit bisa beda beberapa rupiah. Akuntansi menolak posting
     * jurnal yang tidak balance, jadi selisih kecil itu diserap oleh baris
     * "Selisih harga patok produksi" (5069.2).
     *
     * Selisih lebih besar dari toleransi TIDAK diubah (tetap tidak balance
     * dan akan ditahan oleh pengirim).
     */
    public static function seimbangkan(array $rows, float $toleransi = self::TOLERANSI_PEMBULATAN): array
    {
        $net = 0.0; // debit - kredit
        foreach ($rows as $r) {
            $net += $r['map'] === 'd' ? $r['total'] : -$r['total'];
        }

        if ($net == 0.0 || abs($net) > $toleransi) {
            return $rows;
        }

        foreach ($rows as $i => $r) {
            if ($r['no_akun'] === '5069.2' && $r['hit_kbk'] === null) {
                $kini = $r['map'] === 'd' ? $r['total'] : -$r['total'];
                $baru = $kini - $net;

                if (abs($baru) < 0.5) {
                    unset($rows[$i]);

                    return array_values($rows);
                }

                $rows[$i]['map'] = $baru >= 0 ? 'd' : 'k';
                $rows[$i]['total'] = abs($baru);
                $rows[$i]['harga'] = abs($baru);

                return $rows;
            }
        }

        $akhir = end($rows);
        $rows[] = [
            'no_akun' => '5069.2',
            'nama_akun' => 'Selisih harga patok produksi',
            'tgl' => $akhir['tgl'],
            'map' => $net > 0 ? 'k' : 'd',
            'nama' => $akhir['nama'],
            'keterangan' => '',
            'hit_kbk' => null,
            'banyak' => null,
            'm3' => null,
            'harga' => abs($net),
            'total' => abs($net),
            'id_barang' => null,
        ];

        return $rows;
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
