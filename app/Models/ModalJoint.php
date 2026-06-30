<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ModalJoint extends Model
{
    protected $table = 'modal_joint';

    protected $fillable = [
        'id_produksi_joint',
        'id_ukuran',
        'id_jenis_kayu',
        'jumlah',
        'kw',
        'no_palet',
    ];

    public function produksiJoint()
    {
        return $this->belongsTo(ProduksiJoint::class, 'id_produksi_joint');
    }

    public function ukuran()
    {
        return $this->belongsTo(Ukuran::class, 'id_ukuran');
    }

    public function jenisKayu()
    {
        return $this->belongsTo(JenisKayu::class, 'id_jenis_kayu');
    }
}
