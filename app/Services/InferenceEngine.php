<?php

namespace App\Services;

use App\Models\Grade;
use App\Models\GradingSession;
use Illuminate\Support\Facades\Log;

class InferenceEngine
{
    /**
     * @param GradingSession $session
     * @param array $eliminatedGradeIds  Grade yang gugur — tetap dihitung, tidak bisa jadi winner
     */
    public function analyze(GradingSession $session, array $eliminatedGradeIds = []): array
    {
        Log::info('[ENGINE] START', [
            'id_session'    => $session->id,
            'grade_gugur'   => $eliminatedGradeIds,
        ]);

        $answers = $session
            ->answers()
            ->with('criteria')
            ->get()
            ->keyBy('id_criteria');

        // Ambil SEMUA grade — termasuk yang gugur, karena tetap dihitung
        $grades = Grade::where('id_kategori_barang', $session->id_kategori_barang)
            ->with(['gradeRules.criteria'])
            ->get();

        if ($grades->isEmpty()) {
            return $this->buildEmptyResult($session, 'Tidak ada grade untuk kategori ini.');
        }

        $eligible   = []; // grade yang bisa jadi winner
        $eliminated = []; // grade gugur — dihitung tapi tidak bisa menang

        foreach ($grades as $grade) {
            $rules = $grade->gradeRules;

            if ($rules->isEmpty()) {
                Log::warning('[ENGINE] Grade dilewati — rules kosong: ' . $grade->nama_grade);
                continue;
            }

            $totalMaxPoin   = (float) $rules->sum('poin_lulus');
            $earnedPoin     = 0.0;
            $passedCriteria = [];
            $failedCriteria = [];

            foreach ($rules as $rule) {
                $answer       = $answers->get($rule->id_criteria);
                $jawabanValue = $answer?->jawaban ?? 'tidak';
                $points       = (float) $rule->pointsFor($jawabanValue);
                $earnedPoin  += $points;

                $criteriaName = $rule->criteria?->nama_kriteria ?? '—';

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

            $entry = [
                'grade_id'        => $grade->id,
                'grade_name'      => $grade->nama_grade,
                'persentase'      => $persentase,
                'earned'          => $earnedPoin,
                'max'             => $totalMaxPoin,
                'passed_criteria' => $passedCriteria,
                'failed_criteria' => $failedCriteria,
                'is_eliminated'   => in_array($grade->id, $eliminatedGradeIds),
            ];

            Log::info('[ENGINE] ' . $grade->nama_grade, [
                'persentase'   => $persentase . '%',
                'is_eliminated' => $entry['is_eliminated'],
            ]);

            // Pisahkan ke bucket yang sesuai
            if (in_array($grade->id, $eliminatedGradeIds)) {
                $eliminated[$grade->nama_grade] = $entry;
            } else {
                $eligible[$grade->nama_grade] = $entry;
            }
        }

        // Winner hanya dari grade yang tidak gugur
        if (empty($eligible)) {
            return $this->buildEmptyResult($session, 'Semua grade gugur. Produk tidak memenuhi standar grade apapun.');
        }

        uasort($eligible, fn($a, $b) => $b['persentase'] <=> $a['persentase']);
        uasort($eliminated, fn($a, $b) => $b['persentase'] <=> $a['persentase']);

        $winner         = reset($eligible);
        $eligibleList   = array_values($eligible);
        $eliminatedList = array_values($eliminated);

        // Gabungkan untuk tampilan: eligible dulu, eliminated di bawah
        $allResults = array_merge($eligibleList, $eliminatedList);

        $alasan = $this->generateReasoning($winner, $eligibleList, count($eliminatedList));

        Log::info('[ENGINE] WINNER: ' . $winner['grade_name'] . ' ' . $winner['persentase'] . '%');

        try {
            $session->update([
                'status'           => 'completed',
                'hasil_grade_id'   => $winner['grade_id'],
                'persentase_hasil' => array_column($allResults, 'persentase', 'grade_name'),
                'alasan_utama'     => $alasan,
                'durasi_detik'     => now()->diffInSeconds($session->created_at),
            ]);
        } catch (\Throwable $e) {
            Log::error('[ENGINE] Gagal update session: ' . $e->getMessage());
        }

        return [
            'winner'   => $winner,
            'all'      => $allResults,
            'alasan'   => $alasan,
            'reasons'  => $this->buildReasonList($winner, $eligibleList, $eliminatedList),
        ];
    }

    private function generateReasoning(array $winner, array $eligible, int $eliminatedCount = 0): string
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

        $kalimat = "Produk ini {$verdict} grade {$name} dengan tingkat kesesuaian {$pct}%. ";

        if ($eliminatedCount > 0) {
            $kalimat .= "{$eliminatedCount} grade gugur karena ditemukan cacat yang tidak dapat ditoleransi. ";
        }

        $kalimat .= $failed === 0
            ? 'Seluruh parameter teknis terpenuhi.'
            : "{$failed} dari {$total} kriteria terdeteksi memiliki cacat, masih dalam batas toleransi grade ini.";

        return $kalimat;
    }

    private function buildReasonList(array $winner, array $eligible, array $eliminated): array
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
            $sample    = implode(', ', array_slice($winner['passed_criteria'], 0, 3));
            $more      = $passedCount > 3 ? ', dan ' . ($passedCount - 3) . ' lainnya.' : '.';
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

        // Tampilkan grade gugur sebagai informasi
        foreach (array_slice($eliminated, 0, 3) as $out) {
            $reasons[] = [
                'type' => 'fail',
                'icon' => '❌',
                'tag'  => "Grade {$out['grade_name']} Gugur",
                'text' => "Ditemukan cacat fatal yang tidak dapat ditoleransi. Skor teknis: {$out['persentase']}%.",
            ];
        }

        return $reasons;
    }

    private function buildEmptyResult(GradingSession $session, string $pesan = ''): array
    {
        $session->update(['status' => 'cancelled']);
        return [
            'winner'  => null,
            'all'     => [],
            'alasan'  => $pesan ?: 'Konfigurasi belum lengkap.',
            'reasons' => [],
        ];
    }
}
