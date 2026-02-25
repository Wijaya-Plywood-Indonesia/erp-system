<div
    x-data="{
        step: @entangle('step').live,

        log(label, data = null) {
            const style = 'background:#d97706;color:white;padding:2px 6px;border-radius:3px;font-weight:bold'
            console.log('%c[WIZARD] ' + label, style, data ?? '')
        },

        logError(label, data = null) {
            const style = 'background:#dc2626;color:white;padding:2px 6px;border-radius:3px;font-weight:bold'
            console.error('%c[WIZARD ERROR] ' + label, style, data ?? '')
        },

        init() {
            this.log('Component init', { step: this.step })

            this.$watch('step', (newVal, oldVal) => {
                this.log('Step changed', { from: oldVal, to: newVal })
                if (newVal === 'question') {
                    this.$nextTick(() => this.slideIn())
                }
            })

            this.$wire.on('start-inference', () => {
                this.log('Event: start-inference — runInference dalam 2 detik')
                setTimeout(() => {
                    this.log('Calling runInference()')
                    this.$wire.runInference()
                }, 2000)
            })

            this.$wire.on('question-changed', () => {
                this.log('Event: question-changed')
                this.$nextTick(() => this.slideIn())
            })

            document.addEventListener('livewire:exception', (e) => {
                this.logError('Livewire server exception!', {
                    message: e.detail?.message,
                    detail: e.detail
                })
            })
        },

        slideIn() {
            const el = this.$refs.questionBody
            if (!el) {
                this.logError('$refs.questionBody tidak ditemukan!')
                return
            }
            el.style.animation = 'none'
            el.offsetHeight
            el.style.animation = ''
            el.classList.remove('wizard-slide-in')
            void el.offsetWidth
            el.classList.add('wizard-slide-in')
        }
    }"
    class="min-h-[calc(100vh-4rem)] w-full flex flex-col items-center justify-center
           px-4 py-8 transition-colors duration-300
           bg-amber-50 dark:bg-zinc-950">

    {{-- ================================================================
         PENTING: Gunakan HANYA @if Blade untuk mengontrol step mana yang
         di-render. JANGAN gabungkan dengan x-show/x-cloak pada elemen
         yang sama — ini menyebabkan konten hilang karena:
         1. x-cloak menyembunyikan elemen sebelum Alpine init
         2. Livewire re-render mengubah DOM sebelum Alpine bisa sync
         Blade @if sudah cukup karena Livewire wire:navigate re-render
         seluruh komponen saat step berubah di server.
    ================================================================ --}}

    @if($step === 'start')
    {{-- ================================================================ --}}
    {{-- STEP 1: START                                                     --}}
    {{-- ================================================================ --}}
    <div class="w-full max-w-md space-y-6 text-center
                animate-[wizardSlideIn_0.3s_ease-out]">

        <div>
            <h1 class="text-4xl font-light tracking-tight text-zinc-900 dark:text-zinc-50"
                style="font-family: 'DM Serif Display', serif">
                Konfirmasi <span class="italic text-amber-600 dark:text-amber-400">Grade</span>
            </h1>
            <p class="mt-2 text-sm leading-relaxed text-zinc-500 dark:text-zinc-400">
                Jawab pertanyaan tentang kondisi produk.<br>
                Sistem akan mengkonfirmasi grade yang paling sesuai.
            </p>
        </div>

        {{-- Stats — dinamis dari DB --}}
        <div class="grid grid-cols-3 gap-3">
            <div class="rounded-xl py-4 border bg-white dark:bg-zinc-900 border-amber-200 dark:border-zinc-800">
                <div class="font-mono text-2xl font-medium text-amber-600 dark:text-amber-400">
                    {{ $this->totalQuestions > 0 ? $this->totalQuestions : '–' }}
                </div>
                <div class="text-xs uppercase tracking-widest mt-1 text-zinc-400 dark:text-zinc-500">Pertanyaan</div>
            </div>
            <div class="rounded-xl py-4 border bg-white dark:bg-zinc-900 border-amber-200 dark:border-zinc-800">
                <div class="font-mono text-2xl font-medium text-amber-600 dark:text-amber-400">
                    {{ $this->availableGrades->count() > 0 ? $this->availableGrades->count() : '–' }}
                </div>
                <div class="text-xs uppercase tracking-widest mt-1 text-zinc-400 dark:text-zinc-500">Grade</div>
            </div>
            <div class="rounded-xl py-4 border bg-white dark:bg-zinc-900 border-amber-200 dark:border-zinc-800">
                <div class="font-mono text-2xl font-medium text-amber-600 dark:text-amber-400">&lt;2'</div>
                <div class="text-xs uppercase tracking-widest mt-1 text-zinc-400 dark:text-zinc-500">Estimasi</div>
            </div>
        </div>

        @if($this->kategoriList->count() > 1)
        <div class="text-left">
            <label class="block text-xs font-semibold uppercase tracking-widest mb-2
                           text-zinc-500 dark:text-zinc-400">
                Kategori Produk
            </label>
            <select wire:model.live="idKategoriBarang"
                class="w-full rounded-xl border px-4 py-3 text-sm font-medium
                           bg-white dark:bg-zinc-900 border-amber-200 dark:border-zinc-700
                           text-zinc-800 dark:text-zinc-200
                           focus:outline-none focus:ring-2 focus:ring-amber-500">
                @foreach($this->kategoriList as $kategori)
                <option value="{{ $kategori->id }}">{{ $kategori->nama_kategori }}</option>
                @endforeach
            </select>
        </div>
        @endif

        {{-- Debug box — hapus setelah production --}}
        <div class="rounded-xl p-4 text-left font-mono text-xs space-y-1.5
                    bg-zinc-100 dark:bg-zinc-900 border border-zinc-300 dark:border-zinc-700">
            <p class="font-sans font-semibold text-xs uppercase tracking-widest text-zinc-400 mb-2">🔧 Debug</p>
            <div class="flex gap-2">
                <span class="w-32 text-zinc-400">Pertanyaan</span>
                <span class="{{ $this->totalQuestions > 0 ? 'text-emerald-600' : 'text-red-500' }}">
                    {{ $this->totalQuestions > 0 ? '✓ '.$this->totalQuestions.' aktif' : '❌ 0 — tabel criterias kosong?' }}
                </span>
            </div>
            <div class="flex gap-2">
                <span class="w-32 text-zinc-400">Grade</span>
                <span class="{{ $this->availableGrades->count() > 0 ? 'text-emerald-600' : 'text-red-500' }}">
                    {{ $this->availableGrades->count() > 0
                        ? '✓ '.$this->availableGrades->pluck('nama_grade')->join(', ')
                        : '❌ 0 — tidak ada grade' }}
                </span>
            </div>
            <div class="flex gap-2">
                <span class="w-32 text-zinc-400">isReady</span>
                <span class="{{ $this->isReady ? 'text-emerald-600' : 'text-red-500' }}">
                    {{ $this->isReady ? '✓ true' : '❌ false' }}
                </span>
            </div>
            <div class="flex gap-2">
                <span class="w-32 text-zinc-400">Step (PHP)</span>
                <span class="text-amber-500">{{ $step }}</span>
            </div>
            @if($this->readinessError)
            <div class="flex gap-2">
                <span class="w-32 text-zinc-400">Error</span>
                <span class="text-red-500">{{ $this->readinessError }}</span>
            </div>
            @endif
        </div>

        @if($this->readinessError)
        <div class="rounded-xl p-4 text-left bg-amber-50 dark:bg-amber-950/30
                    border border-amber-300 dark:border-amber-800">
            <div class="flex gap-3 items-start">
                <span class="text-amber-500 text-lg">⚠️</span>
                <div>
                    <p class="text-sm font-semibold text-amber-800 dark:text-amber-300">Sistem belum siap</p>
                    <p class="text-sm text-amber-700 dark:text-amber-400 mt-1">{{ $this->readinessError }}</p>
                </div>
            </div>
        </div>
        @endif

        <button
            wire:click="startGrading"
            wire:loading.attr="disabled"
            @if(!$this->isReady) disabled @endif
            x-on:click="log('startGrading clicked', { isReady: {{ $this->isReady ? 'true' : 'false' }} })"
            class="w-full py-4 rounded-xl text-white font-semibold text-lg
            transition-all duration-200 hover:-translate-y-0.5
            disabled:opacity-50 disabled:cursor-not-allowed disabled:translate-y-0
            {{ $this->isReady
                       ? 'bg-amber-600 hover:bg-amber-500 shadow-lg shadow-amber-600/25'
                       : 'bg-zinc-300 dark:bg-zinc-700' }}">
            <span wire:loading.remove wire:target="startGrading">Mulai</span>
            <span wire:loading wire:target="startGrading">Memuat...</span>
        </button>
    </div>


    @elseif($step === 'question')
    {{-- ================================================================ --}}
    {{-- STEP 2: QUESTION                                                  --}}
    {{-- TIDAK ada x-show/x-cloak — Blade @elseif sudah cukup           --}}
    {{-- ================================================================ --}}
    <div class="w-full max-w-lg flex flex-col" style="min-height: calc(100vh - 8rem)">

        {{-- Top bar --}}
        <div class="flex items-center justify-between pb-5 mb-2
                    border-b border-amber-200 dark:border-zinc-800">
            <span class="text-sm text-zinc-400 dark:text-zinc-500"
                style="font-family: 'DM Serif Display', serif">
                Konfirmasi Grade
            </span>

            {{-- Progress dots --}}
            <div class="flex items-center gap-1 flex-wrap justify-center max-w-[200px]">
                @foreach($this->criteria as $i => $c)
                <div class="rounded-full transition-all duration-300
                    {{ $i < $currentIndex
                        ? 'w-2 h-2 bg-amber-500'
                        : ($i === $currentIndex
                            ? 'w-5 h-2 bg-amber-600 dark:bg-amber-400'
                            : 'w-2 h-2 bg-amber-200 dark:bg-zinc-700') }}">
                </div>
                @endforeach
            </div>

            <span class="font-mono text-xs text-zinc-400 dark:text-zinc-500">
                {{ $currentIndex + 1 }} / {{ $this->totalQuestions }}
            </span>
        </div>

        {{-- Body pertanyaan --}}
        <div x-ref="questionBody"
            class="flex-1 flex flex-col justify-center text-center py-8 wizard-slide-in">

            @if($this->currentCriterion)
            <div class="inline-flex items-center justify-center gap-2 self-center
                        mb-6 px-4 py-1.5 rounded-full
                        text-xs font-semibold uppercase tracking-widest
                        bg-amber-100 text-amber-700 dark:bg-zinc-800 dark:text-amber-400">
                Kriteria {{ str_pad($currentIndex + 1, 2, '0', STR_PAD_LEFT) }}
            </div>

            <h2 class="font-light leading-snug mb-4 text-zinc-900 dark:text-zinc-50"
                style="font-family: 'DM Serif Display', serif;
                       font-size: clamp(1.5rem, 4vw, 2rem)">
                {{ $this->currentCriterion->nama_kriteria }}
            </h2>

            @if($this->currentCriterion->deskripsi)
            <p class="text-sm leading-relaxed max-w-sm mx-auto text-zinc-500 dark:text-zinc-400">
                {{ $this->currentCriterion->deskripsi }}
            </p>
            @endif

            @else
            {{-- Fallback debug jika currentCriterion null --}}
            <div class="p-4 rounded-xl bg-red-50 dark:bg-red-950/40
                        border border-red-200 text-sm text-red-700 dark:text-red-400">
                ❌ currentCriterion null!
                index={{ $currentIndex }}, total={{ $this->totalQuestions }}
            </div>
            @endif
        </div>

        {{-- Tombol jawab --}}
        <div class="grid grid-cols-2 gap-3 pt-4 pb-2">
            <button wire:click="answer('ya')"
                wire:loading.attr="disabled"
                x-on:click="log('Jawab: YA', { index: {{ $currentIndex }} })"
                class="py-5 rounded-2xl text-white font-bold text-xl
                           bg-emerald-700 hover:bg-emerald-600
                           shadow-lg shadow-emerald-700/20
                           transition-all active:scale-95 disabled:opacity-50">
                ✓ YA
            </button>
            <button wire:click="answer('tidak')"
                wire:loading.attr="disabled"
                x-on:click="log('Jawab: TIDAK', { index: {{ $currentIndex }} })"
                class="py-5 rounded-2xl font-bold text-xl border-2
                           text-red-700 border-red-200
                           hover:bg-red-700 hover:text-white hover:border-red-700
                           dark:text-red-400 dark:border-red-900
                           dark:hover:bg-red-800 dark:hover:text-white
                           transition-all active:scale-95 disabled:opacity-50">
                ✗ TIDAK
            </button>
        </div>
    </div>


    @elseif($step === 'loading')
    {{-- ================================================================ --}}
    {{-- STEP 3: LOADING                                                   --}}
    {{-- ================================================================ --}}
    <div class="flex flex-col items-center gap-6 text-center">
        <div class="text-5xl wizard-spin">⚙️</div>
        <div>
            <p class="text-2xl font-light text-zinc-800 dark:text-zinc-200"
                style="font-family: 'DM Serif Display', serif">
                Menganalisis jawaban...
            </p>
            <p class="text-sm mt-1 text-zinc-400 dark:text-zinc-500">
                Mencocokkan dengan knowledge base
            </p>
            <div class="w-48 h-0.5 mt-6 bg-amber-200 dark:bg-zinc-800 rounded-full overflow-hidden">
                <div class="h-full bg-amber-500 dark:bg-amber-400 wizard-load-bar"></div>
            </div>
        </div>
    </div>


    @elseif($step === 'result')
    {{-- ================================================================ --}}
    {{-- STEP 4: RESULT                                                    --}}
    {{-- ================================================================ --}}
    <div class="w-full max-w-lg space-y-4">

        @if(!empty($result) && isset($result['winner']))

        <div class="rounded-2xl p-8 text-center relative overflow-hidden
                    bg-zinc-900 dark:bg-zinc-950 shadow-2xl">
            <div class="absolute inset-0 pointer-events-none
                        bg-gradient-to-b from-amber-600/10 to-transparent"></div>
            <p class="text-xs uppercase tracking-widest font-semibold text-amber-500 mb-3 relative">
                ✦ Konfirmasi Sistem
            </p>
            <h2 class="text-5xl font-semibold text-white tracking-tight mb-2 relative"
                style="font-family: 'DM Serif Display', serif">
                {{ $result['winner']['grade_name'] }}
            </h2>
            <p class="font-mono text-xl text-amber-400 mb-2 relative">
                {{ $result['winner']['persentase'] }}% kesesuaian
            </p>
            <p class="text-sm text-zinc-500 relative leading-relaxed">
                {{ $result['alasan'] }}
            </p>
        </div>

        <div class="rounded-2xl p-5 bg-white dark:bg-zinc-900
                    border border-amber-100 dark:border-zinc-800 space-y-4">
            <p class="text-xs uppercase tracking-widest font-semibold text-zinc-400 dark:text-zinc-500">
                Perbandingan Semua Grade
            </p>
            @foreach($result['all'] as $i => $gradeResult)
            <div>
                <div class="flex justify-between items-baseline mb-1.5 text-sm">
                    <span class="{{ $i === 0
                        ? 'font-bold text-amber-600 dark:text-amber-400'
                        : 'text-zinc-500 dark:text-zinc-400' }}">
                        {{ $gradeResult['grade_name'] }}
                        @if($i === 0)<span class="text-xs ml-1">★</span>@endif
                    </span>
                    <span class="font-mono {{ $i === 0 ? 'font-bold text-amber-600 dark:text-amber-400' : 'text-zinc-400' }}">
                        {{ $gradeResult['persentase'] }}%
                    </span>
                </div>
                <div class="h-2 bg-slate-100 dark:bg-zinc-800 rounded-full overflow-hidden">
                    <div class="h-full rounded-full transition-all duration-1000 ease-out
                                {{ $i === 0 ? 'bg-amber-500' : 'bg-zinc-300 dark:bg-zinc-600' }}"
                        style="width: {{ $gradeResult['persentase'] }}%">
                    </div>
                </div>
            </div>
            @endforeach
        </div>

        @if(!empty($result['reasons']))
        <div class="rounded-2xl p-5 bg-white dark:bg-zinc-900
                    border border-amber-100 dark:border-zinc-800">
            <p class="text-xs uppercase tracking-widest font-semibold text-zinc-400 dark:text-zinc-500 mb-4">
                Mengapa Grade Ini?
            </p>
            <div class="space-y-2">
                @foreach($result['reasons'] as $reason)
                @php
                $bg = match($reason['type']) {
                'ok' => 'bg-emerald-50 dark:bg-emerald-950/40',
                'warn' => 'bg-amber-50 dark:bg-amber-950/40',
                'fail' => 'bg-red-50 dark:bg-red-950/40',
                default => 'bg-zinc-50 dark:bg-zinc-800',
                };
                $tag = match($reason['type']) {
                'ok' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/60 dark:text-emerald-300',
                'warn' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/60 dark:text-amber-300',
                'fail' => 'bg-red-100 text-red-800 dark:bg-red-900/60 dark:text-red-300',
                default => 'bg-zinc-200 text-zinc-700',
                };
                @endphp
                <div class="flex gap-3 p-3 rounded-xl {{ $bg }}">
                    <span class="text-base flex-shrink-0 mt-0.5">{{ $reason['icon'] }}</span>
                    <div>
                        <span class="inline-block text-xs font-semibold px-2 py-0.5 rounded mb-1 {{ $tag }}">
                            {{ $reason['tag'] }}
                        </span>
                        <p class="text-sm leading-relaxed text-zinc-700 dark:text-zinc-300">
                            {{ $reason['text'] }}
                        </p>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
        @endif

        <div class="flex gap-3 pb-4">
            <button wire:click="restart"
                class="flex-1 py-3 rounded-xl font-semibold text-sm
                           border-2 border-amber-200 text-amber-700
                           hover:bg-amber-50 dark:border-zinc-700 dark:text-zinc-300
                           dark:hover:bg-zinc-800 transition-all active:scale-95">
                ↩ Grading Baru
            </button>
            <button class="flex-[2] py-3 rounded-xl font-semibold text-sm
                           bg-zinc-900 text-white hover:bg-zinc-800
                           dark:bg-zinc-100 dark:text-zinc-900 dark:hover:bg-white
                           transition-all active:scale-95">
                ✓ Simpan & Selesai
            </button>
        </div>

        @else
        {{-- Error: result kosong --}}
        <div class="text-center py-12">
            <p class="text-4xl mb-4">⚠️</p>
            <p class="font-medium text-zinc-700 dark:text-zinc-300">Tidak dapat menghitung hasil.</p>
            <p class="text-sm mt-1 text-zinc-400">{{ $result['alasan'] ?? 'Hubungi administrator.' }}</p>
            <details class="mt-4 text-left">
                <summary class="text-xs text-zinc-400 cursor-pointer">🔧 Lihat data mentah</summary>
                <pre class="mt-2 p-3 rounded-lg text-xs overflow-auto
                            bg-zinc-100 dark:bg-zinc-900 text-zinc-700 dark:text-zinc-300">{{ json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
            </details>
            <button wire:click="restart"
                class="mt-6 px-6 py-2 rounded-lg bg-amber-600 text-white text-sm font-semibold">
                Coba Lagi
            </button>
        </div>
        @endif
    </div>
    @endif

    <style>
        @keyframes wizardSlideIn {
            from {
                opacity: 0;
                transform: translateY(16px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes wizardLoadBar {
            from {
                width: 0%;
            }

            to {
                width: 100%;
            }
        }

        @keyframes wizardSpin {
            from {
                transform: rotate(0deg);
            }

            to {
                transform: rotate(360deg);
            }
        }

        .wizard-slide-in {
            animation: wizardSlideIn 0.35s cubic-bezier(0.22, 1, 0.36, 1) forwards;
        }

        .wizard-load-bar {
            animation: wizardLoadBar 1.8s ease forwards;
        }

        .wizard-spin {
            animation: wizardSpin 2s linear infinite;
            display: inline-block;
        }
    </style>
</div>