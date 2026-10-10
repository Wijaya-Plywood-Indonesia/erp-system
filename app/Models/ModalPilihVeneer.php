<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class ModalPilihVeneer extends Model
{
    protected $table = 'modal_pilih_veneer';

    protected $fillable = [
        'id_produksi_pilih_veneer',
        'id_stok_veneer_jadi',
        'id_ukuran',
        'id_jenis_kayu',
        'no_palet',
        'kw',
        'jumlah',
        'jenis_veneer',
    ];

    public function produksiPilihVeneer()
    {
        return $this->belongsTo(ProduksiPilihVeneer::class, 'id_produksi_pilih_veneer');
    }

    public function stokVeneerJadi()
    {
        return $this->belongsTo(StokVeneerJadi::class, 'id_stok_veneer_jadi');
    }

    public function ukuran()
    {
        return $this->belongsTo(Ukuran::class, 'id_ukuran');
    }

    public function jenisKayu()
    {
        return $this->belongsTo(JenisKayu::class, 'id_jenis_kayu');
    }

    public function pegawaiPilihVeneers()
    {
        return $this->belongsToMany(
            PegawaiPilihVeneer::class,
            'modal_pilih_veneer_pegawai',
            'id_modal_pilih_veneer',
            'id_pegawai_pilih_veneer'
        );
    }

    public function hasilPilihVeneers()
    {
        return $this->hasMany(HasilPilihVeneer::class, 'id_modal_pilih_veneer');
    }

    public function sisaBelumDipakai(?int $excludeHasilId = null): float
    {
        $terpakai = $this->hasilPilihVeneers()
            ->when($excludeHasilId, fn ($q) => $q->whereKeyNot($excludeHasilId))
            ->sum('jumlah');

        return (float) $this->jumlah - (float) $terpakai;
    }

    protected static function booted()
    {
        static::created(function ($model) {
            static::mutasiKeluar($model);
        });

        static::updated(function ($model) {
            if ($model->wasChanged(['jumlah', 'id_stok_veneer_jadi', 'id_ukuran', 'id_jenis_kayu', 'kw', 'jenis_veneer'])) {
                // Kembalikan stok lama
                $oldModel = new static($model->getOriginal());
                $oldModel->id = $model->id;
                static::mutasiMasuk($oldModel, 'Perubahan/Update Modal Pilih Veneer (Revert data lama)');
                
                // Potong stok baru
                static::mutasiKeluar($model, 'Perubahan/Update Modal Pilih Veneer (Potong data baru)');
            }
        });

        static::deleted(function ($model) {
            static::mutasiMasuk($model);
        });
    }

    protected static function mutasiKeluar($model, $keteranganOverride = null)
    {
        DB::transaction(function () use ($model, $keteranganOverride) {
            $jumlah = (float) $model->jumlah;
            $userName = auth()->user()?->name ?? 'System';
            $jenisVeneerAsal = $model->id_stok_veneer_jadi ? 'jadi' : 'kering';

            $keteranganDefault = sprintf('Dipakai sebagai Modal Pilih Veneer palet %s oleh: %s', $model->no_palet, $userName);
            $keterangan = $keteranganOverride ?: $keteranganDefault;

            if ($jenisVeneerAsal === 'jadi') {
                $stokAsalJadi = StokVeneerJadi::where('id', $model->id_stok_veneer_jadi)->lockForUpdate()->first();
                if (!$stokAsalJadi) return;

                $kubikasiPindah = ($stokAsalJadi->panjang * $stokAsalJadi->lebar * $stokAsalJadi->tebal * $jumlah) / 10000000;
                $nilaiPindah = $stokAsalJadi->hpp_average * $jumlah;

                $stokLembarBefore = $stokAsalJadi->stok_lembar;
                $stokKubikasiBefore = $stokAsalJadi->stok_kubikasi;
                $nilaiStokBefore = $stokAsalJadi->nilai_stok;

                $stokLembarAfter = $stokLembarBefore - $jumlah;
                $stokKubikasiAfter = $stokKubikasiBefore - $kubikasiPindah;
                $nilaiStokAfter = $nilaiStokBefore - $nilaiPindah;

                $logKeluar = HppVeneerJadiLog::create([
                    'id_jenis_kayu' => $stokAsalJadi->id_jenis_kayu,
                    'panjang' => $stokAsalJadi->panjang,
                    'lebar' => $stokAsalJadi->lebar,
                    'tebal' => $stokAsalJadi->tebal,
                    'kw_grade' => $stokAsalJadi->kw_grade,
                    'tanggal' => now(),
                    'tipe_transaksi' => 'KELUAR',
                    'referensi_type' => static::class,
                    'referensi_id' => $model->id,
                    'total_lembar' => $jumlah,
                    'total_kubikasi' => $kubikasiPindah,
                    'hpp_pekerja' => 0,
                    'hpp_bahan_penolong' => 0,
                    'hpp_average' => $stokAsalJadi->hpp_average,
                    'nilai_stok' => $nilaiPindah,
                    'stok_lembar_before' => $stokLembarBefore,
                    'stok_kubikasi_before' => $stokKubikasiBefore,
                    'nilai_stok_before' => $nilaiStokBefore,
                    'stok_lembar_after' => $stokLembarAfter,
                    'stok_kubikasi_after' => $stokKubikasiAfter,
                    'nilai_stok_after' => $nilaiStokAfter,
                    'keterangan' => $keterangan,
                ]);

                $stokAsalJadi->update([
                    'stok_lembar' => $stokLembarAfter,
                    'stok_kubikasi' => $stokKubikasiAfter,
                    'nilai_stok' => $nilaiStokAfter,
                    'id_last_log' => $logKeluar->id,
                ]);
            } else {
                $ukuran = Ukuran::find($model->id_ukuran);
                if (!$ukuran) return;

                $kubikasiPindah = ($ukuran->panjang * $ukuran->lebar * $ukuran->tebal * $jumlah) / 10000000;
                
                $snapshot = StokVeneerKering::snapshotTerakhir($model->id_ukuran, $model->id_jenis_kayu, $model->kw);
                $hppKering = $snapshot['hpp_average'];
                $nilaiPindah = $hppKering * $kubikasiPindah;

                $saldoLembarAsal = StokVeneerKering::saldoLembarTerakhir($model->id_ukuran, $model->id_jenis_kayu, $model->kw);
                $stokM3Asal = $snapshot['stok_m3'];
                $nilaiStokAsal = $snapshot['nilai_stok'];
                
                $stokLembarAfter = $saldoLembarAsal - $jumlah;
                $stokM3After = $stokM3Asal - $kubikasiPindah;
                $nilaiStokAfter = $nilaiStokAsal - $nilaiPindah;

                StokVeneerKering::create([
                    'id_ukuran' => $model->id_ukuran,
                    'id_jenis_kayu' => $model->id_jenis_kayu,
                    'kw' => $model->kw,
                    'jenis_transaksi' => 'keluar',
                    'tanggal_transaksi' => now(),
                    'qty' => $jumlah,
                    'm3' => $kubikasiPindah,
                    'stok_lembar_sebelum' => $saldoLembarAsal,
                    'stok_lembar_sesudah' => $stokLembarAfter,
                    'hpp_veneer_basah_per_m3' => 0,
                    'ongkos_dryer_per_m3' => 0,
                    'hpp_kering_per_m3' => 0,
                    'nilai_transaksi' => $nilaiPindah,
                    'stok_m3_sebelum' => $stokM3Asal,
                    'nilai_stok_sebelum' => $nilaiStokAsal,
                    'stok_m3_sesudah' => $stokM3After,
                    'nilai_stok_sesudah' => $nilaiStokAfter,
                    'hpp_average' => $hppKering,
                    'keterangan' => $keterangan,
                ]);
            }
        });
    }

    protected static function mutasiMasuk($model, $keteranganOverride = null)
    {
        DB::transaction(function () use ($model, $keteranganOverride) {
            $jumlah = (float) $model->jumlah;
            $userName = auth()->user()?->name ?? 'System';
            $jenisVeneerAsal = $model->id_stok_veneer_jadi ? 'jadi' : 'kering';

            $keteranganDefault = sprintf('Pembatalan/Hapus Modal Pilih Veneer palet %s oleh: %s', $model->no_palet, $userName);
            $keterangan = $keteranganOverride ?: $keteranganDefault;

            if ($jenisVeneerAsal === 'jadi') {
                $stokAsalJadi = StokVeneerJadi::where('id', $model->id_stok_veneer_jadi)->lockForUpdate()->first();
                if (!$stokAsalJadi) return;

                $kubikasiPindah = ($stokAsalJadi->panjang * $stokAsalJadi->lebar * $stokAsalJadi->tebal * $jumlah) / 10000000;
                $nilaiPindah = $stokAsalJadi->hpp_average * $jumlah;

                $stokLembarBefore = $stokAsalJadi->stok_lembar;
                $stokKubikasiBefore = $stokAsalJadi->stok_kubikasi;
                $nilaiStokBefore = $stokAsalJadi->nilai_stok;

                $stokLembarAfter = $stokLembarBefore + $jumlah;
                $stokKubikasiAfter = $stokKubikasiBefore + $kubikasiPindah;
                $nilaiStokAfter = $nilaiStokBefore + $nilaiPindah;
                $hppAverageBaru = $stokLembarAfter > 0 ? ($nilaiStokAfter / $stokLembarAfter) : 0;

                $logMasuk = HppVeneerJadiLog::create([
                    'id_jenis_kayu' => $stokAsalJadi->id_jenis_kayu,
                    'panjang' => $stokAsalJadi->panjang,
                    'lebar' => $stokAsalJadi->lebar,
                    'tebal' => $stokAsalJadi->tebal,
                    'kw_grade' => $stokAsalJadi->kw_grade,
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
                    'keterangan' => $keterangan,
                ]);

                $stokAsalJadi->update([
                    'stok_lembar' => $stokLembarAfter,
                    'stok_kubikasi' => $stokKubikasiAfter,
                    'nilai_stok' => $nilaiStokAfter,
                    'hpp_average' => $hppAverageBaru,
                    'id_last_log' => $logMasuk->id,
                ]);
            } else {
                $ukuran = Ukuran::find($model->id_ukuran);
                if (!$ukuran) return;

                $kubikasiPindah = ($ukuran->panjang * $ukuran->lebar * $ukuran->tebal * $jumlah) / 10000000;
                
                $snapshot = StokVeneerKering::snapshotTerakhir($model->id_ukuran, $model->id_jenis_kayu, $model->kw);
                $hppKering = $snapshot['hpp_average'];
                $nilaiPindah = $hppKering * $kubikasiPindah;

                $saldoLembarAsal = StokVeneerKering::saldoLembarTerakhir($model->id_ukuran, $model->id_jenis_kayu, $model->kw);
                $stokM3Asal = $snapshot['stok_m3'];
                $nilaiStokAsal = $snapshot['nilai_stok'];
                
                $stokLembarAfter = $saldoLembarAsal + $jumlah;
                $stokM3After = $stokM3Asal + $kubikasiPindah;
                $nilaiStokAfter = $nilaiStokAsal + $nilaiPindah;
                $hppAverageBaru = $stokM3After > 0 ? ($nilaiStokAfter / $stokM3After) : 0;

                StokVeneerKering::create([
                    'id_ukuran' => $model->id_ukuran,
                    'id_jenis_kayu' => $model->id_jenis_kayu,
                    'kw' => $model->kw,
                    'jenis_transaksi' => 'masuk',
                    'tanggal_transaksi' => now(),
                    'qty' => $jumlah,
                    'm3' => $kubikasiPindah,
                    'stok_lembar_sebelum' => $saldoLembarAsal,
                    'stok_lembar_sesudah' => $stokLembarAfter,
                    'hpp_veneer_basah_per_m3' => 0,
                    'ongkos_dryer_per_m3' => 0,
                    'hpp_kering_per_m3' => 0,
                    'nilai_transaksi' => $nilaiPindah,
                    'stok_m3_sebelum' => $stokM3Asal,
                    'nilai_stok_sebelum' => $nilaiStokAsal,
                    'stok_m3_sesudah' => $stokM3After,
                    'nilai_stok_sesudah' => $nilaiStokAfter,
                    'hpp_average' => $hppAverageBaru,
                    'keterangan' => $keterangan,
                ]);
            }
        });
    }
}
