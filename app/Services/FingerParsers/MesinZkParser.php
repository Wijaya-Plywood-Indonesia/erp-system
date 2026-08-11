<?php

namespace App\Services\FingerParsers;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Format contoh (tab-separated, ADA header):
 *   No\tMchn\tEnNo\t\tName\t\tMode\tIOMd\tDateTime
 *   000001\t1\t000009039\tandri           \t1\t0\t2026/06/12  16:00:17
 *
 * Kolom yang dipakai: EnNo (kode pegawai) dan DateTime.
 * Kolom Name di file TIDAK dipakai untuk matching (kadang kosong / typo),
 * nama pegawai selalu diambil dari tabel Pegawai berdasarkan kode_pegawai.
 */
class MesinZkParser implements FingerParserInterface
{
    public function supports(string $firstLine): bool
    {
        $firstLine = trim($firstLine);

        return Str::contains($firstLine, ['No', 'Mchn', 'EnNo', 'DateTime'])
            && Str::contains($firstLine, "\t");
    }

    public function parse(string $absolutePath): Collection
    {
        $rows = collect();

        $handle = fopen($absolutePath, 'r');

        if (! $handle) {
            return $rows;
        }

        $isFirstLine = true;

        while (($line = fgets($handle)) !== false) {
            $line = rtrim($line, "\r\n");

            if ($isFirstLine) {
                // Lewati baris header.
                $isFirstLine = false;

                continue;
            }

            if (trim($line) === '') {
                continue;
            }

            // Kolom dipisah tab, tapi ada beberapa kolom kosong ganda (Name\t\t)
            // jadi kita split apa adanya lalu ambil berdasarkan posisi dari belakang,
            // karena DateTime & EnNo posisinya konsisten walau jumlah tab di tengah
            // kadang beda karena nama kosong.
            $columns = array_map('trim', explode("\t", $line));

            if (count($columns) < 4) {
                continue;
            }

            // Posisi dari belakang paling stabil:
            // ... , Mode, IOMd, DateTime  <- 3 kolom terakhir
            $datetimeRaw = trim(array_pop($columns));
            array_pop($columns); // IOMd, tidak dipakai
            array_pop($columns); // Mode, tidak dipakai

            // Sisa kolom depan: No, Mchn, EnNo, (Name, mungkin kosong/hilang)
            // EnNo selalu kolom index ke-2 (0-based) dari depan.
            $enNo = $columns[2] ?? null;

            if (! $enNo) {
                continue;
            }

            try {
                $waktu = Carbon::createFromFormat('Y/m/d H:i:s', str_replace('  ', ' ', $datetimeRaw));
            } catch (\Throwable $e) {
                continue;
            }

            $rows->push([
                // Normalisasi: buang leading zero, contoh "000009039" -> "9039".
                'kode_pegawai' => (string) (int) $enNo,
                'waktu' => $waktu,
            ]);
        }

        fclose($handle);

        return $rows;
    }

    public function label(): string
    {
        return 'Mesin Fingerprint (ZK/Wahana)';
    }
}
