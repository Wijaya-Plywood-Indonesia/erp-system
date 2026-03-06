<?php

namespace App\Models;

use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\QueryException;

class ProduksiRotary extends Model
{
    //
    protected $fillable = [
        'id_mesin',
        'tgl_produksi',
        'kendala',
        'jam_kendala',
        'jam_selesai'
    ];

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
    protected static function booted()
    {
        static::deleting(function ($record) {
            try {
                // Coba hapus record anak manual, jika mau
            } catch (QueryException $e) {
                if ($e->getCode() == '23000') {
                    Notification::make()
                        ->title('Data tidak dapat dihapus')
                        ->body('Data ini masih digunakan pada tabel lain.')
                        ->danger()
                        ->send();

                    return false; // Batalkan penghapusan
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
