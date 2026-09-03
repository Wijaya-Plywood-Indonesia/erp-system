<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BarangSetengahJadiHp extends Model
{
    protected $table = 'barang_setengah_jadi_hp';

    protected $fillable = [
        'id_jenis_barang',
        'id_ukuran',
        'id_grade',
        'keterangan',
        'harga',
    ];

    protected $casts = [
        'harga' => 'decimal:2',
    ];

    public function ukuran()
    {
        return $this->belongsTo(Ukuran::class, 'id_ukuran');
    }

    public function jenisBarang()
    {
        return $this->belongsTo(JenisBarang::class, 'id_jenis_barang');
    }

    public function grade()
    {
        return $this->belongsTo(Grade::class, 'id_grade');
    }

    public function modalSandings()
    {
        return $this->hasMany(ModalSanding::class, 'id_barang_setengah_jadi');
    }

    public function detailDempuls()
    {
        return $this->hasMany(DetailDempul::class, 'id_barang_setengah_jadi_hp');
    }

    public function scopeKategori($query, string $keyword)
    {
        return $query->whereHas('grade.kategoriBarang', function ($q) use ($keyword) {
            $q->where('nama_kategori', 'like', "%{$keyword}%");
        });
    }

    public function getLabelAttribute(): string
    {
        $kategori = $this->grade?->kategoriBarang?->nama_kategori ?? '-';
        $jenis = $this->jenisBarang?->nama_jenis_barang;
        $ukuran = $this->ukuran?->nama_ukuran;
        $grade = $this->grade?->nama_grade;

        $parts = array_filter([$kategori, $jenis, $ukuran, $grade ? "{$grade}" : null]);

        return implode(' - ', $parts);
    }
}
