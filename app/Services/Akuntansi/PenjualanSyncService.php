<?php

namespace App\Services\Akuntansi;

use App\Models\NotaBarangKeluar;
use App\Models\Penjualan;
use App\Models\RekeningPerusahaan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PenjualanSyncService
{
    /**
     * Kirim data penjualan/nota barang keluar ke web Akuntansi.
     */
    public function kirimPenjualan(NotaBarangKeluar|Penjualan $record, array $resolvedItems, float $grandTotal): array
    {
        $rawUrl = (string) config('services.akuntansi.url', 'http://localhost:8080');
        if (!preg_match('#^https?://#i', $rawUrl)) {
            $rawUrl = 'http://' . $rawUrl;
        }
        $endpoint = rtrim($rawUrl, '/') . '/api/penjualan/store';
        $apiKey = (string) config('services.akuntansi.key');

        $rekening = null;
        if ($record->id_rekening_perusahaan) {
            $rekening = RekeningPerusahaan::find($record->id_rekening_perusahaan);
        }

        $itemsPayload = [];
        foreach ($resolvedItems as $item) {
            $qty = (float) ($item->qty ?? 0);
            $m3 = isset($item->m3) && $item->m3 !== null ? (float) $item->m3 : null;
            $harga = (float) ($item->harga ?? 0);
            $potongan = (float) ($item->potongan ?? 0);
            $subtotal = (float) ($item->subtotal ?? ($qty * $harga));

            $itemsPayload[] = [
                'nama_barang' => $item->nama_barang,
                'satuan' => $item->satuan ?? 'pcs',
                'qty' => $qty,
                'm3' => $m3,
                'hitung_dari' => $m3 !== null ? 'kubikasi' : 'qty',
                'harga_awal' => $harga,
                'harga_jual' => $harga,
                'potongan' => $potongan,
                'subtotal' => $subtotal,
                'keterangan' => $item->keterangan ?? null,
            ];
        }

        $metodePembayaran = $record->metode_pembayaran ?: 'Tunai';

        $payload = [
            'no_nota' => $record->no_nota,
            'tanggal' => $record->tanggal ? $record->tanggal->format('Y-m-d') : date('Y-m-d'),
            'nama_customer' => $record->tujuan_nota,
            'alamat' => $record->alamat,
            'metode_pembayaran' => $metodePembayaran,
            'jenis_transaksi' => 'BAYAR_DIMUKA',
            'bank' => $rekening?->nama_bank,
            'no_rekening' => $rekening?->no_rekening,
            'sub_total' => $grandTotal,
            'ppn_persen' => 0,
            'ppn_nominal' => 0,
            'total' => $grandTotal,
            'bayar' => $grandTotal,
            'kembalian' => 0,
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
                Log::info('Berhasil sinkronisasi penjualan ke Akuntansi: ' . $record->no_nota, $response->json());
                return [
                    'success' => true,
                    'message' => 'Berhasil mengirim penjualan ke Akuntansi',
                    'data' => $response->json(),
                ];
            }

            Log::error('Gagal sinkronisasi penjualan ke Akuntansi (' . $record->no_nota . '): ' . $response->body());
            return [
                'success' => false,
                'message' => 'Akuntansi merespon error (' . $response->status() . '): ' . ($response->json('message') ?? $response->body()),
            ];
        } catch (\Throwable $e) {
            Log::error('Exception sinkronisasi penjualan ke Akuntansi: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Koneksi ke server Akuntansi gagal: ' . $e->getMessage(),
            ];
        }
    }
}
