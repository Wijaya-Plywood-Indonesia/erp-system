<?php

namespace App\Observers;

use App\Models\ModalSanding;
use App\Models\SerahTerimaHp;
use App\Services\ResolveBarangSetengahJadi;

class ModalSandingObserver
{
    public function creating(ModalSanding $modalSanding): void
    {
        $this->konversiIdNegatif($modalSanding);
        $this->isiBarangSetengahJadi($modalSanding);
    }

    public function created(ModalSanding $modalSanding): void
    {
        // Fitur auto-create Hasil Sanding ditiadakan sesuai permintaan
    }

    public function updating(ModalSanding $modalSanding): void
    {
        $this->konversiIdNegatif($modalSanding);
        $this->isiBarangSetengahJadi($modalSanding);
    }

    public function updated(ModalSanding $modalSanding): void
    {
        //
    }

    // ... method deleted/restored/forceDeleted bawaan Anda tetap dibiarkan ...

    /**
     * Jika id_serah_terima_hp negatif, berarti ini dari Hasil Sanding yang belum diserah.
     */
    private function konversiIdNegatif(ModalSanding $modalSanding): void
    {
        if ($modalSanding->id_serah_terima_hp < 0) {
            $idHasilSanding = abs($modalSanding->id_serah_terima_hp);

            $st = SerahTerimaHp::firstOrCreate(
                ['id_hasil_sanding' => $idHasilSanding, 'tujuan' => 'sanding'],
                [
                    'diterima_oleh' => auth()->user()?->name ?? 'System',
                    'status' => 'Diterima',
                    'tujuan' => 'sanding',
                ]
            );

            $modalSanding->id_serah_terima_hp = $st->id;
        }
    }

    /**
     * Palet dari gudang mentah (Platform Mentah / Triplek Jadi / Triplek Mentah)
     * tidak punya barang setengah jadi -> id_barang_setengah_jadi NULL ->
     * tidak muncul di dropdown Hasil Sanding. Isi otomatis di sini.
     */
    private function isiBarangSetengahJadi(ModalSanding $modalSanding): void
    {
        if ($modalSanding->id_barang_setengah_jadi !== null || ! $modalSanding->id_serah_terima_hp) {
            return;
        }

        $st = SerahTerimaHp::with([
            'platformMthMutasiKeluar.jenisKayu',
            'triplekMutasiKeluar.jenisKayu',
            'triplekMthMutasiKeluar.jenisKayu',
        ])->find($modalSanding->id_serah_terima_hp);

        if ($st) {
            $modalSanding->id_barang_setengah_jadi = ResolveBarangSetengahJadi::fromSerahTerima($st)?->id;
        }
    }
}