<?php

namespace App\Models;

use App\Events\ProductionUpdated;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DetailBarangDikerjakan extends Model
{
    protected $table = 'detail_barang_dikerjakan';

    protected $fillable = [
        'id_produksi_nyusup',
        'id_pegawai_nyusup',
        'id_barang_setengah_jadi_hp',
        'no_palet',
        'modal',
        'hasil',
        'jumlah_dikembalikan', // 🆕 total bahan (modal - hasil) yang sudah dikembalikan ke Gudang Satu
        'diserahkan_at',
        'diserahkan_by',
        'id_serah_terima_gudang_satu',
    ];

    protected $casts = [
        'diserahkan_at' => 'datetime',
    ];

    public function produksiNyusup()
    {
        return $this->belongsTo(ProduksiNyusup::class, 'id_produksi_nyusup');
    }

    public function PegawaiNyusup()
    {
        return $this->belongsTo(PegawaiNyusup::class, 'id_pegawai_nyusup');
    }

    public function barangSetengahJadiHp()
    {
        return $this->belongsTo(BarangSetengahJadiHp::class, 'id_barang_setengah_jadi_hp');
    }

    protected static function booted()
    {
        // Menggunakan static::saved mencakup Created dan Updated
        static::saved(function ($model) {
            if ($model->id_produksi_nyusup) {
                ProductionUpdated::dispatch($model->id_produksi_nyusup, 'nyusup');
            }
        });

        static::deleted(function ($model) {
            if ($model->id_produksi_nyusup) {
                ProductionUpdated::dispatch($model->id_produksi_nyusup, 'nyusup');
            }
        });
    }

    public function serahTerima(): BelongsTo
    {
        return $this->belongsTo(SerahTerimaGudangSatu::class, 'id_serah_terima_gudang_satu');
    }

    /**
     * 🆕 Sisa bahan (modal - hasil) yang belum dikembalikan ke Gudang Satu.
     *
     * "modal" = bahan yang diambil dari Gudang Satu untuk baris ini.
     * "hasil" = bagian dari modal yang benar-benar jadi produk (dan sudah
     * diserahkan lewat tombol "Serah"). Selisihnya adalah bahan yang tidak
     * terpakai dan bisa dikembalikan sebagai stok di Gudang Satu.
     *
     * Dipakai untuk menampilkan & memvalidasi tombol "Kembalikan Sisa".
     */
    public function getSisaAttribute(): float
    {
        $sisa = (float) $this->modal - (float) $this->hasil - (float) $this->jumlah_dikembalikan;

        return max(0, $sisa);
    }

    /**
     * 🆕 Catat sejumlah $jumlah dari sisa (modal - hasil) sebagai sudah
     * dikembalikan ke Gudang Satu.
     *
     * SENGAJA TIDAK menyentuh StokGudangSatu di sini. Stok baru benar-benar
     * dipotong sebesar "modal" pada SAAT produksi divalidasi (lihat
     * ValidasiNyusupObserver), jadi tombol "Kembalikan Sisa" dipakai
     * SEBELUM validasi — cukup menaikkan jumlah_dikembalikan, dan nanti
     * observer akan otomatis memotong stok sebesar (modal -
     * jumlah_dikembalikan), bukan modal penuh.
     *
     * Dikunci (lockForUpdate) & divalidasi ulang di dalam transaksi supaya
     * aman dari race condition kalau ada 2 orang input barengan.
     */
    public function kembalikanSisa(float $jumlah): self
    {
        if ($jumlah <= 0) {
            throw new \RuntimeException('Jumlah pengembalian harus lebih dari 0.');
        }

        return \Illuminate\Support\Facades\DB::transaction(function () use ($jumlah) {
            $detail = self::query()->lockForUpdate()->findOrFail($this->id);

            $sisaSaatIni = (float) $detail->modal - (float) $detail->hasil - (float) $detail->jumlah_dikembalikan;

            if ($jumlah > $sisaSaatIni) {
                throw new \RuntimeException("Jumlah melebihi sisa yang bisa dikembalikan ({$sisaSaatIni}).");
            }

            $detail->update([
                'jumlah_dikembalikan' => (float) $detail->jumlah_dikembalikan + $jumlah,
            ]);

            return $detail->fresh();
        });
    }
}
