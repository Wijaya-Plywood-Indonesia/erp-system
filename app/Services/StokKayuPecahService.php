<?php

namespace App\Services;

use App\Models\LogStokKayuPecahRotary;
use App\Models\StokKayuPecahRotary;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Exception;

/**
 * Class StokKayuPecahService
 *
 * Service layer independen yang mengelola alur persediaan kayu pecah
 * (stok masuk dari pengosongan Lahan Rotary & stok keluar untuk Graji Balken).
 */
class StokKayuPecahService
{
    /**
     * Menambahkan stok kayu pecah saat Lahan Rotary selesai diproses.
     *
     * @param int $idJenisKayu ID Jenis Kayu
     * @param int $panjang Panjang kayu dalam CM (misal: 130, 260)
     * @param int $qty Jumlah batang kayu pecah yang masuk
     * @param int|null $idLahan ID Lahan asal (opsional)
     * @param mixed|null $referensi Eloquent Model referensi pemicu (misal: PenggunaanLahanRotary)
     * @param string|null $keterangan Catatan atau keterangan riwayat
     * @return void
     * @throws InvalidArgumentException
     */
    public function tambahStok(
        int $idJenisKayu,
        int $panjang,
        int $qty,
        ?int $idLahan = null,
        mixed $referensi = null,
        ?string $keterangan = null
    ): void {
        if ($qty <= 0) {
            return;
        }

        DB::transaction(function () use ($idJenisKayu, $panjang, $qty, $idLahan, $referensi, $keterangan) {
            // Ambil atau buat record penampung stok berdasarkan kombinasi Jenis Kayu + Panjang
            $stokRecord = StokKayuPecahRotary::firstOrCreate(
                [
                    'id_jenis_kayu' => $idJenisKayu,
                    'panjang'       => $panjang,
                ],
                [
                    'stok_batang' => 0,
                ]
            );

            $before = (int) $stokRecord->stok_batang;
            $after  = $before + $qty;

            // Update stok aktif
            $stokRecord->update([
                'stok_batang' => $after,
            ]);

            // Catat ke log audit trail
            LogStokKayuPecahRotary::create([
                'id_jenis_kayu'  => $idJenisKayu,
                'panjang'        => $panjang,
                'id_lahan'       => $idLahan,
                'tipe'           => 'masuk',
                'jumlah_batang'  => $qty,
                'stok_before'    => $before,
                'stok_after'     => $after,
                'keterangan'     => $keterangan ?? 'Masuk dari Lahan Rotary',
                'referensi_type' => $referensi ? get_class($referensi) : null,
                'referensi_id'   => $referensi?->id,
            ]);
        });
    }

    /**
     * Mengurangi stok kayu pecah saat digunakan untuk produksi Graji Balken.
     *
     * @param int $idJenisKayu ID Jenis Kayu
     * @param int $panjang Panjang kayu dalam CM
     * @param int $qty Jumlah batang kayu pecah yang digunakan
     * @param mixed|null $referensi Eloquent Model referensi pemicu (misal: ProduksiBalken)
     * @param string|null $keterangan Catatan atau keterangan riwayat
     * @return bool True jika berhasil dikurangi, False jika stok tidak mencukupi
     */
    public function kurangiStok(
        int $idJenisKayu,
        int $panjang,
        int $qty,
        mixed $referensi = null,
        ?string $keterangan = null
    ): bool {
        if ($qty <= 0) {
            return false;
        }

        return DB::transaction(function () use ($idJenisKayu, $panjang, $qty, $referensi, $keterangan) {
            // Gunakan lockForUpdate untuk mencegah race condition / konkurensi data
            $stokRecord = StokKayuPecahRotary::where('id_jenis_kayu', $idJenisKayu)
                ->where('panjang', $panjang)
                ->lockForUpdate()
                ->first();

            // Validasi kecukupan stok
            if (!$stokRecord || $stokRecord->stok_batang < $qty) {
                return false;
            }

            $before = (int) $stokRecord->stok_batang;
            $after  = $before - $qty;

            // Update stok aktif
            $stokRecord->update([
                'stok_batang' => $after,
            ]);

            // Catat ke log audit trail
            LogStokKayuPecahRotary::create([
                'id_jenis_kayu'  => $idJenisKayu,
                'panjang'        => $panjang,
                'id_lahan'       => null,
                'tipe'           => 'keluar',
                'jumlah_batang'  => $qty,
                'stok_before'    => $before,
                'stok_after'     => $after,
                'keterangan'     => $keterangan ?? 'Dipakai Produksi Graji Balken',
                'referensi_type' => $referensi ? get_class($referensi) : null,
                'referensi_id'   => $referensi?->id,
            ]);

            return true;
        });
    }

    /**
     * Mengambil data ringkasan stok kayu pecah aktif (stok > 0)
     * yang dikelompokkan untuk kebutuhan display Widget Graji Balken.
     *
     * @return Collection
     */
    public function getStokGroupedWidget(): Collection
    {
        return StokKayuPecahRotary::with('jenisKayu')
            ->adaStok()
            ->get()
            ->map(function ($item) {
                return (object) [
                    'id_jenis_kayu' => $item->id_jenis_kayu,
                    'jenis_kayu'    => $item->jenisKayu->nama_kayu ?? 'N/A',
                    'panjang'       => $item->panjang,
                    'total_batang'  => $item->stok_batang,
                ];
            });
    }

    /**
     * Mendapatkan jumlah stok aktif untuk spesifikasi kayu tertentu.
     *
     * @param int $idJenisKayu ID Jenis Kayu
     * @param int $panjang Panjang kayu dalam CM
     * @return int Jumlah batang yang tersedia
     */
    public function getStokAktif(int $idJenisKayu, int $panjang): int
    {
        return (int) StokKayuPecahRotary::filterSpesifikasi($idJenisKayu, $panjang)
            ->value('stok_batang') ?? 0;
    }
}
