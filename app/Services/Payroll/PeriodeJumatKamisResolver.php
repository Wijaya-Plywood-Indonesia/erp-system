<?php

namespace App\Services\Payroll;

use Illuminate\Support\Carbon;

/**
 * Resolve "1 siklus minggu terakhir" pola Jumat -> Kamis.
 *
 * CATATAN PENTING: saya tidak menemukan helper periode Jumat-Kamis yang
 * sudah ada di RekapPotonganGajiService, NewRekapAbsensiPegawaiService,
 * maupun AppServiceProvider (satu-satunya file yang sempat saya inspect
 * saat membuat class ini). Kalau ternyata project ini SUDAH punya helper
 * serupa di tempat lain (mis. dipakai halaman rekap absensi mingguan),
 * class ini SEBAIKNYA DIHAPUS dan diganti reuse ke helper existing itu,
 * supaya definisi "minggu terakhir" konsisten di seluruh aplikasi
 * (sesuai instruksi: jangan buat definisi periode baru kalau sudah ada).
 *
 * Definisi yang dipakai di sini (sesuai contoh di requirement):
 * - Kalau hari ini PERSIS hari Jumat -> periode = SIKLUS SEBELUMNYA yang
 *   sudah selesai: Jumat -7 hari s/d Kamis kemarin (hari ini TIDAK ikut).
 * - Kalau hari ini BUKAN Jumat -> periode = siklus yang SEDANG BERJALAN:
 *   Jumat terakhir yang sudah lewat, s/d hari ini.
 */
class PeriodeJumatKamisResolver
{
    /**
     * @return array{0: string, 1: string} [tanggalMulai, tanggalAkhir] (Y-m-d)
     */
    public static function resolveDefault(?Carbon $acuan = null): array
    {
        $acuan = ($acuan ?? Carbon::now())->copy()->startOfDay();

        $jumatIni = $acuan->copy();
        while (! $jumatIni->isFriday()) {
            $jumatIni->subDay();
        }

        if ($acuan->isSameDay($jumatIni)) {
            // Hari ini persis Jumat -> pakai siklus SEBELUMNYA yang sudah
            // selesai (Jumat -7 hari s/d Kamis kemarin).
            $mulai = $jumatIni->copy()->subWeek();
            $akhir = $jumatIni->copy()->subDay();
        } else {
            // Siklus sedang berjalan: Jumat terakhir s/d hari ini.
            $mulai = $jumatIni->copy();
            $akhir = $acuan->copy();
        }

        return [$mulai->toDateString(), $akhir->toDateString()];
    }
}
