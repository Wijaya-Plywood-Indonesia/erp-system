<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BahanPilihPlywood extends Model
{
    protected $table = 'bahan_pilih_plywood';

    protected $fillable = [
        'id_produksi_pilih_plywood',
        'id_barang_setengah_jadi_hp',
        'no_palet',
        'jumlah',
    ];

    public function produksiPilihPlywood()
    {
        return $this->belongsTo(ProduksiPilihPlywood::class, 'id_produksi_pilih_plywood');
    }
    
    public function barangSetengahJadiHp()
    {
        return $this->belongsTo(BarangSetengahJadiHp::class, 'id_barang_setengah_jadi_hp');
    }
}
