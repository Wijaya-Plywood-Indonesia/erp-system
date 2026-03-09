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
// CATATAN: Tidak menyimpan apapun ke DB Perusahaan 1.
//          Nomor jurnal dari P2 hanya ditampilkan di notifikasi Filament.
// ============================================================

class JurnalSyncService
{
    private string $baseUrl;
    private string $apiToken;
    private int    $timeout;

    public function __construct()
    {
        $this->baseUrl  = rtrim(config('services.akuntansi.url', ''), '/');
        $this->apiToken = config('services.akuntansi.token', '');
        $this->timeout  = 30;
    }

    // ----------------------------------------------------------
    // KIRIM payload ke Perusahaan 2
    //
    // Return array:
    //   Berhasil  → ['success' => true,  'no_jurnal' => 2,       'message' => '...']
    //   Duplikat  → ['success' => true,  'no_jurnal' => 1,       'message' => '...', 'duplicate' => true]
    //   Gagal     → ['success' => false, 'no_jurnal' => null,    'message' => '...']
    // ----------------------------------------------------------
    public function kirim(NotaKayu $nota, array $payload): array
    {
        // Validasi konfigurasi sebelum kirim
        if (empty($this->baseUrl) || empty($this->apiToken)) {
            Log::error('[JurnalSync] Konfigurasi tidak lengkap', [
                'baseUrl'  => $this->baseUrl,
                'hasToken' => ! empty($this->apiToken),
            ]);

            return [
                'success'   => false,
                'no_jurnal' => null,
                'message'   => 'Konfigurasi PERUSAHAAN2_URL atau PERUSAHAAN2_API_TOKEN belum diisi di .env',
            ];
        }

        try {
            $response = Http::withToken($this->apiToken)
                ->withHeaders([
                    'Accept'       => 'application/json',
                    'Content-Type' => 'application/json',
                ])
                ->timeout($this->timeout)
                ->post("{$this->baseUrl}/api/jurnal/store", $payload);

            $json = $response->json();

            // ── Berhasil (201) atau Duplikat (200) ─────────────
            if ($response->successful()) {

                $noJurnal = $json['no_jurnal'] ?? null;
                $isDuplikat = $json['duplicate'] ?? false;

                Log::info('[JurnalSync] ' . ($isDuplikat ? 'Duplikat' : 'Berhasil'), [
                    'no_nota'   => $nota->no_nota,
                    'no_jurnal' => $noJurnal,
                ]);

                return [
                    'success'   => true,
                    'no_jurnal' => $noJurnal,
                    'duplicate' => $isDuplikat,
                    'message'   => $isDuplikat
                        ? "Nota ini sudah pernah di-sync sebelumnya. Nomor Jurnal: {$noJurnal}"
                        : "Jurnal berhasil dikirim ke Perusahaan 2. Nomor Jurnal: {$noJurnal}",
                ];
            }

            // ── HTTP Error (4xx / 5xx) ──────────────────────────
            $errorMsg = $json['message'] ?? $response->body();

            Log::error('[JurnalSync] HTTP Error', [
                'no_nota' => $nota->no_nota,
                'status'  => $response->status(),
                'body'    => $errorMsg,
            ]);

            return [
                'success'   => false,
                'no_jurnal' => null,
                'message'   => "HTTP {$response->status()}: {$errorMsg}",
            ];
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            // Server P2 tidak bisa dijangkau
            Log::error('[JurnalSync] Koneksi gagal', [
                'no_nota' => $nota->no_nota,
                'error'   => $e->getMessage(),
            ]);

            return [
                'success'   => false,
                'no_jurnal' => null,
                'message'   => 'Tidak dapat terhubung ke server Perusahaan 2. Pastikan server sedang berjalan.',
            ];
        } catch (\Exception $e) {
            Log::error('[JurnalSync] Exception', [
                'no_nota' => $nota->no_nota,
                'error'   => $e->getMessage(),
            ]);

            return [
                'success'   => false,
                'no_jurnal' => null,
                'message'   => $e->getMessage(),
            ];
        }
    }
}
