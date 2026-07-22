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

    // Untuk Mutasi Veneer Jadi Dari Hasil Pilih Veneer ke Gudang Veneer Jadi, kita perlu menghitung sisa yang belum diterima di gudang.
    protected static function mutasiStokSaatDiterima(self $model): void
    {
        DB::transaction(function () use ($model) {
            $modal = $model->modalPilihVeneer()->with('stokVeneerJadi')->lockForUpdate()->first();
            $stokAsal = $modal?->stokVeneerJadi;

            if (! $stokAsal) {
                throw new \RuntimeException('Data stok asal modal tidak ditemukan, mutasi stok dibatalkan.');
            }

            $kwHasil = (string) $model->kw;
            $kwAsal = (string) $stokAsal->kw_grade;
            $jumlah = (float) $model->jumlah;

            // KW tidak berubah -> tidak ada apa-apa yang perlu dimutasi.
            if ($kwHasil === $kwAsal) {
                return;
            }

            $userName = auth()->user()?->name ?? 'System';

            // Kunci ulang baris stok asal (row yang sama dengan $stokAsal,
            // tapi dengan lock, supaya aman dari transaksi lain).
            $stokLama = StokVeneerJadi::where('id', $stokAsal->id)->lockForUpdate()->first();

            if (! $stokLama || $stokLama->stok_lembar < $jumlah) {
                throw new \RuntimeException('Stok asal tidak mencukupi untuk mutasi turun/naik KW.');
            }

            // ── 1. KELUARKAN dari baris KW lama ──────────────────────────
            $stokLembarBeforeLama = $stokLama->stok_lembar;
            $stokKubikasiBeforeLama = $stokLama->stok_kubikasi;
            $nilaiStokBeforeLama = $stokLama->nilai_stok;

            $kubikasiPindah = ($stokLama->panjang * $stokLama->lebar * $stokLama->tebal * $jumlah) / 10000000;
            $nilaiPindah = $stokLama->hpp_average * $jumlah;

            $stokLembarAfterLama = $stokLembarBeforeLama - $jumlah;
            $stokKubikasiAfterLama = $stokKubikasiBeforeLama - $kubikasiPindah;
            $nilaiStokAfterLama = $nilaiStokBeforeLama - $nilaiPindah;

            $logKeluar = HppVeneerJadiLog::create([
                'id_jenis_kayu' => $stokLama->id_jenis_kayu,
                'panjang' => $stokLama->panjang,
                'lebar' => $stokLama->lebar,
                'tebal' => $stokLama->tebal,
                'kw_grade' => $stokLama->kw_grade,
                'tanggal' => now(),
                'tipe_transaksi' => 'KELUAR',
                'referensi_type' => static::class,
                'referensi_id' => $model->id,
                'total_lembar' => $jumlah,
                'total_kubikasi' => $kubikasiPindah,
                'hpp_pekerja' => 0,
                'hpp_bahan_penolong' => 0,
                'hpp_average' => $stokLama->hpp_average,
                'nilai_stok' => $nilaiPindah,
                'stok_lembar_before' => $stokLembarBeforeLama,
                'stok_kubikasi_before' => $stokKubikasiBeforeLama,
                'nilai_stok_before' => $nilaiStokBeforeLama,
                'stok_lembar_after' => $stokLembarAfterLama,
                'stok_kubikasi_after' => $stokKubikasiAfterLama,
                'nilai_stok_after' => $nilaiStokAfterLama,
                'keterangan' => sprintf(
                    'Hasil pilih veneer palet %s berubah KW %s -> %s, diterima oleh: %s pada %s',
                    $model->no_palet,
                    $kwAsal,
                    $kwHasil,
                    $userName,
                    now()->translatedFormat('d F Y H:i')
                ),
            ]);

            $stokLama->update([
                'stok_lembar' => $stokLembarAfterLama,
                'stok_kubikasi' => $stokKubikasiAfterLama,
                'nilai_stok' => $nilaiStokAfterLama,
                'id_last_log' => $logKeluar->id,
            ]);

            // ── 2. MASUKKAN ke baris KW baru (buat kalau belum ada) ──────
            $stokBaru = StokVeneerJadi::where('id_jenis_kayu', $stokLama->id_jenis_kayu)
                ->where('panjang', $stokLama->panjang)
                ->where('lebar', $stokLama->lebar)
                ->where('tebal', $stokLama->tebal)
                ->where('kw_grade', $kwHasil)
                ->lockForUpdate()
                ->first();

            if (! $stokBaru) {
                $stokBaru = StokVeneerJadi::create([
                    'id_jenis_kayu' => $stokLama->id_jenis_kayu,
                    'panjang' => $stokLama->panjang,
                    'lebar' => $stokLama->lebar,
                    'tebal' => $stokLama->tebal,
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

            $stokLembarBeforeBaru = $stokBaru->stok_lembar;
            $stokKubikasiBeforeBaru = $stokBaru->stok_kubikasi;
            $nilaiStokBeforeBaru = $stokBaru->nilai_stok;

            // Nilai yang dibawa masuk pakai HPP dari stok ASAL (harga
            // pokoknya tidak berubah cuma karena grade berubah).
            $nilaiMasuk = $nilaiPindah;

            $stokLembarAfterBaru = $stokLembarBeforeBaru + $jumlah;
            $stokKubikasiAfterBaru = $stokKubikasiBeforeBaru + $kubikasiPindah;
            $nilaiStokAfterBaru = $nilaiStokBeforeBaru + $nilaiMasuk;

            // Rata-rata tertimbang: gabungkan nilai stok tujuan yang sudah
            // ada dengan nilai yang baru masuk, dibagi total lembar.
            $hppAverageAfterBaru = $stokLembarAfterBaru > 0
                ? ($nilaiStokAfterBaru / $stokLembarAfterBaru)
                : 0;

            $logMasuk = HppVeneerJadiLog::create([
                'id_jenis_kayu' => $stokBaru->id_jenis_kayu,
                'panjang' => $stokBaru->panjang,
                'lebar' => $stokBaru->lebar,
                'tebal' => $stokBaru->tebal,
                'kw_grade' => $stokBaru->kw_grade,
                'tanggal' => now(),
                'tipe_transaksi' => 'MASUK',
                'referensi_type' => static::class,
                'referensi_id' => $model->id,
                'total_lembar' => $jumlah,
                'total_kubikasi' => $kubikasiPindah,
                'hpp_pekerja' => 0,
                'hpp_bahan_penolong' => 0,
                'hpp_average' => $hppAverageAfterBaru,
                'nilai_stok' => $nilaiMasuk,
                'stok_lembar_before' => $stokLembarBeforeBaru,
                'stok_kubikasi_before' => $stokKubikasiBeforeBaru,
                'nilai_stok_before' => $nilaiStokBeforeBaru,
                'stok_lembar_after' => $stokLembarAfterBaru,
                'stok_kubikasi_after' => $stokKubikasiAfterBaru,
                'nilai_stok_after' => $nilaiStokAfterBaru,
                'keterangan' => sprintf(
                    'Hasil pilih veneer palet %s naik/turun KW %s -> %s, diterima oleh: %s pada %s',
                    $model->no_palet,
                    $kwAsal,
                    $kwHasil,
                    $userName,
                    now()->translatedFormat('d F Y H:i')
                ),
            ]);

            $stokBaru->update([
                'stok_lembar' => $stokLembarAfterBaru,
                'stok_kubikasi' => $stokKubikasiAfterBaru,
                'nilai_stok' => $nilaiStokAfterBaru,
                'hpp_average' => $hppAverageAfterBaru,
                'id_last_log' => $logMasuk->id,
            ]);
        });
    }
}
