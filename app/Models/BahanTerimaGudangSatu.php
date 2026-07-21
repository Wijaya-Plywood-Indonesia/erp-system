<?php

namespace App\Models;

use App\Events\ProductionUpdated;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BahanTerimaGudangSatu extends Model
{
    protected $table = 'bahan_terima_gudang_satu';

    protected $fillable = [
        'id_produksi_terima_gudang_satu',
        'id_hasil_terima_gudang_satu',
        'id_serah_terima_gudang_satu',
        'id_barang_setengah_jadi_hp',
        'no_palet',
        'highlight_bahan',
        'jumlah',
    ];

    // ─────────────────────────────────────────────
    // Relations
    // ─────────────────────────────────────────────

    public function produksiTerimaGudangSatu(): BelongsTo
    {
        return $this->belongsTo(ProduksiTerimaGudangSatu::class, 'id_produksi_terima_gudang_satu');
    }

    /**
     * Bahan asal (record serah terima) yang dipakai di sini.
     * Satu bahan bisa dipakai di banyak produksi berbeda (bebas),
     * asalkan sisanya masih cukup — lihat validasi di booted().
     */
    public function serahTerimaGudangSatu(): BelongsTo
    {
        return $this->belongsTo(SerahTerimaGudangSatu::class, 'id_serah_terima_gudang_satu');
    }

    public function barangSetengahJadiHp(): BelongsTo
    {
        return $this->belongsTo(BarangSetengahJadiHp::class, 'id_barang_setengah_jadi_hp');
    }

    // ─────────────────────────────────────────────
    // Booted
    // ─────────────────────────────────────────────

    protected static function booted()
    {
        static::saving(function ($model) {
            if (! $model->id_serah_terima_gudang_satu) {
                return;
            }

            $serah = SerahTerimaGudangSatu::find($model->id_serah_terima_gudang_satu);

            if (! $serah) {
                throw new \RuntimeException('Data serah terima tidak ditemukan.');
            }

            // Syarat: bahan (serah terima) harus SUDAH diterima di Gudang Satu, bebas
            // produksi mana pun yang menerimanya. Kalau masih 'Menunggu', belum boleh
            // dipakai sebagai bahan.
            if ($serah->diterima_oleh === '-') {
                throw new \RuntimeException('Bahan ini belum diterima di Gudang Satu, belum bisa dipakai.');
            }

            // Hitung total pemakaian LAIN (di luar record ini sendiri, penting saat update),
            // dari SEMUA produksi — karena satu bahan memang boleh dipakai lintas produksi.
            $terpakaiLain = self::where('id_serah_terima_gudang_satu', $model->id_serah_terima_gudang_satu)
                ->when($model->exists, fn ($q) => $q->where('id', '!=', $model->id))
                ->sum('jumlah');

            $sisaTersedia = $serah->qty_asli - $terpakaiLain;
            $sisaSetelahIni = $sisaTersedia - $model->jumlah;

            if ($sisaSetelahIni < 0) {
                throw new \RuntimeException("Jumlah melebihi sisa stok bahan ini. Sisa tersedia: {$sisaTersedia}");
            }
        });

        static::saved(function ($model) {
            if ($model->id_produksi_terima_gudang_satu) {
                ProductionUpdated::dispatch($model->id_produksi_terima_gudang_satu, 'terima_gudang_satu');
            }
        });

        static::deleted(function ($model) {
            if ($model->id_produksi_terima_gudang_satu) {
                ProductionUpdated::dispatch($model->id_produksi_terima_gudang_satu, 'terima_gudang_satu');
            }
        });
    }

    public function hasilTerimaGudangSatu(): BelongsTo
    {
        return $this->belongsTo(HasilTerimaGudangSatu::class, 'id_hasil_terima_gudang_satu');
    }
}
