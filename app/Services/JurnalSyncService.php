<?php

namespace App\Services;

use App\Models\NotaKayu;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

// ============================================================
// SERVICE: JurnalSyncService
//
// TUGAS: Menerima payload dari NotaKayuJurnalPayloadService
//        lalu mengirimnya ke API Perusahaan 2 via HTTP POST.
//
// Flow lengkap di controller:
//   1. $payload = (new NotaKayuJurnalPayloadService)->buildPayload($nota)
//   2. $result  = (new JurnalSyncService)->kirim($nota, $payload)
//   3. Jika berhasil → is_synced = true, simpan no jurnal dari P2
// ============================================================

class JurnalSyncService
{
    private string $baseUrl;
    private string $apiToken;
    private int    $timeout;

    public function __construct()
    {
        // Ambil dari .env Perusahaan 1
        // PERUSAHAAN2_URL=https://erp.perusahaan2.com
        // PERUSAHAAN2_API_TOKEN=xxx
        $this->baseUrl  = rtrim(config('services.akuntansi.url', ''), '/');
        $this->apiToken = config('services.akuntasi.token', '');
        $this->timeout  = 30; // detik
    }

    // ----------------------------------------------------------
    // KIRIM payload ke Perusahaan 2
    // Return: array ['success' => bool, 'no_jurnal' => '...', 'message' => '...']
    // ----------------------------------------------------------
    public function kirim(NotaKayu $nota, array $payload): array
    {
        // Jangan kirim ulang jika sudah pernah berhasil
        if ($nota->is_synced) {
            return [
                'success'   => true,
                'no_jurnal' => $nota->sync_jurnal_no,
                'message'   => 'Sudah pernah di-sync sebelumnya.',
                'skipped'   => true,
            ];
        }

        try {
            $response = Http::withToken($this->apiToken)
                ->timeout($this->timeout)
                ->post("{$this->baseUrl}/api/jurnal/store", $payload);

            // Perusahaan 2 harus return JSON: { success: true, no_jurnal: 'J-0044' }
            if ($response->successful()) {
                $json = $response->json();

                // Update flag di Perusahaan 1
                $nota->update([
                    'is_synced'       => true,
                    'synced_at'       => now(),
                    'sync_jurnal_no'  => $json['no_jurnal'] ?? null,
                    'sync_error'      => null,
                ]);

                Log::info("[JurnalSync] Berhasil", [
                    'nota'      => $nota->no_nota,
                    'no_jurnal' => $json['no_jurnal'] ?? '-',
                ]);

                return [
                    'success'   => true,
                    'no_jurnal' => $json['no_jurnal'] ?? null,
                    'message'   => 'Jurnal berhasil dikirim ke Perusahaan 2.',
                ];
            }

            // HTTP error (4xx / 5xx)
            $errorMsg = $response->json('message') ?? $response->body();

            $nota->update(['sync_error' => $errorMsg]);

            Log::error("[JurnalSync] HTTP Error", [
                'nota'   => $nota->no_nota,
                'status' => $response->status(),
                'body'   => $errorMsg,
            ]);

            return [
                'success' => false,
                'message' => "HTTP {$response->status()}: {$errorMsg}",
            ];
        } catch (\Exception $e) {
            $nota->update(['sync_error' => $e->getMessage()]);

            Log::error("[JurnalSync] Exception", [
                'nota'  => $nota->no_nota,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }
}
