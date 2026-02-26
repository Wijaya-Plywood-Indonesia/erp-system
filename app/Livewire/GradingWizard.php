<?php

namespace App\Livewire;

use App\Models\Criteria;
use App\Models\Grade;
use App\Models\GradeRule;
use App\Models\GradingSession;
use App\Models\KategoriBarang;
use App\Models\SessionAnswer;
use App\Services\InferenceEngine;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Computed;
use Livewire\Component;

class GradingWizard extends Component
{
    public string  $step             = 'start';
    public int     $currentIndex     = 0;
    public int     $idKategoriBarang = 0;
    public ?string $kodeProduk       = null;
    public ?int    $sessionId        = null;
    public array   $result           = [];

    /**
     * Grade yang gugur karena cacat fatal (not_allowed + YA).
     * Gugur = tidak bisa jadi winner.
     * Gugur ≠ dihapus dari perhitungan — persentase tetap dihitung.
     */
    public array $eliminatedGradeIds = [];

    public function mount(): void
    {
        $first = KategoriBarang::first();
        if ($first) {
            $this->idKategoriBarang = $first->id;
        }
    }

    #[Computed]
    public function kategoriList()
    {
        return KategoriBarang::all();
    }

    #[Computed]
    public function criteria()
    {
        if (! $this->idKategoriBarang) return collect();

        return Criteria::where('id_kategori_barang', $this->idKategoriBarang)
            ->where('is_active', true)
            ->orderBy('urutan', 'asc')
            ->get();
    }

    #[Computed]
    public function totalQuestions(): int
    {
        return $this->criteria->count();
    }

    #[Computed]
    public function currentCriterion(): ?Criteria
    {
        // Semua pertanyaan diajukan — tidak ada yang dilewati karena eliminasi
        return $this->criteria->get($this->currentIndex);
    }

    #[Computed]
    public function availableGrades()
    {
        if (! $this->idKategoriBarang) return collect();
        return Grade::where('id_kategori_barang', $this->idKategoriBarang)->get();
    }

    #[Computed]
    public function isReady(): bool
    {
        if ($this->criteria->isEmpty()) return false;
        if ($this->availableGrades->isEmpty()) return false;
        $gradeIds = $this->availableGrades->pluck('id');
        return GradeRule::whereIn('id_grade', $gradeIds)->exists();
    }

    #[Computed]
    public function readinessError(): ?string
    {
        if ($this->availableGrades->isEmpty()) {
            return 'Belum ada grade untuk kategori ini.';
        }
        if ($this->criteria->isEmpty()) {
            return 'Belum ada pertanyaan. Tambahkan di menu Pertanyaan.';
        }
        $gradeIds = $this->availableGrades->pluck('id');
        if (! GradeRule::whereIn('id_grade', $gradeIds)->exists()) {
            return 'Aturan grade belum dikonfigurasi. Isi di menu Aturan Grade.';
        }
        return null;
    }

    public function updatedIdKategoriBarang(): void
    {
        $this->currentIndex       = 0;
        $this->eliminatedGradeIds = [];
        unset($this->criteria, $this->availableGrades);
    }

    public function startGrading(): void
    {
        if (! $this->isReady) return;

        $session = GradingSession::create([
            'id_kategori_barang' => $this->idKategoriBarang,
            'kode_produk'        => $this->kodeProduk ?: null,
            'user_id'            => Auth::id(),
            'status'             => 'in_progress',
        ]);

        $this->sessionId          = $session->id;
        $this->currentIndex       = 0;
        $this->eliminatedGradeIds = [];
        $this->step               = 'question';

        $this->dispatch('question-changed');
    }

    public function answer(string $jawaban): void
    {
        if (! in_array($jawaban, ['ya', 'tidak'], true)) return;
        if (! $this->sessionId || ! $this->currentCriterion) return;

        $criterion = $this->currentCriterion;

        SessionAnswer::updateOrCreate(
            [
                'id_session'  => $this->sessionId,
                'id_criteria' => $criterion->id,
            ],
            [
                'jawaban'     => $jawaban,
                'answered_at' => now(),
            ]
        );

        // Tandai grade sebagai gugur jika jawaban YA + kondisi not_allowed
        // Grade gugur tetap dihitung persentasenya, hanya tidak bisa jadi winner
        if ($jawaban === 'ya') {
            $this->markEliminated($criterion->id);
        }

        $this->currentIndex++;

        // Selesai jika semua pertanyaan sudah dijawab
        if ($this->currentIndex >= $this->totalQuestions) {
            Log::info('[WIZARD] Semua pertanyaan selesai', [
                'total_dijawab' => $this->currentIndex,
                'grade_gugur'   => $this->eliminatedGradeIds,
            ]);

            $this->step = 'loading';
            $this->dispatch('start-inference');
        } else {
            $this->dispatch('question-changed');
        }
    }

    /**
     * Tandai grade sebagai gugur (tidak bisa jadi winner).
     * Dipanggil saat jawaban YA pada kriteria dengan kondisi not_allowed.
     */
    private function markEliminated(int $criteriaId): void
    {
        $allGradeIds = $this->availableGrades->pluck('id');

        $toEliminate = GradeRule::where('id_criteria', $criteriaId)
            ->whereIn('id_grade', $allGradeIds)
            ->where('kondisi', 'not_allowed')
            ->pluck('id_grade')
            ->toArray();

        if (! empty($toEliminate)) {
            Log::info('[WIZARD] Grade gugur (tetap dihitung)', [
                'criteria_id' => $criteriaId,
                'gugur'       => Grade::whereIn('id', $toEliminate)->pluck('nama_grade'),
            ]);

            $this->eliminatedGradeIds = array_unique(
                array_merge($this->eliminatedGradeIds, $toEliminate)
            );
        }
    }

    public function runInference(): void
    {
        if (! $this->sessionId) return;

        try {
            $session = GradingSession::with('answers.criteria')
                ->find($this->sessionId);

            if (! $session) return;

            $this->result = (new InferenceEngine())->analyze(
                $session,
                $this->eliminatedGradeIds
            );

            $this->step = 'result';
        } catch (\Throwable $e) {
            Log::error('[WIZARD] runInference() Exception', [
                'message' => $e->getMessage(),
                'line'    => $e->getLine(),
            ]);
        }
    }

    public function restart(): void
    {
        $this->step               = 'start';
        $this->currentIndex       = 0;
        $this->sessionId          = null;
        $this->kodeProduk         = null;
        $this->result             = [];
        $this->eliminatedGradeIds = [];

        unset($this->criteria, $this->availableGrades);
    }

    public function render()
    {
        return view('livewire.grading-wizard');
    }
}
