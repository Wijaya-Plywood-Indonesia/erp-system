<?php

namespace App\Observers;

use App\Models\HasilSanding;
use App\Models\ModalSanding;

class ModalSandingObserver
{
    /**
     * Handle the ModalSanding "created" event.
     */
    public function created(ModalSanding $modalSanding): void
    {
        //
        // Cek apakah sudah ada hasil sanding untuk produksi ini
        $exists = HasilSanding::where('id_produksi_sanding', $modalSanding->id_produksi_sanding)
            ->where('no_palet', $modalSanding->no_palet)
            ->exists();

        if ($exists) {
            // Jika sudah ada, hentikan agar tidak duplikasi
            return;
        }

        // Jika belum ada → buat hasil sanding baru
        HasilSanding::create([
            'id_produksi_sanding' => $modalSanding->id_produksi_sanding,
            'id_barang_setengah_jadi' => $modalSanding->id_barang_setengah_jadi,
            'kuantitas' => $modalSanding->kuantitas,
            'jumlah_sanding_face' => 0,
            'jumlah_sanding_back' => 0,
            'no_palet' => $modalSanding->no_palet,
            'status' => 'Belum Sanding',
        ]);
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
