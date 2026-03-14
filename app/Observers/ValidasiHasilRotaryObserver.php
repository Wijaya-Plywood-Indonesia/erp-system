<?php

namespace App\Observers;

use App\Models\ValidasiHasilRotary;
use App\Models\ProduksiRotary;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ValidasiHasilRotaryObserver
{
    public function saved(ValidasiHasilRotary $validasi): void
    {
        if (!in_array($validasi->status, ['divalidasi', 'disetujui'])) {
            return;
        }

        if (!$validasi->id_produksi) {
            return;
        }

        Log::info("ValidasiObserver: Status '{$validasi->status}' pada id_produksi={$validasi->id_produksi}. Trigger jurnal check.");

        $this->triggerJurnalCheck($validasi->id_produksi);
    }

    private function triggerJurnalCheck(int $idProduksi): void
    {
        try {
            $service  = app(\App\Services\Akuntansi\RotaryJurnalService::class);
            $produksi = ProduksiRotary::find($idProduksi);

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

            Log::info("ValidasiObserver: Semua mesin sudah divalidasi (tanggal={$tanggal}). Mengirim ke akuntansi...", [
                'total_items'  => count($payload['jurnal_items']),
                'total_debit'  => $payload['jurnal_header']['total_debit'],
                'total_kredit' => $payload['jurnal_header']['total_kredit'],
                'is_balance'   => $payload['jurnal_header']['is_balance'],
            ]);

            // ── Ambil produksiList dengan eager load lengkap ──────────────────
            // (buildJurnalPayload sudah load ini, tapi kita butuh untuk tambahStok)
            $produksiList = ProduksiRotary::with([
                'mesin',
                'detailValidasiHasilRotary',
                'detailPegawaiRotary.pegawai',
                'detailLahanRotary.lahan',
                'detailLahanRotary.jenisKayu',
                'detailPaletRotary.ukuran',
                'detailPaletRotary.penggunaanLahan.lahan',
                'bahanPenolongRotary.bahanPenolong',
                'detailKayuPecah.penggunaanLahan',
            ])->whereDate('tgl_produksi', $tanggal)->get();

            // ── Kirim ke web akuntansi ────────────────────────────────────────
            $this->sendToAkuntansi($payload, $tanggal, $produksiList, $service);

        } catch (\Throwable $e) {
            Log::error("ValidasiObserver: Error saat trigger jurnal check: " . $e->getMessage());
            Log::error($e->getTraceAsString());
        }
    }

    private function sendToAkuntansi(array $payload, string $tanggal, $produksiList, $service): void
    {
        $url    = rtrim(config('services.akuntansi.url', 'http://192.168.1.2:5000'), '/')
                  . '/api/jurnal/rotary/create';
        $apiKey = config('services.akuntansi.key', '');

        try {
            $response = Http::timeout(30)
                ->withoutVerifying()
                ->withHeaders([
                    'X-API-KEY' => $apiKey,
                    'Accept'    => 'application/json',
                ])
                ->post($url, $payload);

            if ($response->successful()) {
                Log::info("ValidasiObserver: Jurnal berhasil dikirim ke akuntansi (tanggal={$tanggal}).", [
                    'jurnal'        => $response->json('data.jurnal'),
                    'jumlah_header' => $response->json('data.jumlah_header'),
                    'jumlah_items'  => $response->json('data.jumlah_items'),
                ]);

                // ── Tambah stok veneer basah DULU (butuh stok kayu untuk hitung HPP)
                // ── Baru kurangi stok HPP kayu setelahnya ────────────────────
                $service->tambahStokVeneerBasah($produksiList, $tanggal);
                $service->kurangiStokHpp($produksiList, $tanggal);

            } elseif ($response->status() === 409) {
                Log::warning("ValidasiObserver: Jurnal {$tanggal} sudah ada di akuntansi (duplikasi). Dilewati.");

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