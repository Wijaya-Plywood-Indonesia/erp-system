<?php

namespace App\Models;

use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\QueryException;

class ProduksiRotary extends Model
{
    use HasUuids;

    // Menandaskan bahwa Primary Key tetap ID (Auto Increment)
    protected $primaryKey = 'id';
    protected $keyType = 'int';
    public $incrementing = true;

    protected $fillable = [
        'uuid',
        'id_mesin',
        'tgl_produksi',
        'kendala',
        'jam_kendala',
        'jam_selesai',
    ];

    /**
     * Memberitahu HasUuids untuk mengisi kolom 'uuid', BUKAN 'id'
     */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    /**
     * Memberitahu Filament/Laravel untuk memakai 'uuid' sebagai route key,
     * bukan 'id', sehingga URL admin panel memakai UUID.
     */
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function mesin()
    {
        return $this->belongsTo(Mesin::class, 'id_mesin');
    }

    public function produksi_rotaries()
    {
        return $this->hasMany(ProduksiRotary::class, 'id_produksi');
    }

    public function detailPegawaiRotary()
    {
        return $this->hasMany(PegawaiRotary::class, 'id_produksi');
    }

    public function detailLahanRotary()
    {
        return $this->hasMany(PenggunaanLahanRotary::class, 'id_produksi');
    }

    public function detailValidasiHasilRotary()
    {
        return $this->hasMany(ValidasiHasilRotary::class, 'id_produksi');
    }

    public function detailGantiPisauRotary()
    {
        return $this->hasMany(GantiPisauRotary::class, 'id_produksi');
    }

    public function detailPaletRotary()
    {
        return $this->hasMany(DetailHasilPaletRotary::class, 'id_produksi');
    }

    public function detailKayuPecah()
    {
        return $this->hasMany(KayuPecahRotary::class, 'id_produksi');
    }

    public function bahanPenolongRotary()
    {
        return $this->hasMany(BahanPenolongRotary::class, 'id_produksi');
    }

    public function riwayatKayu(): HasMany
    {
        return $this->hasMany(RiwayatKayu::class, 'id_rotary');
    }

    public function serahTerima(): HasManyThrough
    {
        return $this->hasManyThrough(
            SerahTerimaPivot::class,
            DetailHasilPaletRotary::class,
            'id_produksi',
            'id_detail_hasil_palet_rotary',
            'id',
            'id'
        );
    }

    public function kendalaRotaries(): HasMany
    {
        return $this->hasMany(KendalaRotary::class, 'produksi_rotary_id');
    }

    protected static function booted()
    {
        static::deleting(function ($record) {
            try {
                // Logic hapus record anak jika ada
            } catch (QueryException $e) {
                if ($e->getCode() == '23000') {
                    Notification::make()
                        ->title('Data tidak dapat dihapus')
                        ->body('Data ini masih digunakan pada tabel lain.')
                        ->danger()
                        ->send();

                    return false;
                }
            }
        });

        static::creating(function ($model) {
            $exists = static::where('tgl_produksi', $model->tgl_produksi)
                ->where('id_mesin', $model->id_mesin)
                ->exists();

            if ($exists) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'id_mesin' => 'Mesin ini sudah memiliki laporan pada tanggal tersebut.',
                ]);
            }
        });
    }
}
