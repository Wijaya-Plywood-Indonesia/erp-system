<?php

namespace App\Services\FingerParsers;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Format contoh (tab-separated, tanpa header):
 *   6108\t2022-08-01 08:59:16\t1\t0\t1\t0
 *
 * Kolom: kode_pegawai, datetime, lalu 4 kolom flag yang tidak dipakai.
 */
class KantorDatParser implements FingerParserInterface
{
    public function supports(string $firstLine): bool
    {
        $firstLine = trim($firstLine);
        $columns = preg_split('/\t+/', $firstLine);

        // Baris pertama harus langsung data: kolom[0] numerik (kode pegawai),
        // kolom[1] berupa datetime "Y-m-d H:i:s". Tidak ada header teks.
        if (count($columns) < 2) {
            return false;
        }

        $kode = trim($columns[0]);
        $tanggal = trim($columns[1]);

        return is_numeric($kode) && (bool) preg_match('/^\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}:\d{2}$/', $tanggal);
    }

    public function parse(string $absolutePath): Collection
    {
        $rows = collect();

        $handle = fopen($absolutePath, 'r');

        if (! $handle) {
            return $rows;
        }

        while (($line = fgets($handle)) !== false) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            $columns = preg_split('/\t+/', $line);

            if (count($columns) < 2) {
                continue;
            }

            $kode = trim($columns[0]);
            $waktuRaw = trim($columns[1]);

            if ($kode === '' || ! is_numeric($kode)) {
                continue;
            }

            try {
                $waktu = Carbon::createFromFormat('Y-m-d H:i:s', $waktuRaw);
            } catch (\Throwable $e) {
                continue;
            }

            $rows->push([
                // Normalisasi: buang leading zero supaya konsisten dengan
                // kode_pegawai di tabel Pegawai.
                'kode_pegawai' => (string) (int) $kode,
                'waktu' => $waktu,
            ]);
        }

        fclose($handle);

        return $rows;
    }

    public function label(): string
    {
        return 'Kantor (.dat)';
    }
}
