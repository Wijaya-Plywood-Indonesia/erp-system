<?php

namespace App\Services;

use App\Models\NotaBarangKeluar;
use App\Models\NotaBarangMasuk;
use Illuminate\Support\Carbon;

class NomorNotaService
{
    private const STARTING_SEQUENCE = [
        'bml' => 2029,
        'bm'  => 1132,
        'bkl' => 1122,
        'bk'  => 782,
    ];

    public static function generateBarangKeluar(string $tipe, Carbon $tanggal): string
    {
        return self::generate($tipe, $tanggal, NotaBarangKeluar::class);
    }

    public static function generateBarangMasuk(string $tipe, Carbon $tanggal): string
    {
        return self::generate($tipe, $tanggal, NotaBarangMasuk::class);
    }

    public static function generate(string $tipe, Carbon $tanggal, string $model): string
    {
        $bulan     = $tanggal->format('m');
        $hari      = $tanggal->format('d');
        $suffix    = $bulan . $hari;
        $tipeLower = strtolower($tipe);

        // Gunakan LIKE pada no_nota karena kolom tipe_nota tidak ada
        // Format di DB: "bk 0782-0401" → cari yang diawali "bk %"
        $existing = $model::query()
            ->where('no_nota', 'like', "{$tipeLower} %") // ← pakai kolom yang ADA
            ->pluck('no_nota');

        $usedSequences = $existing->map(function ($nota) {
            // "bk 0782-0401" → split spasi → ["bk", "0782-0401"]
            $parts = explode(' ', $nota);
            if (isset($parts[1])) {
                // "0782-0401" → split "-" → ["0782", "0401"]
                $seqParts = explode('-', $parts[1]);
                return isset($seqParts[0]) ? (int) $seqParts[0] : 0;
            }
            return 0;
        })->filter()->sort()->values();

        $lastSequence = $usedSequences->last()
            ?? self::STARTING_SEQUENCE[$tipeLower]
            ?? 0;

        $nextSequence = ($lastSequence >= 10000) ? 1 : $lastSequence + 1;

        $sequence = str_pad($nextSequence, 4, '0', STR_PAD_LEFT);

        return "{$tipeLower} {$sequence}-{$suffix}";
    }
}
