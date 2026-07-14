<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BahanPenolongTembeltriplek extends Model
{
    protected $table = 'bahan_penolong_tembel_triplek';

    protected $fillable = [
        'id_produksi_tembel_triplek',
        'nama_bahan',
        'jumlah',
    ];

    public function produksiTembeltriplek()
    {
        return $this->belongsTo(ProduksiTembeltriplek::class, 'id_produksi_tembel_triplek');
    }

    public function bahanPenolong()
    {
        return $this->belongsTo(BahanPenolongProduksi::class, 'id_bahan_penolong');
    }

    public function serahTerimaTriplekCacat(): BelongsTo
    {
        return $this->belongsTo(SerahTerimaTriplekCacat::class, 'id_serah_terima_triplek_cacat');
    }
}
