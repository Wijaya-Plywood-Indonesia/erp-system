<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AkunGroup extends Model
{
    use HasFactory;

    protected $table = 'akun_groups';

    protected $fillable = [
        'nama',
        'parent_id',
        'order',
        'hidden',
    ];

    protected $casts = [
        'hidden' => 'boolean',
    ];

    /*
    |--------------------------------------------------------------------------
    | RELATIONS
    |--------------------------------------------------------------------------
    */

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
        )->withTimestamps();
    }

    /**
     * Parent Group
     */
    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * Children Group
     */
    public function children()
    {
        return $this->hasMany(self::class, 'parent_id')
            ->orderBy('order');
    }

    /*
    |--------------------------------------------------------------------------
    | HELPERS
    |--------------------------------------------------------------------------
    */

    /**
     * Check if group is leaf (tidak punya child)
     */
    public function isLeaf(): bool
    {
        return !$this->children()->exists();
    }

    /**
     * Check if group punya child
     */
    public function hasChildren(): bool
    {
        return $this->children()->exists();
    }

    /**
     * Recursive children (untuk laporan)
     */
    public function childrenRecursive()
    {
        return $this->children()->with('childrenRecursive');
    }

    /*
    |--------------------------------------------------------------------------
    | SCOPES
    |--------------------------------------------------------------------------
    */

    /**
     * Scope hanya group leaf
     */
    public function scopeLeaf($query)
    {
        return $query->doesntHave('children');
    }

    /**
     * Scope hanya yang visible
     */
    public function scopeVisible($query)
    {
        return $query->where('hidden', false);
    }

    /**
     * Scope urut berdasarkan order
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('order');
    }
    public function getTotalAnakAkunsAttribute(): int
    {
        // Kalau leaf → hitung langsung
        if ($this->children()->count() === 0) {
            return $this->anakAkuns()->count();
        }

        // Kalau parent → jumlahkan semua anak
        return $this->children()
            ->withCount('anakAkuns')
            ->get()
            ->sum(function ($child) {
                return $child->anak_akuns_count;
            });
    }
}