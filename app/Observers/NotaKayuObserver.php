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
        // Hanya proses kalau status_pelunasan yang berubah
        if (! $nota->wasChanged('status_pelunasan')) {
            return;
        }

        // Hanya proses kalau statusnya Lunas
        if (! str_contains($nota->status_pelunasan ?? '', 'Lunas')) {
            Log::info('[OBSERVER] Nota belum Lunas, skip proses stok', [
                'nota_id' => $nota->id,
                'status' => $nota->status_pelunasan,
            ]);
            return;
        }

        // ✅ CEK APAKAH SUDAH PERNAH DIPROSES (CEGAH DUPLIKAT)
        // Ini sebagai safety net selain dari service
        $existingProcess = \App\Models\HppAverageLog::where('referensi_type', NotaKayu::class)
            ->where('referensi_id', $nota->id)
            ->exists();

        if ($existingProcess) {
            Log::warning('[OBSERVER] SKIP - Nota sudah pernah diproses', [
                'nota_id' => $nota->id,
                'no_nota' => $nota->no_nota,
            ]);
            return;
        }

        Log::info('[OBSERVER] Nota Lunas — mulai proses stok masuk (DENGAN LOG HPP)', [
            'nota_id' => $nota->id,
            'no_nota' => $nota->no_nota,
        ]);

        try {
            // Panggil service untuk proses nota lunas (SEKARANG DENGAN LOG HPP)
            $this->hppService->prosesNotaKayuLunas($nota);

            Log::info('[OBSERVER] Nota Lunas — proses stok berhasil', [
                'nota_id' => $nota->id,
                'no_nota' => $nota->no_nota,
            ]);
        } catch (\Throwable $e) {
            // Error stok tidak menggagalkan save nota
            Log::error('[OBSERVER] prosesNotaKayuLunas GAGAL', [
                'nota_id' => $nota->id,
                'no_nota' => $nota->no_nota,
                'error'   => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
            ]);

            // Optional: Bisa kirim notifikasi ke admin
            // Notification::route('mail', 'admin@example.com')->notify(...);
        }
    }

    public function deleting(NotaKayu $nota): void
    {
        // Hanya rollback kalau nota sudah pernah Lunas (sudah masuk stok)
        if (! str_contains($nota->status_pelunasan ?? '', 'Lunas')) {
            Log::info('[OBSERVER] deleting SKIP — nota belum Lunas', [
                'nota_id' => $nota->id,
            ]);
            return;
        }

        // ✅ CEK APAKAH SUDAH PERNAH DIROLLBACK SEBELUMNYA
        // Cek apakah sudah tidak ada stok yang terkait? (opsional)

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
                'error'   => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
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
