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
     * Helper formatting Rupiah / Angka: jika ada desimal tampilkan 2 angka di belakang koma, jika bulat tampilkan tanpa desimal.
     */
    public static function formatRupiah($val): string
    {
        $floatVal = (float) $val;
        return (abs($floatVal - round($floatVal)) > 0.0001)
            ? number_format($floatVal, 2, ',', '.')
            : number_format($floatVal, 0, ',', '.');
    }

    /**
     * Helper parsing input string harga/rupiah (mendukung '117.117,12', '117,117.12', '117117,12', '117117.12')
     */
    public static function parseRupiahInput($val): float
    {
        if (is_numeric($val)) {
            return (float) $val;
        }
        $val = trim((string) $val);
        if ($val === '') {
            return 0.0;
        }

        if (strpos($val, ',') !== false && strpos($val, '.') !== false) {
            if (strrpos($val, ',') > strrpos($val, '.')) {
                $val = str_replace('.', '', $val);
                $val = str_replace(',', '.', $val);
            } else {
                $val = str_replace(',', '', $val);
            }
        } elseif (strpos($val, ',') !== false) {
            $val = str_replace(',', '.', $val);
        }

        return (float) $val;
    }

    /**
     * Hitung subtotal presisi tinggi (mendukung presisi desimal DPP Excel saat PPN ON).
     */
    public static function calculateItemSubtotal(float $qty, float $harga, float $potongan = 0, ?float $m3 = null, string $hitKbk = 'b', float $ppnPersen = 11.0, bool $pakaiPpn = true): float
    {
        $multiplier = ($hitKbk === 'm' && $m3 !== null) ? $m3 : $qty;

        // Jika PPN ON: cari tahu apakah harga adalah DPP dari harga include (rumus Excel: Harga Gross * 100 / 111)
        if ($pakaiPpn) {
            $factors = array_unique([1 + ($ppnPersen / 100), 1.11, 1.12]);
            foreach ($factors as $f) {
                if ($f <= 1) continue;
                $inc = $harga * $f;
                $roundInc = round($inc);
                // Toleransi perbedaan pecahan pembulatan harga DPP (misal 117.117,12 * 1.11 = 130.000,0032)
                if (abs($inc - $roundInc) < 0.05) {
                    $baseSub = round(($multiplier * $roundInc) / $f, 2);
                    return max(0, $baseSub - ($qty * $potongan));
                }
            }
        }

        return max(0, round(($multiplier * $harga) - ($qty * $potongan), 2));
    }

    /**
     * Helper resolve items dan penamaan untuk nota kantor dan nota sales.
     */
    public function resolvePenjualanItems(Penjualan $record, string $jenisNota = 'kantor'): array
    {
        $record->load(['pembuat', 'validator', 'rekeningPerusahaan', 'detail.ukuran', 'detail.jenisKayu']);

        $items = [];
        $grandTotal = 0;

        foreach ($record->detail as $detail) {
            $satuan = $detail->satuan ?? 'lembar';
            $qty = (float) ($detail->jumlah ?? 0);
            $harga = (float) ($detail->harga ?? 0);
            $potongan = (float) ($detail->potongan ?? 0);
            $m3 = $detail->custom_m3 !== null ? (float) $detail->custom_m3 : null;

            // Helper untuk mengambil data tebal secara dinamis
            $tebalVal = null;
            if (isset($detail->ukuran) && filled($detail->ukuran?->tebal)) {
                $tebalVal = $detail->ukuran->tebal;
            } elseif (!empty($detail->ukuran_key)) {
                $parts = explode('|', $detail->ukuran_key);
                if (isset($parts[2])) {
                    $tebalVal = $parts[2];
                }
            } elseif (!empty($detail->nama_barang)) {
                if (preg_match('/(\d+(?:[\.,]\d+)?)\s*(?:mm|m)?\b/i', $detail->nama_barang, $matches)) {
                    $tebalVal = str_replace(',', '.', $matches[1]);
                }
            }

            $tebalStr = null;
            if ($tebalVal !== null && $tebalVal !== '') {
                $tebalFormatted = (float) $tebalVal == (int) $tebalVal
                    ? (int) $tebalVal
                    : rtrim(rtrim((string) $tebalVal, '0'), '.');
                $tebalStr = "{$tebalFormatted} mm";
            }

            // Cari merek dan master BSJ HP jika ada
            $merek = null;
            if (!empty($detail->ukuran_key) && !empty($detail->id_jenis_kayu)) {
                [$a, $b, $tebal] = array_map('floatval', explode('|', $detail->ukuran_key));
                $namaKayu = $detail->jenisKayu?->nama_kayu;
                $gradeName = $detail->kw_grade;

                $matchedUkuranIds = \App\Models\Ukuran::where('tebal', $tebal)
                    ->where(function ($q) use ($a, $b) {
                        $q->where(fn ($s) => $s->where('panjang', $a)->where('lebar', $b))
                          ->orWhere(fn ($s) => $s->where('panjang', $b)->where('lebar', $a));
                    })
                    ->pluck('id');

                $jenisBarang = $namaKayu ? \App\Models\JenisBarang::where('nama_jenis_barang', 'like', $namaKayu)->first() : null;
                $gradeObj = $gradeName ? \App\Models\Grade::whereRaw('LOWER(TRIM(nama_grade)) = ?', [strtolower(trim($gradeName))])->first() : null;

                if ($matchedUkuranIds->isNotEmpty()) {
                    $bshp = \App\Models\BarangSetengahJadiHp::whereIn('id_ukuran', $matchedUkuranIds)
                        ->when($jenisBarang, fn($q) => $q->where('id_jenis_barang', $jenisBarang->id))
                        ->when($gradeObj, fn($q) => $q->where('id_grade', $gradeObj->id))
                        ->first();
                    $merek = $bshp?->merek;
                }
            }

            $gradeName = !empty(trim($detail->kw_grade ?? '')) ? trim($detail->kw_grade) : null;

            if ($jenisNota === 'sales') {
                // NOTA SALES: Jika ada custom_nama -> pakai custom_nama
                if (!empty(trim($detail->custom_nama ?? ''))) {
                    $namaBarang = trim($detail->custom_nama);
                } elseif (!empty(trim($merek ?? ''))) {
                    $namaBarang = $tebalStr !== null ? "{$tebalStr} {$merek}" : $merek;
                } elseif ($tebalStr !== null && $gradeName !== null) {
                    $namaBarang = "{$tebalStr} {$gradeName}";
                } elseif ($tebalStr !== null) {
                    $namaBarang = $tebalStr;
                } elseif ($gradeName !== null) {
                    $namaBarang = $gradeName;
                } else {
                    $namaBarang = $detail->nama_barang;
                }
            } else {
                // NOTA KANTOR & INVOICE RESMI:
                // Jika ada nama yang diedit/ditimpa (custom_nama atau nama_barang manual), prioritaskan itu
                if (!empty(trim($detail->custom_nama ?? ''))) {
                    $namaBarang = trim($detail->custom_nama);
                } elseif (!empty(trim($detail->nama_barang ?? ''))) {
                    $namaBarang = trim($detail->nama_barang);
                } elseif ($tebalStr !== null && $gradeName !== null) {
                    $namaBarang = "{$tebalStr} {$gradeName}";
                } elseif ($tebalStr !== null) {
                    $namaBarang = $tebalStr;
                } elseif ($gradeName !== null) {
                    $namaBarang = $gradeName;
                } else {
                    $namaBarang = $detail->nama_barang;
                }
            }

            $hitKbk = $detail->hit_kbk ?? 'b';
            $pakaiPpn = (bool) ($record->pakai_ppn ?? false);
            $ppnPersen = (float) ($record->ppn_persen ?: 11.0);

            // Hitung otomatis dengan presisi Excel atau gunakan dari database jika ada
            if ($detail->subtotal !== null && (float) $detail->subtotal > 0) {
                // Cek apakah subtotal yang tersimpan adalah perkalian biasa yang selisih dengan presisi Excel
                $excelSubtotal = self::calculateItemSubtotal($qty, $harga, $potongan, $m3, $hitKbk, $ppnPersen, $pakaiPpn);
                $storedSubtotal = (float) $detail->subtotal;
                $directSubtotal = max(0, (($hitKbk === 'm' && $m3 !== null ? $m3 : $qty) * $harga) - ($qty * $potongan));

                // Jika nilai tersimpan sama dengan perkalian kasar (ada selisih desimal dengan Excel), gunakan otomatis presisi Excel
                if (abs($storedSubtotal - $directSubtotal) < 0.05 && abs($storedSubtotal - $excelSubtotal) > 0.01) {
                    $subtotal = $excelSubtotal;
                } else {
                    $subtotal = $storedSubtotal;
                }
            } else {
                $subtotal = self::calculateItemSubtotal($qty, $harga, $potongan, $m3, $hitKbk, $ppnPersen, $pakaiPpn);
            }
            $grandTotal += $subtotal;

            // Susun nama master asli sistem untuk sinkronisasi ke Akuntansi (tanpa nama editan nota/merek)
            $namaMasterAsli = null;
            if ($tebalStr !== null && $gradeName !== null) {
                $namaMasterAsli = "{$tebalStr} {$gradeName}";
            } elseif ($tebalStr !== null) {
                $namaMasterAsli = $tebalStr;
            } elseif ($gradeName !== null) {
                $namaMasterAsli = $gradeName;
            }

            $items[] = (object) [
                'id_detail'        => $detail->id,
                'nama_barang'      => $namaBarang,
                'nama_barang_asli' => $namaMasterAsli,
                'satuan'           => $satuan,
                'qty'              => $qty,
                'm3'               => $m3,
                'hit_kbk'          => $hitKbk,
                'harga'            => $harga,
                'potongan'         => $potongan,
                'total_pot'        => $potongan * $qty,
                'subtotal'         => $subtotal,
                'keterangan'       => $detail->keterangan ?? '',
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
                    $detail->harga = self::parseRupiahInput($data['harga']);
                }
                if (isset($data['potongan'])) {
                    $detail->potongan = self::parseRupiahInput($data['potongan']);
                }

                if (isset($data['hit_kbk'])) {
                    $detail->hit_kbk = $data['hit_kbk'] === 'm' ? 'm' : 'b';
                }

                if (isset($data['subtotal']) && filled($data['subtotal'])) {
                    $detail->subtotal = self::parseRupiahInput($data['subtotal']);
                } else {
                    $qty = (float) ($detail->jumlah ?? 0);
                    $m3 = $detail->custom_m3 !== null ? (float) $detail->custom_m3 : null;
                    $harga = (float) ($detail->harga ?? 0);
                    $pot = (float) ($detail->potongan ?? 0);
                    $hitKbk = $detail->hit_kbk ?? 'b';
                    $ppnPersen = (float) ($record->ppn_persen ?: 11.0);
                    $pakaiPpn = (bool) ($record->pakai_ppn ?? false);
                    $detail->subtotal = self::calculateItemSubtotal($qty, $harga, $pot, $m3, $hitKbk, $ppnPersen, $pakaiPpn);
                }

                $detail->save();
            }
        }

        // Update total di tabel penjualans
        [$items, $grandTotal] = $this->resolvePenjualanItems($record, 'kantor');
        $pakaiPpn = (bool) ($record->pakai_ppn ?? false);
        $ppnPersen = $pakaiPpn ? (float) ($record->ppn_persen ?: 11) : 0;
        $ppnNominal = round(($grandTotal * $ppnPersen) / 100, 2);
        $ongkir = (float) ($record->ongkir ?? 0);
        $totalAkhir = $grandTotal + $ppnNominal + $ongkir;

        $record->update([
            'sub_total'   => $grandTotal,
            'ppn_nominal' => $ppnNominal,
            'total'       => $totalAkhir,
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
            'cetak_action'           => 'required|string|in:nota,sj,semua,invoice_resmi',
            'pakai_ppn'              => 'nullable|boolean',
            'no_invoice_resmi'       => 'nullable|string|max:255',
            'pkp_nama'               => 'nullable|string|max:255',
            'pkp_alamat'             => 'nullable|string|max:500',
            'pkp_npwp'               => 'nullable|string|max:100',
            'pembeli_bkp_nama'       => 'nullable|string|max:255',
            'pembeli_bkp_alamat'     => 'nullable|string|max:500',
            'pembeli_bkp_npwp'       => 'nullable|string|max:100',
        ]);

        [$items, $grandTotal] = $this->resolvePenjualanItems($record, $validated['jenis']);

        $pakaiPpn = $request->has('pakai_ppn') ? (bool) $request->input('pakai_ppn') : false;
        $ppnPersen = $pakaiPpn ? 11.0 : 0.0;
        $ppnNominal = round(($grandTotal * $ppnPersen) / 100, 2);
        $ongkir = $request->has('ongkir') ? self::parseRupiahInput($request->input('ongkir')) : (float) ($record->ongkir ?? 0);
        $totalAkhir = $grandTotal + $ppnNominal + $ongkir;

        $record->update([
            'metode_pembayaran'      => $validated['metode_pembayaran'],
            'id_rekening_perusahaan' => $validated['metode_pembayaran'] === 'Transfer'
                ? $validated['id_rekening_perusahaan']
                : null,
            'pakai_ppn'              => $pakaiPpn,
            'ppn_persen'             => $ppnPersen,
            'ppn_nominal'            => $ppnNominal,
            'ongkir'                 => $ongkir,
            'sub_total'              => $grandTotal,
            'total'                  => $totalAkhir,
            'no_invoice_resmi'       => $request->input('no_invoice_resmi', $record->no_invoice_resmi),
            'pkp_nama'               => $request->input('pkp_nama', $record->pkp_nama),
            'pkp_alamat'             => $request->input('pkp_alamat', $record->pkp_alamat),
            'pkp_npwp'               => $request->input('pkp_npwp', $record->pkp_npwp),
            'pembeli_bkp_nama'       => $request->input('pembeli_bkp_nama', $record->pembeli_bkp_nama),
            'pembeli_bkp_alamat'     => $request->input('pembeli_bkp_alamat', $record->pembeli_bkp_alamat),
            'pembeli_bkp_npwp'       => $request->input('pembeli_bkp_npwp', $record->pembeli_bkp_npwp),
        ]);

        // Otomatis kirim / sinkronkan ke web Akuntansi HANYA jika transaksi sudah divalidasi
        if (!empty($record->divalidasi_oleh)) {
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
        }

        if ($validated['cetak_action'] === 'invoice_resmi') {
            return redirect()->route('penjualan.invoice-resmi', $record);
        } elseif ($validated['cetak_action'] === 'nota') {
            $targetRoute = $validated['jenis'] === 'sales' ? 'penjualan.nota-sales' : 'penjualan.nota-kantor';
            return redirect()->route($targetRoute, $record);
        } elseif ($validated['cetak_action'] === 'sj') {
            return redirect()->route('penjualan.surat-jalan', ['record' => $record->id, 'jenis' => $validated['jenis']]);
        } else {
            return redirect()->route('penjualan.cetak-semua', ['record' => $record->id, 'jenis' => $validated['jenis']]);
        }
    }

    /**
     * Cetak Invoice Resmi
     */
    public function printInvoiceResmi(Penjualan $record)
    {
        [$items, $grandTotal] = $this->resolvePenjualanItems($record, 'kantor');

        return view('penjualan.invoice-resmi', [
            'record'     => $record,
            'items'      => $items,
            'grandTotal' => $grandTotal,
        ]);
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
