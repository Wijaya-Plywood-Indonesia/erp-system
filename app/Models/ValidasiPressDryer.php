<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ValidasiPressDryer extends Model
{
    protected $table = 'validasis';

    protected $fillable = [
        'id_produksi_dryer',
        'role',
        'status',
    ];

    public function produksi()
    {
        return $this->belongsTo(ProduksiPressDryer::class, 'id_produksi_dryer');
    }

    protected static function booted()
    {
        static::saved(function ($model) {
            // Hanya trigger kalau status = 'disetujui' (sesuaikan dengan nilai status di sistemmu)
            if ($model->id_produksi_dryer && $model->status === 'divalidasi') {
                \App\Events\ProductionUpdated::dispatch(
                    $model->id_produksi_dryer,
                    'dryer'
                );
            }
        });
    }
}