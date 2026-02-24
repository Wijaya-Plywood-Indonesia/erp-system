<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AkunGroup extends Model
{
    use HasFactory;

    protected $fillable = [
        'nama',
        'parent_id',
        'order',
        'hidden',
    ];

    protected $casts = [
        'hidden' => 'boolean',
    ];

    /**
     * Many-to-many: AkunGroup <-> AnakAkun
     */
    public function anakAkuns()
    {
        return $this->belongsToMany(
            AnakAkun::class,
            'akun_group_anak_akun',
            'akun_group_id',
            'anak_akun_id'
        )->orderBy('kode_anak_akun');
    }

    /**
     * Relasi Group Parent
     */
    public function parent()
    {
        return $this->belongsTo(AkunGroup::class, 'parent_id');
    }

    /**
     * Relasi Group Children
     */
    public function children()
    {
        return $this->hasMany(AkunGroup::class, 'parent_id');
    }
}