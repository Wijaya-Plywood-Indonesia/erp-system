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

    protected $casts = [
        'status' => 'boolean',
    ];

    /*
    |--------------------------------------------------------------------------
    | RELATIONS
    |--------------------------------------------------------------------------
    */

    /**
     * Many to Many - Akun Group
     */
    public function akunGroups()
    {
        return $this->belongsToMany(
            AkunGroup::class,
            'akun_group_anak_akun',
            'anak_akun_id',
            'akun_group_id'
        )->withTimestamps();
    }

    /**
     * Induk Akun
     */
    public function indukAkun()
    {
        return $this->belongsTo(IndukAkun::class, 'id_induk_akun');
    }

    /**
     * Parent Self Reference
     */
    public function parentAkun()
    {
        return $this->belongsTo(self::class, 'parent');
    }

    /**
     * Children Self Reference
     */
    public function children()
    {
        return $this->hasMany(self::class, 'parent');
    }

    /**
     * Sub Anak Akun
     */
    public function subAnakAkuns()
    {
        return $this->hasMany(SubAnakAkun::class, 'id_anak_akun');
    }

    /**
     * Creator
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /*
    |--------------------------------------------------------------------------
    | HELPERS
    |--------------------------------------------------------------------------
    */

    /**
     * Check if akun is leaf (tidak punya children)
     */
    public function isLeaf(): bool
    {
        return !$this->children()->exists();
    }

    /**
     * Check if akun punya children
     */
    public function hasChildren(): bool
    {
        return $this->children()->exists();
    }

    /*
    |--------------------------------------------------------------------------
    | SCOPES
    |--------------------------------------------------------------------------
    */

    /**
     * Scope only active akun
     */
    public function scopeActive($query)
    {
        return $query->where('status', true);
    }

    /**
     * Scope root akun (tanpa parent)
     */
    public function scopeRoot($query)
    {
        return $query->whereNull('parent');
    }

    public function getLabelAttribute(): string
    {
        return "{$this->kode_anak_akun} - {$this->nama_anak_akun}";
    }
}