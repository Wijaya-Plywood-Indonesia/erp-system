<?php

namespace App\Observers;

use App\Models\HppAverageLog;
use App\Models\NotaKayu;
use App\Services\HppAverageService;
use Illuminate\Support\Facades\Log;

/**
 * NotaKayuObserver
 *
 * TRIGGER HPP:
 *   updated()  → status baru berubah ke "Sudah Diperiksa" → prosesNotaKayuMasuk()
 *   deleting() → nota yang sudah diperiksa dihapus        → batalkanNotaKayuMasuk()
 *
 * Guard duplikasi di updated():
 *   Cek apakah log HPP untuk nota ini sudah ada.
 *   Mencegah proses ganda jika admin save ulang nota yang sudah diperiksa.
 */
class NotaKayuObserver
{
    public function __construct(
        private readonly HppAverageService $hppService
    ) {}

    // -------------------------------------------------------------------------
    // created — tidak diproses, tunggu status Sudah Diperiksa
    // -------------------------------------------------------------------------

    public function created(NotaKayu $nota): void
    {
        // Intentionally blank — HPP diproses saat status berubah ke Sudah Diperiksa
    }

    // -------------------------------------------------------------------------
    // updated — proses HPP hanya saat status baru berubah ke Sudah Diperiksa
    // -------------------------------------------------------------------------

    public function updated(NotaKayu $nota): void
    {
        // Lewati jika status tidak berubah
        if (! $nota->wasChanged('status')) {
            return;
        }

        // Lewati jika status bukan Sudah Diperiksa
        if (! str_contains($nota->status ?? '', 'Sudah Diperiksa')) {
            return;
        }

        // Guard duplikasi: cek apakah nota ini sudah pernah masuk ke HPP log
        // Mencegah proses ganda jika admin edit & save ulang nota yang sudah diperiksa
        $sudahDiproses = HppAverageLog::where('referensi_type', NotaKayu::class)
            ->where('referensi_id', $nota->id)
            ->whereNull('grade')
            ->exists();

        if ($sudahDiproses) {
            Log::info('[HPP] Observer updated SKIP — nota sudah diproses ke HPP', [
                'nota_id' => $nota->id,
                'no_nota' => $nota->no_nota,
                'status'  => $nota->status,
            ]);
            return;
        }

        Log::info('[HPP] Observer updated — mulai proses HPP', [
            'nota_id' => $nota->id,
            'no_nota' => $nota->no_nota,
            'status'  => $nota->status,
        ]);

        try {
            $this->hppService->prosesNotaKayuMasuk($nota);
        } catch (\Throwable $e) {
            // Error HPP tidak menggagalkan save nota
            Log::error('[HPP] Observer updated GAGAL', [
                'nota_id' => $nota->id,
                'no_nota' => $nota->no_nota,
                'error'   => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
            ]);
        }
    }

    // -------------------------------------------------------------------------
    // deleting — batalkan HPP sebelum nota dihapus (relasi masih bisa diakses)
    // -------------------------------------------------------------------------

    public function deleting(NotaKayu $nota): void
    {
        // Hanya batalkan jika nota sudah pernah diperiksa (berarti HPP sudah diproses)
        if (! str_contains($nota->status ?? '', 'Sudah Diperiksa')) {
            Log::info('[HPP] Observer deleting SKIP — nota belum diperiksa', [
                'nota_id' => $nota->id,
                'status'  => $nota->status,
            ]);
            return;
        }

        Log::info('[HPP] Observer deleting — batalkan HPP', [
            'nota_id' => $nota->id,
            'no_nota' => $nota->no_nota,
        ]);

        try {
            // Gunakan deleting (bukan deleted) agar relasi kayuMasuk.detailTurusanKayus
            // masih bisa diakses oleh batalkanNotaKayuMasuk()
            $this->hppService->batalkanNotaKayuMasuk($nota);
        } catch (\Throwable $e) {
            // Error HPP tidak menggagalkan delete nota
            Log::error('[HPP] Observer deleting GAGAL', [
                'nota_id' => $nota->id,
                'no_nota' => $nota->no_nota,
                'error'   => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
            ]);
        }
    }

    // -------------------------------------------------------------------------
    // deleted — kosong, sudah ditangani di deleting()
    // -------------------------------------------------------------------------

    public function deleted(NotaKayu $nota): void
    {
        // Intentionally blank — sudah ditangani di deleting()
    }
}
