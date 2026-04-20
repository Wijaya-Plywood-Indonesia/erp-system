<?php

namespace App\Observers;

use App\Models\NotaKayu;
use App\Services\HppAverageService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class NotaKayuObserver
{
    protected $hppService;

    public function __construct(HppAverageService $hppService)
    {
        $this->hppService = $hppService;
    }

    public function created(NotaKayu $nota): void
    {
        // Intentionally blank — tunggu status Lunas
    }

    public function updated(NotaKayu $nota): void
    {
        // Hanya proses kalau status_pelunasan yang berubah
        if (! $nota->wasChanged('status_pelunasan')) return;

        // Hanya proses kalau statusnya Lunas
        if (! str_contains($nota->status_pelunasan ?? '', 'Lunas')) return;

        Log::info('[STOK] Nota Lunas — mulai proses stok masuk', [
            'nota_id' => $nota->id,
            'no_nota' => $nota->no_nota,
        ]);

        try {
            // Panggil service untuk proses nota lunas (update langsung ke summary)
            $this->hppService->prosesNotaKayuLunas($nota);
        } catch (\Throwable $e) {
            // Error stok tidak menggagalkan save nota
            Log::error('[STOK] prosesNotaKayuLunas GAGAL', [
                'nota_id' => $nota->id,
                'no_nota' => $nota->no_nota,
                'error'   => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
            ]);
        }
    }

    public function deleting(NotaKayu $nota): void
    {
        // Hanya rollback kalau nota sudah pernah Lunas (sudah masuk stok)
        if (! str_contains($nota->status_pelunasan ?? '', 'Lunas')) {
            Log::info('[STOK] deleting SKIP — nota belum Lunas', [
                'nota_id' => $nota->id,
            ]);
            return;
        }

        Log::info('[STOK] deleting — rollback stok karena nota dihapus', [
            'nota_id' => $nota->id,
            'no_nota' => $nota->no_nota,
        ]);

        try {
            $this->hppService->rollbackNotaKayuLunas($nota);
        } catch (\Throwable $e) {
            Log::error('[STOK] rollbackNotaKayuLunas GAGAL', [
                'nota_id' => $nota->id,
                'error'   => $e->getMessage(),
            ]);
        }
    }

    public function deleted(NotaKayu $nota): void
    {
        // Intentionally blank — sudah ditangani di deleting()
    }
}
