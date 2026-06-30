<?php

namespace App\Observers;

use App\Models\RencanaKerjaHp;
use App\Models\Komposisi;
use App\Models\VeneerBahanHp;
use App\Models\PlatformBahanHp;
use App\Models\BarangSetengahJadiHp;

class RencanaKerjaHpObserver
{
    /**
     * Handle the RencanaKerjaHp "created" event.
     * Logika: Membuat Bahan Baku dan mengisi 'isi' dengan jumlah Rencana Kerja.
     */
    public function created(RencanaKerjaHp $rencanaKerjaHp): void
    {
        $idBarangSetengahJadiHp = $rencanaKerjaHp->id_barang_setengah_jadi_hp;
        
        // --- AMBIL JUMLAH DARI RENCANA KERJA ---
        $jumlahRencanaKerja = $rencanaKerjaHp->jumlah; // Baris ini harus ditambahkan kembali

        // 1. Ambil BarangSetengahJadiHp (dengan relasi Kategori)
        $barangJadi = BarangSetengahJadiHp::with('grade.kategoriBarang')
            ->find($idBarangSetengahJadiHp);

        if (!$barangJadi || !$barangJadi->grade || !$barangJadi->grade->kategoriBarang) {
            return; 
        }

        $tipeProduk = strtoupper($barangJadi->grade->kategoriBarang->nama_kategori);
        
        // 2. Cari Komposisi yang sesuai
        $komposisi = Komposisi::where('id_barang_setengah_jadi_hp', $idBarangSetengahJadiHp)->first();

        if (!$komposisi) {
             return; 
        }

        // 3. Ambil Detail Komposisi
        $komposisi->load('detailKomposisis'); 

        foreach ($komposisi->detailKomposisis as $detail) {
            
            // Kolom 'isi' akan diisi dengan jumlah Rencana Kerja
            $totalKebutuhan = $jumlahRencanaKerja; 
            
            // Catatan: Jika Anda menggunakan kolom 'jumlah_lembar_terpakai' (Realisasi), 
            // kolom tersebut harus diinisialisasi 0 di sini, dan Service akan mengupdate kolom tersebut.
            
            if ($tipeProduk === 'PLYWOOD' || $tipeProduk === 'VENEER') { 
                VeneerBahanHp::create([
                    'id_produksi_hp'             => $rencanaKerjaHp->id_produksi_hp,
                    'id_rencana_kerja_hp'        => $rencanaKerjaHp->id, 
                    'id_barang_setengah_jadi_hp' => $detail->id_barang_setengah_jadi_hp, 
                    'id_detail_komposisi'        => $detail->id, 
                    'no_palet'                   => null, 
                    'isi'                        => $totalKebutuhan, // <--- KEMBALI MENGISI DARI RENCANA
                ]);
            }
            elseif ($tipeProduk === 'PLATFORM') { 
                PlatformBahanHp::create([
                    'id_produksi_hp'             => $rencanaKerjaHp->id_produksi_hp,
                    'id_rencana_kerja_hp'        => $rencanaKerjaHp->id, 
                    'id_barang_setengah_jadi_hp' => $detail->id_barang_setengah_jadi_hp, 
                    'id_detail_komposisi'        => $detail->id, 
                    'no_palet'                   => null, 
                    'isi'                        => $totalKebutuhan, // <--- KEMBALI MENGISI DARI RENCANA
                ]);
            }
        }
    }

    /**
     * Handle the RencanaKerjaHp "updated" event.
     * Logika: Jika Barang atau Jumlah diubah, hapus Bahan lama dan buat Bahan baru.
     */
    public function updated(RencanaKerjaHp $rencanaKerjaHp): void
    {
        // // Proses jika: 
        // // 1. Komposisi barang berubah (id_barang_setengah_jadi_hp), ATAU
        // // 2. Jumlah Rencana Kerjanya berubah (jumlah)
        // if (!$rencanaKerjaHp->isDirty('id_barang_setengah_jadi_hp') && !$rencanaKerjaHp->isDirty('jumlah')) {
        //     return;
        // }

        // // // Hapus Bahan Baku lama
        // // VeneerBahanHp::where('id_rencana_kerja_hp', $rencanaKerjaHp->id)->delete();
        // // PlatformBahanHp::where('id_rencana_kerja_hp', $rencanaKerjaHp->id)->delete();
                 
        // // // Panggil ulang logika 'created' untuk membuat Bahan Baku baru dengan jumlah yang baru
        // // $this->created($rencanaKerjaHp);
    }

    /**
     * Handle the RencanaKerjaHp "deleted" event.
     */
    public function deleted(RencanaKerjaHp $rencanaKerjaHp): void
    {
        // // Hapus Bahan Baku terkait
        // VeneerBahanHp::where('id_rencana_kerja_hp', $rencanaKerjaHp->id)->delete();
        // PlatformBahanHp::where('id_rencana_kerja_hp', $rencanaKerjaHp->id)->delete();
    }

    public function restored(RencanaKerjaHp $rencanaKerjaHp): void
    {
        //
    }

    public function forceDeleted(RencanaKerjaHp $rencanaKerjaHp): void
    {
        //
    }
}