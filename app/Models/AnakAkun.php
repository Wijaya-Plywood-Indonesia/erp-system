<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AnakAkun extends Model
{
    use HasFactory;

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
     * Many-to-many: AnakAkun <-> AkunGroup
     */
    public function akunGroups()
    {
        return $this->belongsToMany(
            AkunGroup::class,
            'akun_group_anak_akun',
            'anak_akun_id',
            'akun_group_id'
        );
    }
    public function anakAkuns()
    {
        return $this->belongsToMany(
            AkunGroup::class,
            'akun_group_anak_akun',
            'anak_akun_id',
            'akun_group_id'
        );
    }
    public function getFilamentTitle(): string
    {
        return "{$this->kode_anak_akun} - {$this->nama_anak_akun}";
    }

    /**
     * Label tampilan untuk select Filament
     */
    public function getSelectLabelAttribute()
    {
        return "{$this->kode_anak_akun} - {$this->nama_anak_akun}";
    }

    /**
     * Relasi ke IndukAkun
     */
    public function indukAkun()
    {
        return $this->belongsTo(IndukAkun::class, 'id_induk_akun');
    }

    /**
     * Self Parent Akun
     */
    public function parentAkun()
    {
        return $this->belongsTo(AnakAkun::class, 'parent');
    }

    /**
     * Children AnakAkun
     */
    public function children()
    {
        return $this->hasMany(AnakAkun::class, 'parent');
    }

    /**
     * Sub Anak Akun
     */
    public function subAnakAkuns()
    {
        return $this->hasMany(SubAnakAkun::class, 'id_anak_akun');
    }

    /**
     * Pembuat
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}