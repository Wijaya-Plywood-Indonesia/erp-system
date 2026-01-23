<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class HasilPilihPlywood extends Model
{
    protected $table = 'hasil_pilih_plywood';

    protected $fillable = [
        'id_produksi_pilih_plywood',
        'id_barang_setengah_jadi_hp',
        'id_produksi_pilih_plywood',
        'jenis_cacat',
        'jumlah',
        'jumlah_bagus',
        'kondisi',
        'ket',
    ];

    public function produksiPilihPlywood()
    {
        return $this->belongsTo(ProduksiPilihPlywood::class, 'id_produksi_pilih_plywood');
    }

    public function barangSetengahJadiHp()
    {
        return $this->belongsTo(BarangSetengahJadiHp::class, 'id_barang_setengah_jadi_hp');
    }

    public function pegawaiPilihPlywood()
    {
        return $this->hasMany(PegawaiPilihPlywood::class, 'id_produksi_pilih_plywood');
    }

    public function pegawais(): BelongsToMany
    {
        return $this->belongsToMany(
            Pegawai::class,
            'hasil_pilih_plywood_pegawai', // Nama tabel pivot
            'id_hasil_pilih_plywood',      // Foreign key model ini
            'id_pegawai'                   // Foreign key model Pegawai
        );
    }
}
