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
        'order',
        'hidden',
    ];

    protected $casts = [
        'akun' => 'array',
        'hidden' => 'boolean',
    ];


    // RELASI HIERARKI
    public function parent()
    {
        return $this->belongsTo(AkunGroup::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(AkunGroup::class, 'parent_id')->orderBy('order');
    }

    //| RECURSIVE: Ambil struktur tree lengkap
    public function allAccountsRecursive()
    {
        $accounts = collect($this->akunList()->get());

        foreach ($this->children as $child) {
            $accounts = $accounts->merge(
                $child->allAccountsRecursive()
            );
        }

        return $accounts;
    }
    public function mappedTree()
    {
        return [
            'group' => $this,
            'accounts' => $this->akunList()->get(),
            'children' => $this->children->map(fn($child) => $child->mappedTree()),
        ];
    }
}
