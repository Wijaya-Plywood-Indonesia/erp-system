<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Criteria extends Model
{
    protected $fillable = [
        'id_kategori_barang',
        'nama_kriteria',
        'kode_kriteria',
        'icon_emoji',
        'deskripsi',
        'urutan',
        'bobot',
        'is_active',
    ];

    protected $casts = [
        'bobot'     => 'float',
        'is_active' => 'boolean',
        'urutan'    => 'integer',
    ];

    /**
     * Filter criteria berdasarkan kategori barang.
     * Penggunaan: Criterion::forKategori(4)->active()->get()
     */
    public function scopeForKategori($query, int $kategoriId)
    {
        return $query->where('id_kategori_barang', $kategoriId);
    }

    /**
     * Hanya ambil criteria yang aktif, diurutkan sesuai urutan tampil.
     * Penggunaan: Criterion::active()->get()
     */
    public function scopeActive($query)
    {
        return $query
            ->where('is_active', true)
            ->orderBy('urutan', 'asc');
    }

    // ── Relations ────────────────────────────────────────────────────────────

    /**
     * Kategori barang pemilik pertanyaan ini.
     */
    public function kategoriBarang(): BelongsTo
    {
        return $this->belongsTo(KategoriBarang::class, 'id_kategori_barang');
    }

    /**
     * Aturan per grade untuk kriteria ini.
     * Contoh: kriteria "Pecah Terbuka" punya 3 aturan (BBCC, UTY, BTR).
     */
    public function gradeRules(): HasMany
    {
        return $this->hasMany(GradeRule::class);
    }

    /**
     * Semua jawaban yang pernah diberikan untuk kriteria ini.
     */
    public function sessionAnswers(): HasMany
    {
        return $this->hasMany(SessionAnswer::class);
    }
}
