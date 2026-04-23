<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PenggunaanLahanRotary extends Model
{
    protected $table = 'penggunaan_lahan_rotaries';
    protected $primaryKey = 'id';
    //
    protected $fillable = [
        'id_lahan',
        'id_produksi',
        'id_jenis_kayu',
        'jumlah_batang',
        'hpp_average',

    ];

    protected $casts = [
        'jumlah_batang' => 'integer',
        'hpp_average' => 'decimal:2', // ✅ Cast ke decimal
    ];

    public function produksi_rotary()
    {
        return $this->belongsTo(ProduksiRotary::class, 'id_produksi');
    }
    public function lahan()
    {
        return $this->belongsTo(Lahan::class, 'id_lahan', 'id');
    }
    public function jenisKayu()
    {
        return $this->belongsTo(JenisKayu::class, 'id_jenis_kayu', 'id');
    }
    public function detailProduksiPalet()
    {
        return $this->hasMany(DetailHasilPaletRotary::class, 'id_penggunaan_lahan');
    }
    public function detailKayuPecah()
    {
        return $this->hasMany(KayuPecahRotary::class, 'id_penggunaan_lahan');
    }

    public function isSelesai(): bool
    {
        return $this->jumlah_batang == 0 && $this->hpp_average > 0;
    }

    /**
     * ✅ Tandai sebagai selesai dengan HPP tertentu
     */
    public function markAsSelesai(float $hppValue): void
    {
        $this->hpp_average = $hppValue;
        $this->save();
    }
}
