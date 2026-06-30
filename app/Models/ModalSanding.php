<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ModalSanding extends Model
{
    use HasFactory;

    protected $table = 'modal_sandings';

    protected $fillable = [
        'id_produksi_sanding',
        'id_barang_setengah_jadi',
        'kuantitas',
        'jumlah_sanding_face',
        'jumlah_sanding_back',
        'no_palet',
    ];

    /**
     * Relasi ke ProduksiSanding
     * modal_sandings.id_produksi_sanding -> produksi_sandings.id
     */
    public function produksiSanding()
    {
        return $this->belongsTo(ProduksiSanding::class, 'id_produksi_sanding');
    }

    /**
     * Relasi ke BarangSetengahJadi
     * modal_sandings.id_barang_setengah_jadi -> barang_setengah_jadi_hp.id
     */
    public function barangSetengahJadi()
    {
        return $this->belongsTo(BarangSetengahJadiHp::class, 'id_barang_setengah_jadi');
    }
}
