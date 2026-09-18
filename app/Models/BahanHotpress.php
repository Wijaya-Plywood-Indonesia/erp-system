<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use RuntimeException;

class BahanHotpress extends Model
{
    protected $table = 'bahan_hotpress';

    protected $fillable = [
        'id_mutasi_keluar_palet',
        'id_mutasi_keluar_platform',
        'id_mutasi_keluar_triplek', 
        'id_produksi_hp',
        'no_palet',
        'id_barang_setengah_jadi',
        'isi',
        'ket',
        'sumber',
    ];

    private const SUMBER_KE_KOLOM_FK = [
        'veneer' => 'id_mutasi_keluar_palet',
        'platform' => 'id_mutasi_keluar_platform',
        'triplek' => 'id_mutasi_keluar_triplek',
    ];

    protected static function booted(): void
    {
        static::saving(function (BahanHotpress $record) {
            $kolomTerkait = array_merge(['sumber'], array_values(self::SUMBER_KE_KOLOM_FK));
            $adaPerubahanTerkait = collect($kolomTerkait)->contains(fn ($kolom) => $record->isDirty($kolom));

            if ($record->exists && ! $adaPerubahanTerkait) {
                return;
            }

            $sumber = $record->sumber;

            // Baris lama (sebelum kolom `sumber` ada) boleh tanpa sumber —
            // tidak divalidasi supaya data lama tidak tiba-tiba gagal disimpan.
            if (! $sumber) {
                return;
            }

            if (! array_key_exists($sumber, self::SUMBER_KE_KOLOM_FK)) {
                throw new RuntimeException(
                    "Bahan Hotpress: nilai sumber \"{$sumber}\" tidak dikenali. ".
                    'Nilai yang valid: '.implode(', ', array_keys(self::SUMBER_KE_KOLOM_FK)).'.'
                );
            }

            $kolomWajib = self::SUMBER_KE_KOLOM_FK[$sumber];

            if (blank($record->{$kolomWajib})) {
                throw new RuntimeException(
                    "Bahan Hotpress: sumber diset ke \"{$sumber}\" tapi kolom \"{$kolomWajib}\" ".
                    'kosong. Data tidak disimpan supaya sisa stok / relasi jenis barang, grade, '.
                    'dan ukuran tetap akurat. Periksa kode yang membuat/mengubah record ini — '.
                    'field tersebut wajib ikut tersimpan.'
                );
            }

            // Kolom FK dari sumber LAIN harus kosong, supaya tidak ada
            // ambiguitas record ini sebenarnya berasal dari mana.
            foreach (self::SUMBER_KE_KOLOM_FK as $sumberLain => $kolomLain) {
                if ($sumberLain !== $sumber && filled($record->{$kolomLain})) {
                    throw new RuntimeException(
                        "Bahan Hotpress: sumber diset ke \"{$sumber}\" tapi kolom \"{$kolomLain}\" ".
                        "(milik sumber \"{$sumberLain}\") juga terisi. Hanya satu kolom FK sumber ".
                        'yang boleh terisi per baris.'
                    );
                }
            }
        });
    }

    public function produksiHp()
    {
        return $this->belongsTo(ProduksiHp::class, 'id_produksi_hp');
    }

    public function barangSetengahJadi()
    {
        return $this->belongsTo(
            \App\Models\BarangSetengahJadiHp::class,
            'id_barang_setengah_jadi'
        );
    }

    public function ukuran()
    {
        return $this->belongsTo(Ukuran::class, 'id_ukuran');
    }

    public function jenisKayu()
    {
        return $this->belongsTo(JenisKayu::class, 'id_jenis_kayu', 'id');
    }

    public function mutasiKeluarPalet()
    {
        return $this->belongsTo(VeneerJadiMutasiKeluarPalet::class, 'id_mutasi_keluar_palet');
    }

    public function mutasiKeluarPlatform()
    {
        return $this->belongsTo(PlatformJadiMutasiKeluarPalet::class, 'id_mutasi_keluar_platform');
    }

    public function mutasiKeluarTriplek()
    {
        return $this->belongsTo(TriplekJadiMutasiKeluarPalet::class, 'id_mutasi_keluar_triplek');
    }
}