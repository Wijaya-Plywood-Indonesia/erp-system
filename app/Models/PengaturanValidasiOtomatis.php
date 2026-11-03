<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;

/**
 * Pengaturan validasi otomatis produksi (satu baris saja).
 * Dibaca oleh App\Services\ValidasiOtomatisProduksiService.
 */
class PengaturanValidasiOtomatis extends Model
{
    public const CACHE_KEY = 'pengaturan_validasi_otomatis';

    protected $table = 'pengaturan_validasi_otomatis';

    protected $guarded = [];

    protected $casts = [
        'aktif' => 'boolean',
        'hari_setelah' => 'integer',
        'mulai_tanggal' => 'date',
        'menu_aktif' => 'array',
    ];

    protected static function booted(): void
    {
        // Perubahan di menu pengaturan langsung berlaku di scheduler.
        static::saved(fn () => Cache::forget(self::CACHE_KEY));
        static::deleted(fn () => Cache::forget(self::CACHE_KEY));
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_updated_by');
    }

    /** Ambil baris pengaturan, buat dengan nilai default bila belum ada. */
    public static function getSettings(): self
    {
        return self::first() ?? self::create([])->fresh();
    }

    /**
     * Nilai pengaturan siap pakai (di-cache 5 menit, otomatis dibuang saat disimpan).
     * Bila tabel belum ada / error, dianggap NONAKTIF supaya aman.
     *
     * @return array{aktif:bool,jam:string,hari_setelah:int,mulai_tanggal:?string,menu_aktif:array<int,string>}
     */
    public static function nilai(): array
    {
        $mati = ['aktif' => false, 'jam' => '11:00', 'hari_setelah' => 1, 'mulai_tanggal' => null, 'menu_aktif' => []];

        try {
            return Cache::remember(self::CACHE_KEY, 300, function () {
                $s = self::first();

                if (! $s) {
                    return ['aktif' => false, 'jam' => '11:00', 'hari_setelah' => 1, 'mulai_tanggal' => null, 'menu_aktif' => []];
                }

                return [
                    'aktif' => (bool) $s->aktif,
                    'jam' => substr((string) $s->jam_validasi, 0, 5),
                    'hari_setelah' => max(0, (int) $s->hari_setelah),
                    'mulai_tanggal' => $s->mulai_tanggal?->toDateString(),
                    'menu_aktif' => array_values(array_filter((array) $s->menu_aktif, 'is_string')),
                ];
            });
        } catch (\Throwable) {
            return $mati;
        }
    }
}
