<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AkunGroup extends Model
{
    //
    protected $fillable = [
        'nama',
        'parent_id',
        'akun',
        'hidden',
    ];

    protected $casts = [
        'akun' => 'array',
        'hidden' => 'boolean',
    ];

    public function parent()
    {
        return $this->belongsTo(AkunGroup::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(AkunGroup::class, 'parent_id');
    }
}
