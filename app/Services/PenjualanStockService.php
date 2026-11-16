<?php

namespace App\Services;

use App\Models\DetailPenjualan;
use App\Models\HppPlywoodSiapJualLog;
use App\Models\HppVeneerBasahLog;
use App\Models\HppVeneerBasahSummary;
use App\Models\Penjualan;
use App\Models\StokPlywoodSiapJual;
use App\Models\StokVeneerJadi;
use App\Models\StokVeneerKering;
use App\Models\Ukuran;
use App\Services\BarangUmumInventoryService;
use App\Services\PlywoodMutasiService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PenjualanStockService
{
    /**
     * Potong stok saat penjualan divalidasi.
     */
    public function potongStok(Penjualan $penjualan): void
    {
        DB::transaction(function () use ($penjualan) {
            foreach ($penjualan->detail as $detail) {
                $nama = $detail->nama_barang;
                $qty = (float) $detail->jumlah;

                if (str_starts_with($nama, 'Plywood ') || $detail->kategori_barang === 'plywood') {
                    $this->potongStokPlywood($penjualan, $detail);
                } elseif (str_starts_with($nama, 'Veneer ') || $detail->kategori_barang === 'veneer') {
                    $this->potongStokVeneer($penjualan, $detail);
                } elseif (str_starts_with($nama, 'Barang Umum - ') || $detail->kategori_barang === 'barang_umum') {
                    $this->potongStokBarangUmum($penjualan, $detail);
                }
            }
        });
    }

    /**
     * Potong stok Plywood Siap Jual
     */
    protected function potongStokPlywood(Penjualan $penjualan, DetailPenjualan $detail): void
    {
        $plywoodService = app(PlywoodMutasiService::class);
        $ukuran = null;

        if ($detail->id_ukuran) {
            $ukuran = Ukuran::find($detail->id_ukuran);
        } elseif ($detail->ukuran_key) {
            [$a, $b, $tebal] = array_map('floatval', explode('|', $detail->ukuran_key));
            $ukuran = Ukuran::firstOrCreate([
                'panjang' => max($a, $b),
                'lebar'   => min($a, $b),
                'tebal'   => $tebal,
            ], [
                'nama_ukuran' => "{$a}x{$b}x{$tebal}",
            ]);
        }

        if (!$ukuran) {
            // Coba ambil dari nama barang regex
            if (preg_match('/(\d+(?:[\.,]\d+)?)\s*x\s*(\d+(?:[\.,]\d+)?)\s*x\s*(\d+(?:[\.,]\d+)?)/i', $detail->nama_barang, $m)) {
                $a = (float) str_replace(',', '.', $m[1]);
                $b = (float) str_replace(',', '.', $m[2]);
                $tebal = (float) str_replace(',', '.', $m[3]);
                $ukuran = Ukuran::firstOrCreate([
                    'panjang' => max($a, $b),
                    'lebar'   => min($a, $b),
                    'tebal'   => $tebal,
                ], [
                    'nama_ukuran' => "{$a}x{$b}x{$tebal}",
                ]);
            }
        }

        if (!$ukuran) {
            Log::warning("Ukuran plywood tidak ditemukan untuk baris detail #{$detail->id} - {$detail->nama_barang}");
            return;
        }

        $idJenisKayu = $detail->id_jenis_kayu;
        $kw = $detail->kw_grade;

        $stokQuery = StokPlywoodSiapJual::where('tebal', (float) $ukuran->tebal);
        if ($idJenisKayu) {
            $stokQuery->where('id_jenis_kayu', $idJenisKayu);
        }
        if ($kw) {
            $stokQuery->where('kw_grade', $kw);
        }

        $a = (float) $ukuran->panjang;
        $b = (float) $ukuran->lebar;
        $stok = (clone $stokQuery)->where(function ($q) use ($a, $b) {
            $q->where(fn ($s) => $s->where('panjang', $a)->where('lebar', $b))
              ->orWhere(fn ($s) => $s->where('panjang', $b)->where('lebar', $a));
        })->first();

        if (!$stok) {
            $stok = (clone $stokQuery)->first();
        }

        if (!$stok) {
            Log::warning("Baris stok plywood tidak ditemukan untuk {$detail->nama_barang}");
            return;
        }

        $qty = (int) $detail->jumlah;
        $m3 = (float) ($detail->custom_m3 ?? (($ukuran->panjang * $ukuran->lebar * $ukuran->tebal * $qty) / 10000000));

        $lembarBefore = (int) $stok->stok_lembar;
        $m3Before     = (float) $stok->stok_kubikasi;

        $stok->stok_lembar   = $lembarBefore - $qty;
        $stok->stok_kubikasi = max(0, $m3Before - $m3);
        $stok->save();

        // Catat mutasi log
        $log = HppPlywoodSiapJualLog::create([
            'id_stok_plywood_siap_jual' => $stok->id,
            'id_jenis_kayu'        => $stok->id_jenis_kayu,
            'panjang'              => $stok->panjang,
            'lebar'                => $stok->lebar,
            'tebal'                => $stok->tebal,
            'kw_grade'             => $stok->kw_grade,
            'tanggal'              => $penjualan->tanggal ?? today(),
            'tipe_transaksi'       => 'keluar',
            'keterangan'           => 'Penjualan Nota ' . $penjualan->no_nota . ' - ' . ($penjualan->tujuan_nota ?? ''),
            'referensi_type'       => Penjualan::class,
            'referensi_id'         => $penjualan->id,
            'total_lembar'         => $qty,
            'total_kubikasi'       => $m3,
            'stok_lembar_before'   => $lembarBefore,
            'stok_kubikasi_before' => $m3Before,
            'stok_lembar_after'    => $stok->stok_lembar,
            'stok_kubikasi_after'  => $stok->stok_kubikasi,
        ]);

        $stok->update(['id_last_log' => $log->id]);
    }

    /**
     * Potong stok Veneer
     */
    protected function potongStokVeneer(Penjualan $penjualan, DetailPenjualan $detail): void
    {
        $idUkuran = $detail->id_ukuran;
        $idJenisKayu = $detail->id_jenis_kayu;
        $kw = $detail->kw_grade;
        $tipe = $detail->tipe_veneer ?? 'jadi';
        $qty = (int) $detail->jumlah;

        $ukuran = $idUkuran ? Ukuran::find($idUkuran) : null;
        $m3 = (float) ($detail->custom_m3 ?? ($ukuran ? ($ukuran->panjang * $ukuran->lebar * $ukuran->tebal * $qty) / 10000000 : 0));

        if ($tipe === 'jadi' || $tipe === 'basah') {
            $stok = StokVeneerJadi::where('id_jenis_kayu', $idJenisKayu)
                ->when($ukuran, fn ($q) => $q->where('tebal', (float) $ukuran->tebal))
                ->when($kw, fn ($q) => $q->where('kw_grade', $kw))
                ->first();

            if ($stok) {
                $lembarBefore = (int) $stok->stok_lembar;
                $m3Before = (float) $stok->stok_kubikasi;

                $stok->stok_lembar = $lembarBefore - $qty;
                $stok->stok_kubikasi = max(0, $m3Before - $m3);
                $stok->save();
            }
        } else {
            // Kering
            $latest = StokVeneerKering::where([
                'id_ukuran' => $idUkuran,
                'id_jenis_kayu' => $idJenisKayu,
                'kw' => $kw,
            ])->orderBy('tanggal_transaksi', 'desc')->first();

            if ($latest) {
                $lembarBefore = (int) $latest->stok_lembar_sesudah;
                $lembarAfter = $lembarBefore - $qty;

                StokVeneerKering::create([
                    'id_ukuran' => $idUkuran,
                    'id_jenis_kayu' => $idJenisKayu,
                    'kw' => $kw,
                    'qty' => -$qty,
                    'stok_lembar_sebelum' => $lembarBefore,
                    'stok_lembar_sesudah' => $lembarAfter,
                    'tanggal_transaksi' => $penjualan->tanggal ?? today(),
                    'keterangan' => 'Penjualan Nota ' . $penjualan->no_nota,
                ]);
            }
        }
    }

    /**
     * Potong stok Barang Umum
     */
    protected function potongStokBarangUmum(Penjualan $penjualan, DetailPenjualan $detail): void
    {
        $idBarang = $detail->id_barang_umum;
        if (!$idBarang) {
            return;
        }

        $service = app(BarangUmumInventoryService::class);
        $service->keluarkanBarang(
            $idBarang,
            (float) $detail->jumlah,
            Penjualan::class,
            $penjualan->id,
            'Penjualan Nota ' . $penjualan->no_nota,
            $penjualan->tanggal ?? today()
        );
    }
}
