<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Baris Triplek Jadi / Triplek Mth pada BK/BM baru. Stok berubah saat nota divalidasi. */
class BkbmTriplekItem extends Model
{
    protected $table = 'bkbm_triplek_item';
    protected $fillable = ['jenis', 'id_ukuran', 'id_jenis_kayu', 'kw_grade', 'qty', 'm3'];

    public function ukuran() { return $this->belongsTo(Ukuran::class, 'id_ukuran'); }
}
