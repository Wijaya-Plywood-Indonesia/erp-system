<div
    x-data="{
        step: @entangle('step').live,
        isAnswering: false,

        init() {
            this.$watch('step', (val) => {
                if (val === 'question') {
                    this.isAnswering = false
                    this.$nextTick(() => this.slideIn())
                }
            })

            this.$wire.on('start-inference', () => {
                setTimeout(() => this.$wire.runInference(), 2000)
            })

            this.$wire.on('question-changed', () => {
                this.isAnswering = false
                this.$nextTick(() => this.slideIn())
            })
        },

        handleAnswer(jawaban) {
            if (this.isAnswering) return
            this.isAnswering = true
            this.$wire.answer(jawaban)
        },

        slideIn() {
            const el = this.$refs.questionBody
            if (!el) return
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

    @if($step === 'start')
    {{-- ================================================================ --}}
    {{-- STEP 1: START                                                     --}}
    {{-- ================================================================ --}}
    <div class="w-full max-w-md space-y-6 text-center">

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
            <label class="block text-xs font-semibold uppercase tracking-widest mb-2 text-zinc-500 dark:text-zinc-400">
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

        @if($this->readinessError)
        <div class="rounded-xl p-4 text-left
                    bg-red-50 dark:bg-red-950/40 border border-red-200 dark:border-red-900">
            <div class="flex gap-3 items-start">
                <span class="text-red-500 text-lg flex-shrink-0">⚠️</span>
                <div>
                    <p class="text-sm font-semibold text-red-700 dark:text-red-400 mb-1">Sistem belum siap</p>
                    <p class="text-sm text-red-600 dark:text-red-500">{{ $this->readinessError }}</p>
                </div>
            </div>
        </div>
        @endif

        <button
            wire:click="startGrading"
            wire:loading.attr="disabled"
            wire:target="startGrading"
            @if(!$this->isReady) disabled @endif
            class="w-full py-4 rounded-xl text-white font-semibold text-lg
            transition-all duration-200 hover:-translate-y-0.5
            disabled:opacity-50 disabled:cursor-not-allowed
            {{ $this->isReady
                       ? 'bg-amber-600 hover:bg-amber-500 shadow-lg shadow-amber-600/25'
                       : 'bg-zinc-300 dark:bg-zinc-700 cursor-not-allowed' }}">
            <span wire:loading.remove wire:target="startGrading">Mulai</span>
            <span wire:loading wire:target="startGrading">Memuat...</span>
        </button>

        <p class="text-xs uppercase tracking-widest text-zinc-300 dark:text-zinc-700">
            Sistem Pakar Grading Plywood
        </p>
    </div>


    @elseif($step === 'question')
    {{-- ================================================================ --}}
    {{-- STEP 2: QUESTION — tidak ada notifikasi/banner selama pengerjaan --}}
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
            @endif
        </div>

        {{-- Tombol YA / TIDAK --}}
        <div class="grid grid-cols-2 gap-3 pt-4 pb-2">
            <button
                x-on:click="handleAnswer('ya')"
                x-bind:disabled="isAnswering"
                x-bind:class="isAnswering ? 'opacity-50 cursor-wait' : 'hover:bg-emerald-600 active:scale-95'"
                class="py-5 rounded-2xl text-white font-bold text-xl
                       bg-emerald-700 shadow-lg shadow-emerald-700/20 transition-all duration-150">
                ✓ YA
            </button>
            <button
                x-on:click="handleAnswer('tidak')"
                x-bind:disabled="isAnswering"
                x-bind:class="isAnswering ? 'opacity-50 cursor-wait' : 'hover:bg-red-700 hover:text-white hover:border-red-700 active:scale-95'"
                class="py-5 rounded-2xl font-bold text-xl border-2
                       text-red-700 border-red-200
                       dark:text-red-400 dark:border-red-900 transition-all duration-150">
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

        {{-- Hero: winner --}}
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

        {{-- Perbandingan semua grade (eligible + eliminated) --}}
        <div class="rounded-2xl p-5 bg-white dark:bg-zinc-900
                    border border-amber-100 dark:border-zinc-800 space-y-4">
            <p class="text-xs uppercase tracking-widest font-semibold text-zinc-400 dark:text-zinc-500">
                Perbandingan Semua Grade
            </p>

            @foreach($result['all'] as $i => $gradeResult)
            @php $isEliminated = $gradeResult['is_eliminated'] ?? false; @endphp
            <div class="{{ $isEliminated ? 'opacity-50' : '' }}">
                <div class="flex justify-between items-baseline mb-1.5 text-sm">
                    <span class="flex items-center gap-1.5
                        {{ !$isEliminated && $i === 0
                            ? 'font-bold text-amber-600 dark:text-amber-400'
                            : 'text-zinc-500 dark:text-zinc-400' }}
                        {{ $isEliminated ? 'line-through' : '' }}">
                        {{ $gradeResult['grade_name'] }}
                        @if(!$isEliminated && $i === 0)<span class="text-xs no-underline" style="text-decoration:none">★</span>@endif
                        @if($isEliminated)<span class="text-xs no-underline text-red-400" style="text-decoration:none">✕</span>@endif
                    </span>
                    <span class="font-mono text-sm
                        {{ !$isEliminated && $i === 0
                            ? 'font-bold text-amber-600 dark:text-amber-400'
                            : 'text-zinc-400' }}">
                        {{ $gradeResult['persentase'] }}%
                    </span>
                </div>
                <div class="h-2 bg-slate-100 dark:bg-zinc-800 rounded-full overflow-hidden">
                    <div class="h-full rounded-full transition-all duration-1000 ease-out
                                {{ $isEliminated
                                    ? 'bg-red-300 dark:bg-red-900'
                                    : ($i === 0 ? 'bg-amber-500' : 'bg-zinc-300 dark:bg-zinc-600') }}"
                        style="width: {{ $gradeResult['persentase'] }}%">
                    </div>
                </div>
            </div>
            @endforeach
        </div>

        {{-- Alasan --}}
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