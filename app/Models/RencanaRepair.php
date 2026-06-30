<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RencanaRepair extends Model
{
    use HasFactory;

    protected $table = 'rencana_repairs';

    protected $fillable = [
        'id_produksi_repair',
        'id_rencana_pegawai',
        'id_modal_repair',
        'kw',
    ];

    // === RELASI ===

    /**
     * Relasi ke ProduksiRepair (hari produksi)
     */
    public function produksiRepair(): BelongsTo
    {
        return $this->belongsTo(ProduksiRepair::class, 'id_produksi_repair');
    }

    /**
     * Relasi ke Ukuran
     */
    public function modalRepairs()
    {
        return $this->belongsTo(ModalRepair::class, 'id_modal_repair');
    }

    public function rencanaPegawai()
    {
        return $this->belongsTo(RencanaPegawai::class, 'id_rencana_pegawai');
    }

    public function hasilRepairs()
    {
        return $this->hasMany(HasilRepair::class, 'id_rencana_repair');
    }
}