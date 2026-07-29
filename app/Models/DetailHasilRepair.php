<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DetailHasilRepair extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'diserahkan_at' => 'datetime',
    ];

    // Relasi ke Produksi Repair Utama
    public function produksiRepair()
    {
        return $this->belongsTo(ProduksiRepair::class, 'id_produksi_repair');
    }

    // Relasi ke Modal Repair
    public function modalRepair()
    {
        return $this->belongsTo(ModalRepair::class, 'id_modal_repair');
    }

    // Relasi ke Ukuran Final
    public function ukuran()
    {
        return $this->belongsTo(Ukuran::class, 'id_ukuran');
    }

    // Relasi ke User Serah Terima
    public function diserahkanBy()
    {
        return $this->belongsTo(User::class, 'diserahkan_by');
    }

    // Relasi Multi-Select Pegawai Repair via Pivot Table
    public function rencanaPegawais()
    {
        return $this->belongsToMany(
            RencanaPegawai::class,
            'detail_repair_pegawai',
            'detail_hasil_repair_id',
            'rencana_pegawai_repair_id'
        )->withTimestamps();
    }

    public function jenisKayu()
    {
        return $this->belongsTo(JenisKayu::class, 'id_jenis_kayu');
    }
}
