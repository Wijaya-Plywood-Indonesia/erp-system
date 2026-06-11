<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Target extends Model
{
    use HasFactory;

    protected $table = 'targets';
    protected $primaryKey = 'id';

    protected $fillable = [
        'id_mesin',
        'id_ukuran',
        'id_jenis_kayu',
        'kode_ukuran',
        'target',
        'orang',
        'jam',
        'gaji',
        'status',
    ];

    protected $casts = [
        'target' => 'decimal:4',
        'orang' => 'integer',
        'jam' => 'integer',
        'targetperjam' => 'decimal:4',
        'targetperorang' => 'decimal:4',
        'gaji' => 'decimal:2',
        'potongan' => 'decimal:4',
    ];
    public function mesin()
    {
        return $this->belongsTo(Mesin::class, 'id_mesin', 'id');
    }

    public function ukuranModel()
    {
        return $this->belongsTo(Ukuran::class, 'id_ukuran', 'id');
    }

    public function jenisKayu()
    {
        return $this->belongsTo(JenisKayu::class, 'id_jenis_kayu', 'id');
    }

    public function getDeskripsiAttribute()
    {
        $namaMesin = $this->mesin?->nama_mesin ?? '-';
        $ukuran = $this->ukuranModel ? "{$this->ukuranModel->panjang}x{$this->ukuranModel->lebar}x{$this->ukuranModel->tebal}" : '-';
        $kodeKayu = $this->jenisKayu?->kode_kayu ?? '-';

        return "{$namaMesin} | {$ukuran} | {$kodeKayu}";

    }

    // Target Repair
    public static function getTargetRepair($id_mesin, $id_ukuran, $id_jenis_kayu, $kw)
    {
        // 1. Ambil Data Ukuran untuk mendapatkan Panjang, Lebar, Tebal
        $dataUkuran = Ukuran::find($id_ukuran);

        if (!$dataUkuran) {
            return null;
        }

        $tebalFormatted = str_replace('.', ',', $dataUkuran->tebal);

        $generatedKode = "REPAIR" .
            $dataUkuran->panjang .
            $dataUkuran->lebar .
            $tebalFormatted .
            $kw .
            "s";

        $target = self::where('id_mesin', $id_mesin)
            ->where('id_jenis_kayu', $id_jenis_kayu)
            ->where('kode_ukuran', $generatedKode)
            ->first();

        return $target;
    }

    protected $appends = ['deskripsi'];

}
