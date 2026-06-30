<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ModalGrajiStik extends Model
{
    protected $table = 'modal_graji_stiks';

    protected $fillable = [
        'id_graji_stiks',
        'id_ukuran',
        'jumlah_bahan',
        'nomor_palet'
    ];

    public function grajiStik()
    {
        return $this->belongsTo(GrajiStik::class, 'id_graji_stiks');
    }

    public function ukuran()
    {
        return $this->belongsTo(Ukuran::class, 'id_ukuran');
    }
}
