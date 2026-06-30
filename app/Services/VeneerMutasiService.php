<?php

namespace App\Services;

use App\Models\VeneerMutasi;
use App\Models\VeneerMutasiDetail;
use App\Models\Ukuran;
use App\Models\JenisKayu;
use App\Models\NotaBarangKeluar;
use App\Models\DetailNotaBarangKeluar;
use App\Models\NotaBarangMasuk;
use App\Models\DetailNotaBarangMasuk;
use App\Models\HppVeneerBasahSummary;
use App\Models\HppVeneerBasahLog;
use App\Models\StokVeneerKering;
use Illuminate\Support\Facades\DB;

class VeneerMutasiService
{
    /**
     * Step 1 — Called when user clicks "Kirim" on VK/VM.
     * Creates the BK/BM nota + details, but does NOT touch stock.
     * Stock changes only happen after validation by another user.
     */
    public function process(VeneerMutasi $mutasi): void
    {
        DB::transaction(function () use ($mutasi) {
            $mutasi->dibuat_oleh = auth()->id();
            $mutasi->save();

            $details = $mutasi->details;
            if ($mutasi->status === 'kirim' && $details->isEmpty()) {
                throw new \Exception("Detail barang harus diisi minimal 1 item.");
            }

            // Calculate m3 for each detail
            foreach ($details as $detail) {
                $ukuran = Ukuran::findOrFail($detail->id_ukuran);
                $detail->m3 = ($ukuran->panjang * $ukuran->lebar * $ukuran->tebal * $detail->qty) / 10000000;
                $detail->save();
            }

            // Create or update BK or BM nota header
            if ($mutasi->tipe_transaksi === 'keluar') {
                if ($mutasi->id_nota_bk) {
                    $nota = NotaBarangKeluar::findOrFail($mutasi->id_nota_bk);
                    $nota->update([
                        'tanggal'     => $mutasi->tanggal,
                        'no_nota'     => $mutasi->no_nota,
                        'tujuan_nota' => $mutasi->tujuan_nota ?? '-',
                    ]);
                    // Delete old veneer details
                    DetailNotaBarangKeluar::where('id_nota_bk', $nota->id)->where('nama_barang', 'like', 'Veneer %')->delete();
                } else {
                    $nota = NotaBarangKeluar::create([
                        'tanggal'      => $mutasi->tanggal,
                        'no_nota'      => $mutasi->no_nota,
                        'tujuan_nota'  => $mutasi->tujuan_nota ?? '-',
                        'dibuat_oleh'  => auth()->id(),
                    ]);
                    $mutasi->id_nota_bk = $nota->id;
                }
            } else {
                if ($mutasi->id_nota_bm) {
                    $nota = NotaBarangMasuk::findOrFail($mutasi->id_nota_bm);
                    $nota->update([
                        'tanggal'     => $mutasi->tanggal,
                        'no_nota'     => $mutasi->no_nota,
                        'tujuan_nota' => $mutasi->tujuan_nota ?? '-',
                    ]);
                    // Delete old veneer details
                    DetailNotaBarangMasuk::where('id_nota_bm', $nota->id)->where('nama_barang', 'like', 'Veneer %')->delete();
                } else {
                    $nota = NotaBarangMasuk::create([
                        'tanggal'      => $mutasi->tanggal,
                        'no_nota'      => $mutasi->no_nota,
                        'tujuan_nota'  => $mutasi->tujuan_nota ?? '-',
                        'dibuat_oleh'  => auth()->id(),
                    ]);
                    $mutasi->id_nota_bm = $nota->id;
                }
            }

            // Create nota line items (descriptive string — no stock change yet)
            foreach ($details as $detail) {
                $ukuran    = Ukuran::findOrFail($detail->id_ukuran);
                $jenisKayu = JenisKayu::findOrFail($detail->id_jenis_kayu);
                $namaBarang = "Veneer " . ucfirst($detail->tipe_veneer)
                    . " - " . $ukuran->nama_ukuran
                    . " - " . $jenisKayu->nama_kayu
                    . " - KW " . $detail->kw;

                if ($mutasi->tipe_transaksi === 'keluar') {
                    DetailNotaBarangKeluar::create([
                        'id_nota_bk'  => $nota->id,
                        'nama_barang' => $namaBarang,
                        'jumlah'      => $detail->qty,
                        'satuan'      => 'Lembar',
                        'keterangan'  => $mutasi->keterangan ?? 'Otomatis dari Mutasi Veneer Keluar',
                    ]);
                } else {
                    DetailNotaBarangMasuk::create([
                        'id_nota_bm'  => $nota->id,
                        'nama_barang' => $namaBarang,
                        'jumlah'      => $detail->qty,
                        'satuan'      => 'Lembar',
                        'keterangan'  => $mutasi->keterangan ?? 'Otomatis dari Mutasi Veneer Masuk',
                    ]);
                }
            }

            $mutasi->save();
        });
    }

    /**
     * Step 2 — Called when validator clicks "Validasi Nota" on BK or BM.
     * Actually updates the stock. Validator must be a different user from creator.
     *
     * @param  NotaBarangKeluar|NotaBarangMasuk  $nota
     * @throws \Exception if validator is the same as creator, or stock is insufficient
     */
    public function processStockFromNota($nota): void
    {
        // Guard: cannot validate own nota (except Super Admin)
        $user = auth()->user();
        $isSuperAdmin = $user && $user->hasAnyRole(['super_admin', 'Super Admin']);
        if (!$isSuperAdmin && (int) $nota->dibuat_oleh === (int) auth()->id()) {
            throw new \Exception("Anda tidak dapat memvalidasi nota yang Anda buat sendiri.");
        }

        // Guard: already validated
        if ($nota->divalidasi_oleh !== null) {
            throw new \Exception("Nota ini sudah divalidasi sebelumnya.");
        }

        // Determine tipe_transaksi from nota type
        $isKeluar = $nota instanceof NotaBarangKeluar;

        // Find related VeneerMutasi
        $mutasi = $isKeluar
            ? VeneerMutasi::where('id_nota_bk', $nota->id)->first()
            : VeneerMutasi::where('id_nota_bm', $nota->id)->first();

        if (!$mutasi) {
            // Nota is manual/general (not from Veneer Mutasi menu), e.g., for general items (karung, etc.)
            // We just validate the nota without changing veneer stock.
            $nota->update(['divalidasi_oleh' => auth()->id()]);
            return;
        }

        DB::transaction(function () use ($nota, $mutasi, $isKeluar) {
            $details = $mutasi->details;
            if ($details->isEmpty()) {
                throw new \Exception("Tidak ada detail barang untuk diproses.");
            }

            foreach ($details as $detail) {
                $ukuran    = Ukuran::findOrFail($detail->id_ukuran);
                $jenisKayu = JenisKayu::findOrFail($detail->id_jenis_kayu);
                $namaBarang = "Veneer " . ucfirst($detail->tipe_veneer)
                    . " - " . $ukuran->nama_ukuran
                    . " - " . $jenisKayu->nama_kayu
                    . " - KW " . $detail->kw;

                if ($detail->tipe_veneer === 'basah') {
                    $this->updateStokBasah($mutasi, $detail, $ukuran, $namaBarang, $isKeluar);
                } else {
                    $this->updateStokKering($mutasi, $detail, $isKeluar);
                }
            }

            // Mark nota as validated
            $nota->update(['divalidasi_oleh' => auth()->id()]);

            app(HppDryerService::class)->updateLogHarian($mutasi->tanggal);
        });
    }

    /* ──────────────────────────────────────────────────
     *  Private: basah stock update
     * ────────────────────────────────────────────────── */
    private function updateStokBasah(VeneerMutasi $mutasi, VeneerMutasiDetail $detail, Ukuran $ukuran, string $namaBarang, bool $isKeluar): void
    {
        $summary = HppVeneerBasahSummary::where([
            'id_jenis_kayu' => $detail->id_jenis_kayu,
            'panjang'       => $ukuran->panjang,
            'lebar'         => $ukuran->lebar,
            'tebal'         => $ukuran->tebal,
            'kw'            => $detail->kw,
        ])->lockForUpdate()->first();

        if (!$summary) {
            $summary = HppVeneerBasahSummary::create([
                'id_jenis_kayu' => $detail->id_jenis_kayu,
                'panjang'       => $ukuran->panjang,
                'lebar'         => $ukuran->lebar,
                'tebal'         => $ukuran->tebal,
                'kw'            => $detail->kw,
                'stok_lembar'   => 0,
                'stok_kubikasi' => 0,
                'nilai_stok'    => 0,
                'hpp_average'   => 0,
            ]);
        }

        $stokSistem      = (int) $summary->stok_lembar;
        $kubikasiSistem  = (float) $summary->stok_kubikasi;
        $nilaiStokBefore = (float) $summary->nilai_stok;

        if ($isKeluar) {
            if ($stokSistem < $detail->qty) {
                throw new \Exception("Stok veneer basah tidak cukup untuk: {$namaBarang}. Tersedia: {$stokSistem} lembar.");
            }
            $stokFisik     = $stokSistem - $detail->qty;
            $kubikasiFisik = max(0.0, round($kubikasiSistem - $detail->m3, 6));
            $nilaiKeluar   = round($detail->m3 * $summary->hpp_average, 2);
            $nilaiStokBaru = max(0.0, round($nilaiStokBefore - $nilaiKeluar, 2));
        } else {
            $stokFisik     = $stokSistem + $detail->qty;
            $kubikasiFisik = round($kubikasiSistem + $detail->m3, 6);
            $nilaiMasuk    = round($detail->m3 * $summary->hpp_average, 2);
            $nilaiStokBaru = round($nilaiStokBefore + $nilaiMasuk, 2);
        }

        $summary->update([
            'stok_lembar'   => $stokFisik,
            'stok_kubikasi' => $kubikasiFisik,
            'nilai_stok'    => $nilaiStokBaru,
        ]);

        $log = HppVeneerBasahLog::create([
            'id_jenis_kayu'        => $detail->id_jenis_kayu,
            'panjang'              => $ukuran->panjang,
            'lebar'                => $ukuran->lebar,
            'tebal'                => $ukuran->tebal,
            'kw'                   => $detail->kw,
            'tanggal'              => $mutasi->tanggal,
            'tipe_transaksi'       => $mutasi->tipe_transaksi,
            'keterangan'           => strtoupper(($isKeluar ? "Veneer Keluar #" : "Veneer Masuk #") . $mutasi->no_nota)
                                      . ($mutasi->keterangan ? " - " . strtoupper($mutasi->keterangan) : ""),
            'referensi_type'       => VeneerMutasiDetail::class,
            'referensi_id'         => $detail->id,
            'total_lembar'         => $detail->qty,
            'total_kubikasi'       => $detail->m3,
            'stok_lembar_before'   => $stokSistem,
            'stok_lembar_after'    => $stokFisik,
            'stok_kubikasi_before' => $kubikasiSistem,
            'stok_kubikasi_after'  => $kubikasiFisik,
            'hpp_average'          => $summary->hpp_average,
            'nilai_stok_before'    => $nilaiStokBefore,
            'nilai_stok_after'     => $nilaiStokBaru,
        ]);

        $summary->update(['id_last_log' => $log->id]);
    }

    /* ──────────────────────────────────────────────────
     *  Private: kering stock update
     * ────────────────────────────────────────────────── */
    private function updateStokKering(VeneerMutasi $mutasi, VeneerMutasiDetail $detail, bool $isKeluar): void
    {
        StokVeneerKering::create([
            'id_produksi_dryer'       => null,
            'id_ukuran'               => $detail->id_ukuran,
            'id_jenis_kayu'           => $detail->id_jenis_kayu,
            'kw'                      => $detail->kw,
            'jenis_transaksi'         => $mutasi->tipe_transaksi,
            'tanggal_transaksi'       => $mutasi->tanggal,
            'qty'                     => $detail->qty,
            'm3'                      => $detail->m3,
            'hpp_veneer_basah_per_m3' => 0,
            'ongkos_dryer_per_m3'     => 0,
            'hpp_kering_per_m3'       => 0,
            'nilai_transaksi'         => 0,
            'stok_lembar_sebelum'     => 0,
            'stok_lembar_sesudah'     => 0,
            'stok_m3_sebelum'         => 0,
            'stok_m3_sesudah'         => 0,
            'nilai_stok_sebelum'      => 0,
            'nilai_stok_sesudah'      => 0,
            'hpp_average'             => 0,
            'keterangan'              => strtoupper(($isKeluar ? "Veneer Keluar #" : "Veneer Masuk #") . $mutasi->no_nota)
                                         . ($mutasi->keterangan ? " - " . strtoupper($mutasi->keterangan) : ""),
            'id_veneer_mutasi'        => $mutasi->id,
            'id_veneer_mutasi_detail' => $detail->id,
        ]);

        $this->recalculateStokKering($detail->id_ukuran, $detail->id_jenis_kayu, $detail->kw);
    }

    /**
     * Reverse all stock changes when a VeneerMutasi is deleted.
     * Only reverses if the associated nota was already validated.
     */
    public function reverse(VeneerMutasi $mutasi): void
    {
        if ($mutasi->status !== 'kirim') {
            return;
        }

        DB::transaction(function () use ($mutasi) {
            // Check if nota was already validated — only then do we need to reverse stock
            $notaValidated = false;
            if ($mutasi->id_nota_bk) {
                $bk = NotaBarangKeluar::find($mutasi->id_nota_bk);
                if ($bk) {
                    $notaValidated = $bk->divalidasi_oleh !== null;
                    $bk->detail()->delete();
                    $bk->delete();
                }
            }
            if ($mutasi->id_nota_bm) {
                $bm = NotaBarangMasuk::find($mutasi->id_nota_bm);
                if ($bm) {
                    $notaValidated = $bm->divalidasi_oleh !== null;
                    $bm->detail()->delete();
                    $bm->delete();
                }
            }

            // Only reverse stock if the nota was validated (stock was actually changed)
            if ($notaValidated) {
                foreach ($mutasi->details as $detail) {
                    $ukuran = Ukuran::findOrFail($detail->id_ukuran);

                    if ($detail->tipe_veneer === 'basah') {
                        $summary = HppVeneerBasahSummary::where([
                            'id_jenis_kayu' => $detail->id_jenis_kayu,
                            'panjang'       => $ukuran->panjang,
                            'lebar'         => $ukuran->lebar,
                            'tebal'         => $ukuran->tebal,
                            'kw'            => $detail->kw,
                        ])->lockForUpdate()->first();

                        if ($summary) {
                            $stokSistem      = (int) $summary->stok_lembar;
                            $kubikasiSistem  = (float) $summary->stok_kubikasi;
                            $nilaiStokBefore = (float) $summary->nilai_stok;

                            if ($mutasi->tipe_transaksi === 'keluar') {
                                $stokFisik     = $stokSistem + $detail->qty;
                                $kubikasiFisik = round($kubikasiSistem + $detail->m3, 6);
                                $nilaiStokBaru = round($nilaiStokBefore + round($detail->m3 * $summary->hpp_average, 2), 2);
                            } else {
                                $stokFisik     = max(0, $stokSistem - $detail->qty);
                                $kubikasiFisik = max(0.0, round($kubikasiSistem - $detail->m3, 6));
                                $nilaiStokBaru = max(0.0, round($nilaiStokBefore - round($detail->m3 * $summary->hpp_average, 2), 2));
                            }

                            $summary->update([
                                'stok_lembar'   => $stokFisik,
                                'stok_kubikasi' => $kubikasiFisik,
                                'nilai_stok'    => $nilaiStokBaru,
                            ]);

                            HppVeneerBasahLog::where([
                                'referensi_type' => VeneerMutasiDetail::class,
                                'referensi_id'   => $detail->id,
                            ])->delete();

                            $lastLog = HppVeneerBasahLog::where([
                                'id_jenis_kayu' => $detail->id_jenis_kayu,
                                'panjang'       => $ukuran->panjang,
                                'lebar'         => $ukuran->lebar,
                                'tebal'         => $ukuran->tebal,
                                'kw'            => $detail->kw,
                            ])->latest()->first();

                            $summary->update(['id_last_log' => $lastLog?->id]);
                        }
                    } else {
                        StokVeneerKering::where('id_veneer_mutasi_detail', $detail->id)->delete();
                        $this->recalculateStokKering($detail->id_ukuran, $detail->id_jenis_kayu, $detail->kw);
                    }
                }

                app(HppDryerService::class)->updateLogHarian($mutasi->tanggal);
            }
        });
    }

    /**
     * Recalculate dry veneer ledger running balances.
     */
    public function recalculateStokKering(int $idUkuran, int $idJenisKayu, string $kw): void
    {
        $records = StokVeneerKering::where('id_ukuran', $idUkuran)
            ->where('id_jenis_kayu', $idJenisKayu)
            ->where('kw', $kw)
            ->orderBy('tanggal_transaksi', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        $stokLembar = 0;
        $stokM3     = 0.0;
        $nilaiStok  = 0.0;
        $hppAverage = 0.0;

        foreach ($records as $record) {
            $stokLembarSebelum = $stokLembar;
            $stokM3Sebelum     = $stokM3;
            $nilaiSebelum      = $nilaiStok;

            if ($record->jenis_transaksi === 'masuk') {
                $stokLembar += $record->qty;
                $stokM3     += (float) $record->m3;

                $hppKering = (float) $record->hpp_kering_per_m3;
                if ($hppKering <= 0) $hppKering = $hppAverage;

                $nilaiTx = (float) $record->nilai_transaksi;
                if ($nilaiTx <= 0) $nilaiTx = round($hppKering * (float) $record->m3, 4);

                $nilaiStok  += $nilaiTx;
                $hppAverage  = $stokM3 > 0 ? $nilaiStok / $stokM3 : $hppKering;

                $record->update([
                    'hpp_kering_per_m3'   => round($hppKering, 4),
                    'nilai_transaksi'     => round($nilaiTx, 4),
                    'stok_lembar_sebelum' => $stokLembarSebelum,
                    'stok_lembar_sesudah' => $stokLembar,
                    'stok_m3_sebelum'     => round($stokM3Sebelum, 6),
                    'stok_m3_sesudah'     => round($stokM3, 6),
                    'nilai_stok_sebelum'  => round($nilaiSebelum, 4),
                    'nilai_stok_sesudah'  => round($nilaiStok, 4),
                    'hpp_average'         => round($hppAverage, 4),
                ]);
            } else {
                $stokLembar -= $record->qty;
                $stokM3     -= (float) $record->m3;

                $nilaiTx   = round($hppAverage * (float) $record->m3, 4);
                $nilaiStok -= $nilaiTx;

                if ($stokM3 <= 0) { $stokM3 = 0.0; $nilaiStok = 0.0; }

                $record->update([
                    'hpp_kering_per_m3'   => round($hppAverage, 4),
                    'nilai_transaksi'     => round($nilaiTx, 4),
                    'stok_lembar_sebelum' => $stokLembarSebelum,
                    'stok_lembar_sesudah' => $stokLembar,
                    'stok_m3_sebelum'     => round($stokM3Sebelum, 6),
                    'stok_m3_sesudah'     => round($stokM3, 6),
                    'nilai_stok_sebelum'  => round($nilaiSebelum, 4),
                    'nilai_stok_sesudah'  => round($nilaiStok, 4),
                    'hpp_average'         => round($hppAverage, 4),
                ]);
            }
        }
    }
}
