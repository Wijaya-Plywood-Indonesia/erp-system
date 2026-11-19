<?php

namespace App\Services\BkBm;

use App\Models\Bk;
use App\Models\Bm;

/**
 * Jejak "siapa membuat, siapa memvalidasi, jam berapa" untuk ditempel ke keterangan log stok.
 * Dipakai saat Validasi Nota, jadi validator = user yang sedang login dan jamnya = sekarang.
 */
class BkBmJejak
{
    public static function teks(Bk|Bm $nota): string
    {
        $validator = auth()->user()?->name ?? 'Sistem';

        if ($nota->dibuat_oleh && $nota->pembuat) {
            $pembuat = $nota->pembuat->name;
        } elseif ($nota instanceof Bm && $nota->ref_no_bk) {
            $pembuat = 'OTOMATIS DARI ' . $nota->ref_no_bk . ($nota->ref_pabrik ? " ({$nota->ref_pabrik})" : '');
        } else {
            $pembuat = '-';
        }

        $dibuat = $nota->created_at ? $nota->created_at->format('d/m/y H:i') : '';

        return mb_strtoupper(
            "DIBUAT: {$pembuat} {$dibuat} | DIVALIDASI: {$validator} " . now()->format('d/m/y H:i')
        );
    }

    /** Tempel jejak di belakang keterangan; keterangan dasar dipotong bila terlalu panjang (kolom log 255 huruf). */
    public static function tempel(?string $dasar, Bk|Bm $nota, int $maks = 255): string
    {
        $jejak = self::teks($nota);
        $dasar = trim((string) $dasar);
        if ($dasar === '') {
            return mb_substr($jejak, 0, $maks);
        }
        $sisa = $maks - mb_strlen($jejak) - 3;
        if ($sisa <= 0) {
            return mb_substr($jejak, 0, $maks);
        }
        return mb_substr($dasar, 0, $sisa) . ' | ' . $jejak;
    }
}
