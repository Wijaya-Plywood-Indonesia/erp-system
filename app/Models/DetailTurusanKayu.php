<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class DetailTurusanKayu extends Model
{
    use HasFactory;

    protected $table = 'detail_turusan_kayus';
    //
    protected $fillable = [
        'id_kayu_masuk',
        'nomer_urut',
        'lahan_id',
        'jenis_kayu_id',
        'panjang',
        'grade',
        'diameter',
        'kuantitas',
        'created_by',
        'updated_by',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (Auth::check()) {
                $model->created_by = Auth::id();
                $model->updated_by = Auth::id();
            }
        });

        static::updating(function ($model) {
            if (Auth::check()) {
                $model->updated_by = Auth::id();
            }
        });
    }



    public function kayuMasuk()
    {
        return $this->belongsTo(KayuMasuk::class, 'id_kayu_masuk');
    }

    /**
     * Relasi ke tabel lahans
     * DetailTurusanKayu milik satu Lahan
     */
    public function lahan()
    {
        return $this->belongsTo(Lahan::class, 'lahan_id');
    }

    /**
     * Relasi ke tabel jenis_kayus
     * DetailTurusanKayu milik satu JenisKayu
     */
    public function jenisKayu()
    {
        return $this->belongsTo(JenisKayu::class, 'jenis_kayu_id');
    }
    protected $appends = ['kubikasi', 'harga_satuan', 'total_harga'];

    public function getKubikasiAttribute()
    {
        $diameter = (float) ($this->diameter ?? 0); // cm
        $jumlah = (float) ($this->kuantitas ?? 0);
        $panjang = (float) ($this->panjang ?? 0);

        // formula: diameter * jumlah * 0.785 / 1_000_000
        // kembalikan float dengan presisi cukup tinggi
        $kubikasi = ($panjang * $diameter * $diameter * $jumlah * 0.785) / 1_000_000;

        return $kubikasi; // mis. 0.123456789
    }
    public function getHargaSatuanAttribute()
    {
        $harga = HargaKayu::where('id_jenis_kayu', $this->id_jenis_kayu)
            ->where('grade', $this->grade)
            ->where('panjang', $this->panjang)
            ->where('diameter_terkecil', '<=', $this->diameter)
            ->where('diameter_terbesar', '>=', $this->diameter)
            ->value('harga_beli');

        return (float) ($harga ?? 0);
    }

    public function getTotalHargaAttribute()
    {
        $hargaSatuan = $this->harga_satuan; // float
        $kubikasiRaw = $this->getAttribute('kubikasi'); // akan memanggil accessor di atas

        // Kalkulasi presisi lalu lakukan pembulatan akhir
        $total = $hargaSatuan * $kubikasiRaw * 1000;

        // Jika kamu menyimpan/menampilkan dalam rupiah tanpa decimal, gunakan round($total, 0)
        // Jika butuh 2 desimal, gunakan round($total, 2)
        return round($total, 2);
    }

    public function hargaKayu()
    {
        return $this->belongsTo(HargaKayu::class, 'id_jenis_kayu', 'id_jenis_kayu')
            ->whereColumn('harga_kayus.grade', 'detail_kayu_masuks.grade')
            ->whereColumn('harga_kayus.panjang', 'detail_kayu_masuks.panjang')
            ->whereRaw('? BETWEEN harga_kayus.diameter_terkecil AND harga_kayus.diameter_terbesar', [$this->diameter]);
    }
    public static function hitungTotalByKayuMasuk($idKayuMasuk): array
    {
        $records = self::where('id_kayu_masuk', $idKayuMasuk)->get();

        $totalBatang = $records->sum('kuantitas');
        $totalKubikasi = $records->sum(function ($r) {
            return ($r->panjang * $r->diameter * $r->diameter * $r->kuantitas * 0.785) / 1_000_000;
        });

        return [
            'total_batang' => $totalBatang,
            'total_kubikasi' => $totalKubikasi,
        ];
    }
    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
