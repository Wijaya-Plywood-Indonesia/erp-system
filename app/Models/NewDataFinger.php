<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $id_absensi_masuk
 * @property int|null $id_absensi_pulang
 * @property string $kode_pegawai
 * @property Carbon $tanggal
 * @property string|null $jam_masuk
 * @property string|null $jam_pulang
 */
class NewDataFinger extends Model
{
    protected $table = 'new_data_finger';

    protected $fillable = [
        'id_absensi_masuk',
        'id_absensi_pulang',
        'kode_pegawai',
        'tanggal',
        'jam_masuk',
        'jam_pulang',
    ];

    protected $casts = [
        'tanggal' => 'date',
    ];

    public function uploadMasuk()
    {
        return $this->belongsTo(NewAbsensiUpload::class, 'id_absensi_masuk');
    }

    public function uploadPulang()
    {
        return $this->belongsTo(NewAbsensiUpload::class, 'id_absensi_pulang');
    }
}
