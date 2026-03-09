<?php

namespace App\Services;

use App\Models\ProduksiPressDryer;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ProduksiDryerApiService
{
    // ⭐ Method 1: Hanya susun data (untuk preview di Postman)
    public function getPayload(int $idProduksi): array
    {
        $produksi = ProduksiPressDryer::with([
            'detailMesins.mesin',
            'detailMasuks.jenisKayu',    // ← relasi jenisKayu() ada di DetailMasuk
            'detailMasuks.ukuran',       // ← relasi ukuran() ada di DetailMasuk
            'detailHasils.jenisKayu',    // ← relasi jenisKayu() ada di DetailHasil
            'detailHasils.ukuran',       // ← relasi ukuran() ada di DetailHasil
            'validasiPressDryers',
            'detailPegawais.pegawai',    // ← relasi pegawai() ada di DetailPegawai
        ])->findOrFail($idProduksi);

        return [
            'produksi' => [
                'id' => $produksi->id,
                'tanggal_produksi' => $produksi->tanggal_produksi?->format('Y-m-d'),
                'shift' => $produksi->shift,
                'kendala' => $produksi->kendala,
            ],

            'detail_mesin' => ($produksi->detailMesins ?? collect())->map(fn($m) => [
                'id_mesin_dryer' => $m->id_mesin_dryer,
                'nama_mesin' => $m->mesin?->nama_mesin,
                'jam_kerja_mesin' => $m->jam_kerja_mesin,
            ])->values(),

            // ✅ fix: id_ukuran (bukan id_kayu_masuk)
            'detail_masuk' => ($produksi->detailMasuks ?? collect())->map(fn($m) => [
                'no_palet' => $m->no_palet,
                'kw' => $m->kw,
                'isi' => $m->isi,
                'ukuran' => $m->ukuran
                    ? "{$m->ukuran->panjang} x {$m->ukuran->lebar} x {$m->ukuran->tebal}"
                    : null,
                'jenis_kayu' => $m->jenisKayu?->nama_kayu,
            ])->values(),

            // ✅ fix: id_ukuran (bukan id_kayu_masuk)
            'detail_hasil' => ($produksi->detailHasils ?? collect())->map(fn($h) => [
                'no_palet' => $h->no_palet,
                'kw' => $h->kw,
                'isi' => $h->isi,
                'ukuran' => $h->ukuran
                    ? "{$h->ukuran->panjang} x {$h->ukuran->lebar} x {$h->ukuran->tebal}"
                    : null,
                'jenis_kayu' => $h->jenisKayu?->nama_kayu,
            ])->values(),


            'validasi' => ($produksi->validasiPressDryers ?? collect())->map(fn($v) => [
                'role' => $v->role,
                'status' => $v->status,
            ])->values(),

            // ✅ fix: kolom lengkap sesuai model DetailPegawai
            'detail_pegawai' => ($produksi->detailPegawais ?? collect())->map(fn($p) => [
                'kode_pegawai' => $p->pegawai?->kode_pegawai,
                'nama_pegawai' => $p->pegawai?->nama_pegawai,  // kolom: nama_pegawai
                'tugas' => $p->tugas,
                'masuk' => $p->masuk,
                'pulang' => $p->pulang,
                'ijin' => $p->ijin,
                'ket' => $p->ket,
            ])->values(),
        ];
    }

    // ⭐ Method 2: Kirim data ke web tujuan
    public function kirimData(int $idProduksi): array
    {
        $payload = $this->getPayload($idProduksi);

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