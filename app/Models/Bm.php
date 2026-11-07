<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Bm extends Model
{
    protected $table = 'bm';

    protected $fillable = [
        'siklus_uuid', 'kategori', 'no_nota', 'tanggal', 'dari', 'id_bk_referensi',
        'keterangan', 'status', 'dibuat_oleh', 'divalidasi_oleh', 'divalidasi_at',
        'ref_no_bk', 'ref_pabrik', 'ref_snapshot',
    ];

    protected $casts = ['tanggal' => 'date', 'divalidasi_at' => 'datetime', 'ref_snapshot' => 'array'];

    public function detail(): HasMany { return $this->hasMany(BmDetail::class, 'id_bm'); }
    public function pembuat(): BelongsTo { return $this->belongsTo(User::class, 'dibuat_oleh'); }
    public function validator(): BelongsTo { return $this->belongsTo(User::class, 'divalidasi_oleh'); }
    public function divalidasiOleh(): BelongsTo { return $this->validator(); }
    public function bkReferensi(): BelongsTo { return $this->belongsTo(Bk::class, 'id_bk_referensi'); }

    // Relasi yang dipakai service stok lama (Plywood/Platform) lewat $nota->plywoodMutasi dst.
    public function plywoodMutasi(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(PlywoodMutasi::class, 'id_bm');
    }

    public function platformJadiMutasi(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(PlatformJadiMutasi::class, 'id_bm');
    }

    public function platformMthMutasi(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(PlatformMthMutasi::class, 'id_bm');
    }

    /** Terkunci untuk edit/hapus: sudah divalidasi, kecuali untuk super_admin. */
    public function terkunci(): bool
    {
        $u = auth()->user();
        return $this->sudahDivalidasi() && ! ($u && method_exists($u, 'hasRole') && $u->hasRole('super_admin'));
    }

    public function sudahDivalidasi(): bool { return $this->status === 'divalidasi'; }
}
