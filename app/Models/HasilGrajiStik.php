<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HasilGrajiStik extends Model
{
    protected $table = 'hasil_graji_stiks';

    protected $fillable = [
        'id_graji_stiks',
        'id_modal_graji_stiks',
        'hasil_graji',
    ];

    public function grajiStik()
    {
        return $this->belongsTo(GrajiStik::class, 'id_graji_stiks');
    }

    public function modalGrajiStik()
    {
        return $this->belongsTo(ModalGrajiStik::class, 'id_modal_graji_stiks');
    }
}
