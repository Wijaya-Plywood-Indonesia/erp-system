<?php

namespace App\Services\Akuntansi;

use App\Models\ReturPenjualan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ReturPenjualanSyncService
{
    /**
     * Kirim data retur penjualan ke web Akuntansi.
     */
    public function kirimRetur(ReturPenjualan $record): array
    {
        $rawUrl = (string) config('services.akuntansi.url', 'http://localhost:8080');
        if (!preg_match('#^https?://#i', $rawUrl)) {
            $rawUrl = 'http://' . $rawUrl;
        }
        $endpoint = rtrim($rawUrl, '/') . '/api/retur-penjualan/store';
        $apiKey = (string) config('services.akuntansi.key');

        $record->loadMissing(['details', 'penjualan']);

        $itemsPayload = [];
        $subtotalCalc = 0.0;

        foreach ($record->details as $item) {
            $qty = (float) ($item->qty ?? 0);
            $m3 = isset($item->custom_m3) && $item->custom_m3 !== null ? (float) $item->custom_m3 : null;
            $harga = (float) ($item->harga ?? 0);
            $potongan = (float) ($item->potongan ?? 0);

            $hitKbk = $item->hit_kbk ?? 'b';
            $hitungDari = ($hitKbk === 'm' && $m3 !== null && $m3 > 0) ? 'm3' : 'qty';
            $multiplier = ($hitungDari === 'm3') ? $m3 : $qty;

            $subtotal = (float) ($item->subtotal ?? (($multiplier * $harga) - $potongan));
            $subtotalCalc += $subtotal;

            $itemsPayload[] = [
                'penjualan_detail_id' => $item->detail_penjualan_id,
                'nama_barang' => $item->nama_barang,
                'satuan' => $item->satuan ?? 'pcs',
                'qty' => $qty,
                'm3' => $m3,
                'hit_kbk' => $hitKbk,
                'hitung_dari' => $hitungDari,
                'harga_awal' => $harga,
                'harga_jual' => $harga,
                'potongan' => $potongan,
                'subtotal' => $subtotal,
                'keterangan' => $item->keterangan ?? null,
            ];
        }

        $metodePembayaran = $record->metode_pembayaran ?: ($record->penjualan?->metode_pembayaran ?: 'Tunai');
        $subTotal = (float) ($record->sub_total > 0 ? $record->sub_total : $subtotalCalc);
        $ppnNominal = (float) ($record->ppn_nominal ?? 0);
        $total = (float) ($record->total > 0 ? $record->total : ($subTotal + $ppnNominal));

        // Cari apakah ada ID penjualan di respon sync penjualan asal
        $penjualanIdAkuntansi = null;
        if ($record->penjualan && !empty($record->penjualan->sync_response)) {
            $syncData = json_decode($record->penjualan->sync_response, true);
            $penjualanIdAkuntansi = $syncData['data']['id'] ?? ($syncData['id'] ?? null);
        }

        $payload = [
            'no_retur' => $record->no_retur,
            'no_nota' => $record->no_nota,
            'penjualan_id_akuntansi' => $penjualanIdAkuntansi,
            'tanggal' => $record->tanggal ? $record->tanggal->format('Y-m-d') : date('Y-m-d'),
            'nama_customer' => $record->tujuan_nota,
            'alamat' => $record->alamat,
            'metode_pembayaran' => $metodePembayaran,
            'akun_pengembalian' => $record->akun_pengembalian,
            'kendaraan' => $record->kendaraan,
            'plat_kendaraan' => $record->plat_kendaraan,
            'nama_sopir' => $record->nama_sopir,
            'sub_total' => $subTotal,
            'ppn_nominal' => $ppnNominal,
            'total' => $total,
            'keterangan' => $record->keterangan,
            'items' => $itemsPayload,
        ];

        try {
            $response = Http::withoutVerifying()
                ->timeout(15)
                ->withHeaders([
                    'X-API-KEY' => $apiKey,
                    'Accept' => 'application/json',
                ])
                ->post($endpoint, $payload);

            if ($response->successful()) {
                Log::info('Berhasil sinkronisasi retur penjualan ke Akuntansi: ' . $record->no_retur, $response->json());
                $record->update([
                    'status_sync_akuntansi' => 'synced',
                    'sync_response' => json_encode($response->json()),
                ]);

                return [
                    'success' => true,
                    'message' => 'Berhasil mengirim retur penjualan ke Akuntansi',
                    'data' => $response->json(),
                ];
            }

            $errorMessage = $response->json('message') ?? $response->body();
            Log::error('Gagal sinkronisasi retur penjualan ke Akuntansi: ' . $record->no_retur, [
                'status' => $response->status(),
                'response' => $errorMessage,
            ]);

            $record->update([
                'status_sync_akuntansi' => 'failed',
                'sync_response' => is_string($errorMessage) ? $errorMessage : json_encode($errorMessage),
            ]);

            return [
                'success' => false,
                'message' => 'Gagal ke Akuntansi (' . $response->status() . '): ' . (is_string($errorMessage) ? $errorMessage : json_encode($errorMessage)),
            ];
        } catch (\Throwable $e) {
            Log::error('Exception saat sinkronisasi retur penjualan: ' . $e->getMessage());

            $record->update([
                'status_sync_akuntansi' => 'failed',
                'sync_response' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Koneksi ke Akuntansi gagal: ' . $e->getMessage(),
            ];
        }
    }
}
