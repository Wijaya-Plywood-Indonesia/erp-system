<?php

namespace Database\Seeders;

use App\Models\Criteria;
use App\Models\Grade;
use App\Models\GradeRule;
use App\Models\KategoriBarang;
use Illuminate\Database\Seeder;

class GradeRuleSeeder extends Seeder
{
    /**
     * Membangun Knowledge Base berdasarkan PDF Grading Role SLM.
     * Aturan ini menghubungkan 15 kriteria dengan 3 grade utama.
     */
    public function run(): void
    {
        // 1. Pastikan Kategori dan Grade tersedia
        // DISESUAIKAN: Nama kategori sekarang adalah "Plywood"
        $kategori = KategoriBarang::where('nama_kategori', 'Plywood')->first();

        if (!$kategori) {
            $this->command->error('Kategori "Plywood" tidak ditemukan. Jalankan CriteriaSeeder terlebih dahulu!');
            return;
        }

        $grades = [
            'BBCC' => Grade::firstOrCreate(['id_kategori_barang' => $kategori->id, 'nama_grade' => 'BBCC']),
            'UTY'  => Grade::firstOrCreate(['id_kategori_barang' => $kategori->id, 'nama_grade' => 'UTY / BTR']),
            'EXP'  => Grade::firstOrCreate(['id_kategori_barang' => $kategori->id, 'nama_grade' => 'UTY EXPORT']),
        ];

        // 2. Ambil kriteria untuk dipetakan
        $criteria = Criteria::where('id_kategori_barang', $kategori->id)->get()->keyBy('nama_kriteria');

        // 3. DEFINISI ATURAN (KNOWLEDGE BASE)
        // Nama kriteria di bawah ini harus SAMA PERSIS dengan yang ada di CriteriaSeeder

        // --- GRADE: BBCC (Premium - Sangat Ketat) ---
        $this->mapRules($grades['BBCC'], $criteria, [
            'Pecah Terbuka (Open Split)'        => ['not_allowed', 0],
            'Lubang Mata Kayu Mati (Dead Knots)' => ['not_allowed', 0],
            'Perbedaan Warna (Discoloration)'   => ['not_allowed', 0],
            'Dieliminasi / Ripping'             => ['not_allowed', 0],
            'Gelembung F/B'                     => ['not_allowed', 0],
            'Patching / Tambalan'               => ['not_allowed', 0],
            'Face Sambungan (Face Join)'        => ['not_allowed', 0],
            'Samping Kurang'                    => ['conditional', 20],
            'Botak (Over Sanding)'              => ['not_allowed', 0],
            'Core Hole'                         => ['conditional', 30],
            'Core Overlap'                      => ['conditional', 30],
            'Press Mark'                        => ['conditional', 40],
            'Cutter Mark'                       => ['not_allowed', 0],
            'Berjamur (Glue Penetration)'       => ['not_allowed', 0],
            'Blue Stain'                        => ['not_allowed', 0],
        ]);

        // --- GRADE: UTY / BTR (Standar Lokal - Moderat) ---
        $this->mapRules($grades['UTY'], $criteria, [
            'Pecah Terbuka (Open Split)'        => ['conditional', 50],
            'Lubang Mata Kayu Mati (Dead Knots)' => ['conditional', 50],
            'Perbedaan Warna (Discoloration)'   => ['allowed', 100],
            'Dieliminasi / Ripping'             => ['not_allowed', 0],
            'Gelembung F/B'                     => ['not_allowed', 0],
            'Patching / Tambalan'               => ['conditional', 60],
            'Face Sambungan (Face Join)'        => ['not_allowed', 0],
            'Samping Kurang'                    => ['conditional', 50],
            'Botak (Over Sanding)'              => ['not_allowed', 0],
            'Core Hole'                         => ['conditional', 60],
            'Core Overlap'                      => ['conditional', 60],
            'Press Mark'                        => ['conditional', 60],
            'Cutter Mark'                       => ['conditional', 70],
            'Berjamur (Glue Penetration)'       => ['not_allowed', 0],
            'Blue Stain'                        => ['conditional', 50],
        ]);

        // --- GRADE: UTY EXPORT (Kualitas Ekspor - Toleransi Tinggi) ---
        $this->mapRules($grades['EXP'], $criteria, [
            'Pecah Terbuka (Open Split)'        => ['conditional', 80],
            'Lubang Mata Kayu Mati (Dead Knots)' => ['allowed', 100],
            'Perbedaan Warna (Discoloration)'   => ['allowed', 100],
            'Dieliminasi / Ripping'             => ['allowed', 100],
            'Gelembung F/B'                     => ['not_allowed', 0],
            'Patching / Tambalan'               => ['conditional', 80],
            'Face Sambungan (Face Join)'        => ['conditional', 70],
            'Samping Kurang'                    => ['allowed', 100],
            'Botak (Over Sanding)'              => ['not_allowed', 0],
            'Core Hole'                         => ['conditional', 80],
            'Core Overlap'                      => ['conditional', 80],
            'Press Mark'                        => ['conditional', 80],
            'Cutter Mark'                       => ['allowed', 100],
            'Berjamur (Glue Penetration)'       => ['conditional', 60],
            'Blue Stain'                        => ['conditional', 80],
        ]);

        $this->command->info('Knowledge Base (Aturan Grade) berhasil diinstal!');
    }

    /**
     * Helper untuk memetakan aturan secara massal.
     */
    private function mapRules($grade, $criteriaCollection, $ruleMap)
    {
        foreach ($ruleMap as $criteriaName => $settings) {
            $criterion = $criteriaCollection->get($criteriaName);

            if ($criterion) {
                GradeRule::updateOrCreate(
                    [
                        'id_grade'    => $grade->id,
                        'id_criteria' => $criterion->id,
                    ],
                    [
                        'kondisi'      => $settings[0],
                        'poin_lulus'   => 100,
                        'poin_parsial' => $settings[1],
                        'penjelasan'   => "Standar {$grade->nama_grade}: " . ($settings[0] === 'not_allowed' ? 'Mutlak dilarang.' : 'Terdapat toleransi terbatas.'),
                    ]
                );
            } else {
                $this->command->warn("Kriteria tidak ditemukan: {$criteriaName}");
            }
        }
    }
}
