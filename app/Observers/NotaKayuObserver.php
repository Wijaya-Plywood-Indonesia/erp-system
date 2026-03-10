<?php

namespace App\Observers;

use App\Models\NotaKayu;
use App\Services\HppAverageService;
use Illuminate\Support\Facades\Log;

class NotaKayuObserver
{
    public function __construct(
        private readonly HppAverageService $hppService
    ) {}

    public function created(NotaKayu $nota): void
    {
        try {
            $this->hppService->prosesNotaKayuMasuk($nota);
        } catch (\Throwable $e) {
            Log::error("HppAverage gagal diproses untuk NotaKayu #{$nota->id}: {$e->getMessage()}", [
                'nota_id'   => $nota->id,
                'no_nota'   => $nota->no_nota,
                'exception' => $e,
            ]);
        }
    }

    public function updated(NotaKayu $nota): void
    {
        // Intentionally left blank.
        // Gunakan HppAverageService::recalculateAll() untuk rekalkuasi manual.
    }

    /**
     * Hapus nota → tidak menghapus log HPP secara otomatis.
     * Audit trail tetap tersimpan; gunakan recalculateAll() untuk sinkronisasi.
     */
    public function deleted(NotaKayu $nota): void
    {
        // Intentionally left blank.
    }
}
