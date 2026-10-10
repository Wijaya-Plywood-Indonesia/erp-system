<?php

namespace App\Observers;

use App\Models\NotaKayu;
use App\Services\HppAverageService;
use Illuminate\Support\Facades\Log;

class NotaKayuObserver
{
    protected $hppService;

    public function __construct(HppAverageService $hppService)
    {
        $this->hppService = $hppService;
    }

    /**
     * Status dianggap Lunas hanya jika diawali kata "Lunas"
     * (contoh: "Lunas - 02/10/2026 10:00 (Budi)").
     *
     * JANGAN pakai str_contains: "Belum Lunas" juga mengandung kata "Lunas".
     */
    private function isLunas(?string $status): bool
    {
        return str_starts_with(trim($status ?? ''), 'Lunas');
    }

    public function created(NotaKayu $nota): void
    {
        // Intentionally blank — tunggu status Lunas
        Log::info('[OBSERVER] NotaKayu created', [
            'nota_id' => $nota->id,
            'status_pelunasan' => $nota->status_pelunasan,
        ]);
    }

    public function updated(NotaKayu $nota): void
    {
        if (! $nota->wasChanged('status_pelunasan')) {
            return;
        }

        $wasLunas = $this->isLunas($nota->getOriginal('status_pelunasan'));
        $isLunas = $this->isLunas($nota->status_pelunasan);

        if ($wasLunas && ! $isLunas) {
            Log::info('[OBSERVER] Nota batal Lunas - membatalkan stok', [
                'nota_id' => $nota->id,
                'no_nota' => $nota->no_nota,
            ]);
            try {
                $this->hppService->rollbackNotaKayuLunas($nota);
            } catch (\Throwable $e) {
                Log::error('[OBSERVER] Batal Lunas rollback GAGAL', [
                    'nota_id' => $nota->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        } elseif (! $wasLunas && $isLunas) {
            Log::info('[OBSERVER] Nota Lunas - mulai proses stok masuk', [
                'nota_id' => $nota->id,
                'no_nota' => $nota->no_nota,
            ]);

            try {
                $this->hppService->prosesNotaKayuLunas($nota);
            } catch (\Throwable $e) {
                Log::error('[OBSERVER] prosesNotaKayuLunas GAGAL', [
                    'nota_id' => $nota->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }
    }

    public function deleting(NotaKayu $nota): void
    {
        // Hanya rollback kalau nota sudah pernah Lunas (sudah masuk stok)
        if (! $this->isLunas($nota->status_pelunasan)) {
            Log::info('[OBSERVER] deleting SKIP — nota belum Lunas', [
                'nota_id' => $nota->id,
            ]);

            return;
        }

        Log::info('[OBSERVER] deleting — rollback stok karena nota dihapus', [
            'nota_id' => $nota->id,
            'no_nota' => $nota->no_nota,
        ]);

        try {
            $this->hppService->rollbackNotaKayuLunas($nota);

            Log::info('[OBSERVER] rollback stok berhasil', [
                'nota_id' => $nota->id,
                'no_nota' => $nota->no_nota,
            ]);
        } catch (\Throwable $e) {
            Log::error('[OBSERVER] rollbackNotaKayuLunas GAGAL', [
                'nota_id' => $nota->id,
                'no_nota' => $nota->no_nota,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    public function deleted(NotaKayu $nota): void
    {
        // Intentionally blank — sudah ditangani di deleting()
        Log::info('[OBSERVER] NotaKayu deleted', [
            'nota_id' => $nota->id,
            'no_nota' => $nota->no_nota,
        ]);
    }
}
