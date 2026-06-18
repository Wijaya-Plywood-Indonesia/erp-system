<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Ukuran;
use App\Models\JenisKayu;
use App\Models\HargaVeneer;
use App\Models\AnakAkun;
use App\Models\SubAnakAkun;
use App\Models\ReferensiHargaProduksi;

class ReferensiHargaProduksiSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Kosongkan dulu tabel referensi_harga_produksi
        ReferensiHargaProduksi::query()->delete();

        // 2. Buatkan sub_anak_akun (.00) untuk akun WJY yang ada di anak_akuns jika belum ada
        $anakAkunsWjy = AnakAkun::where('nama_anak_akun', 'like', '%Veneer%')
            ->where('nama_anak_akun', 'like', '%WJY%')
            ->get();

        foreach ($anakAkunsWjy as $anakAkun) {
            $codeSub = $anakAkun->kode_anak_akun . '.00';
            SubAnakAkun::firstOrCreate(
                [
                    'kode_sub_anak_akun' => $codeSub,
                ],
                [
                    'id_anak_akun' => $anakAkun->id,
                    'nama_sub_anak_akun' => $anakAkun->nama_anak_akun,
                    'status' => 'aktif',
                    'saldo_normal' => $anakAkun->saldo_normal ?? 'debet',
                ]
            );
        }

        // 3. Persiapkan data harga yang akan di-seed (prioritas dari tabel harga_veneers, fallback ke hardcoded WJY prices)
        $items = [];
        if (HargaVeneer::exists()) {
            foreach (HargaVeneer::all() as $hv) {
                $items[] = [
                    'id_jenis_kayu' => $hv->id_jenis_kayu,
                    'ukuran' => $hv->ukuran,
                    'harga_basah' => $hv->harga_basah,
                    'harga_kering' => $hv->harga_kering,
                    'harga_jadi' => $hv->harga_jadi,
                ];
            }
        } else {
            // Standar Harga WJY jika tabel harga_veneers kosong
            $items = [
                ['id_jenis_kayu' => 1, 'ukuran' => 'faceback', 'harga_basah' => 2700000, 'harga_kering' => 2950000, 'harga_jadi' => 4000000],
                ['id_jenis_kayu' => 3, 'ukuran' => 'face', 'harga_basah' => 8000000, 'harga_kering' => 8500000, 'harga_jadi' => 12500000],
                ['id_jenis_kayu' => 3, 'ukuran' => 'back', 'harga_basah' => 8000000, 'harga_kering' => 8500000, 'harga_jadi' => 10000000],
                ['id_jenis_kayu' => 1, 'ukuran' => 'core', 'harga_basah' => 1700000, 'harga_kering' => 2000000, 'harga_jadi' => 2250000],
                ['id_jenis_kayu' => 3, 'ukuran' => 'core', 'harga_basah' => 2100000, 'harga_kering' => 2500000, 'harga_jadi' => 2800000],
                ['id_jenis_kayu' => 1, 'ukuran' => 'ppc_core', 'harga_basah' => 1500000, 'harga_kering' => 1500000, 'harga_jadi' => 1500000],
                ['id_jenis_kayu' => 3, 'ukuran' => 'ppc_core', 'harga_basah' => 1800000, 'harga_kering' => 1800000, 'harga_jadi' => 1800000],
                ['id_jenis_kayu' => 1, 'ukuran' => 'ppc_faceback', 'harga_basah' => 1700000, 'harga_kering' => 1700000, 'harga_jadi' => 1700000],
                ['id_jenis_kayu' => 3, 'ukuran' => 'ppc_faceback', 'harga_basah' => 2000000, 'harga_kering' => 2000000, 'harga_jadi' => 2000000],
            ];
        }

        // Ambil atau buat data ukuran 0x0x0
        $ukuran0 = Ukuran::where('panjang', 0)
            ->where('lebar', 0)
            ->where('tebal', 0)
            ->first();

        if (!$ukuran0) {
            $ukuran0 = Ukuran::create([
                'panjang' => 0,
                'lebar' => 0,
                'tebal' => 0
            ]);
        }

        foreach ($items as $hv) {
            $idJenisKayu = $hv['id_jenis_kayu'];
            $jenisKayu = JenisKayu::find($idJenisKayu);
            $woodName = strtolower($jenisKayu ? $jenisKayu->nama_kayu : '');
            
            // Tentukan Ukuran (pakai 0x0x0 karena ini referensi harga awal)
            $idUkuran = $ukuran0->id;

            // Kata kunci posisi
            $posTerm = match($hv['ukuran']) {
                'faceback', 'face', 'back' => 'face',
                'core' => 'core',
                'ppc_faceback', 'ppc_core' => 'ppc',
                default => '',
            };

            // Suffix KW agar unik
            $kw = 'KW 1 - ' . ucfirst(str_replace('_', ' ', $hv['ukuran']));

            // 1. VENEER BASAH
            if ($hv['harga_basah'] > 0) {
                $subAkunBasah = null;
                if ($woodName && $posTerm) {
                    // Cari WJY terlebih dahulu
                    $subAkunBasah = SubAnakAkun::where('nama_sub_anak_akun', 'like', '%Veneer Basah%')
                        ->where('nama_sub_anak_akun', 'like', "%{$posTerm}%")
                        ->where('nama_sub_anak_akun', 'like', "%{$woodName}%")
                        ->where('nama_sub_anak_akun', 'like', '%WJY%')
                        ->first();
                    
                    // Jika tidak ada, fallback
                    if (!$subAkunBasah) {
                        $subAkunBasah = SubAnakAkun::where('nama_sub_anak_akun', 'like', '%Veneer Basah%')
                            ->where('nama_sub_anak_akun', 'like', "%{$posTerm}%")
                            ->where('nama_sub_anak_akun', 'like', "%{$woodName}%")
                            ->first();
                    }
                }
                if (!$subAkunBasah) {
                    $subAkunBasah = SubAnakAkun::where('nama_sub_anak_akun', 'Veneer Basah')->first();
                }

                ReferensiHargaProduksi::create([
                    'id_ukuran' => $idUkuran,
                    'id_jenis_kayu' => $idJenisKayu,
                    'id_sub_anak_akun' => $subAkunBasah?->id,
                    'jenis_barang' => 'Veneer Basah',
                    'kw' => $kw,
                    'harga' => $hv['harga_basah'],
                ]);
            }

            // 2. VENEER KERING
            if ($hv['harga_kering'] > 0) {
                $subAkunKering = null;
                if ($woodName && $posTerm) {
                    // Cari WJY terlebih dahulu
                    $subAkunKering = SubAnakAkun::where('nama_sub_anak_akun', 'like', '%Veneer Kering%')
                        ->where('nama_sub_anak_akun', 'like', "%{$posTerm}%")
                        ->where('nama_sub_anak_akun', 'like', "%{$woodName}%")
                        ->where('nama_sub_anak_akun', 'like', '%WJY%')
                        ->first();
                    
                    // Jika tidak ada, fallback
                    if (!$subAkunKering) {
                        $subAkunKering = SubAnakAkun::where('nama_sub_anak_akun', 'like', '%Veneer Kering%')
                            ->where('nama_sub_anak_akun', 'like', "%{$posTerm}%")
                            ->where('nama_sub_anak_akun', 'like', "%{$woodName}%")
                            ->first();
                    }
                }
                if (!$subAkunKering) {
                    $subAkunKering = SubAnakAkun::where('nama_sub_anak_akun', 'Veneer Kering')->first();
                }

                ReferensiHargaProduksi::create([
                    'id_ukuran' => $idUkuran,
                    'id_jenis_kayu' => $idJenisKayu,
                    'id_sub_anak_akun' => $subAkunKering?->id,
                    'jenis_barang' => 'Veneer Kering',
                    'kw' => $kw,
                    'harga' => $hv['harga_kering'],
                ]);
            }

            // 3. VENEER JADI
            if ($hv['harga_jadi'] > 0) {
                $subAkunJadi = null;
                if ($woodName && $posTerm) {
                    // Cari WJY terlebih dahulu
                    $subAkunJadi = SubAnakAkun::where('nama_sub_anak_akun', 'like', '%Veneer Jadi%')
                        ->where('nama_sub_anak_akun', 'like', "%{$posTerm}%")
                        ->where('nama_sub_anak_akun', 'like', "%{$woodName}%")
                        ->where('nama_sub_anak_akun', 'like', '%WJY%')
                        ->first();
                    
                    // Jika tidak ada, fallback
                    if (!$subAkunJadi) {
                        $subAkunJadi = SubAnakAkun::where('nama_sub_anak_akun', 'like', '%Veneer Jadi%')
                            ->where('nama_sub_anak_akun', 'like', "%{$posTerm}%")
                            ->where('nama_sub_anak_akun', 'like', "%{$woodName}%")
                            ->first();
                    }
                }
                if (!$subAkunJadi) {
                    $subAkunJadi = SubAnakAkun::where('nama_sub_anak_akun', 'like', '%Veneer Jadi%')->first();
                }

                ReferensiHargaProduksi::create([
                    'id_ukuran' => $idUkuran,
                    'id_jenis_kayu' => $idJenisKayu,
                    'id_sub_anak_akun' => $subAkunJadi?->id,
                    'jenis_barang' => 'Veneer Jadi',
                    'kw' => $kw,
                    'harga' => $hv['harga_jadi'],
                ]);
            }
        }
    }
}
