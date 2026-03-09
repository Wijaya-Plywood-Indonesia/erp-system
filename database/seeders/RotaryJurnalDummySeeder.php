<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * RotaryJurnalDummySeeder
 *
 * Jalankan:   php artisan db:seed --class=RotaryJurnalDummySeeder
 *
 * Reset manual via tinker:
 *   $ids = \Illuminate\Support\Facades\DB::table('produksi_rotaries')->whereDate('tgl_produksi','2026-01-15')->pluck('id')->toArray();
 *   \Illuminate\Support\Facades\DB::table('validasi_hasil_rotaries')->whereIn('id_produksi',$ids)->delete();
 *   \Illuminate\Support\Facades\DB::table('bahan_penolong_rotary')->whereIn('id_produksi',$ids)->delete();
 *   \Illuminate\Support\Facades\DB::table('detail_hasil_palet_rotaries')->whereIn('id_produksi',$ids)->delete();
 *   \Illuminate\Support\Facades\DB::table('pegawai_rotaries')->whereIn('id_produksi',$ids)->delete();
 *   \Illuminate\Support\Facades\DB::table('penggunaan_lahan_rotaries')->whereIn('id_produksi',$ids)->delete();
 *   \Illuminate\Support\Facades\DB::table('riwayat_kayus')->whereIn('id_rotary',$ids)->delete();
 *   \Illuminate\Support\Facades\DB::table('produksi_rotaries')->whereIn('id',$ids)->delete();
 */
class RotaryJurnalDummySeeder extends Seeder
{
    const TGL = '2026-01-15';

    const MESIN = [
        ['id' => 1, 'nama' => 'SPINDLESS', 'jenis' => 'f/b'],
        ['id' => 2, 'nama' => 'MERANTI',   'jenis' => 'f/b'],
        ['id' => 3, 'nama' => 'SANJI',     'jenis' => 'core'],
    ];

    public function run(): void
    {
        $this->command->info('Membuat data dummy untuk tanggal ' . self::TGL . '...');

        DB::transaction(function () {

            // 1. ProduksiRotary
            $produksiIds = [];
            foreach (self::MESIN as $mesin) {
                $id = DB::table('produksi_rotaries')->insertGetId([
                    'id_mesin'     => $mesin['id'],
                    'tgl_produksi' => self::TGL,
                    'kendala'      => null,
                    'created_at'   => self::TGL . ' 08:00:00',
                    'updated_at'   => self::TGL . ' 17:00:00',
                ]);
                $produksiIds[$mesin['nama']] = $id;
                $this->command->info("  ProduksiRotary id={$id} mesin={$mesin['nama']}");
            }

            // 2. PenggunaanLahanRotary
            $lahanConfig = [
                'SPINDLESS' => [
                    ['id_lahan' => 1, 'id_jenis_kayu' => 1, 'jumlah_batang' => 50],
                    ['id_lahan' => 8, 'id_jenis_kayu' => 2, 'jumlah_batang' => 30],
                ],
                'MERANTI' => [
                    ['id_lahan' => 3, 'id_jenis_kayu' => 1, 'jumlah_batang' => 40],
                ],
                'SANJI' => [
                    ['id_lahan' => 9, 'id_jenis_kayu' => 3, 'jumlah_batang' => 60],
                ],
            ];

            $lahanIds = [];
            foreach ($lahanConfig as $namaMesin => $lahans) {
                $lahanIds[$namaMesin] = [];
                foreach ($lahans as $lahan) {
                    $lahanId = DB::table('penggunaan_lahan_rotaries')->insertGetId([
                        'id_produksi'   => $produksiIds[$namaMesin],
                        'id_lahan'      => $lahan['id_lahan'],
                        'id_jenis_kayu' => $lahan['id_jenis_kayu'],
                        'jumlah_batang' => $lahan['jumlah_batang'],
                        'created_at'    => self::TGL . ' 08:00:00',
                        'updated_at'    => self::TGL . ' 08:00:00',
                    ]);
                    $lahanIds[$namaMesin][] = [
                        'id'       => $lahanId,
                        'id_lahan' => $lahan['id_lahan'],
                    ];
                }
            }
            $this->command->info('  PenggunaanLahanRotary selesai');

            // 3. DetailHasilPaletRotary
            $paletConfig = [
                'SPINDLESS' => [
                    ['lahan_idx' => 0, 'id_ukuran' => 1, 'kw' => 'A', 'palet' => 1, 'total_lembar' => 200],
                    ['lahan_idx' => 0, 'id_ukuran' => 1, 'kw' => 'B', 'palet' => 1, 'total_lembar' => 150],
                    ['lahan_idx' => 1, 'id_ukuran' => 1, 'kw' => 'A', 'palet' => 1, 'total_lembar' => 180],
                ],
                'MERANTI' => [
                    ['lahan_idx' => 0, 'id_ukuran' => 1, 'kw' => 'A', 'palet' => 1, 'total_lembar' => 220],
                    ['lahan_idx' => 0, 'id_ukuran' => 1, 'kw' => 'B', 'palet' => 1, 'total_lembar' => 160],
                ],
                'SANJI' => [
                    ['lahan_idx' => 0, 'id_ukuran' => 1, 'kw' => 'A', 'palet' => 1, 'total_lembar' => 300],
                    ['lahan_idx' => 0, 'id_ukuran' => 1, 'kw' => 'B', 'palet' => 1, 'total_lembar' => 250],
                    ['lahan_idx' => 0, 'id_ukuran' => 1, 'kw' => 'C', 'palet' => 1, 'total_lembar' => 100],
                ],
            ];

            foreach ($paletConfig as $namaMesin => $palets) {
                foreach ($palets as $palet) {
                    $idPenggunaanLahan = $lahanIds[$namaMesin][$palet['lahan_idx']]['id'] ?? null;
                    DB::table('detail_hasil_palet_rotaries')->insert([
                        'id_produksi'         => $produksiIds[$namaMesin],
                        'id_penggunaan_lahan' => $idPenggunaanLahan,
                        'id_ukuran'           => $palet['id_ukuran'],
                        'kw'                  => $palet['kw'],
                        'palet'               => $palet['palet'],
                        'total_lembar'        => $palet['total_lembar'],
                        'timestamp_laporan'   => self::TGL . ' 17:00:00',
                        'created_at'          => self::TGL . ' 17:00:00',
                        'updated_at'          => self::TGL . ' 17:00:00',
                    ]);
                }
            }
            $this->command->info('  DetailHasilPaletRotary selesai');

            // 4. BahanPenolongRotary
            DB::table('bahan_penolong_rotary')->insert([
                'id_produksi' => $produksiIds['SPINDLESS'],
                'nama_bahan'  => 'Reeling Tape',
                'jumlah'      => 42000,
                'created_at'  => self::TGL . ' 08:00:00',
                'updated_at'  => self::TGL . ' 08:00:00',
            ]);
            $this->command->info('  BahanPenolongRotary selesai');

            // 5. PegawaiRotary
            $pegawaiId = DB::table('pegawais')->value('id');
            if ($pegawaiId) {
                foreach ($produksiIds as $namaMesin => $idProduksi) {
                    DB::table('pegawai_rotaries')->insert([
                        'id_produksi' => $idProduksi,
                        'id_pegawai'  => $pegawaiId,
                        'role'        => 'Operator',
                        'jam_masuk'   => '08:00:00',
                        'jam_pulang'  => '17:00:00',
                        'created_at'  => self::TGL . ' 08:00:00',
                        'updated_at'  => self::TGL . ' 08:00:00',
                    ]);
                }
                $this->command->info('  PegawaiRotary selesai');
            }

            // 6. ValidasiHasilRotary
            foreach ($produksiIds as $namaMesin => $idProduksi) {
                DB::table('validasi_hasil_rotaries')->insert([
                    'id_produksi' => $idProduksi,
                    'role'        => 'Mandor',
                    'status'      => 'divalidasi',
                    'created_at'  => self::TGL . ' 17:30:00',
                    'updated_at'  => self::TGL . ' 17:30:00',
                ]);
            }
            $this->command->info('  ValidasiHasilRotary selesai (semua divalidasi)');

            // 7. Data Kayu (kayu_masuks + nota_kayus + detail_kayu_masuks + riwayat_kayus)
            $this->seedDataKayu($produksiIds, $lahanIds);
        });

        $this->command->info('');
        $this->command->info('Seeder selesai! Tanggal: ' . self::TGL);
        $this->command->info('Test: klik tombol Test Kirim Jurnal -> pilih ' . self::TGL);
    }

    private function seedDataKayu(array $produksiIds, array $lahanIds): void
    {
        // Pastikan harga kayu tersedia
        $hargaList = [
            ['id_jenis_kayu' => 1, 'panjang' => 130, 'diameter_terkecil' => 10, 'diameter_terbesar' => 20, 'harga_beli' => 750, 'grade' => 2],
            ['id_jenis_kayu' => 2, 'panjang' => 260, 'diameter_terkecil' => 10, 'diameter_terbesar' => 20, 'harga_beli' => 800, 'grade' => 1],
            ['id_jenis_kayu' => 3, 'panjang' => 260, 'diameter_terkecil' => 10, 'diameter_terbesar' => 20, 'harga_beli' => 750, 'grade' => 1],
        ];
        foreach ($hargaList as $h) {
            $exists = DB::table('harga_kayus')
                ->where('id_jenis_kayu', $h['id_jenis_kayu'])
                ->where('panjang', $h['panjang'])
                ->where('grade', $h['grade'])
                ->exists();
            if (!$exists) {
                DB::table('harga_kayus')->insert(array_merge($h, ['created_at' => now(), 'updated_at' => now()]));
            }
        }

        // Data kayu per lahan
        $kayuPerLahan = [
            1 => ['id_jenis_kayu' => 1, 'panjang' => 130, 'diameter' => 13, 'jumlah_batang' => 50, 'harga_beli' => 750, 'grade' => 2],
            3 => ['id_jenis_kayu' => 1, 'panjang' => 130, 'diameter' => 13, 'jumlah_batang' => 40, 'harga_beli' => 750, 'grade' => 2],
            8 => ['id_jenis_kayu' => 2, 'panjang' => 260, 'diameter' => 13, 'jumlah_batang' => 30, 'harga_beli' => 800, 'grade' => 1],
            9 => ['id_jenis_kayu' => 3, 'panjang' => 260, 'diameter' => 13, 'jumlah_batang' => 60, 'harga_beli' => 750, 'grade' => 1],
        ];

        foreach ($kayuPerLahan as $idLahan => $kayu) {
            $kubikasi = round(
                $kayu['panjang'] * $kayu['diameter'] * $kayu['diameter']
                * $kayu['jumlah_batang'] * 0.785 / 1_000_000, 6
            );

            $kayuMasukId = DB::table('kayu_masuks')->insertGetId([
                'jenis_dokumen_angkut'        => 'SKTM',
                'upload_dokumen_angkut'       => 'dummy.pdf',
                'tgl_kayu_masuk'              => '2026-01-10',
                'seri'                        => 9000 + $idLahan,
                'kubikasi'                    => $kubikasi,
                'id_supplier_kayus'           => null,
                'id_kendaraan_supplier_kayus' => null,
                'id_dokumen_kayus'            => null,
                'created_at'                  => '2026-01-10 08:00:00',
                'updated_at'                  => '2026-01-10 08:00:00',
            ]);

            DB::table('nota_kayus')->insert([
                'id_kayu_masuk'    => $kayuMasukId,
                'no_nota'          => 'DUMMY-L' . $idLahan,
                'penanggung_jawab' => 'Dummy',
                'penerima'         => 'Dummy',
                'satpam'           => 'Dummy',
                'status'           => 'Sudah Diperiksa',
                'created_at'       => '2026-01-10 08:00:00',
                'updated_at'       => '2026-01-10 08:00:00',
            ]);

            DB::table('detail_kayu_masuks')->insert([
                'id_kayu_masuk'  => $kayuMasukId,
                'id_jenis_kayu'  => $kayu['id_jenis_kayu'],
                'id_lahan'       => $idLahan,
                'diameter'       => $kayu['diameter'],
                'panjang'        => $kayu['panjang'],
                'grade'          => $kayu['grade'],
                'jumlah_batang'  => $kayu['jumlah_batang'],
                'keterangan'     => 'Dummy seeder',
                'created_at'     => '2026-01-10 08:00:00',
                'updated_at'     => '2026-01-10 08:00:00',
            ]);

            // Relasi riwayat_kayu ke produksi yang pakai lahan ini
            foreach ($lahanIds as $namaMesin => $lahans) {
                foreach ($lahans as $lahan) {
                    if ($lahan['id_lahan'] == $idLahan) {
                        DB::table('riwayat_kayus')->insert([
                            'tanggal_masuk'      => '2026-01-10',
                            'tanggal_digunakan'  => self::TGL,
                            'tanggal_habis'      => self::TGL,
                            'id_tempat_kayu'     => null,
                            'id_rotary'          => $produksiIds[$namaMesin],
                            'id_produksi_rotary' => $produksiIds[$namaMesin],
                            'created_at'         => self::TGL . ' 08:00:00',
                            'updated_at'         => self::TGL . ' 08:00:00',
                        ]);

                        $poin = round($kayu['harga_beli'] * $kubikasi * 1000, 2);
                        $kode = DB::table('lahans')->where('id', $idLahan)->value('kode_lahan');
                        $this->command->info("  Lahan {$kode}(id={$idLahan}): kubikasi={$kubikasi} poin=Rp" . number_format($poin, 0, ',', '.'));
                    }
                }
            }
        }

        $this->command->info('  KayuMasuk + RiwayatKayu selesai');
    }
}