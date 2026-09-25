<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PengaturanAbsensi extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'auto_fix_enabled' => 'boolean',
        'toleransi_sesi_tunggal_menit' => 'integer',
        'batas_total_durasi_menit' => 'integer',
        'toleransi_masuk_lebih_cepat_malam_menit' => 'integer',
        'toleransi_pulang_lebih_lambat_malam_menit' => 'integer',
        'auto_fix_batas_selisih_menit' => 'integer',
    ];

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_updated_by');
    }

    /**
     * Get the single settings record, or create a default one.
     */
    public static function getSettings(): self
    {
        $settings = self::first();
        if (!$settings) {
            $settings = self::create([]); // Will use default values from migration
        }
        return $settings;
    }
}
