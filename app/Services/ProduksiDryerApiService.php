<?php

namespace App\Services;

use App\Models\ProduksiPressDryer;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ProduksiDryerApiService
{
    public function kirimData(int $idProduksi): array
    {
        $produksi = ProduksiPressDryer::with([
            'detailMesins',
            'detailMasuks',
            'detailHasils',
            'validasiPressDryers',  // ← fix: bukan 'validasis'
            'detailPegawais',       // ← tambahan
        ])->findOrFail($idProduksi);

        $payload = [
            'produksi' => [
                'id' => $produksi->id,
                'tanggal_produksi' => $produksi->tanggal_produksi,
                'shift' => $produksi->shift,
                'kendala' => $produksi->kendala,
            ],
            'detail_mesin' => ($produksi->detailMesins ?? collect())->map(fn($m) => [
                'id_mesin_dryer' => $m->id_mesin_dryer,
                'jam_kerja_mesin' => $m->jam_kerja_mesin,
            ])->values(),

            'detail_masuk' => ($produksi->detailMasuks ?? collect())->map(fn($m) => [
                'no_palet' => $m->no_palet,
                'kw' => $m->kw,
                'isi' => $m->isi,
                'id_kayu_masuk' => $m->id_kayu_masuk,
                'id_jenis_kayu' => $m->id_jenis_kayu,
            ])->values(),

            'detail_hasil' => ($produksi->detailHasils ?? collect())->map(fn($h) => [
                'no_palet' => $h->no_palet,
                'kw' => $h->kw,
                'isi' => $h->isi,
                'id_kayu_masuk' => $h->id_kayu_masuk,
                'id_jenis_kayu' => $h->id_jenis_kayu,
            ])->values(),

            'validasi' => ($produksi->validasiPressDryers ?? collect())->map(fn($v) => [
                'role' => $v->role,
                'status' => $v->status,
            ])->values(),

            'detail_pegawai' => ($produksi->detailPegawais ?? collect())->map(fn($p) => [
                'id_pegawai' => $p->id_pegawai ?? null,
                // sesuaikan kolom dengan tabel detail_pegawais kamu
            ])->values(),
        ];

        try {
            $response = Http::timeout(10)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post(config('services.produksi_api.url'), $payload);

            Log::info('Kirim data produksi dryer', [
                'id' => $idProduksi,
                'status_code' => $response->status(),
            ]);

            return [
                'success' => $response->successful(),
                'status_code' => $response->status(),
            ];

        } catch (\Exception $e) {
            Log::error('Gagal kirim data produksi dryer', [
                'id' => $idProduksi,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
}