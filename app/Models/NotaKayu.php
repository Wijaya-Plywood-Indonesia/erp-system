<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotaKayu extends Model
{
    //
    protected $primaryKey = 'id';
    protected $fillable = [
        'id_kayu_masuk',
        'no_nota',
        'penanggung_jawab',
        'penerima',
        'satpam',
        'status'
    ];
    public function kayuMasuk()
    {
        return $this->belongsTo(KayuMasuk::class, 'id_kayu_masuk');
    }
}
