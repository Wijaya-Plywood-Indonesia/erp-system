<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PengajuanPotonganHistory extends Model
{
    protected $table = 'pengajuan_potongan_histories';

    protected $fillable = [
        'pengajuan_potongan_id',
        'user_id',
        'aksi',
        'nominal_sebelumnya',
        'nominal_baru',
        'catatan',
    ];

    protected $casts = [
        'nominal_sebelumnya' => 'decimal:2',
        'nominal_baru' => 'decimal:2',
    ];

    public function pengajuan(): BelongsTo
    {
        return $this->belongsTo(PengajuanPotongan::class, 'pengajuan_potongan_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
