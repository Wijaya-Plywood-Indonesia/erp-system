<?php

namespace App\Livewire;

use App\Models\Criteria;
use App\Models\Criterion;
use App\Models\GradingSession;
use App\Models\KategoriBarang;
use App\Models\SessionAnswer;
use App\Services\InferenceEngine;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * LIVEWIRE COMPONENT: GradingWizard
 *
 * Ini adalah PENGONTROL UTAMA tampilan sistem pakar.
 *
 * ═══════════════════════════════════════════════════════════
 * APA ITU LIVEWIRE?
 * ═══════════════════════════════════════════════════════════
 * Livewire memungkinkan PHP berjalan secara real-time di browser
 * tanpa perlu menulis JavaScript. Setiap kali pengawas klik tombol,
 * Livewire mengirim request ke server, menjalankan PHP, lalu
 * update DOM yang berubah saja — tanpa page reload.
 *
 * ═══════════════════════════════════════════════════════════
 * STATE MACHINE — 4 STEP
 * ═══════════════════════════════════════════════════════════
 *
 * [start] ──startGrading()──► [question] ──answer()──► [loading]
 * ▲                        │
 * │                 runInference()
 * restart()                  │
 * │                        ▼
 * [result] ◄─────────────────────
 *
 * ═══════════════════════════════════════════════════════════
 * ALUR DETAIL
 * ═══════════════════════════════════════════════════════════
 *
 * 1. mount()         → set default kategori dari KategoriBarang::first()
 * 2. startGrading()  → buat GradingSession di DB, pindah ke step 'question'
 * 3. answer('ya')    → simpan jawaban ke session_answers, increment index
 * answer('tidak') → (sama seperti di atas)
 * 4. Jika index >= total pertanyaan → step = 'loading'
 * Alpine.js mendeteksi perubahan step dan memanggil runInference()
 * 5. runInference()  → panggil InferenceEngine::analyze(), step = 'result'
 * 6. Blade view render hasil berdasarkan $this->result
 * 7. restart()        → kembali ke step 'start' untuk grading berikutnya
 */
class GradingWizard extends Component
{
    // ── Public Properties (State) ─────────────────────────────────────────────
    // Semua property public di Livewire otomatis di-sync ke view (blade).
    // Perubahan property → Livewire re-render bagian yang berubah saja.

    /** Step aktif saat ini: 'start' | 'question' | 'loading' | 'result' */
    public string $step = 'start';

    /** Index pertanyaan saat ini (0-based) */
    public int $currentIndex = 0;

    /** ID kategori barang yang dipilih pengawas */
    public int $idKategoriBarang = 0;

    /** Kode produk opsional (untuk identifikasi di lapangan) */
    public ?string $kodeProduk = null;

    /** ID session yang sedang aktif */
    public ?int $sessionId = null;

    /** Hasil akhir dari InferenceEngine::analyze() */
    public array $result = [];

    // ── Lifecycle ─────────────────────────────────────────────────────────────

    /**
     * Dipanggil sekali saat komponen pertama kali di-render.
     * Set default kategori ke yang pertama ada di database.
     */
    public function mount(): void
    {
        $first = KategoriBarang::first();
        if ($first) {
            $this->idKategoriBarang = $first->id;
        }
    }

    // ── Computed Properties ───────────────────────────────────────────────────
    // #[Computed] = hasil-nya di-cache dalam satu request.
    // Tidak dihitung ulang jika dipanggil berkali-kali dalam view.

    /**
     * Semua kategori barang yang tersedia.
     * Digunakan untuk dropdown pemilih kategori di step 'start'.
     */
    #[Computed]
    public function kategoriList()
    {
        return KategoriBarang::all();
    }

    /**
     * Semua kriteria aktif untuk kategori yang dipilih, urut berdasarkan kolom 'urutan'.
     * Ini adalah daftar pertanyaan yang akan ditampilkan satu per satu.
     */
    #[Computed]
    public function criteria()
    {
        if (! $this->idKategoriBarang) return collect();

        return Criteria::forKategori($this->idKategoriBarang)
            ->active()
            ->get();
    }

    /**
     * Total jumlah pertanyaan untuk kategori ini.
     */
    #[Computed]
    public function totalQuestions(): int
    {
        return $this->criteria->count();
    }

    /**
     * Pertanyaan yang sedang aktif saat ini (berdasarkan currentIndex).
     * Null jika index sudah melewati batas.
     */
    #[Computed]
    public function currentCriterion(): ?Criteria
    {
        return $this->criteria->get($this->currentIndex);
    }

    // ── Actions ───────────────────────────────────────────────────────────────

    /**
     * Dipanggil saat pengawas mengubah pilihan kategori di dropdown.
     * Reset index pertanyaan agar mulai dari awal.
     */
    public function updatedIdKategoriBarang(): void
    {
        $this->currentIndex = 0;
        unset($this->criteria); // invalidate computed cache
    }

    /**
     * Mulai sesi grading baru.
     * Membuat GradingSession di database dan pindah ke step 'question'.
     *
     * Dipanggil: wire:click="startGrading" di blade
     */
    public function startGrading(): void
    {
        // Guard: pastikan kategori dipilih dan ada pertanyaan
        if (! $this->idKategoriBarang) return;
        if ($this->criteria->isEmpty()) return;

        // Buat session baru di DB
        $session = GradingSession::create([
            'id_kategori_barang' => $this->idKategoriBarang,
            'kode_produk'         => $this->kodeProduk ?: null,
            'user_id'             => Auth::id(),
            'status'              => 'in_progress',
        ]);

        $this->sessionId    = $session->id;
        $this->currentIndex = 0;
        $this->step          = 'question';

        // Trigger animasi slide di Alpine.js
        $this->dispatch('question-changed');
    }

    /**
     * Rekam jawaban pengawas dan lanjut ke pertanyaan berikutnya.
     *
     * Ini adalah action paling sering dipanggil — setiap klik YA/TIDAK.
     * Prosesnya cepat: hanya satu INSERT ke database lalu increment index.
     *
     * @param  string  $jawaban  'ya' atau 'tidak'
     */
    public function answer(string $jawaban): void
    {
        // Guard: validasi input
        if (! in_array($jawaban, ['ya', 'tidak'], true)) return;
        if (! $this->sessionId) return;
        if (! $this->currentCriterion) return;

        // Simpan jawaban ke database
        // updateOrCreate → aman jika pengawas entah bagaimana klik dua kali
        SessionAnswer::updateOrCreate(
            [
                'session_id'   => $this->sessionId,
                'criterion_id' => $this->currentCriterion->id,
            ],
            [
                'jawaban'     => $jawaban,
                'answered_at' => now(),
            ]
        );

        // Lanjut ke pertanyaan berikutnya
        $this->currentIndex++;

        // Cek apakah semua pertanyaan sudah dijawab
        if ($this->currentIndex >= $this->totalQuestions) {
            // Pindah ke loading screen
            $this->step = 'loading';
            // Beritahu Alpine.js untuk mulai countdown lalu panggil runInference
            $this->dispatch('start-inference');
        } else {
            // Trigger animasi slide untuk pertanyaan baru
            $this->dispatch('question-changed');
        }
    }

    /**
     * Jalankan Inference Engine setelah animasi loading selesai.
     *
     * Dipanggil dari Alpine.js via: $wire.runInference()
     * (setelah delay 2 detik untuk animasi loading)
     */
    public function runInference(): void
    {
        $session = GradingSession::with('answers.criterion')
            ->find($this->sessionId);

        if (! $session) return;

        $engine       = new InferenceEngine();
        $this->result = $engine->analyze($session);
        $this->step   = 'result';
    }

    /**
     * Reset semua state — kembali ke halaman awal.
     * Dipanggil saat pengawas klik "Grading Baru".
     *
     * Method ini diubah namanya menjadi restart() untuk menghindari
     * tabrakan dengan method reset() bawaan Livewire Component.
     */
    public function restart(): void
    {
        $this->step          = 'start';
        $this->currentIndex = 0;
        $this->sessionId    = null;
        $this->kodeProduk   = null;
        $this->result       = [];

        unset($this->criteria); // invalidate computed cache
    }

    // ── Render ────────────────────────────────────────────────────────────────

    public function render()
    {
        return view('livewire.grading-wizard');
    }
}
