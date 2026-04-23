<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Lahan extends Model
{
    protected $table = 'lahans';
    protected $primaryKey = 'id';
    //
    protected $fillable = [
        'kode_lahan',
        'nama_lahan',
        'detail',


    ];
    public function penggunaanLahanRotaries()
    {
        return $this->hasMany(PenggunaanLahanRotary::class, 'id_lahan');
    }

    public function tempatKayu(): HasMany
    {
        return $this->hasMany(TempatKayu::class, 'id_lahan');
    }
    public function detailKayuMasuk(): HasMany
    {
        return $this->hasMany(DetailKayuMasuk::class, 'id_lahan');
    }
    public function detailTurusanKayus()
    {
        return $this->hasMany(DetailTurusanKayu::class, 'lahan_id');
    }
    public function summaries(): HasMany
    {
        // Pastikan nama model dan foreign key sudah sesuai dengan database kamu
        return $this->hasMany(HppAverageSummarie::class, 'id_lahan');
    }
}
