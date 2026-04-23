<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class HppAverageLog extends Model
{
    protected $fillable = [
        // Migration hpp_average_logs tidak punya lahan_id
        'id_lahan',
        'id_jenis_kayu',
        'grade',
        'panjang',
        'tanggal',
        'tipe_transaksi',
        'keterangan',
        'referensi_id',
        'referensi_type',
        'total_batang',
        'total_kubikasi',
        'harga',
        'nilai_stok',
        'stok_batang_before',
        'stok_kubikasi_before',
        'nilai_stok_before',
        'stok_batang_after',
        'stok_kubikasi_after',
        'nilai_stok_after',
        'hpp_average',
    ];

    protected $casts = [
        'tanggal'              => 'date',
        'total_batang'         => 'integer',
        'total_kubikasi'       => 'decimal:6',
        'harga'                => 'decimal:2',
        'nilai_stok'           => 'decimal:2',
        'stok_batang_before'   => 'integer',
        'stok_kubikasi_before' => 'decimal:6',
        'nilai_stok_before'    => 'decimal:2',
        'stok_batang_after'    => 'integer',
        'stok_kubikasi_after'  => 'decimal:6',
        'nilai_stok_after'     => 'decimal:2',
        'hpp_average'          => 'decimal:4',
    ];

    public function lahan(): BelongsTo
    {
        return $this->belongsTo(Lahan::class, 'id_lahan');
    }

    public function jenisKayu(): BelongsTo
    {
        return $this->belongsTo(JenisKayu::class, 'id_jenis_kayu');
    }

    public function referensi(): MorphTo
    {
        return $this->morphTo();
    }

    // Atau tambahkan mapping morph map
    protected $morphMap = [
        'NotaKayu' => NotaKayu::class,
        'PenggunaanLahanRotary' => PenggunaanLahanRotary::class,
        // 'StokOpnameKayu' => OpnameStokKayu::class, // Hapus jika tidak diperlukan
    ];

    public function scopeKombinasi($query, int $jenisKayuId, string $grade, int $panjang)
    {
        return $query
            ->where('id_jenis_kayu', $jenisKayuId)
            ->where('grade',         $grade)
            ->where('panjang',       $panjang);
    }

    public static function latestForKombinasi(int $jenisKayuId, string $grade, int $panjang): ?self
    {
        return static::kombinasi($jenisKayuId, $grade, $panjang)
            ->orderByDesc('tanggal')
            ->orderByDesc('id')
            ->first();
    }

    public function getTipeTransaksiLabelAttribute(): string
    {
        return match ($this->tipe_transaksi) {
            'masuk'  => 'Kayu Masuk',
            'keluar' => 'Kayu Keluar',
            default  => ucfirst($this->tipe_transaksi),
        };
    }
}
