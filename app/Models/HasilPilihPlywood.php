<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HasilPilihPlywood extends Model
{
    protected $table = 'hasil_pilih_plywood';

    protected $fillable = [
        'id_produksi_pilih_plywood',
        'id_barang_setengah_jadi_hp',
        'jenis_cacat',
        'jumlah',
        'kondisi',
        'ket',
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
