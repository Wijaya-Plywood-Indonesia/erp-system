<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PersentaseKayuReport extends Model
{
    protected $table = 'persentase_kayu_reports';

    // ini penting supaya Eloquent tidak nyari table
    public $timestamps = false;

    protected $guarded = [];
}
