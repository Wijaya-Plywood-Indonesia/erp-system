<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Riwayat pengiriman jurnal produksi ke akuntansi (satu baris per no_dokumen per kirim).
 */
class JurnalProduksiKirimLog extends Model
{
    protected $table = 'jurnal_produksi_kirim_logs';

    protected $fillable = [
        'jenis', 'tanggal', 'no_dokumen', 'status', 'payload_hash',
        'total_debit', 'total_kredit', 'jumlah_baris', 'pesan', 'dikirim_oleh',
    ];

    protected $casts = [
        'tanggal' => 'date',
        'total_debit' => 'float',
        'total_kredit' => 'float',
    ];

    public function pengirim(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'dikirim_oleh');
    }

    /** Status yang berarti jurnal sudah ada di akuntansi. */
    public const STATUS_BERHASIL = ['terkirim', 'diperbarui'];
}
