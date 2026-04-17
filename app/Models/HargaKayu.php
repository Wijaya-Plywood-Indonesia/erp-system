<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HargaKayu extends Model
{
    protected $table = 'harga_kayus';

    protected $fillable = [
        'panjang',
        'diameter_terkecil',
        'diameter_terbesar',
        'harga_beli',
        'grade',
        'id_jenis_kayu',
        'harga_baru',
        'updated_by',
        'approved_by',
        'status'
    ];
    protected $casts = [
        'panjang' => 'integer',
        'diameter_terkecil' => 'decimal:2',
        'diameter_terbesar' => 'decimal:2',
        'harga_beli' => 'integer',
    ];
    //
    public function jenisKayu()
    {
        return $this->belongsTo(JenisKayu::class, 'id_jenis_kayu', 'id');
    }
    public function getRentangDiameterAttribute(): string
    {
        if ($this->diameter_terkecil && $this->diameter_terbesar) {
            return "{$this->diameter_terkecil} - {$this->diameter_terbesar}";
        }

        return '-';
    }
}
