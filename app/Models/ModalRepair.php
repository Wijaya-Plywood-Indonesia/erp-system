<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ModalRepair extends Model
{
    protected $table = 'modal_repairs';

    protected $fillable = [
        'id_produksi_repair',
        'id_serah_terima_veneer_kering',
        'id_ukuran',
        'id_jenis_kayu',
        'jumlah',
        'kw',
        'nomor_palet',
        'keterangan',
        'sumber',
        'ditutup_manual_at',
        'ditutup_oleh',
        'catatan_penutupan',
    ];

    // Eager load biar nggak N+1 query
    protected $with = [
        'produksiRepair',
        'ukuran',
        'jenisKayu',
    ];

    public function produksiRepair(): BelongsTo
    {
        return $this->belongsTo(ProduksiRepair::class, 'id_produksi_repair');
    }

    /** Ukuran kayu */
    public function ukuran(): BelongsTo
    {
        return $this->belongsTo(Ukuran::class, 'id_ukuran');
    }

    /** Jenis kayu */
    public function jenisKayu(): BelongsTo
    {
        return $this->belongsTo(JenisKayu::class, 'id_jenis_kayu');
    }

    public function rencanaRepairs()
    {
        return $this->hasMany(RencanaRepair::class, 'id_modal_repair');
    }

    public function serahTerimaVeneerKering(): BelongsTo
    {
        return $this->belongsTo(SerahTerimaVeneerKering::class, 'id_serah_terima_veneer_kering');
    }

    // Accesor attributes
    public function getTerpakaiAttribute(): int
    {
        return (int) $this->detailHasilRepairs()->sum('jumlah');
    }

    public function getSisaStokAttribute(): int
    {
        return max(0, (int) $this->jumlah - $this->terpakai);
    }

    public function detailHasilRepairs()
    {
        return $this->hasMany(DetailHasilRepair::class, 'id_modal_repair');
    }
}
