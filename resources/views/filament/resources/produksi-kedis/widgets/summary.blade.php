<x-filament::widget>
    @php
    /**
    * LOGIKA PENENTU MODE
    * Kita mendeteksi mode berdasarkan query string 'activeRelationManager'
    * (default Filament) atau 'tab' (custom).
    * 0 biasanya Index pertama (Masuk), 1 biasanya Index kedua (Bongkar).
    */
    $activeTab = request()->query('activeRelationManager');
    $customTab = request()->query('tab');

    // Tentukan mode: 'masuk', 'bongkar', atau 'all' (default)
    $mode = 'all';
    if ($activeTab === '0' || $customTab === 'masuk') $mode = 'masuk';
    if ($activeTab === '1' || $customTab === 'bongkar') $mode = 'bongkar';
    @endphp

    <x-filament::card class="w-full space-y-12 dark:bg-gray-900 dark:border-gray-800">

        {{-- ================= STATISTIK UTAMA (DINAMIS) ================= --}}
        <div @class([ 'grid gap-4 divide-gray-200 dark:divide-gray-700' , 'grid-cols-2 md:grid-cols-4 divide-x-0 md:divide-x'=> $mode === 'all',
            'grid-cols-2 divide-x' => $mode !== 'all',
            ])>

            @if($mode === 'all' || $mode === 'masuk')
            {{-- TOTAL MASUK --}}
            <div class="text-center py-2">
                <div class="text-3xl font-black text-emerald-600 dark:text-emerald-500">
                    {{ number_format($summary['totalMasuk'] ?? 0) }}
                </div>
                <div class="text-[10px] font-bold uppercase tracking-widest text-gray-500 dark:text-gray-400">
                    Total Kayu Masuk
                </div>
            </div>
            @endif

            @if($mode === 'all' || $mode === 'bongkar')
            {{-- TOTAL BONGKAR --}}
            <div class="text-center py-2">
                <div class="text-3xl font-black text-primary-600 dark:text-primary-500">
                    {{ number_format($summary['totalBongkar'] ?? 0) }}
                </div>
                <div class="text-[10px] font-bold uppercase tracking-widest text-gray-500 dark:text-gray-400">
                    Total Hasil Bongkar
                </div>
            </div>
            @endif

            @if($mode === 'all')
            {{-- SELISIH / WIP (Hanya muncul di tampilan gabungan) --}}
            <div class="text-center py-2">
                <div @class([ 'text-3xl font-black' , 'text-amber-500'=> ($summary['selisih'] ?? 0) > 0,
                    'text-gray-400' => ($summary['selisih'] ?? 0) <= 0,
                        ])>
                        {{ number_format($summary['selisih'] ?? 0) }}
                </div>
                <div class="text-[10px] font-bold uppercase tracking-widest text-gray-500 dark:text-gray-400">
                    Sisa Dalam Kedi (WIP)
                </div>
            </div>
            @endif

            {{-- TOTAL PEGAWAI (Selalu muncul sebagai konteks kapasitas) --}}
            <div class="text-center py-2">
                <div class="text-3xl font-black text-indigo-600 dark:text-indigo-500">
                    {{ number_format($summary['totalPegawai'] ?? 0) }}
                </div>
                <div class="text-[10px] font-bold uppercase tracking-widest text-gray-500 dark:text-gray-400">
                    Pegawai
                </div>
            </div>

        </div>

        {{-- ================= RINCIAN PER UKURAN (DINAMIS) ================= --}}
        <div @class([ 'grid grid-cols-1 gap-8' , 'lg:grid-cols-2'=> $mode === 'all',
            ])>

            @if($mode === 'all' || $mode === 'masuk')
            {{-- RINCIAN MASUK --}}
            <div class="space-y-4">
                <div class="flex items-center gap-2 font-bold text-sm text-gray-800 dark:text-gray-200 uppercase tracking-tighter">
                    <span class="w-2 h-4 bg-emerald-500 rounded-sm"></span>
                    Rincian Kayu Masuk
                </div>

                <div class="space-y-2">
                    @forelse ($summary['summaryMasuk'] as $row)
                    <div class="flex items-center justify-between rounded-lg border border-gray-100 bg-emerald-50/30 px-4 py-2 dark:bg-emerald-950/10 dark:border-emerald-900/30">
                        <div class="text-sm font-medium text-gray-700 dark:text-gray-300">
                            {{ $row->ukuran }}
                        </div>
                        <div class="text-base font-black text-emerald-700 dark:text-emerald-400">
                            {{ number_format($row->total) }} <span class="text-[10px] font-normal">Lbr</span>
                        </div>
                    </div>
                    @empty
                    <div class="text-center py-4 text-xs text-gray-400 italic">Belum ada data masuk</div>
                    @endforelse
                </div>
            </div>
            @endif

            @if($mode === 'all' || $mode === 'bongkar')
            {{-- RINCIAN BONGKAR --}}
            <div class="space-y-4">
                <div class="flex items-center gap-2 font-bold text-sm text-gray-800 dark:text-gray-200 uppercase tracking-tighter">
                    <span class="w-2 h-4 bg-primary-500 rounded-sm"></span>
                    Rincian Hasil Bongkar
                </div>

                <div class="space-y-2">
                    @forelse ($summary['summaryBongkar'] as $row)
                    <div class="flex items-center justify-between rounded-lg border border-gray-100 bg-blue-50/30 px-4 py-2 dark:bg-blue-950/10 dark:border-blue-900/30">
                        <div class="text-sm font-medium text-gray-700 dark:text-gray-300">
                            {{ $row->ukuran }}
                        </div>
                        <div class="text-base font-black text-primary-700 dark:text-primary-400">
                            {{ number_format($row->total) }} <span class="text-[10px] font-normal">Lbr</span>
                        </div>
                    </div>
                    @empty
                    <div class="text-center py-4 text-xs text-gray-400 italic">Belum ada data bongkar</div>
                    @endforelse
                </div>
            </div>
            @endif

        </div>

        {{-- INFO FOOTER --}}
        <div class="flex items-center justify-center gap-4 text-[9px] font-bold text-gray-400 uppercase tracking-[0.2em] border-t border-gray-100 dark:border-gray-800 pt-6">
            <div class="flex items-center gap-1.5">
                <span class="w-1.5 h-1.5 bg-emerald-500 rounded-full animate-pulse"></span>
                Monitoring Mode: {{ strtoupper($mode) }}
            </div>
            <span>|</span>
            <div>Auto-Refresh Aktif</div>
        </div>

    </x-filament::card>
</x-filament::widget>