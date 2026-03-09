<?php

namespace App\Observers;

use App\Models\ValidasiHasilRotary;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * ValidasiHasilRotaryObserver
 *
 * Otomatis trigger API generate jurnal setelah validasi disimpan.
 *
 * DAFTARKAN di App\Providers\AppServiceProvider::boot():
 * ───────────────────────────────────────────────────────
 *   ValidasiHasilRotary::observe(ValidasiHasilRotaryObserver::class);
 * ───────────────────────────────────────────────────────
 *
 * CATATAN:
 * Observer ini akan memanggil endpoint internal /api/jurnal/rotary/trigger
 * setiap kali status validasi berubah menjadi 'divalidasi' atau 'disetujui'.
 * Sistem secara otomatis mengecek apakah semua mesin pada tanggal tersebut
 * sudah tervalidasi sebelum membuat jurnal.
 */
class ValidasiHasilRotaryObserver
{
    /**
     * Handle saat validasi dibuat/diupdate
     */
    public function saved(ValidasiHasilRotary $validasi): void
    {
        if (!in_array($validasi->status, ['divalidasi', 'disetujui'])) {
            return; // Hanya trigger jika status valid
        }

        if (!$validasi->id_produksi) {
            return;
        }

        Log::info("ValidasiObserver: Status '{$validasi->status}' pada id_produksi={$validasi->id_produksi}. Trigger jurnal check.");

        $this->triggerJurnalCheck($validasi->id_produksi);
    }

    /**
     * Panggil API internal untuk cek & generate jurnal
     */
    private function triggerJurnalCheck(int $idProduksi): void
    {
        try {
            // Opsi A: Panggil service langsung (lebih efisien, tanpa HTTP call)
            $service = app(\App\Services\Akuntansi\RotaryJurnalService::class);
            $produksi = \App\Models\ProduksiRotary::find($idProduksi);

            if (!$produksi) {
                Log::warning("ValidasiObserver: id_produksi={$idProduksi} tidak ditemukan.");
                return;
            }

            $tanggal = $produksi->tgl_produksi instanceof \Carbon\Carbon
                ? $produksi->tgl_produksi->format('Y-m-d')
                : $produksi->tgl_produksi;

            $payload = $service->buildJurnalPayload($tanggal);

            if ($payload === null) {
                Log::info("ValidasiObserver: Belum semua mesin divalidasi (tanggal={$tanggal}). Jurnal ditunda.");
                return;
            }

            Log::info("ValidasiObserver: Semua mesin sudah divalidasi (tanggal={$tanggal}). Payload siap.", [
                'total_items'  => count($payload['jurnal_items']),
                'total_debit'  => $payload['jurnal_header']['total_debit'],
                'total_kredit' => $payload['jurnal_header']['total_kredit'],
                'is_balance'   => $payload['jurnal_header']['is_balance'],
            ]);

            // ── Kirim ke web akuntansi (uncomment setelah siap) ──────────────
            // $this->sendToAkuntansi($payload, $tanggal);

            // ── Atau: Dispatch job untuk proses background ────────────────────
            // \App\Jobs\CreateJurnalRotaryJob::dispatch($payload, $tanggal);

        } catch (\Throwable $e) {
            Log::error("ValidasiObserver: Error saat trigger jurnal check: " . $e->getMessage());
            Log::error($e->getTraceAsString());
        }
    }

    /**
     * Kirim payload ke web akuntansi (siap digunakan setelah endpoint akuntansi ready)
     */
    private function sendToAkuntansi(array $payload, string $tanggal): void
    {
        $akuntansiUrl = config('services.akuntansi.url', 'https://akuntansi.wijayaplywoods.com');
        $apiKey       = config('services.akuntansi.key', '');

        try {
            /** @var \Illuminate\Http\Client\Response $response */
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$apiKey}",
                'Accept'        => 'application/json',
            ])
            ->timeout(30)
            ->post("{$akuntansiUrl}/api/jurnal/rotary/create", $payload);

            if ($response->successful()) {
                Log::info("ValidasiObserver: Jurnal berhasil dikirim ke akuntansi (tanggal={$tanggal}).", [
                    'response' => $response->json(),
                ]);
            } else {
                Log::error("ValidasiObserver: Gagal kirim jurnal ke akuntansi (tanggal={$tanggal}).", [
                    'status'   => $response->status(),
                    'response' => $response->body(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::error("ValidasiObserver: HTTP error kirim ke akuntansi: " . $e->getMessage());
        }
    }
}