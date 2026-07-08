<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SerahTerimaMasukHp extends Model
{
    protected $table = 'serah_terima_masuk_hp';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $casts = [
        'panjang'        => 'float',
        'lebar'          => 'float',
        'tebal'          => 'float',
        'tanggal_keluar' => 'datetime',
        'diterima_at'    => 'datetime',
    ];

    public function operator()
    {
        return $this->belongsTo(User::class, 'dikeluarkan_by');
    }

    public function penerima()
    {
        return $this->belongsTo(User::class, 'diterima_by');
    }

    public function kubikasi(): float
    {
        return ($this->panjang * $this->lebar * $this->tebal * $this->jumlah_lembar) / 10000000;
    }
}
