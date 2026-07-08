<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HasilTerimaGudangSatu extends Model
{
    protected $table = 'hasil_terima_gudang_satu';

    protected $fillable = [
        'id_produksi_terima_gudang_satu',
        'id_grade',
        'id_jenis_barang',
        'id_ukuran',
        'jumlah',
        'ket',
    ];

    public function produksi(): BelongsTo
    {
        return $this->belongsTo(ProduksiTerimaGudangSatu::class, 'id_produksi_terima_gudang_satu');
    }

    public function grade(): BelongsTo
    {
        return $this->belongsTo(Grade::class, 'id_grade');
    }

    public function jenisBarang(): BelongsTo
    {
        return $this->belongsTo(JenisBarang::class, 'id_jenis_barang');
    }

    public function ukuran(): BelongsTo
    {
        return $this->belongsTo(Ukuran::class, 'id_ukuran');
    }
}
