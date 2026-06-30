<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlatformBahanHp extends Model
{
    protected $table = 'platform_bahan_hp';

    protected $fillable = [
        'id_produksi_hp',
        'id_barang_setengah_jadi_hp',
        'id_detail_komposisi',
        'no_palet',
        'isi',
    ];

    public function produksiHp()
    {
        return $this->belongsTo(ProduksiHp::class, 'id_produksi_hp');
    }

    public function barangSetengahJadiHp()
    {
        return $this->belongsTo(BarangSetengahJadiHp::class, 'id_barang_setengah_jadi_hp');
    }

    public function detailKomposisi()
    {
        return $this->belongsTo(DetailKomposisi::class, 'id_detail_komposisi');
    }
}