<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Bk extends Model
{
    protected $table = 'bk';

    protected $fillable = [
        'siklus_uuid', 'kategori', 'no_nota', 'tanggal', 'tujuan_nota', 'alamat',
        'keterangan', 'status', 'dibuat_oleh', 'divalidasi_oleh', 'divalidasi_at',
        'metode_pembayaran', 'id_rekening_perusahaan',
        'id_bmk_asal', 'ref_no_bkk',
    ];

    protected $casts = ['tanggal' => 'date', 'divalidasi_at' => 'datetime'];

    public function detail(): HasMany { return $this->hasMany(BkDetail::class, 'id_bk'); }
    public function pembuat(): BelongsTo { return $this->belongsTo(User::class, 'dibuat_oleh'); }
    public function validator(): BelongsTo { return $this->belongsTo(User::class, 'divalidasi_oleh'); }
    public function divalidasiOleh(): BelongsTo { return $this->validator(); }
    public function dibuatOleh(): BelongsTo { return $this->pembuat(); }
    public function rekeningPerusahaan(): BelongsTo { return $this->belongsTo(RekeningPerusahaan::class, 'id_rekening_perusahaan'); }
    /** Mutasi veneer milik BK ini (dipakai nota/surat jalan, sama seperti menu lama). */
    public function mutasi(): \Illuminate\Database\Eloquent\Relations\HasOne { return $this->hasOne(VeneerMutasi::class, 'id_bk'); }
    public function bmTerkait(): HasMany { return $this->hasMany(Bm::class, 'id_bk_referensi'); }
    /** BKR: BMK tempat barang pinjaman ini dikembalikan. */
    public function bmkAsal(): BelongsTo { return $this->belongsTo(Bm::class, 'id_bmk_asal'); }
    /** BKK: BMR (di pabrik ini) yang menutup pinjaman. */
    public function bmrPengembalian(): HasMany { return $this->hasMany(Bm::class, 'id_bkk_asal'); }

    // Relasi yang dipakai service stok lama (Plywood/Platform) lewat $nota->plywoodMutasi dst.
    public function plywoodMutasi(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(PlywoodMutasi::class, 'id_bk');
    }

    public function platformJadiMutasi(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(PlatformJadiMutasi::class, 'id_bk');
    }

    public function platformMthMutasi(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(PlatformMthMutasi::class, 'id_bk');
    }

    /** Terkunci untuk edit/hapus: sudah divalidasi, kecuali untuk super_admin. */
    public function terkunci(): bool
    {
        $u = auth()->user();
        return $this->sudahDivalidasi() && ! ($u && method_exists($u, 'hasRole') && $u->hasRole('super_admin'));
    }

    public function sudahDivalidasi(): bool { return $this->status !== 'draft'; }

    /** Warna baris: abu (draft), merah (barang keluar), kuning (BKK: sudah diterima, belum kembali), hijau (selesai). */
    public function warna(): string
    {
        return match ($this->status) {
            'draft' => 'gray',
            'dipinjam' => 'warning',
            'selesai' => 'success',
            default => 'danger',
        };
    }

    /** Barang masih di luar: belum kembali / belum selesai (merah atau kuning). */
    public function masihDiLuar(): bool { return in_array($this->status, ['divalidasi', 'dipinjam'], true); }

    public function labelStatus(): string
    {
        return match ($this->status) {
            'draft' => 'Draft',
            'selesai' => 'Selesai',
            'dipinjam' => 'Dipinjam (belum kembali)',
            default => 'Barang di luar',
        };
    }
}
