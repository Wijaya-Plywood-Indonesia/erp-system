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

    public function anakAkun()
{
    return $this->belongsTo(
        \App\Models\AnakAkun::class,
        'no_akun',
        'kode_anak_akun'
    );
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
        'harga' => 'decimal:2',
        'synced_at' => 'datetime',
    ];
}
