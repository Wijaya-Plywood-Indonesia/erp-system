<?php

namespace Database\Seeders;

use App\Models\Criteria;
use App\Models\KategoriBarang;
use Illuminate\Database\Seeder;

class CriteriaSeeder extends Seeder
{
    /**
     * Mengisi data kriteria grading berdasarkan PDF Grading Role SLM.
     * Hanya menyertakan nama_kriteria, bobot, dan deskripsi sesuai permintaan.
     */
    public function run(): void
    {
        // 1. Ambil atau buat kategori "Plywood"
        $kategori = KategoriBarang::firstOrCreate(['nama_kategori' => 'Plywood']);

        // 2. Daftar 15 Parameter Teknis dari PDF
        $data = [
            [
                'nama' => 'Pecah Terbuka (Open Split)',
                'bobot' => 0.8,
                'deskripsi' => 'Cek celah permukaan. BBCC: Max 3mmx250mm (2 titik). UTY: Max 3mmx500mm (2 titik). Export: Max 9mm.',
            ],
            [
                'nama' => 'Lubang Mata Kayu Mati (Dead Knots)',
                'bobot' => 0.7,
                'deskripsi' => 'Cek lubang mata kayu. BBCC: Diameter 10mm (2 titik). UTY: 20mm (3 titik). Export: 20mm (Max 5 titik).',
            ],
            [
                'nama' => 'Perbedaan Warna (Discoloration)',
                'bobot' => 0.3,
                'deskripsi' => 'BBCC: Tidak boleh. UTY: Boleh asal tidak extreme. Export: Diperbolehkan.',
            ],
            [
                'nama' => 'Dieliminasi / Ripping',
                'bobot' => 0.6,
                'deskripsi' => 'Proses pembuangan cacat. BBCC & UTY: Tidak diperbolehkan. Export: Diperbolehkan.',
            ],
            [
                'nama' => 'Gelembung F/B',
                'bobot' => 1.0,
                'deskripsi' => 'FATAL: Pemisahan lapisan antar veneer. Tidak diperbolehkan untuk semua grade.',
            ],
            [
                'nama' => 'Patching / Tambalan',
                'bobot' => 0.4,
                'deskripsi' => 'BBCC: Tidak boleh. UTY: Max 10cmx15mm (3 titik). Export: Max 10cmx15mm (5 titik) & sewarna.',
            ],
            [
                'nama' => 'Face Sambungan (Face Join)',
                'bobot' => 0.7,
                'deskripsi' => 'BBCC & UTY: Tidak diperbolehkan. Export: Max 2 titik dan harus sewarna.',
            ],
            [
                'nama' => 'Samping Kurang',
                'bobot' => 0.5,
                'deskripsi' => 'BBCC: Face lebar 2mm panjang 300mm. UTY: Lebar 3mm panjang 5cm. Export: Lebar 3mm.',
            ],
            [
                'nama' => 'Botak (Over Sanding)',
                'bobot' => 1.0,
                'deskripsi' => 'FATAL: Pengamplasan terlalu dalam. Tidak diperbolehkan untuk semua grade.',
            ],
            [
                'nama' => 'Core Hole',
                'bobot' => 0.6,
                'deskripsi' => 'Lubang di core tengah. BBCC: Max 2mm. UTY: Max 3mm. Export: Max 5mm. Harus disumbat.',
            ],
            [
                'nama' => 'Core Overlap',
                'bobot' => 0.7,
                'deskripsi' => 'Tumpang tindih core. BBCC: L 2mm P 150mm. UTY: L 3mm P 200mm. Export: L 5mm P 400mm.',
            ],
            [
                'nama' => 'Press Mark',
                'bobot' => 0.4,
                'deskripsi' => 'Bekas tekanan. BBCC: L 2mm P 50mm. UTY: L 3mm P 60mm. Export: L 5mm P 100mm.',
            ],
            [
                'nama' => 'Cutter Mark',
                'bobot' => 0.5,
                'deskripsi' => 'BBCC: Tidak boleh. UTY: Max 2-3 titik digosok halus. Export: Digosok halus & rata.',
            ],
            [
                'nama' => 'Berjamur (Glue Penetration)',
                'bobot' => 0.8,
                'deskripsi' => 'Penembusan lem ke muka. BBCC & UTY: Tidak diperbolehkan. Export: Sedikit & tidak bergerombol.',
            ],
            [
                'nama' => 'Blue Stain',
                'bobot' => 0.4,
                'deskripsi' => 'Noda biru alami. BBCC: Tidak boleh. UTY: Max 10cm di samping. Export: Max 20cm di samping.',
            ],
        ];

        // 3. Simpan data secara massal
        foreach ($data as $index => $item) {
            Criteria::updateOrCreate(
                [
                    'id_kategori_barang' => $kategori->id,
                    'nama_kriteria' => $item['nama']
                ],
                [
                    'deskripsi' => $item['deskripsi'],
                    'bobot' => $item['bobot'],
                    'urutan' => ($index + 1) * 10,
                    'is_active' => true,
                ]
            );
        }
    }
}
