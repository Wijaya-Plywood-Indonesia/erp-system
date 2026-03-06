<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ProduksiDryerApiService
{
    // Ganti dengan URL tujuan yang sebenarnya nanti
    // Untuk testing, pakai webhook.site dulu
    protected string $endpointUrl;

    public function __construct()
    {
        $this->endpointUrl = config('services.produksi_api.url');
    }

    public function kirimData(int $idProduksi): array
    {
        // 1. Ambil semua data yang dibutuhkan dari database
        $produksi = \App\Models\ProduksiPressDryer::with([
            'detailMesins',
            'detailMasuks',
            'detailHasils',
            'validasis',
        ])->findOrFail($idProduksi);

        // 2. Susun struktur JSON
        $payload = [
            'produksi' => [
                'id' => $produksi->id,
                'tanggal_produksi' => $produksi->tanggal_produksi,
                'shift' => $produksi->shift,
                'kendala' => $produksi->kendala,
            ],
            'detail_mesin' => $produksi->detailMesins->map(fn($m) => [
                'id_mesin_dryer' => $m->id_mesin_dryer,
                'jam_kerja_mesin' => $m->jam_kerja_mesin,
            ]),
            'detail_masuk' => $produksi->detailMasuks->map(fn($m) => [
                'no_palet' => $m->no_palet,
                'kw' => $m->kw,
                'isi' => $m->isi,
                'id_kayu_masuk' => $m->id_kayu_masuk,
                'id_jenis_kayu' => $m->id_jenis_kayu,
            ]),
            'detail_hasil' => $produksi->detailHasils->map(fn($h) => [
                'no_palet' => $h->no_palet,
                'kw' => $h->kw,
                'isi' => $h->isi,
                'id_kayu_masuk' => $h->id_kayu_masuk,
                'id_jenis_kayu' => $h->id_jenis_kayu,
            ]),
            'validasi' => $produksi->validasis->map(fn($v) => [
                'role' => $v->role,
                'status' => $v->status,
            ]),
        ];

        // 3. Kirim via HTTP POST
        try {
            $response = Http::timeout(10)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post($this->endpointUrl, $payload);

            Log::info('API Produksi Dryer dikirim', [
                'id_produksi' => $idProduksi,
                'status_code' => $response->status(),
            ]);

            return [
                'success' => $response->successful(),
                'status_code' => $response->status(),
                'response' => $response->json() ?? $response->body(),
            ];

        } catch (\Exception $e) {
            Log::error('Gagal kirim API Produksi Dryer', [
                'id_produksi' => $idProduksi,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
}