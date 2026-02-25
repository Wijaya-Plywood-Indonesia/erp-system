<?php

namespace App\Livewire;

use App\Models\Criteria;
use App\Models\Grade;
use App\Models\GradeRule;
use App\Models\GradingSession;
use App\Models\KategoriBarang;
use App\Models\SessionAnswer;
use App\Services\InferenceEngine;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Computed;
use Livewire\Component;

class GradingWizard extends Component
{
    // --- State Properties ---
    public string  $step             = 'start';
    public int     $currentIndex     = 0;
    public int     $idKategoriBarang = 0;
    public ?string $kodeProduk       = null;
    public ?int    $sessionId        = null;
    public array   $result           = [];

    /**
     * Inisialisasi komponen saat pertama kali dimuat.
     */
    public function mount(): void
    {
        $first = KategoriBarang::first();

        Log::channel('stack')->info('[WIZARD] mount()', [
            'user_id'     => Auth::id(),
            'kategori_id' => $first?->id,
            'kategori'    => $first?->nama_kategori,
        ]);

        if ($first) {
            $this->idKategoriBarang = $first->id;
        } else {
            Log::channel('stack')->warning('[WIZARD] mount() — KategoriBarang::first() null! Tabel kategori_barang kosong?');
        }
    }

    // --- Computed Properties (Caching logic for performance) ---

    #[Computed]
    public function kategoriList()
    {
        return KategoriBarang::all();
    }

    #[Computed]
    public function criteria()
    {
        if (!$this->idKategoriBarang) {
            Log::channel('stack')->warning('[WIZARD] criteria() — idKategoriBarang = 0, return collect kosong');
            return collect();
        }

        $result = Criteria::where('id_kategori_barang', $this->idKategoriBarang)
            ->where('is_active', true)
            ->orderBy('urutan', 'asc')
            ->get();

        Log::channel('stack')->info('[WIZARD] criteria() loaded', [
            'id_kategori_barang' => $this->idKategoriBarang,
            'count'              => $result->count(),
            'names'              => $result->pluck('nama_kriteria')->toArray(),
        ]);

        if ($result->isEmpty()) {
            Log::channel('stack')->warning('[WIZARD] criteria() — KOSONG! Kemungkinan: Tabel belum diisi atau is_active semua false.');
        }

        return $result;
    }

    #[Computed]
    public function totalQuestions(): int
    {
        return $this->criteria->count();
    }

    #[Computed]
    public function currentCriterion(): ?Criteria
    {
        $criterion = $this->criteria->get($this->currentIndex);

        if (!$criterion) {
            Log::channel('stack')->warning('[WIZARD] currentCriterion() — NULL!', [
                'currentIndex'   => $this->currentIndex,
                'totalQuestions' => $this->totalQuestions,
            ]);
        }

        return $criterion;
    }

    #[Computed]
    public function availableGrades()
    {
        if (!$this->idKategoriBarang) return collect();

        $grades = Grade::where('id_kategori_barang', $this->idKategoriBarang)->get();

        Log::channel('stack')->info('[WIZARD] availableGrades()', [
            'id_kategori_barang' => $this->idKategoriBarang,
            'count'              => $grades->count(),
            'grades'             => $grades->pluck('nama_grade')->toArray(),
        ]);

        return $grades;
    }

    #[Computed]
    public function isReady(): bool
    {
        if ($this->criteria->isEmpty()) {
            Log::channel('stack')->warning('[WIZARD] isReady = false — criteria kosong');
            return false;
        }
        if ($this->availableGrades->isEmpty()) {
            Log::channel('stack')->warning('[WIZARD] isReady = false — availableGrades kosong');
            return false;
        }

        $gradeIds = $this->availableGrades->pluck('id');
        $hasRules = GradeRule::whereIn('id_grade', $gradeIds)->exists();

        Log::channel('stack')->info('[WIZARD] isReady check', [
            'criteria_count' => $this->criteria->count(),
            'has_rules'      => $hasRules,
            'result'         => $hasRules,
        ]);

        return $hasRules;
    }

    #[Computed]
    public function readinessError(): ?string
    {
        if ($this->availableGrades->isEmpty()) {
            return 'Belum ada grade untuk kategori ini.';
        }
        if ($this->criteria->isEmpty()) {
            return 'Belum ada pertanyaan. Tambahkan kriteria di menu Master Kriteria.';
        }
        $gradeIds = $this->availableGrades->pluck('id');
        if (!GradeRule::whereIn('id_grade', $gradeIds)->exists()) {
            return 'Aturan grade (Knowledge Base) belum dikonfigurasi. Isi di menu Aturan Grade.';
        }
        return null;
    }

    // --- Actions & Methods ---

    /**
     * Triggered saat user mengganti kategori barang di layar start.
     */
    public function updatedIdKategoriBarang(): void
    {
        Log::channel('stack')->info('[WIZARD] updatedIdKategoriBarang()', [
            'new_value' => $this->idKategoriBarang,
        ]);
        $this->currentIndex = 0;
        unset($this->criteria, $this->availableGrades);
    }

    /**
     * Membuat sesi baru dan memulai kuesioner.
     */
    public function startGrading(): void
    {
        Log::channel('stack')->info('[WIZARD] startGrading() called', [
            'isReady'            => $this->isReady,
            'idKategoriBarang'   => $this->idKategoriBarang,
            'totalQuestions'     => $this->totalQuestions,
            'user_id'            => Auth::id(),
        ]);

        if (!$this->isReady) {
            Log::channel('stack')->warning('[WIZARD] startGrading() — ABORTED, isReady = false');
            return;
        }

        try {
            $session = GradingSession::create([
                'id_kategori_barang' => $this->idKategoriBarang,
                'kode_produk'        => $this->kodeProduk ?: null,
                'user_id'            => Auth::id(),
                'status'             => 'in_progress',
            ]);

            Log::channel('stack')->info('[WIZARD] GradingSession created', [
                'id_session' => $session->id,
            ]);

            $this->sessionId    = $session->id;
            $this->currentIndex = 0;
            $this->step         = 'question';

            $this->dispatch('question-changed');
        } catch (\Throwable $e) {
            Log::channel('stack')->error('[WIZARD] startGrading() — Exception!', [
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Menyimpan jawaban per pertanyaan dan navigasi index.
     */
    public function answer(string $jawaban): void
    {
        Log::channel('stack')->info('[WIZARD] answer() called', [
            'jawaban'      => $jawaban,
            'sessionId'    => $this->sessionId,
            'currentIndex' => $this->currentIndex,
            'criterionId'  => $this->currentCriterion?->id,
        ]);

        if (!in_array($jawaban, ['ya', 'tidak'], true)) return;
        if (!$this->sessionId || !$this->currentCriterion) return;

        try {
            // Menggunakan updateOrCreate untuk mendukung fitur 'kembali' (jika nanti ditambahkan)
            SessionAnswer::updateOrCreate(
                [
                    'id_session'  => $this->sessionId,
                    'id_criteria' => $this->currentCriterion->id,
                ],
                [
                    'jawaban'     => $jawaban,
                    'answered_at' => now(),
                ]
            );

            Log::channel('stack')->info('[WIZARD] SessionAnswer saved', [
                'id_criteria' => $this->currentCriterion->id,
                'jawaban'     => $jawaban,
            ]);
        } catch (\Throwable $e) {
            Log::channel('stack')->error('[WIZARD] answer() — SessionAnswer Exception!', [
                'message' => $e->getMessage(),
            ]);
            return;
        }

        $this->currentIndex++;

        if ($this->currentIndex >= $this->totalQuestions) {
            Log::channel('stack')->info('[WIZARD] All questions answered — switching to loading');
            $this->step = 'loading';
            $this->dispatch('start-inference');
        } else {
            $this->dispatch('question-changed');
        }
    }

    /**
     * Menjalankan mesin inferensi untuk mendapatkan hasil rekomendasi grade.
     */
    public function runInference(): void
    {
        Log::channel('stack')->info('[WIZARD] runInference() called', [
            'sessionId' => $this->sessionId,
        ]);

        if (!$this->sessionId) return;

        try {
            // Load session beserta fakta-fakta jawabannya
            $session = GradingSession::with('answers.criteria')->find($this->sessionId);

            if (!$session) {
                Log::channel('stack')->error('[WIZARD] runInference() — GradingSession not found!');
                return;
            }

            // Eksekusi Mesin Inferensi
            $this->result = (new InferenceEngine())->analyze($session);

            Log::channel('stack')->info('[WIZARD] InferenceEngine result', [
                'winner'     => $this->result['winner']['grade_name'] ?? 'null',
                'persentase' => $this->result['winner']['persentase'] ?? 'null',
            ]);

            $this->step = 'result';
        } catch (\Throwable $e) {
            Log::channel('stack')->error('[WIZARD] runInference() — Exception!', [
                'message' => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Mereset seluruh state untuk memulai grading baru.
     */
    public function restart(): void
    {
        Log::channel('stack')->info('[WIZARD] restart() called');

        $this->step         = 'start';
        $this->currentIndex = 0;
        $this->sessionId    = null;
        $this->kodeProduk   = null;
        $this->result       = [];

        // Clear computed properties cache
        unset($this->criteria, $this->availableGrades);
    }

    public function render()
    {
        return view('livewire.grading-wizard');
    }
}
