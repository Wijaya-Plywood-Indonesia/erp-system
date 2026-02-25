<?php

namespace App\Services;

use App\Models\Grade;
use App\Models\GradingSession;

class InferenceEngine
{
    public function analyze(GradingSession $session): array
    {
        // keyBy('id_criteria') sudah benar — kolom FK di session_answers
        $answers = $session
            ->answers()
            ->with('criteria')
            ->get()
            ->keyBy('id_criteria');

        $grades = Grade::where('id_kategori_barang', $session->id_kategori_barang)
            ->with(['gradeRules.criteria'])
            ->get();

        if ($grades->isEmpty()) {
            return $this->buildEmptyResult($session);
        }

        $results = [];

        foreach ($grades as $grade) {
            $rules = $grade->gradeRules;
            if ($rules->isEmpty()) continue;

            $totalMaxPoin   = (float) $rules->sum('poin_lulus');
            $earnedPoin     = 0.0;
            $passedCriteria = [];
            $failedCriteria = [];

            foreach ($rules as $rule) {
                $answer       = $answers->get($rule->id_criteria);
                $jawabanValue = $answer?->jawaban ?? 'tidak';

                // MASALAH #6: calculatePoints() tidak ada — method yang benar pointsFor()
                // FIX: ganti calculatePoints → pointsFor
                $points = (float) $rule->pointsFor($jawabanValue);
                $earnedPoin += $points;

                $criteriaName = $rule->criteria->nama_kriteria ?? '—';

                if ($jawabanValue === 'tidak' || $rule->kondisi === 'allowed') {
                    $passedCriteria[] = $criteriaName;
                } else {
                    $failedCriteria[] = [
                        'nama'       => $criteriaName,
                        'penjelasan' => $rule->penjelasan,
                        'kondisi'    => $rule->kondisi,
                        'poin'       => $points,
                        'max_poin'   => $rule->poin_lulus,
                    ];
                }
            }

            $persentase = $totalMaxPoin > 0
                ? round(($earnedPoin / $totalMaxPoin) * 100, 1)
                : 0.0;

            $results[$grade->nama_grade] = [
                'grade_id'        => $grade->id,
                'grade_name'      => $grade->nama_grade,
                'persentase'      => $persentase,
                'earned'          => $earnedPoin,
                'max'             => $totalMaxPoin,
                'passed_criteria' => $passedCriteria,
                'failed_criteria' => $failedCriteria,
            ];
        }

        if (empty($results)) {
            return $this->buildEmptyResult($session);
        }

        uasort($results, fn($a, $b) => $b['persentase'] <=> $a['persentase']);

        $winner     = reset($results);
        $allResults = array_values($results);
        $alasan     = $this->generateReasoning($winner, $allResults);

        $session->update([
            'status'           => 'completed',
            'hasil_grade_id'   => $winner['grade_id'],
            'persentase_hasil' => array_column($allResults, 'persentase', 'grade_name'),
            'alasan_utama'     => $alasan,
            'durasi_detik'     => now()->diffInSeconds($session->created_at),
        ]);

        return [
            'winner'  => $winner,
            'all'     => $allResults,
            'alasan'  => $alasan,
            'reasons' => $this->buildReasonList($winner, $allResults),
        ];
    }

    private function generateReasoning(array $winner, array $all): string
    {
        $pct    = $winner['persentase'];
        $name   = $winner['grade_name'];
        $failed = count($winner['failed_criteria']);
        $total  = count($winner['passed_criteria']) + $failed;

        $verdict = match (true) {
            $pct >= 90 => 'sangat memenuhi standar',
            $pct >= 75 => 'memenuhi sebagian besar standar',
            $pct >= 55 => 'cukup memenuhi standar dengan beberapa toleransi',
            default    => 'memerlukan perhatian khusus',
        };

        $kalimat  = "Produk ini {$verdict} grade {$name} dengan tingkat kesesuaian {$pct}%. ";
        $kalimat .= $failed === 0
            ? 'Seluruh parameter teknis terpenuhi dengan sempurna.'
            : "{$failed} dari {$total} kriteria terdeteksi memiliki cacat fisik, namun masih dalam batas toleransi grade ini.";

        return $kalimat;
    }

    private function buildReasonList(array $winner, array $all): array
    {
        $reasons = [];

        $reasons[] = [
            'type' => 'ok',
            'icon' => '✅',
            'tag'  => 'Rekomendasi Terpilih',
            'text' => "Grade {$winner['grade_name']} memiliki tingkat kecocokan tertinggi ({$winner['persentase']}%).",
        ];

        $passedCount = count($winner['passed_criteria']);
        if ($passedCount > 0) {
            $sample = implode(', ', array_slice($winner['passed_criteria'], 0, 3));
            $more   = $passedCount > 3 ? ", dan " . ($passedCount - 3) . " lainnya." : '.';
            $reasons[] = [
                'type' => 'ok',
                'icon' => '✅',
                'tag'  => "{$passedCount} Kriteria Terpenuhi",
                'text' => "Termasuk: {$sample}{$more}",
            ];
        }

        foreach (array_slice($winner['failed_criteria'], 0, 3) as $fail) {
            $reasons[] = [
                'type' => 'warn',
                'icon' => '⚠️',
                'tag'  => 'Toleransi Terpakai',
                'text' => $fail['penjelasan'] ?? "Cacat pada {$fail['nama']} masih dalam batas toleransi.",
            ];
        }

        $losers = array_filter($all, fn($r) => $r['grade_name'] !== $winner['grade_name']);
        foreach (array_slice(array_values($losers), 0, 2) as $loser) {
            if (count($loser['failed_criteria']) > 0) {
                $reasons[] = [
                    'type' => 'fail',
                    'icon' => '❌',
                    'tag'  => "Grade {$loser['grade_name']}",
                    'text' => "Hanya {$loser['persentase']}% — " . count($loser['failed_criteria']) . " kriteria tidak memenuhi standar.",
                ];
            }
        }

        return $reasons;
    }

    private function buildEmptyResult(GradingSession $session): array
    {
        $session->update(['status' => 'cancelled']);
        return [
            'winner'  => null,
            'all'     => [],
            'alasan'  => 'Konfigurasi grade atau kriteria belum lengkap di database.',
            'reasons' => [],
        ];
    }
}
