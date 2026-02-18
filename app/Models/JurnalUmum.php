<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JurnalUmum extends Model
{
    //
    /**
     * Karena nama tabel tidak mengikuti konvensi Laravel (harusnya jurnal_umums),
     * maka wajib didefinisikan manual.
     */
    protected $table = 'jurnal_umum';

    protected $guarded = [];

    /**
     * Kolom yang boleh diisi mass-assignment.
     */
    protected $fillable = [
        'nama_akun',
        'tgl',
        'jurnal',
        'no_akun',
        'no-dokumen',
        'mm',
        'nama',
        'keterangan',
        'map',
        'hit_kbk',
        'banyak',
        'm3',
        'harga',
        'created_by',
        'status',
        'synced_at',
        'synced_by',
    ];

    public function syncedBy()
    {
        return $this->belongsTo(User::class, 'synced_by');
    }

    /**
     * Casting untuk tipe data.
     */
    protected $casts = [
        'tgl' => 'date',
        'jurnal' => 'integer',
        'no_akun' => 'string',
        'mm' => 'integer',
        'banyak' => 'integer',
        'm3' => 'decimal:4',
        'synced_at' => 'datetime',
    ];
    public function subAkun()
    {
        return $this->belongsTo(
            SubAnakAkun::class,
            'no_akun',
            'kode_sub_anak_akun'
        );
    }
    public function anakAkun()
    {
        return $this->subAkun?->anakAkun();
    }

    public function indukAkun()
    {
        return $this->subAkun?->indukAkun();
    }

    public function getNilaiAttribute()
    {
        // Jika debit/kredit nanti pakai map (d/k)

        // Default jika tidak ada hit_kbk
        if (empty($this->hit_kbk) || $this->hit_kbk === '0') {
            return $this->harga ?? 0;
        }

        return match ($this->hit_kbk) {
            'b', 'B' => ($this->banyak ?: 1) * $this->harga,
            'm3', 'M3' => ($this->m3 ?: 1) * $this->harga,
            default => $this->harga,
        };
    }
    public function getDebitAttribute()
    {
        return in_array(strtolower($this->map), ['d', 'debit'])
            ? $this->nilai
            : 0;
    }

    public function getKreditAttribute()
    {
        return in_array(strtolower($this->map), ['k', 'kredit'])
            ? $this->nilai
            : 0;
    }

}
