<?php

namespace App\Services\Akuntansi\Produksi;

/**
 * Parser untuk sheet "jurnal produksi v2" yang berbentuk FromCollection
 * dengan blok per jurnal (Rotary, Kayu Keluar):
 *
 *   ['No. Jurnal: ROT/20261016/RTR1', '', ...]   <- baris judul = no_dokumen
 *   ['Nama Akun', 'tgl', ...]                    <- header (dilewati)
 *   ...baris jurnal...
 *   [kosong]
 *
 * Tiap blok diubah memakai JurnalSheetRows. Baris pesan seperti
 * "Tidak ada data ..." tidak punya judul/akun sehingga menghasilkan [].
 */
final class JurnalBerjudulRows
{
    /**
     * @param  iterable<array>  $sheetRows  hasil ->collection() sheet
     * @param  callable|null  $bersihkanNomor  fn(string $no): string
     * @return array<int, array{no_dokumen:string, rows:array}>
     */
    public static function toJurnals(iterable $sheetRows, string $tanggal, ?callable $bersihkanNomor = null): array
    {
        $jurnals = [];
        $nomor = null;
        $blok = [];

        $tutup = function () use (&$nomor, &$blok, &$jurnals, $tanggal) {
            if ($nomor !== null && $blok) {
                foreach (JurnalSheetRows::toJurnals($blok, $tanggal, fn () => $nomor) as $j) {
                    $jurnals[] = $j;
                }
            }
            $nomor = null;
            $blok = [];
        };

        foreach ($sheetRows as $row) {
            $row = array_values((array) $row);
            $sel0 = trim((string) ($row[0] ?? ''));

            if (str_starts_with($sel0, 'No. Jurnal:')) {
                $tutup();
                $no = trim(substr($sel0, strlen('No. Jurnal:')));
                $nomor = $bersihkanNomor ? $bersihkanNomor($no) : $no;

                continue;
            }

            if (strtolower($sel0) === 'nama akun') {
                continue;
            }

            if (trim(implode('', array_map(fn ($c) => (string) $c, $row))) === '') {
                $tutup();

                continue;
            }

            if ($nomor !== null) {
                $blok[] = $row;
            }
        }

        $tutup();

        return $jurnals;
    }
}