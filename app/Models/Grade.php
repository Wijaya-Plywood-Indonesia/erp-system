<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Grade extends Model
{
    protected $table = 'grades';
    protected $fillable = [
        'nama_grade',
        'id_kategori_barang',
    ];

    public function kategoriBarang()
    {
        return $this->belongsTo(KategoriBarang::class, 'id_kategori_barang');
    }

    public function barangSetengahJadiHp()
    {
        return $this->hasMany(BarangSetengahJadiHp::class, 'id_grade');
    }

    public function gradeRules(): HasMany
    {
        return $this->hasMany(GradeRule::class, 'id_grade');
    }

    /**
     * Sesi grading yang berakhir dengan rekomendasi grade ini.
     * Berguna untuk laporan dan statistik.
     */


    public function gradingSessions(): HasMany
    {
        return $this->hasMany(GradingSession::class, 'hasil_grade_id');
    }
}
