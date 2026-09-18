<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DetailMasuk extends Model
{
    protected $table = 'detail_masuks';

    protected $fillable = [
        'no_palet',
        'kw',
        'isi',
        'id_ukuran',
        'id_jenis_kayu',
        'id_produksi_dryer',
        'id_serah_terima_veneer_basah',
    ];

    // ✅ FIX: sebelumnya no_palet di-cast dgn NomorPaletCast (dipakai untuk
    // alur lama Stik yang menyimpan ID hasil palet rotary), padahal alur
    // baru Dryer (via Serah Terima Veneer Basah dari Gudang) tidak pernah
    // mengisi ID rotary yang valid — no_palet cuma diisi manual oleh user
    // (persis seperti Kedi), jadi harus tampil apa adanya, bukan "AF".
    protected $casts = [
        'no_palet' => 'integer',
    ];

    public function ukuran()
    {
        return $this->belongsTo(Ukuran::class, 'id_ukuran');
    }

    public function jenisKayu()
    {
        return $this->belongsTo(JenisKayu::class, 'id_jenis_kayu', 'id');
    }

    public function produksiDryer()
    {
        return $this->belongsTo(ProduksiPressDryer::class, 'id_produksi_dryer');
    }

    public function serahTerimaVeneerBasah()
    {
        return $this->belongsTo(SerahTerimaVeneerBasah::class, 'id_serah_terima_veneer_basah');
    }
}