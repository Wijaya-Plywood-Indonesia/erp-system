<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HasilSanding extends Model
{
    protected $table = 'hasil_sandings';

    protected $fillable = [
        'id_produksi_sanding',
        'id_serah_terima_hp',
        'id_barang_setengah_jadi',
        'kuantitas',
        'jumlah_sanding_face',
        'jumlah_sanding_back',
        'no_palet',
        'status',
        // ─── Serah hasil sanding (arah gudang) ───
        'tujuan_serah',      // 'platform_jadi' | 'triplek_jadi' | null (belum diserahkan)
        'diserahkan_oleh',
        'diserahkan_at',
    ];

    protected $casts = [
        'diserahkan_at' => 'datetime',
    ];

    public function produksiSanding()
    {
        return $this->belongsTo(ProduksiSanding::class, 'id_produksi_sanding');
    }

    public function barangSetengahJadi()
    {
        return $this->belongsTo(BarangSetengahJadiHp::class, 'id_barang_setengah_jadi');
    }

    public function serahTerimaHp()
    {
        return $this->belongsTo(SerahTerimaHp::class, 'id_serah_terima_hp');
    }

    public function mesin()
    {
        return $this->belongsTo(Mesin::class, 'id_mesin');
    }

    /** User yang menyerahkan hasil sanding ke gudang. */
    public function penyerah()
    {
        return $this->belongsTo(User::class, 'diserahkan_oleh');
    }

    /** Relasi 1-to-1 ke ModalSanding yang sinkron otomatis */
    public function modalSanding()
    {
        return $this->hasOne(ModalSanding::class, 'id_hasil_sanding');
    }

    /** Relasi ke ModalSanding berdasarkan id_produksi_sanding untuk eager loading */
    public function modalSandings()
    {
        return $this->hasMany(ModalSanding::class, 'id_produksi_sanding', 'id_produksi_sanding');
    }

    /** Accessor untuk mengambil ModalSanding yang sesuai */
    public function getModalSandingAttribute()
    {
        return $this->relationLoaded('modalSanding') && $this->getRelation('modalSanding')
            ? $this->getRelation('modalSanding')
            : ($this->modalSandings->firstWhere('id_hasil_sanding', $this->id)
                ?? $this->modalSandings->firstWhere('no_palet', $this->no_palet));
    }

    // ─── Helper serah ─────────────────────────────────────────────

    public function sudahDiserahkan(): bool
    {
        return $this->diserahkan_at !== null;
    }

    public function getLabelTujuanSerahAttribute(): string
    {
        return match ($this->tujuan_serah) {
            'platform_jadi' => 'Gudang Platform Jadi',
            'triplek_jadi'  => 'Gudang Triplek Jadi',
            default         => '-',
        };
    }
}