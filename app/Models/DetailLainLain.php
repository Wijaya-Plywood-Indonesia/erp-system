<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DetailLainLain extends Model
{
    protected $table = 'detail_lain_lains';

    protected $fillable = [
        'tanggal',
    ];

    public function lainLains()
    {
        return $this->hasMany(LainLain::class, 'id_detail_lain_lain');
    }
}
