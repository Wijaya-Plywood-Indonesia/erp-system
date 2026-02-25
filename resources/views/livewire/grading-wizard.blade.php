{{--
    LIVEWIRE VIEW: grading-wizard.blade.php
    Path: resources/views/livewire/grading-wizard.blade.php

    Tugas:
    Menampilkan 4 step wizard secara bergantian berdasarkan
    property $step dari GradingWizard.php (Livewire component).

    Teknologi:
    - Tailwind CSS (class utility, light + dark mode via 'dark:')
    - Alpine.js (animasi, watch step changes, trigger inference)
    - Livewire directives (wire:click, wire:loading, @entangle)

    Dark mode:
    Filament secara otomatis menambahkan class 'dark' ke <html>
    saat user toggle dark mode. Tailwind membaca class ini dan
    mengaktifkan semua class dengan prefix 'dark:'.

    Contoh:
    bg-amber-50 dark:bg-zinc-950
    → Light mode: background amber muda
    → Dark mode:  background zinc gelap
--}}

<div
    {{-- Alpine.js: sinkronkan 'step' dengan property Livewire --}}
    x-data="{
        step: @entangle('step').live,

        init() {
            {{-- Watch perubahan step dari Livewire --}}
            this.$watch('step', (val) => {
                if (val === 'question') {
                    {{-- Trigger animasi slide saat pertanyaan berganti --}}
                    this.$nextTick(() => this.slideIn())
                }
            })

            {{-- Listen event dari Livewire untuk trigger inference --}}
            this.$wire.on('start-inference', () => {
                setTimeout(() => this.$wire.runInference(), 2000)
            })

            {{-- Listen event untuk animasi pertanyaan baru --}}
            this.$wire.on('question-changed', () => {
                this.$nextTick(() => this.slideIn())
            })
        },

        slideIn() {
            const el = this.$refs.questionBody
            if (!el) return
            el.style.animation = 'none'
            el.offsetHeight {{-- force reflow --}}
            el.style.animation = ''
            el.classList.remove('wizard-slide-in')
            void el.offsetWidth
            el.classList.add('wizard-slide-in')
        }
    }"
    class="min-h-[calc(100vh-4rem)] flex flex-col items-center justify-center
           px-4 py-8 transition-colors duration-300
           bg-amber-50 dark:bg-zinc-950">

    {{-- ====================================================================== --}}
    {{-- STEP 1: START                                                           --}}
    {{-- Pengawas memilih kategori, opsional isi kode produk, lalu mulai.       --}}
    {{-- ====================================================================== --}}
    <div
        x-show="step === 'start'"
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0 translate-y-4"
        x-transition:enter-end="opacity-100 translate-y-0"
        class="w-full max-w-md space-y-8 text-center">
        {{-- Logo dan judul --}}
        <div class="flex flex-col items-center gap-4">
            <div class="w-20 h-20 rounded-2xl flex items-center justify-center text-4xl
                    shadow-lg shadow-amber-600/20
                    bg-amber-600 dark:bg-amber-500">
                🪵
            </div>
            <div>
                <h1 class="text-4xl font-light tracking-tight
                       text-zinc-900 dark:text-zinc-50"
                    style="font-family: 'DM Serif Display', serif">
                    Konfirmasi
                    <span class="italic text-amber-600 dark:text-amber-400">Grade</span>
                </h1>
                <p class="mt-2 text-sm leading-relaxed
                       text-zinc-500 dark:text-zinc-400">
                    Jawab pertanyaan tentang kondisi produk.<br>
                    Sistem akan mengkonfirmasi grade yang paling sesuai.
                </p>
            </div>
        </div>

        {{-- Stats --}}
        <div class="grid grid-cols-3 gap-3">
            @foreach([['15','Pertanyaan'], ['3','Grade'], ["<2'",'Estimasi']] as [$val, $lbl])
                <div class="rounded-xl py-4 border
                    bg-white dark:bg-zinc-900
                    border-amber-200 dark:border-zinc-800">
                <div class="font-mono text-2xl font-medium
                        text-amber-600 dark:text-amber-400">
                    {{ $val }}
                </div>
                <div class="text-xs uppercase tracking-widest mt-1
                        text-zinc-400 dark:text-zinc-500">
                    {{ $lbl }}
                </div>
        </div>
        @endforeach
    </div>

    {{-- Pilihan Kategori (jika lebih dari 1) --}}
    @if($this->kategoriList->count() > 1)
    <div class="text-left">
        <label class="block text-xs font-semibold uppercase tracking-widest mb-2
                       text-zinc-500 dark:text-zinc-400">
            Kategori Produk
        </label>
        <select
            wire:model.live="idKategoriBarang"
            class="w-full rounded-xl border px-4 py-3 text-sm font-medium
                   bg-white dark:bg-zinc-900
                   border-amber-200 dark:border-zinc-700
                   text-zinc-800 dark:text-zinc-200
                   focus:outline-none focus:ring-2 focus:ring-amber-500">
            @foreach($this->kategoriList as $kategori)
            <option value="{{ $kategori->id }}">{{ $kategori->nama_kategori }}</option>
            @endforeach
        </select>
    </div>
    @endif

    {{-- Kode Produk (opsional) --}}
    <div class="text-left">
        <label class="block text-xs font-semibold uppercase tracking-widest mb-2
                       text-zinc-500 dark:text-zinc-400">
            Kode Produk
            <span class="normal-case font-normal">(opsional)</span>
        </label>
        <input
            wire:model="kodeProduk"
            type="text"
            placeholder="Contoh: PLY-2024-0848"
            class="w-full rounded-xl border px-4 py-3 text-sm font-mono
                   bg-white dark:bg-zinc-900
                   border-amber-200 dark:border-zinc-700
                   text-zinc-800 dark:text-zinc-200
                   placeholder:text-zinc-300 dark:placeholder:text-zinc-600
                   focus:outline-none focus:ring-2 focus:ring-amber-500">
    </div>

    {{-- Tombol Mulai --}}
    <button
        wire:click="startGrading"
        wire:loading.attr="disabled"
        wire:loading.class="opacity-60 cursor-wait"
        class="w-full py-4 rounded-xl text-white font-semibold text-lg
               bg-amber-600 hover:bg-amber-500
               dark:bg-amber-500 dark:hover:bg-amber-400
               shadow-lg shadow-amber-600/25 dark:shadow-amber-500/20
               transition-all duration-200 hover:-translate-y-0.5
               disabled:opacity-60 disabled:cursor-wait">
        <span wire:loading.remove wire:target="startGrading">Mulai Konfirmasi</span>
        <span wire:loading wire:target="startGrading">Memuat pertanyaan...</span>
    </button>

    <p class="text-xs uppercase tracking-widest text-zinc-300 dark:text-zinc-700">
        Sistem Pakar Grading Plywood · Fase 1
    </p>
</div>


{{-- ====================================================================== --}}
{{-- STEP 2: QUESTION                                                        --}}
{{-- Satu pertanyaan per layar. Klik YA/TIDAK langsung lanjut.              --}}
{{-- ====================================================================== --}}
<div
    x-show="step === 'question'"
    x-cloak
    class="w-full max-w-lg flex flex-col"
    style="min-height: calc(100vh - 8rem)">

    {{-- Top bar: brand + progress dots + counter --}}
    <div class="flex items-center justify-between pb-5 mb-2
                border-b border-amber-200 dark:border-zinc-800">

        <span class="text-sm text-zinc-400 dark:text-zinc-500"
            style="font-family: 'DM Serif Display', serif">
            Konfirmasi Grade
        </span>

        {{-- Progress dots — satu dot per pertanyaan --}}
        <div class="flex items-center gap-1 flex-wrap justify-center max-w-[200px]">
            @foreach($this->criteria as $i => $c)
            <div class="rounded-full transition-all duration-300
                    {{ $i < $currentIndex
                        ? 'w-2 h-2 bg-amber-500 dark:bg-amber-400'
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

    {{-- Question body — area yang dianimasikan saat pertanyaan berganti --}}
    <div
        x-ref="questionBody"
        class="flex-1 flex flex-col justify-center text-center py-8 wizard-slide-in">
        @if($this->currentCriterion)

        {{-- Chip kategori pertanyaan --}}
        <div class="inline-flex items-center justify-center gap-2 self-center
                    mb-6 px-4 py-1.5 rounded-full
                    text-xs font-semibold uppercase tracking-widest
                    bg-amber-100 text-amber-700
                    dark:bg-zinc-800 dark:text-amber-400">
            <span>{{ $this->currentCriterion->icon_emoji }}</span>
            Kriteria {{ str_pad($currentIndex + 1, 2, '0', STR_PAD_LEFT) }}
        </div>

        {{-- Teks pertanyaan utama --}}
        <h2 class="font-light leading-snug mb-4
                   text-zinc-900 dark:text-zinc-50"
            style="font-family: 'DM Serif Display', serif;
                   font-size: clamp(1.5rem, 4vw, 2rem)">
            {{ $this->currentCriterion->nama_kriteria }}
        </h2>

        {{-- Deskripsi / petunjuk --}}
        @if($this->currentCriterion->deskripsi)
        <p class="text-sm leading-relaxed max-w-sm mx-auto
                   text-zinc-500 dark:text-zinc-400">
            {{ $this->currentCriterion->deskripsi }}
        </p>
        @endif

        @endif
    </div>

    {{-- Tombol YA dan TIDAK --}}
    <div class="grid grid-cols-2 gap-3 pt-4 pb-2">
        <button
            wire:click="answer('ya')"
            wire:loading.attr="disabled"
            wire:loading.class="opacity-50 cursor-wait"
            class="group py-5 rounded-2xl text-white font-bold text-xl tracking-wide
                   bg-emerald-700 hover:bg-emerald-600
                   dark:bg-emerald-700 dark:hover:bg-emerald-600
                   shadow-lg shadow-emerald-700/20
                   transition-all duration-200 hover:-translate-y-1 active:translate-y-0
                   disabled:opacity-50 disabled:cursor-wait">
            ✓&nbsp;&nbsp;YA
        </button>
        <button
            wire:click="answer('tidak')"
            wire:loading.attr="disabled"
            wire:loading.class="opacity-50 cursor-wait"
            class="group py-5 rounded-2xl font-bold text-xl tracking-wide
                   border-2
                   text-red-700 border-red-200
                   hover:bg-red-700 hover:text-white hover:border-red-700
                   dark:text-red-400 dark:border-red-900
                   dark:hover:bg-red-800 dark:hover:text-white dark:hover:border-red-800
                   transition-all duration-200 hover:-translate-y-1 active:translate-y-0
                   disabled:opacity-50 disabled:cursor-wait">
            ✗&nbsp;&nbsp;TIDAK
        </button>
    </div>
</div>


{{-- ====================================================================== --}}
{{-- STEP 3: LOADING                                                         --}}
{{-- Tampil 2 detik saat InferenceEngine sedang berjalan.                   --}}
{{-- ====================================================================== --}}
<div
    x-show="step === 'loading'"
    x-cloak
    x-transition:enter="transition ease-out duration-500"
    x-transition:enter-start="opacity-0"
    x-transition:enter-end="opacity-100"
    class="flex flex-col items-center gap-6 text-center">
    <div class="text-5xl wizard-spin">⚙️</div>

    <div>
        <p class="text-2xl font-light text-zinc-800 dark:text-zinc-200"
            style="font-family: 'DM Serif Display', serif">
            Menganalisis jawaban...
        </p>
        <p class="text-sm mt-1 text-zinc-400 dark:text-zinc-500">
            Mencocokkan dengan knowledge base
        </p>
    </div>

    {{-- Loading bar animasi --}}
    <div class="w-48 h-0.5 rounded-full overflow-hidden
                bg-amber-200 dark:bg-zinc-800">
        <div class="h-full rounded-full bg-amber-500 dark:bg-amber-400
                    wizard-load-bar">
        </div>
    </div>
</div>


{{-- ====================================================================== --}}
{{-- STEP 4: RESULT                                                          --}}
{{-- Tampilan hasil akhir: grade, persentase, dan alasan.                   --}}
{{-- ====================================================================== --}}
<div
    x-show="step === 'result'"
    x-cloak
    x-transition:enter="transition ease-out duration-500"
    x-transition:enter-start="opacity-0 translate-y-4"
    x-transition:enter-end="opacity-100 translate-y-0"
    class="w-full max-w-lg space-y-4">
    @if(!empty($result) && isset($result['winner']))

    {{-- Hero: Grade Terpilih --}}
    <div class="rounded-2xl p-8 text-center relative overflow-hidden
                bg-zinc-900 dark:bg-zinc-950 shadow-2xl">
        {{-- Glow effect --}}
        <div class="absolute inset-0 pointer-events-none
                    bg-gradient-to-b from-amber-600/10 to-transparent">
        </div>

        <p class="text-xs uppercase tracking-widest font-semibold
                   text-amber-500 mb-3 relative">
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

    {{-- Perbandingan Semua Grade --}}
    <div class="rounded-2xl p-5 space-y-4
                bg-white dark:bg-zinc-900
                border border-amber-100 dark:border-zinc-800">

        <p class="text-xs uppercase tracking-widest font-semibold
                   text-zinc-400 dark:text-zinc-500">
            Perbandingan Semua Grade
        </p>

        @foreach($result['all'] as $i => $gradeResult)
        @php
        $isWinner = $i === 0;
        $barClass = $isWinner
        ? 'bg-amber-500'
        : ($i === 1 ? 'bg-zinc-300 dark:bg-zinc-600' : 'bg-zinc-200 dark:bg-zinc-700');
        $nameClass = $isWinner
        ? 'font-semibold text-amber-700 dark:text-amber-400'
        : 'text-zinc-500 dark:text-zinc-400';
        $pctClass = $isWinner
        ? 'font-bold text-amber-600 dark:text-amber-400'
        : 'text-zinc-400 dark:text-zinc-500';
        @endphp
        <div>
            <div class="flex justify-between items-baseline mb-1.5">
                <span class="text-sm {{ $nameClass }}">
                    {{ $gradeResult['grade_name'] }}
                    @if($isWinner)
                    <span class="text-xs ml-1">★</span>
                    @endif
                </span>
                <span class="font-mono text-sm {{ $pctClass }}">
                    {{ $gradeResult['persentase'] }}%
                </span>
            </div>
            <div class="h-2.5 rounded-full overflow-hidden
                        bg-amber-50 dark:bg-zinc-800">
                <div
                    class="h-full rounded-full transition-all duration-1000 ease-out {{ $barClass }}"
                    x-data
                    x-init="setTimeout(() => $el.style.width = '{{ $gradeResult['persentase'] }}%', {{ 200 + $i * 200 }})"
                    style="width: 0%">
                </div>
            </div>
        </div>
        @endforeach
    </div>

    {{-- Alasan Detail --}}
    @if(!empty($result['reasons']))
    <div class="rounded-2xl p-5
                bg-white dark:bg-zinc-900
                border border-amber-100 dark:border-zinc-800">

        <p class="text-xs uppercase tracking-widest font-semibold
                   text-zinc-400 dark:text-zinc-500 mb-4">
            Mengapa Grade Ini?
        </p>

        <div class="space-y-2">
            @foreach($result['reasons'] as $reason)
            @php
            $bgClass = match($reason['type']) {
            'ok' => 'bg-emerald-50 dark:bg-emerald-950/40',
            'warn' => 'bg-amber-50 dark:bg-amber-950/40',
            'fail' => 'bg-red-50 dark:bg-red-950/40',
            default => 'bg-zinc-50 dark:bg-zinc-800',
            };
            $tagClass = match($reason['type']) {
            'ok' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/60 dark:text-emerald-300',
            'warn' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/60 dark:text-amber-300',
            'fail' => 'bg-red-100 text-red-800 dark:bg-red-900/60 dark:text-red-300',
            default => 'bg-zinc-200 text-zinc-700',
            };
            $textClass = 'text-zinc-700 dark:text-zinc-300';
            @endphp
            <div class="flex gap-3 p-3 rounded-xl {{ $bgClass }}">
                <span class="text-base flex-shrink-0 mt-0.5">{{ $reason['icon'] }}</span>
                <div>
                    <span class="inline-block text-xs font-semibold px-2 py-0.5 rounded mb-1.5
                                 {{ $tagClass }}">
                        {{ $reason['tag'] }}
                    </span>
                    <p class="text-sm leading-relaxed {{ $textClass }}">
                        {{ $reason['text'] }}
                    </p>
                </div>
            </div>
            @endforeach
        </div>
    </div>
    @endif

    {{-- Tombol aksi --}}
    <div class="flex gap-3 pb-4">
        <button
            wire:click="reset"
            class="flex-1 py-3 rounded-xl font-semibold text-sm
                   border-2 border-amber-200 text-amber-700
                   hover:border-amber-400 hover:bg-amber-50
                   dark:border-zinc-700 dark:text-zinc-300
                   dark:hover:border-zinc-500 dark:hover:bg-zinc-800
                   transition-all duration-200">
            ↩ Grading Baru
        </button>
        <button
            class="flex-[2] py-3 rounded-xl font-semibold text-sm
                   bg-zinc-900 text-white hover:bg-zinc-700
                   dark:bg-zinc-100 dark:text-zinc-900 dark:hover:bg-white
                   transition-all duration-200">
            ✓ Simpan & Selesai
        </button>
    </div>

    @else
    {{-- Error state: tidak ada hasil --}}
    <div class="text-center py-12 text-zinc-400 dark:text-zinc-600">
        <p class="text-4xl mb-4">⚠️</p>
        <p class="font-medium">Tidak dapat menghitung hasil.</p>
        <p class="text-sm mt-1">{{ $result['alasan'] ?? 'Hubungi administrator.' }}</p>
        <button wire:click="reset"
            class="mt-6 px-6 py-2 rounded-lg bg-amber-600 text-white text-sm font-semibold">
            Coba Lagi
        </button>
    </div>
    @endif

</div>

</div>

{{-- ── CSS Animasi ──────────────────────────────────────────────────── --}}
<style>
    [x-cloak] {
        display: none !important;
    }

    /* Slide in dari bawah saat pertanyaan berganti */
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

    /* Loading bar mengisi dari kiri ke kanan */
    @keyframes wizardLoadBar {
        from {
            width: 0%;
        }

        to {
            width: 100%;
        }
    }

    /* Ikon gear berputar */
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