<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RencanaKerjaHp extends Model
{
    protected $table = 'rencana_kerja_hp';

    protected $fillable = [
        'id_produksi_hp',
        'id_barang_setengah_jadi_hp',
        'jumlah',
    ];

    public function barangSetengahJadiHp()
    {
        return $this->belongsTo(BarangSetengahJadiHp::class, 'id_barang_setengah_jadi_hp');
    }

    public function produksiHp()
    {
        return $this->belongsTo(ProduksiHp::class, 'id_produksi_hp');
    }
}
