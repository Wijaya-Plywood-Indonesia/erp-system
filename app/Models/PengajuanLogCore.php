<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PengajuanLogCore extends Model
{
    protected $fillable = [
        'stok_log_core_id',
        'jumlah',
        'keterangan',
        'status',
        'created_by',
        'validated_by',
        'validated_at',
    ];

    protected $casts = [
        'jumlah' => 'float',
        'validated_at' => 'datetime',
    ];

    public function stokLogCore()
    {
        return $this->belongsTo(StokLogCore::class, 'stok_log_core_id');
    }

    public function pembuat()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function validator()
    {
        return $this->belongsTo(User::class, 'validated_by');
    }
}
