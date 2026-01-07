<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ListPekerjaanMenumpuk extends Model
{
    protected $table = 'list_pekerjaan_menumpuk';

    protected $fillable = [
        'id_produksi_pilih_plywood',
        'tanggal',
        'id_barang_setengah_jadi_hp',
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
