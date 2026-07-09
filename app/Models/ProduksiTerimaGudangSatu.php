<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProduksiTerimaGudangSatu extends Model
{
    protected $table = 'produksi_terima_gudang_satu';

    protected $fillable = [
        'tanggal_produksi',
        'kendala',
    ];

    public function pegawaiTerimaGudangSatu()
    {
        return $this->hasMany(PegawaiTerimaGudangSatu::class, 'id_produksi_terima_gudang_satu');
    }

    public function bahanTerimaGudangSatu()
    {
        return $this->hasMany(BahanTerimaGudangSatu::class, 'id_produksi_terima_gudang_satu');
    }

    public function validasiTerimaGudangSatu()
    {
        return $this->hasMany(ValidasiTerimaGudangSatu::class, 'id_produksi_terima_gudang_satu');
    }

    public function validasiTerakhir()
    {
        return $this->hasOne(ValidasiTerimaGudangSatu::class, 'id_produksi_terima_gudang_satu')->latestOfMany();
    }

    // app/Models/ProduksiTerimaGudangSatu.php

    public function hasil(): HasMany
    {
        return $this->hasMany(HasilTerimaGudangSatu::class, 'id_produksi_terima_gudang_satu');
    }

    public function serahTerima(): HasMany
    {
        return $this->hasMany(SerahTerimaGudangSatu::class, 'id_produksi_terima_gudang_satu');
    }
}
