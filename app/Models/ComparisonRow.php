<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ComparisonRow extends Model
{
    protected $table = 'kayu_compare_temp';

    public $incrementing = false;
    public $timestamps = false;

    protected $primaryKey = 'id'; // pakai ROW_NUMBER()

    protected $guarded = [];
}