<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Merepresentasikan 1 kali proses upload (1 batch), bisa berisi
 * lebih dari 1 file (multi-mesin) sekaligus.
 *
 * @property int $id
 * @property Carbon $tanggal
 * @property array $file_path
 * @property string $uploaded_by
 */
class NewAbsensiUpload extends Model
{
    protected $table = 'new_absensi_uploads';

    protected $fillable = [
        'tanggal',
        'file_path',
        'uploaded_by',
    ];

    protected $casts = [
        'tanggal' => 'date',
        'file_path' => 'array',
    ];

    public function dataFingerMasuk()
    {
        return $this->hasMany(NewDataFinger::class, 'id_absensi_masuk');
    }

    public function dataFingerPulang()
    {
        return $this->hasMany(NewDataFinger::class, 'id_absensi_pulang');
    }

    /**
     * Nama file asli (tanpa path folder), untuk ditampilkan di tabel riwayat.
     */
    public function getFileNamesAttribute(): array
    {
        return collect($this->file_path)
            ->map(fn ($path) => basename($path))
            ->all();
    }
}
