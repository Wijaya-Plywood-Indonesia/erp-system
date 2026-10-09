<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class HasilPilihVeneer extends Model
{
    protected $table = 'hasil_pilih_veneer';

    protected $fillable = [
        'id_produksi_pilih_veneer',
        'id_modal_pilih_veneer',
        'jenis_veneer',
        'kw',
        'no_palet',
        'jumlah',
        'diserahkan_at',
        'diserahkan_by',
        'diterima_gudang_at',
        'diterima_gudang_by',
    ];

    protected $casts = [
        'diserahkan_at' => 'datetime',
        'diterima_gudang_at' => 'datetime',
    ];

    public function diserahkanOleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diserahkan_by');
    }

    public function produksiPilihVeneer()
    {
        return $this->belongsTo(ProduksiPilihVeneer::class, 'id_produksi_pilih_veneer');
    }

    public function modalPilihVeneer()
    {
        return $this->belongsTo(ModalPilihVeneer::class, 'id_modal_pilih_veneer');
    }

    public function pegawaiPilihVeneers()
    {
        return $this->belongsToMany(
            PegawaiPilihVeneer::class,
            'hasil_pilih_veneer_pegawai',
            'id_hasil_pilih_veneer',
            'id_pegawai_pilih_veneer'
        );
    }


    protected static function booted()
    {
        static::saved(function ($model) {
            if ($model->id_produksi_pilih_veneer) {
                \App\Events\ProductionUpdated::dispatch($model->id_produksi_pilih_veneer, 'veneer');
            }

            if (
                $model->wasChanged('diterima_gudang_at')
                && $model->getOriginal('diterima_gudang_at') === null
                && $model->diterima_gudang_at !== null
            ) {
                static::mutasiStokSaatDiterima($model);
            }
        });

        static::deleted(function ($model) {
            if ($model->id_produksi_pilih_veneer) {
                \App\Events\ProductionUpdated::dispatch($model->id_produksi_pilih_veneer, 'veneer');
            }
        });
    }

    protected static function mutasiStokSaatDiterima(self $model): void
    {
        DB::transaction(function () use ($model) {
            $modal = $model->modalPilihVeneer()->first();
            if (!$modal) {
                throw new \RuntimeException('Modal pilih veneer tidak ditemukan.');
            }

            $jenisVeneerAsal = $modal->id_stok_veneer_jadi ? 'jadi' : 'kering';
            $jenisVeneerHasil = $model->jenis_veneer ?? 'jadi';
            
            $jumlah = (float) $model->jumlah;
            $userName = auth()->user()?->name ?? 'System';
            $kwHasil = (string) $model->kw;

            // AMBIL DATA ASAL UNTUK MENDAPATKAN UKURAN DAN JENIS KAYU
            if ($jenisVeneerAsal === 'jadi') {
                $stokAsalJadi = StokVeneerJadi::where('id', $modal->id_stok_veneer_jadi)->first();
                if (!$stokAsalJadi) {
                    throw new \RuntimeException('Stok Veneer Jadi asal tidak ditemukan.');
                }
                
                $idJenisKayu = $stokAsalJadi->id_jenis_kayu;
                $kwAsal = (string) $stokAsalJadi->kw_grade;
                $panjang = $stokAsalJadi->panjang;
                $lebar = $stokAsalJadi->lebar;
                $tebal = $stokAsalJadi->tebal;
                $idUkuran = null;
                $hppAsal = $stokAsalJadi->hpp_average;
            } else {
                $idUkuran = $modal->id_ukuran;
                $idJenisKayu = $modal->id_jenis_kayu;
                $kwAsal = (string) $modal->kw;
                
                $ukuran = Ukuran::find($idUkuran);
                if (!$ukuran) {
                    throw new \RuntimeException('Ukuran Veneer Kering asal tidak ditemukan.');
                }
                $panjang = $ukuran->panjang;
                $lebar = $ukuran->lebar;
                $tebal = $ukuran->tebal;
                
                $snapshotAsal = StokVeneerKering::snapshotTerakhir($idUkuran, $idJenisKayu, $kwAsal);
                $hppAsal = $snapshotAsal['hpp_average'];
            }

            $kubikasiPindah = ($panjang * $lebar * $tebal * $jumlah) / 10000000;
            
            // HPP yang dibawa masuk ke gudang dari hasil pilih veneer menggunakan HPP rata-rata dari stok asal
            if ($jenisVeneerAsal === 'jadi') {
                $nilaiPindah = $hppAsal * $jumlah; // hpp veneer jadi = per lembar
            } else {
                $nilaiPindah = $hppAsal * $kubikasiPindah; // hpp veneer kering = per m3
            }

            // KITA HANYA PERLU MEMASUKKAN STOK HASIL, KARENA STOK ASAL SUDAH DIKURANGI SAAT MODAL DIBUAT
            if ($jenisVeneerHasil === 'jadi') {
                $stokBaru = StokVeneerJadi::where('id_jenis_kayu', $idJenisKayu)
                    ->where('panjang', $panjang)
                    ->where('lebar', $lebar)
                    ->where('tebal', $tebal)
                    ->where('kw_grade', $kwHasil)
                    ->lockForUpdate()
                    ->first();

                if (! $stokBaru) {
                    $stokBaru = StokVeneerJadi::create([
                        'id_jenis_kayu' => $idJenisKayu,
                        'panjang' => $panjang,
                        'lebar' => $lebar,
                        'tebal' => $tebal,
                        'kw_grade' => $kwHasil,
                        'stok_lembar' => 0,
                        'stok_kubikasi' => 0,
                        'nilai_stok' => 0,
                        'hpp_average' => 0,
                        'hpp_pekerja_last' => 0,
                        'hpp_bahan_penolong_last' => 0,
                        'id_last_log' => null,
                    ]);
                }

                $stokLembarBefore = $stokBaru->stok_lembar;
                $stokKubikasiBefore = $stokBaru->stok_kubikasi;
                $nilaiStokBefore = $stokBaru->nilai_stok;

                $stokLembarAfter = $stokLembarBefore + $jumlah;
                $stokKubikasiAfter = $stokKubikasiBefore + $kubikasiPindah;
                $nilaiStokAfter = $nilaiStokBefore + $nilaiPindah;

                $hppAverageBaru = $stokLembarAfter > 0 ? ($nilaiStokAfter / $stokLembarAfter) : 0;

                $logMasuk = HppVeneerJadiLog::create([
                    'id_jenis_kayu' => $idJenisKayu,
                    'panjang' => $panjang,
                    'lebar' => $lebar,
                    'tebal' => $tebal,
                    'kw_grade' => $kwHasil,
                    'tanggal' => now(),
                    'tipe_transaksi' => 'MASUK',
                    'referensi_type' => static::class,
                    'referensi_id' => $model->id,
                    'total_lembar' => $jumlah,
                    'total_kubikasi' => $kubikasiPindah,
                    'hpp_pekerja' => 0,
                    'hpp_bahan_penolong' => 0,
                    'hpp_average' => $hppAverageBaru,
                    'nilai_stok' => $nilaiPindah,
                    'stok_lembar_before' => $stokLembarBefore,
                    'stok_kubikasi_before' => $stokKubikasiBefore,
                    'nilai_stok_before' => $nilaiStokBefore,
                    'stok_lembar_after' => $stokLembarAfter,
                    'stok_kubikasi_after' => $stokKubikasiAfter,
                    'nilai_stok_after' => $nilaiStokAfter,
                    'keterangan' => sprintf(
                        'Hasil pilih veneer palet %s (%s KW %s), diterima oleh: %s',
                        $model->no_palet,
                        $jenisVeneerHasil,
                        $kwHasil,
                        $userName
                    ),
                ]);

                $stokBaru->update([
                    'stok_lembar' => $stokLembarAfter,
                    'stok_kubikasi' => $stokKubikasiAfter,
                    'nilai_stok' => $nilaiStokAfter,
                    'hpp_average' => $hppAverageBaru,
                    'id_last_log' => $logMasuk->id,
                ]);
            } else {
                $idUkuranHasil = $idUkuran;
                if (!$idUkuranHasil) {
                    $ukuranMatch = Ukuran::firstOrCreate([
                        'panjang' => $panjang,
                        'lebar' => $lebar,
                        'tebal' => $tebal,
                    ]);
                    $idUkuranHasil = $ukuranMatch->id;
                }

                $snapshot = StokVeneerKering::snapshotTerakhir($idUkuranHasil, $idJenisKayu, $kwHasil);
                $saldoLembarHasil = StokVeneerKering::saldoLembarTerakhir($idUkuranHasil, $idJenisKayu, $kwHasil);
                $stokM3Hasil = $snapshot['stok_m3'];
                $nilaiStokHasil = $snapshot['nilai_stok'];
                
                $stokLembarAfter = $saldoLembarHasil + $jumlah;
                $stokM3After = $stokM3Hasil + $kubikasiPindah;
                $nilaiStokAfter = $nilaiStokHasil + $nilaiPindah;
                
                $hppAverageBaru = $stokM3After > 0 ? ($nilaiStokAfter / $stokM3After) : 0;
                
                StokVeneerKering::create([
                    'id_ukuran' => $idUkuranHasil,
                    'id_jenis_kayu' => $idJenisKayu,
                    'kw' => $kwHasil,
                    'jenis_transaksi' => 'masuk',
                    'tanggal_transaksi' => now(),
                    'qty' => $jumlah,
                    'm3' => $kubikasiPindah,
                    'stok_lembar_sebelum' => $saldoLembarHasil,
                    'stok_lembar_sesudah' => $stokLembarAfter,
                    'hpp_veneer_basah_per_m3' => 0,
                    'ongkos_dryer_per_m3' => 0,
                    'hpp_kering_per_m3' => 0,
                    'nilai_transaksi' => $nilaiPindah,
                    'stok_m3_sebelum' => $stokM3Hasil,
                    'nilai_stok_sebelum' => $nilaiStokHasil,
                    'stok_m3_sesudah' => $stokM3After,
                    'nilai_stok_sesudah' => $nilaiStokAfter,
                    'hpp_average' => $hppAverageBaru,
                    'keterangan' => sprintf(
                        'Hasil pilih veneer palet %s (%s KW %s), diterima oleh: %s',
                        $model->no_palet,
                        $jenisVeneerHasil,
                        $kwHasil,
                        $userName
                    ),
                ]);
            }
        });
    }
}
