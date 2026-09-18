<?php

namespace App\Observers;

use App\Models\HasilSanding;
use App\Models\ModalSanding;

class ModalSandingObserver
{
    /**
     * Handle the ModalSanding "creating" event.
     */
    public function creating(ModalSanding $modalSanding): void
    {
        // Jika id_serah_terima_hp negatif, berarti ini dari Hasil Sanding yang belum diserah
        if ($modalSanding->id_serah_terima_hp < 0) {
            $idHasilSanding = abs($modalSanding->id_serah_terima_hp);
            
            // Cek apakah sudah dibuat sebelumnya (mencegah duplikasi)
            $st = \App\Models\SerahTerimaHp::firstOrCreate(
                ['id_hasil_sanding' => $idHasilSanding, 'tujuan' => 'sanding'],
                [
                    'diterima_oleh' => auth()->user()?->name ?? 'System',
                    'status' => 'Diterima',
                    'tujuan' => 'sanding',
                ]
            );

            // Ganti id_serah_terima_hp dengan ID SerahTerimaHp yang asli
            $modalSanding->id_serah_terima_hp = $st->id;
        }
    }

    /**
     * Handle the ModalSanding "created" event.
     */
    public function created(ModalSanding $modalSanding): void
    {
        // Fitur auto-create Hasil Sanding ditiadakan sesuai permintaan
    }

    /**
     * Handle the ModalSanding "updated" event.
     */
    public function updated(ModalSanding $modalSanding): void
    {
        //
    }

    /**
     * Handle the ModalSanding "deleted" event.
     */
    public function deleted(ModalSanding $modalSanding): void
    {
        //
    }

    /**
     * Handle the ModalSanding "restored" event.
     */
    public function restored(ModalSanding $modalSanding): void
    {
        //
    }

    /**
     * Handle the ModalSanding "force deleted" event.
     */
    public function forceDeleted(ModalSanding $modalSanding): void
    {
        //
    }
}
