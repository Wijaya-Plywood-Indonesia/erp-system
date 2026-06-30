<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Komposisi extends Model
{
    protected $table = 'komposisi';
    protected $fillable = [
        'id_barang_setengah_jadi_hp',
    ];

    public function barangSetengahJadiHp()
    {
        return $this->belongsTo(BarangSetengahJadiHp::class, 'id_barang_setengah_jadi_hp');
    }

    public function detailKomposisis()
    {
        return $this->hasMany(DetailKomposisi::class, 'id_komposisi');
    }
}
