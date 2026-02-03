<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AnakAkun extends Model
{
    //
    protected $table = 'anak_akuns';

    protected $fillable = [
        'id_induk_akun',
        'kode_anak_akun',
        'nama_anak_akun',
        'keterangan',
        'parent',
        'status',
        'created_by',
    ];

    /**
     * Relasi ke IndukAkun
     * Banyak Anak Akun milik satu Induk Akun
     */
    public function indukAkun()
    {
        return $this->belongsTo(IndukAkun::class, 'id_induk_akun');
    }
    /**
     * Relasi Self Parent
     * AnakAkun dapat memiliki 1 parent (AnakAkun lain)
     */
    public function parentAkun()
    {
        return $this->belongsTo(AnakAkun::class, 'parent');
    }
    /**
     * Relasi Self Children
     * AnakAkun dapat memiliki banyak child (AnakAkun lain)
     */
    public function children()
    {
        return $this->hasMany(AnakAkun::class, 'parent');
    }

    /**
     * Relasi ke User Pembuat (created_by)
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Relasi ke SubAnakAkun
     * Satu Anak Akun punya banyak Sub Anak Akun
     */
    public function subAnakAkuns()
    {
        return $this->hasMany(SubAnakAkun::class, 'id_anak_akun');
    }
}
