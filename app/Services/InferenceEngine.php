<?php

namespace App\Services;

use App\Models\Grade;
use App\Models\GradingSession;
use Illuminate\Support\Facades\DB;

class InferenceEngine
{
    /**
     * Entry point utama - jalankan analisis untuk satu sesi grading.
     *
     * @param GradingSession $session
     * @return array
     */
    public function analyze(GradingSession $session): array
    {
        // STEP 1: Kumpulkan semua FAKTA (jawaban pengawas)
        $answers = $session
            ->answers()
            ->with('criterion')
            ->get()
            ->keyBy('criterion_id');

        // STEP 2: Ambil semua Grade untuk kategori ini
        $grades = Grade::where('id_kategori_barang', $session->id_kategori_barang)
            ->with(['gradeRules.criterion'])
            ->get();

        if ($grades->isEmpty()) {
            return $this->buildEmptyResult($session);
        }

        // STEP 3: Hitung poin untuk setiap Grade
        $results = [];

        foreach ($grades as $grade) {
            $rules = $grade->gradeRules;

            // Skip grade yang belum punya knowledge base
            if ($rules->isEmpty()) {
                continue;
            }

            $totalMaxPoin = (float) $rules->sum('poin_lulus');
            $earnedPoin = 0.0;
            $passedCriteria = [];
            $failedCriteria = [];

            foreach ($rules as $rule) {
                // Cari jawaban pengawas untuk kriteria ini
                $answer = $answers->get($rule->criterion_id);

                // Jika tidak ada jawaban, anggap 'tidak' (tidak ada cacat)
                $jawabanValue = $answer?->jawaban ?? 'tidak';

                // Hitung poin (Pastikan method pointsFor tersedia di model GradeRule)
                $points = (float) $rule->pointsFor($jawabanValue);
                $earnedPoin += $points;

                $criterionName = $rule->criterion->nama_kriteria;

                // Klasifikasi kriteria
                if ($jawabanValue === 'tidak' || $rule->kondisi === 'allowed') {
                    $passedCriteria[] = $criterionName;
                } else {
                    $failedCriteria[] = [
                        'nama' => $criterionName,
                        'penjelasan' => $rule->penjelasan,
                        'kondisi' => $rule->kondisi,
                        'poin' => $points,
                        'max_poin' => $rule->poin_lulus,
                    ];
                }
            }

            // Hitung persentase akhir untuk grade ini
            $persentase = $totalMaxPoin > 0
                ? round(($earnedPoin / $totalMaxPoin) * 100, 1)
                : 0.0;

            $results[$grade->nama_grade] = [
                'grade_id' => $grade->id,
                'grade_name' => $grade->nama_grade,
                'persentase' => $persentase,
                'earned' => $earnedPoin,
                'max' => $totalMaxPoin,
                'passed_criteria' => $passedCriteria,
                'failed_criteria' => $failedCriteria,
            ];
        }

        if (empty($results)) {
            return $this->buildEmptyResult($session);
        }

        // STEP 4: Urutkan - grade dengan skor tertinggi = rekomendasi
        uasort($results, function ($a, $b) {
            return $b['persentase'] <=> $a['persentase'];
        });

        $winner = reset($results);
        $allResults = array_values($results);
        $alasan = $this->generateReasoning($winner, $allResults);

        // STEP 5: Simpan hasil ke database
        $session->update([
            'status' => 'completed',
            'hasil_grade_id' => $winner['grade_id'],
            'persentase_hasil' => array_column($allResults, 'persentase', 'grade_name'),
            'alasan_utama' => $alasan,
            'durasi_detik' => now()->diffInSeconds($session->created_at),
        ]);

        // STEP 6: Kembalikan hasil
        return [
            'winner' => $winner,
            'all' => $allResults,
            'alasan' => $alasan,
            'reasons' => $this->buildReasonList($winner, $allResults),
        ];
    }

    /**
     * Buat satu paragraf ringkas alasan pemilihan grade.
     */
    private function generateReasoning(array $winner, array $all): string
    {
        $pct = $winner['persentase'];
        $name = $winner['grade_name'];
        $failed = count($winner['failed_criteria']);
        $total = count($winner['passed_criteria']) + $failed;

        $verdict = 'memerlukan perhatian khusus';
        if ($pct >= 90) {
            $verdict = 'sangat memenuhi standar';
        } elseif ($pct >= 75) {
            $verdict = 'memenuhi sebagian besar standar';
        } elseif ($pct >= 55) {
            $verdict = 'cukup memenuhi standar dengan beberapa catatan';
        }

        $kalimat = "Produk ini " . $verdict . " grade " . $name . " dengan kesesuaian " . $pct . "%. ";

        if ($failed === 0) {
            $kalimat .= "Seluruh kriteria terpenuhi tanpa cacat yang mendiskualifikasi.";
        } else {
            $kalimat .= $failed . " dari " . $total . " kriteria memiliki cacat, namun masih dalam batas toleransi grade ini.";
        }

        return $kalimat;
    }

    /**
     * Bangun daftar alasan detail untuk UI.
     */
    private function buildReasonList(array $winner, array $all): array
    {
        $reasons = [];

        // Rekomendasi Utama
        $reasons[] = [
            'type' => 'ok',
            'icon' => 'check',
            'tag' => 'Rekomendasi Terpilih',
            'text' => "Grade " . $winner['grade_name'] . " memiliki tingkat kesesuaian tertinggi (" . $winner['persentase'] . "%) dibanding grade lainnya.",
        ];

        // Kriteria Terpenuhi
        $passedCount = count($winner['passed_criteria']);
        if ($passedCount > 0) {
            $sample = implode(', ', array_slice($winner['passed_criteria'], 0, 3));
            $suffix = $passedCount > 3 ? ", dan " . ($passedCount - 3) . " kriteria lainnya." : ".";

            $reasons[] = [
                'type' => 'ok',
                'icon' => 'check',
                'tag' => $passedCount . " Kriteria Terpenuhi",
                'text' => "Termasuk: " . $sample . $suffix,
            ];
        }

        // Peringatan Cacat
        foreach (array_slice($winner['failed_criteria'], 0, 3) as $fail) {
            $reasons[] = [
                'type' => 'warn',
                'icon' => 'alert',
                'tag' => 'Perlu Diperhatikan',
                'text' => $fail['penjelasan'] ?? $fail['nama'] . " memiliki cacat yang masih dalam batas toleransi.",
            ];
        }

        // Analisis Losers
        $losers = array_slice(
            array_filter($all, function ($r) use ($winner) {
                return $r['grade_name'] !== $winner['grade_name'];
            }),
            0,
            2
        );

        foreach ($losers as $loser) {
            $failCount = count($loser['failed_criteria']);
            if ($failCount > 0) {
                $reasons[] = [
                    'type' => 'fail',
                    'icon' => 'x',
                    'tag' => "Grade " . $loser['grade_name'],
                    'text' => "Hanya " . $loser['persentase'] . "% kesesuaian - " . $failCount . " kriteria tidak memenuhi standar grade ini.",
                ];
            }
        }

        return $reasons;
    }

    /**
     * Hasil kosong jika konfigurasi tidak ditemukan.
     */
    private function buildEmptyResult(GradingSession $session): array
    {
        $session->update(['status' => 'cancelled']);

        return [
            'winner' => null,
            'all' => [],
            'alasan' => 'Tidak ada grade yang dikonfigurasi untuk kategori ini. Hubungi administrator.',
            'reasons' => [],
        ];
    }
}
