<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MasukGrajiTriplek extends Model
{
    protected $table = 'masuk_graji_triplek';

    protected $fillable = [
        'id_produksi_graji_triplek',
        'id_barang_setengah_jadi_hp',
        'no_palet',
        'isi',
    ];

    public function produksiGrajiTriplek()
    {
        return $this->belongsTo(ProduksiGrajitriplek::class, 'id_produksi_graji_triplek');
    }

    public function barangSetengahJadiHp()
    {
        return $this->belongsTo(BarangSetengahJadiHp::class,'id_barang_setengah_jadi_hp');
    }
}
