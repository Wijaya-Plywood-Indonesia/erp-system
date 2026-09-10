<?php

namespace App\Models;

use App\Traits\HasRouteUuid;
use Illuminate\Database\Eloquent\Model;

class DetailLainLain extends Model
{
    use HasRouteUuid;
    protected $table = 'detail_lain_lains';

    protected $fillable = [
        'tanggal',
        'uuid'
    ];

    public function lainLains()
    {
        return $this->hasMany(LainLain::class, 'id_detail_lain_lain');
    }
}
