<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class JurnalTiga extends Model
{
    // Traits For Logs
    use LogsActivity;

    // Inisiasi Table
    protected $table = 'jurnal_tigas';


    protected $fillable = [
        'modif1000',
        'akun_seratus',
        'detail',
        'banyak',
        'kubikasi',
        'harga',
        'total',
        'createdBy',
        'status',
        'synchronized_by',
        'synchronized_at'
    ];

    protected $casts = [
        'akun_seratus' => 'integer'
    ];
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable() // Mencatat semua kolom sensitif di atas
            ->logOnlyDirty() // Hanya mencatat jika ada perubahan angka (audit trail)
            ->useLogName('Jurnal 3rd'); // Memberi label pada menu log
    }
}
