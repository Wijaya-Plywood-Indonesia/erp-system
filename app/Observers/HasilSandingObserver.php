<?php

namespace App\Observers;

use App\Events\ProductionUpdated;
use App\Models\HasilSanding;
use App\Models\ModalSanding;
use App\Models\SerahTerimaHp;
use App\Services\ResolveBarangSetengahJadi;

class HasilSandingObserver
{
    public function creating(HasilSanding $hasilSanding): void
    {
        $this->konversiIdNegatif($hasilSanding);
        $this->isiBarangSetengahJadi($hasilSanding);
    }

    public function created(HasilSanding $hasilSanding): void
    {
        ModalSanding::create([
            'id_produksi_sanding'     => $hasilSanding->id_produksi_sanding,
            'id_serah_terima_hp'      => $hasilSanding->id_serah_terima_hp,
            'id_hasil_sanding'        => $hasilSanding->id,
            'id_barang_setengah_jadi' => $hasilSanding->id_barang_setengah_jadi,
            'kuantitas'               => $hasilSanding->kuantitas,
            'jumlah_sanding_face'     => $hasilSanding->jumlah_sanding_face ?? 0,
            'jumlah_sanding_back'     => $hasilSanding->jumlah_sanding_back ?? 0,
            'no_palet'                => $hasilSanding->no_palet,
        ]);

        if ($hasilSanding->id_produksi_sanding) {
            ProductionUpdated::dispatch($hasilSanding->id_produksi_sanding, 'sanding');
        }
    }

    public function updating(HasilSanding $hasilSanding): void
    {
        $this->konversiIdNegatif($hasilSanding);
        $this->isiBarangSetengahJadi($hasilSanding);
    }

    public function updated(HasilSanding $hasilSanding): void
    {
        $modal = ModalSanding::where('id_hasil_sanding', $hasilSanding->id)->first();

        if (! $modal) {
            $modal = ModalSanding::where('id_produksi_sanding', $hasilSanding->id_produksi_sanding)
                ->where('no_palet', $hasilSanding->getOriginal('no_palet') ?? $hasilSanding->no_palet)
                ->first();
        }

        if ($modal) {
            $modal->update([
                'id_serah_terima_hp'      => $hasilSanding->id_serah_terima_hp,
                'id_hasil_sanding'        => $hasilSanding->id,
                'id_barang_setengah_jadi' => $hasilSanding->id_barang_setengah_jadi,
                'kuantitas'               => $hasilSanding->kuantitas,
                'jumlah_sanding_face'     => $hasilSanding->jumlah_sanding_face ?? 0,
                'jumlah_sanding_back'     => $hasilSanding->jumlah_sanding_back ?? 0,
                'no_palet'                => $hasilSanding->no_palet,
            ]);
        } else {
            ModalSanding::create([
                'id_produksi_sanding'     => $hasilSanding->id_produksi_sanding,
                'id_serah_terima_hp'      => $hasilSanding->id_serah_terima_hp,
                'id_hasil_sanding'        => $hasilSanding->id,
                'id_barang_setengah_jadi' => $hasilSanding->id_barang_setengah_jadi,
                'kuantitas'               => $hasilSanding->kuantitas,
                'jumlah_sanding_face'     => $hasilSanding->jumlah_sanding_face ?? 0,
                'jumlah_sanding_back'     => $hasilSanding->jumlah_sanding_back ?? 0,
                'no_palet'                => $hasilSanding->no_palet,
            ]);
        }

        if ($hasilSanding->id_produksi_sanding) {
            ProductionUpdated::dispatch($hasilSanding->id_produksi_sanding, 'sanding');
        }
    }

    public function deleted(HasilSanding $hasilSanding): void
    {
        $modal = ModalSanding::where('id_hasil_sanding', $hasilSanding->id)->first();

        if (! $modal) {
            $modal = ModalSanding::where('id_produksi_sanding', $hasilSanding->id_produksi_sanding)
                ->where('no_palet', $hasilSanding->no_palet)
                ->first();
        }

        if ($modal) {
            $modal->delete();
        }

        if ($hasilSanding->id_produksi_sanding) {
            ProductionUpdated::dispatch($hasilSanding->id_produksi_sanding, 'sanding');
        }
    }

    /**
     * Jika id_serah_terima_hp negatif, berarti ini dari Hasil Sanding yang belum diserah.
     */
    private function konversiIdNegatif(HasilSanding $hasilSanding): void
    {
        if ($hasilSanding->id_serah_terima_hp && $hasilSanding->id_serah_terima_hp < 0) {
            $idHasilSanding = abs($hasilSanding->id_serah_terima_hp);

            $st = SerahTerimaHp::firstOrCreate(
                ['id_hasil_sanding' => $idHasilSanding, 'tujuan' => 'sanding'],
                [
                    'diterima_oleh' => auth()->user()?->name ?? 'System',
                    'status'        => 'Diterima',
                    'tujuan'        => 'sanding',
                ]
            );

            $hasilSanding->id_serah_terima_hp = $st->id;
        }
    }

    /**
     * Mengisi otomatis id_barang_setengah_jadi jika belum ada.
     */
    private function isiBarangSetengahJadi(HasilSanding $hasilSanding): void
    {
        if ($hasilSanding->id_barang_setengah_jadi !== null || ! $hasilSanding->id_serah_terima_hp) {
            return;
        }

        $st = SerahTerimaHp::with([
            'platformMthMutasiKeluar.jenisKayu',
            'triplekMutasiKeluar.jenisKayu',
            'triplekMthMutasiKeluar.jenisKayu',
            'platformHasilHp.barangSetengahJadi',
            'hasilGrajiTriplek.barangSetengahJadiHp',
            'hasilSanding.barangSetengahJadi',
        ])->find($hasilSanding->id_serah_terima_hp);

        if (! $st) {
            return;
        }

        $bsj = $st->barangSetengahJadi;
        if ($bsj) {
            $hasilSanding->id_barang_setengah_jadi = $bsj->id;
            return;
        }

        // Jika dari gudang mentah (mutasi keluar)
        $resolved = ResolveBarangSetengahJadi::fromSerahTerima($st);
        if ($resolved) {
            $hasilSanding->id_barang_setengah_jadi = $resolved->id;
        }
    }
}
