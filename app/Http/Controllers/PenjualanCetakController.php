<?php

namespace App\Http\Controllers;

use App\Models\DetailPenjualan;
use App\Models\Penjualan;
use App\Models\RekeningPerusahaan;
use App\Services\Akuntansi\PenjualanSyncService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PenjualanCetakController extends Controller
{
    /**
     * Helper resolve items dan penamaan untuk nota kantor dan nota sales.
     */
    public function resolvePenjualanItems(Penjualan $record, string $jenisNota = 'kantor'): array
    {
        $record->load(['pembuat', 'validator', 'rekeningPerusahaan', 'detail']);

        $items = [];
        $grandTotal = 0;

        foreach ($record->detail as $detail) {
            $satuan = $detail->satuan ?? 'lembar';
            $qty = (float) ($detail->jumlah ?? 0);
            $harga = (float) ($detail->harga ?? 0);
            $potongan = (float) ($detail->potongan ?? 0);
            $m3 = $detail->custom_m3 !== null ? (float) $detail->custom_m3 : null;

            $namaBarang = $detail->nama_barang;
            if ($jenisNota === 'sales' && !empty(trim($detail->custom_nama ?? ''))) {
                $namaBarang = trim($detail->custom_nama);
            }

            // Hitung subtotal: ($qty * $harga) - ($qty * $potongan)
            $subtotal = ($m3 !== null ? ($m3 * $harga) : ($qty * $harga)) - ($qty * $potongan);
            if ($subtotal < 0) {
                $subtotal = 0;
            }
            $grandTotal += $subtotal;

            $items[] = (object) [
                'id_detail'   => $detail->id,
                'nama_barang' => $namaBarang,
                'satuan'      => $satuan,
                'qty'         => $qty,
                'm3'          => $m3,
                'harga'       => $harga,
                'potongan'    => $potongan,
                'total_pot'   => $potongan * $qty,
                'subtotal'    => $subtotal,
                'keterangan'  => $detail->keterangan ?? '',
            ];
        }

        return [$items, $grandTotal];
    }

    /**
     * Preview Nota & Input Harga / Pembayaran oleh Orang Kantor
     */
    public function preview(Penjualan $record, string $jenis)
    {
        $jenis = strtolower($jenis) === 'sales' ? 'sales' : 'kantor';
        [$items, $grandTotal] = $this->resolvePenjualanItems($record, $jenis);
        $rekeningList = RekeningPerusahaan::all();

        return view('penjualan.preview', [
            'record'        => $record,
            'jenis'         => $jenis,
            'items'         => $items,
            'grandTotal'    => $grandTotal,
            'rekeningList'  => $rekeningList,
        ]);
    }

    /**
     * Update harga, potongan, nama custom barang oleh Orang Kantor
     */
    public function updateItems(Request $request, Penjualan $record)
    {
        $itemsData = $request->input('items', []);

        foreach ($itemsData as $id => $data) {
            $detail = DetailPenjualan::find($id);
            if ($detail && $detail->penjualan_id == $record->id) {
                if (isset($data['nama_barang'])) {
                    if ($request->input('jenis') === 'sales') {
                        $detail->custom_nama = $data['nama_barang'];
                    } else {
                        $detail->nama_barang = $data['nama_barang'];
                    }
                }
                if (isset($data['harga'])) {
                    $hargaStr = str_replace('.', '', $data['harga']);
                    $hargaStr = str_replace(',', '.', $hargaStr);
                    $detail->harga = (float) $hargaStr;
                }
                if (isset($data['potongan'])) {
                    $potStr = str_replace('.', '', $data['potongan']);
                    $potStr = str_replace(',', '.', $potStr);
                    $detail->potongan = (float) $potStr;
                }

                $qty = (float) ($detail->jumlah ?? 0);
                $m3 = $detail->custom_m3 !== null ? (float) $detail->custom_m3 : null;
                $harga = (float) ($detail->harga ?? 0);
                $pot = (float) ($detail->potongan ?? 0);
                $detail->subtotal = max(0, ($m3 !== null ? ($m3 * $harga) : ($qty * $harga)) - ($qty * $pot));

                $detail->save();
            }
        }

        // Update total di tabel penjualans
        [$items, $grandTotal] = $this->resolvePenjualanItems($record, 'kantor');
        $record->update([
            'sub_total' => $grandTotal,
            'total'     => $grandTotal,
        ]);

        return redirect()->back()->with('success', 'Perubahan harga dan rincian barang berhasil disimpan.');
    }

    /**
     * Reset harga ke kosong / default
     */
    public function resetItems(Penjualan $record)
    {
        DetailPenjualan::where('penjualan_id', $record->id)
            ->update([
                'custom_nama' => null,
                'harga'       => null,
                'potongan'    => 0,
                'subtotal'    => 0,
            ]);

        $record->update([
            'sub_total' => 0,
            'total'     => 0,
        ]);

        return redirect()->back()->with('success', 'Berhasil mereset harga barang.');
    }

    /**
     * Simpan metode pembayaran & Cetak / Otomatis Buat Penjualan di Web Akuntansi
     */
    public function savePayment(Request $request, Penjualan $record)
    {
        $validated = $request->validate([
            'metode_pembayaran'      => 'required|string|in:Tunai,Transfer,Cek / Giro',
            'id_rekening_perusahaan' => 'nullable|integer|exists:rekening_perusahaan,id',
            'jenis'                  => 'required|string|in:kantor,sales',
            'cetak_action'           => 'required|string|in:nota,sj,semua',
        ]);

        [$items, $grandTotal] = $this->resolvePenjualanItems($record, $validated['jenis']);

        $record->update([
            'metode_pembayaran'      => $validated['metode_pembayaran'],
            'id_rekening_perusahaan' => $validated['metode_pembayaran'] === 'Transfer'
                ? $validated['id_rekening_perusahaan']
                : null,
            'sub_total'              => $grandTotal,
            'total'                  => $grandTotal,
            'divalidasi_oleh'        => $record->divalidasi_oleh ?? auth()->id(),
        ]);

        // Otomatis kirim / sinkronkan ke web Akuntansi
        try {
            $syncService = app(PenjualanSyncService::class);
            $syncResult = $syncService->kirimPenjualan($record, $items, $grandTotal);

            if ($syncResult['success']) {
                $record->update([
                    'status_sync_akuntansi' => 'synced',
                    'sync_response'         => json_encode($syncResult['data'] ?? []),
                ]);
            } else {
                $record->update([
                    'status_sync_akuntansi' => 'failed',
                    'sync_response'         => $syncResult['message'] ?? 'Error',
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('Gagal sinkronisasi penjualan ke Akuntansi: ' . $e->getMessage());
            $record->update([
                'status_sync_akuntansi' => 'failed',
                'sync_response'         => $e->getMessage(),
            ]);
        }

        if ($validated['cetak_action'] === 'nota') {
            $targetRoute = $validated['jenis'] === 'sales' ? 'penjualan.nota-sales' : 'penjualan.nota-kantor';
            return redirect()->route($targetRoute, $record);
        } elseif ($validated['cetak_action'] === 'sj') {
            return redirect()->route('penjualan.surat-jalan', ['record' => $record->id, 'jenis' => $validated['jenis']]);
        } else {
            return redirect()->route('penjualan.cetak-semua', ['record' => $record->id, 'jenis' => $validated['jenis']]);
        }
    }

    /**
     * Cetak Nota Kantor
     */
    public function printNotaKantor(Penjualan $record)
    {
        [$items, $grandTotal] = $this->resolvePenjualanItems($record, 'kantor');

        return view('penjualan.nota-kantor', [
            'record'     => $record,
            'items'      => $items,
            'grandTotal' => $grandTotal,
        ]);
    }

    /**
     * Cetak Nota Sales
     */
    public function printNotaSales(Penjualan $record)
    {
        [$items, $grandTotal] = $this->resolvePenjualanItems($record, 'sales');

        return view('penjualan.nota-sales', [
            'record'     => $record,
            'items'      => $items,
            'grandTotal' => $grandTotal,
        ]);
    }

    /**
     * Cetak Surat Jalan (SJ)
     */
    public function printSuratJalan(Penjualan $record, $jenis = 'kantor')
    {
        [$items, $grandTotal] = $this->resolvePenjualanItems($record, $jenis);

        return view('penjualan.surat-jalan', [
            'record'  => $record,
            'nota'    => $record,
            'items'   => $items,
            'details' => $record->detail,
            'jenis'   => $jenis,
        ]);
    }

    /**
     * Cetak Semua (SJ + Nota)
     */
    public function cetakSemua(Penjualan $record, $jenis = 'kantor')
    {
        [$items, $grandTotal] = $this->resolvePenjualanItems($record, $jenis);

        return view('penjualan.cetak-semua', [
            'record'     => $record,
            'nota'       => $record,
            'jenis'      => $jenis,
            'items'      => $items,
            'grandTotal' => $grandTotal,
            'details'    => $record->detail,
        ]);
    }
}
