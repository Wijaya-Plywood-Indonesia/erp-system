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
        $pakaiPpn = (bool) ($record->pakai_ppn ?? false);
        $rawPpnPersen = (float) ($record->ppn_persen ?? 0);
        $ppnPersen = $pakaiPpn ? ($rawPpnPersen > 0 ? $rawPpnPersen : 11.0) : 0.0;

        foreach ($resolvedItems as $item) {
            $qty = (float) ($item->qty ?? 0);
            $m3 = isset($item->m3) && $item->m3 !== null ? (float) $item->m3 : null;
            $harga = (float) ($item->harga ?? 0);
            $potongan = (float) ($item->potongan ?? 0);
            // Nilai subtotal langsung mengambil hasil hitung resmi dari ERP (sudah presisi DPP jika PPN ON)
            $subtotal = (float) ($item->subtotal ?? ($qty * $harga));

            $hitKbk = $item->hit_kbk ?? 'b';
            $hitungDari = ($hitKbk === 'm' && $m3 !== null) ? 'm3' : 'qty';

            // Prioritaskan nama_barang_asli (mengandung tebal & grade asli dari ERP)
            // agar Akuntansi dapat memetakan ke master barang yang tepat.
            $namaBarangKirim = !empty(trim($item->nama_barang_asli ?? ''))
                ? trim($item->nama_barang_asli)
                : $item->nama_barang;

            $itemsPayload[] = [
                'nama_barang' => $namaBarangKirim,
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

        // Metode pembayaran ERP (Tunai / Transfer / Cek / Giro) → normalize ke TUNAI atau TRANSFER
        // untuk dikirim ke web Akuntansi yang hanya mengenal TUNAI / TRANSFER.
        $rawMetode = strtolower(trim($record->metode_pembayaran ?? ''));
        if (str_contains($rawMetode, 'transfer')) {
            $metodePembayaran = 'TRANSFER';
        } else {
            // Tunai, Cek / Giro, atau apapun selain transfer → TUNAI
            $metodePembayaran = 'TUNAI';
        }

        // Subtotal kiriman ke akuntansi adalah grandTotal barang dari ERP (DPP saat PPN ON)
        $subTotalFinal = (float) $grandTotal;
        $ppnNominal = (float) ($record->ppn_nominal ?? 0);

        // Jika pakai PPN, hitung atau gunakan PPN nominal resmi dari ERP
        if ($pakaiPpn && $ppnPersen > 0) {
            if ($ppnNominal <= 0) {
                $ppnNominal = round(($subTotalFinal * $ppnPersen) / 100, 2);
            }
        } else {
            $ppnNominal = 0.0;
        }

        $ongkir = (float) ($record->ongkir ?? 0);
        $totalAkhir = round($subTotalFinal + $ppnNominal + $ongkir, 2);

        // Pastikan header record di ERP juga selalu ter-update sinkron jika ada perbedaan
        if (abs((float)$record->total - $totalAkhir) > 0.01 || abs((float)$record->sub_total - $subTotalFinal) > 0.01) {
            $record->update([
                'sub_total'   => $subTotalFinal,
                'ppn_nominal' => $ppnNominal,
                'total'       => $totalAkhir,
            ]);
        }

        // Semua penjualan dari ERP masuk sebagai COD ke Akuntansi:
        // - metode_pembayaran di ERP hanya menentukan DI MANA nantinya uang dibayarkan
        //   (tunai / transfer), tapi belum berarti uang sudah diterima.
        // - Pembayaran aktual diproses di web Akuntansi lewat menu Pelunasan Penjualan.
        // - jenis_transaksi = 'COD', status_transaksi = 'BELUM DIBAYAR', bayar = 0.
        $payload = [
            'no_nota' => $record->no_nota,
            'tanggal' => $record->tanggal ? $record->tanggal->format('Y-m-d') : date('Y-m-d'),
            'nama_customer' => $record->tujuan_nota,
            'alamat' => $record->alamat,
            'npwp' => $record->pembeli_bkp_npwp,
            'metode_pembayaran' => $metodePembayaran,
            'jenis_transaksi' => 'COD',
            'bank' => $rekening?->nama_bank,
            'no_rekening' => $rekening?->no_rekening,
            'sub_total' => $subTotalFinal,
            'ppn_persen' => $ppnPersen,
            'ppn_nominal' => $ppnNominal,
            'ongkir' => $ongkir,
            'total' => $totalAkhir,
            'bayar' => 0,
            'kembalian' => 0,
            'status_transaksi' => 'BELUM DIBAYAR',
            'keterangan_pembayaran' => $record->metode_pembayaran
                ? 'Metode bayar ERP: ' . $record->metode_pembayaran
                : null,
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
