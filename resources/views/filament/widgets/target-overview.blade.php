<x-filament-widgets::widget>
@php
    // Data Default & Inisialisasi
    $data = $this->data ?? [
        'data_hari_ini' => [
            'nama_produksi' => 'Produksi Rotary',
            'mesin_produksi' => 'Spindler-X1',
            'progress' => 75,
            'hasil_produksi' => 270000
        ],
        'data_minggu_ini' => [
            ['tanggal' => '2026-02-02', 'hasil_produksi' => 900000], 
            ['tanggal' => '2026-02-03', 'hasil_produksi' => 280000],
            ['tanggal' => '2026-02-04', 'hasil_produksi' => 360000],
        ],
        'target' => 360000,
    ];

    // 1. Logika Sumbu X Statis (Senin - Minggu)
    $hariStatis = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
    $mappedData = [];
    foreach ($hariStatis as $index => $hari) {
        $found = collect($data['data_minggu_ini'])->first(function($item) use ($hari) {
            return \Carbon\Carbon::parse($item['tanggal'])->format('D') === $hari;
        });
        
        $nilai = $found ? $found['hasil_produksi'] : null;
        
        $mappedData[] = [
            'hari' => $hari,
            'nilai' => $nilai,
            'x' => ($index / 6) * 1000,
            // Logika Warna: Jika ada nilai dan di bawah target -> Merah, jika capai -> Emerald
            'is_low' => ($nilai !== null && $nilai < $data['target'])
        ];
    }

    // 2. Kalkulasi Skala Y
    $maxProduksi = collect($data['data_minggu_ini'])->max('hasil_produksi') ?? 0;
    $maxChart = max($maxProduksi, $data['target']) * 1.25; 
    $maxChart = $maxChart == 0 ? 100000 : $maxChart;

    // 3. Membuat Titik Koordinat Garis
    $pointsArray = [];
    foreach ($mappedData as $item) {
        if ($item['nilai'] !== null) {
            $y = 400 - (($item['nilai'] / $maxChart) * 400);
            $pointsArray[] = $item['x'] . "," . $y;
        }
    }
    $points = implode(' ', $pointsArray);
    $yTarget = 400 - (($data['target'] / $maxChart) * 400);

    // Mencari nilai terakhir yang tersedia untuk menentukan warna tema grafik
    $lastValidNilai = collect($data['data_minggu_ini'])->last()['hasil_produksi'] ?? 0;

    // Jika produksi terakhir di bawah target, tema grafik jadi merah (RGB untuk gradient, Hex untuk stroke)
    $isTrendLow = $lastValidNilai < $data['target'];
    $primaryRGB = $isTrendLow ? '239, 68, 68' : '16, 185, 129'; 
    $strokeHex  = $isTrendLow ? '#ef4444' : '#10b981';
@endphp

<div class="w-full antialiased" x-data="{ viewMode: 'stat' }">
    <div class="flex flex-col lg:flex-row gap-4">
        
        {{-- SECTION KIRI: KPI SUMMARY (LEBIH RINGKAS) --}}
        <div class="w-full lg:w-1/3 flex flex-col gap-4">
            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-3xl p-6 shadow-sm flex-grow">
                <div class="flex items-center justify-between mb-6">
                    <div class="p-2 bg-emerald-500/10 rounded-xl">
                        <svg class="w-6 h-6 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                        </svg>
                    </div>
                    <div class="flex items-center gap-1.5 px-3 py-1 bg-gray-100 dark:bg-gray-800 rounded-full">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                        <span class="text-[10px] font-bold text-gray-600 dark:text-gray-400 uppercase tracking-wider">Live System</span>
                    </div>
                </div>

                <div class="mb-6">
                    <h2 class="text-sm font-black text-gray-400 uppercase tracking-[0.1em] mb-1">Produksi Hari ini</h2>
                    <p class="text-xl font-black text-gray-800 dark:text-white">{{ $data['data_hari_ini']['nama_produksi'] }}</p>
                    <p class="text-xs font-medium text-gray-700 dark:text-white italic">Mesin {{ $data['data_hari_ini']['mesin_produksi'] }}</p>
                </div>

                <div class="grid grid-cols-2 gap-4 mb-8">
                    <div>
                        <p class="text-[10px] font-black text-gray-400 uppercase mb-1">Production</p>
                        <p class="text-2xl font-black text-gray-800 dark:text-white">{{ number_format($data['data_hari_ini']['hasil_produksi'] / 1000, 1) }}k <span class="text-xs font-normal opacity-50 text-gray-500">Pcs</span></p>
                    </div>
                    <div class="text-right">
                        <p class="text-[10px] font-black text-gray-400 uppercase mb-1">Progress</p>
                        <p class="text-2xl font-black text-emerald-500">{{ $data['data_hari_ini']['progress'] }}%</p>
                    </div>
                </div>

                <div class="space-y-2">
                    <div class="flex justify-between text-[10px] font-black text-gray-500 uppercase">
                        <span>Daily Progress</span>
                        <span>Target: {{ number_format($data['target'] / 1000, 0) }}k</span>
                    </div>
                    <div class="h-4 w-full bg-gray-100 dark:bg-gray-800 rounded-xl overflow-hidden p-1 border border-gray-200 dark:border-gray-700">
                        <div style="width: {{ $data['data_hari_ini']['progress'] }}%" class="h-full bg-gradient-to-r from-emerald-500 to-teal-400 rounded-lg shadow-sm transition-all duration-1000"></div>
                    </div>
                </div>
            </div>
        </div>

        {{-- SECTION KANAN: GRAPH ANALYTICS --}}
        <div class="w-full lg:w-2/3 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-3xl shadow-sm flex flex-col overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-800 flex justify-between items-center bg-gray-50/30">
                <h3 class="text-xs font-black text-gray-500 dark:text-gray-50 uppercase tracking-widest">Analisis Minggu Ini</h3>
                <div class="flex bg-gray-100 dark:bg-gray-800 p-1 rounded-xl border border-gray-200 dark:border-gray-700">
                    <button @click="viewMode = 'stat'" :class="viewMode === 'stat' ? 'bg-white dark:bg-gray-700 shadow-sm text-gray-900 dark:text-white' : 'text-gray-500'" class="px-4 py-1.5 text-[10px] font-black uppercase rounded-lg transition-all">Graph</button>
                    <button @click="viewMode = 'list'" :class="viewMode === 'list' ? 'bg-white dark:bg-gray-700 shadow-sm text-gray-900 dark:text-white' : 'text-gray-500'" class="px-4 py-1.5 text-[10px] font-black uppercase rounded-lg transition-all">List</button>
                </div>
            </div>

            <div class="p-8 flex-grow min-h-[350px]">
                {{-- GRAPH VIEW --}}
                <div x-show="viewMode === 'stat'" x-transition class="relative h-full flex flex-col">
                    <div class="flex flex-grow items-stretch">
                        {{-- Y-Axis --}}
                        <div class="flex flex-col justify-between text-[9px] font-black text-gray-400 pr-4 border-r border-gray-100 dark:border-gray-800 relative w-16">
                            <span class="absolute right-3" style="top: 0%">{{ number_format($maxChart/1000, 0) }}k</span>
                            
                            @foreach($mappedData as $item)
                                @if($item['nilai'] !== null)
                                    <div class="absolute right-0 flex items-center" style="top: {{ (($maxChart - $item['nilai']) / $maxChart) * 100 }}%; transform: translateY(-50%);">
                                        <span class="{{ $item['is_low'] ? 'text-red-500 bg-red-50 dark:bg-red-500/10' : 'text-emerald-600 bg-emerald-50 dark:bg-emerald-500/10' }} px-2 py-1 rounded-md mr-3 border {{ $item['is_low'] ? 'border-red-100 dark:border-red-500/20' : 'border-emerald-100 dark:border-emerald-500/20' }} font-bold shadow-sm">
                                            {{ number_format($item['nilai']/1000, 0) }}k
                                        </span>
                                        <div class="w-2 h-[2px] {{ $item['is_low'] ? 'bg-red-400' : 'bg-emerald-500' }} rounded-lg"></div>
                                    </div>
                                @endif
                            @endforeach

                            {{-- TARGET! --}}
                            <div class="absolute right-0 z-10 flex items-center" style="top: {{ (($maxChart - $data['target']) / $maxChart) * 100 }}%; transform: translateY(-50%);">
                                <span class=" whitespace-nowrap text-gray-900  bg-slate-50 dark:bg-slate-50  font-bold px-2 py-1 rounded-md mr-3 border border-gray-900 dark:border-gray-900/20  italic shadow-sm tracking-tighter">
                                    TARGET: {{ number_format($data['target']/1000, 0) }}k
                                </span>
                                <div class="w-2 h-[1px] bg-slate-950 bg-slate-50 z-10 rounded-lg"></div>
                            </div>

                            <span class="absolute right-3 bottom-0">0</span>
                        </div>
                        
                        {{-- SVG Chart --}}
                        <div class="flex-grow relative ml-4">
                            {{-- Target Line --}}
                            <div class="absolute w-full border-t border-dashed border-slate-900 dark:border-slate-100 z-0" style="top: {{ ($yTarget/400)*100 }}%"></div>
                                <svg viewBox="0 0 1000 400" class="w-full h-full overflow-visible">
                                    <defs>
                                        <linearGradient id="chartFill" x1="0%" y1="0%" x2="0%" y2="100%">
                                            {{-- Warna gradient area bawah sekarang dinamis --}}
                                            <stop offset="0%" style="stop-color:rgb({{ $primaryRGB }});stop-opacity:0.2" />
                                            <stop offset="100%" style="stop-color:rgb({{ $primaryRGB }});stop-opacity:0" />
                                        </linearGradient>
                                    </defs>
                                    
                                    @if(!empty($points))
                                        {{-- Area Gradient --}}
                                        <polyline fill="url(#chartFill)" stroke="none" points="0,400 {{ $points }} 1000,400" />
                                        
                                        {{-- Garis Utama (Stroke) --}}
                                        <polyline fill="none" 
                                                stroke="{{ $strokeHex }}" 
                                                stroke-width="8" 
                                                stroke-linecap="round" 
                                                stroke-linejoin="round" 
                                                points="{{ $points }}" 
                                                class="transition-all duration-500" />
                                        
                                        {{-- Loop Titik Koordinat --}}
                                        @foreach(explode(' ', trim($points)) as $index => $p)
                                            @php 
                                                $c = explode(',', $p); 
                                                $currentData = collect($mappedData)->where('nilai', '!==', null)->values()[$index];
                                                // Titik individu tetap bisa merah sendiri jika dia di bawah target
                                                $pointColor = $currentData['is_low'] ? '#ef4444' : '#10b981';
                                            @endphp
                                            <g>
                                                <circle cx="{{ $c[0] }}" cy="{{ $c[1] }}" r="12" fill="white" class="dark:fill-gray-900" />
                                                <circle cx="{{ $c[0] }}" cy="{{ $c[1] }}" r="6" fill="{{ $pointColor }}" />
                                            </g>
                                        @endforeach
                                    @endif
                                </svg>
                        </div>
                    </div>
                    {{-- X-Axis Labels --}}
                    <div class="flex justify-between mt-6 ml-16 px-2 text-[10px] font-black text-gray-400 uppercase tracking-widest">
                        @foreach($mappedData as $item)
                            <div class="flex flex-col items-center gap-1.5">
                                <span class="{{ $item['nilai'] ? 'text-gray-900 dark:text-gray-200' : 'opacity-80' }}">
                                    {{ $item['hari'] }}
                                </span>
                                <div class="w-1 h-1 rounded-full {{ $item['nilai'] ? ($item['is_low'] ? 'bg-red-500' : 'bg-emerald-500') : 'opacity-80 dark:opacity-100  dark:bg-gray-800' }}"></div>
                            </div>
                        @endforeach
                    </div>
                </div>

                {{-- LIST VIEW --}}
                <div x-show="viewMode === 'list'" x-transition class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    @foreach($data['data_minggu_ini'] as $m)
                        <div class="flex items-center justify-between p-4 rounded-2xl bg-gray-50 dark:bg-gray-800/50 border border-gray-100 dark:border-gray-800">
                            <div class="flex items-center gap-3">
                                <div class="text-center">
                                    <p class="text-[9px] font-black text-gray-400 uppercase leading-none">{{ \Carbon\Carbon::parse($m['tanggal'])->format('M') }}</p>
                                    <p class="text-lg font-black text-gray-700 dark:text-gray-200 leading-none">{{ \Carbon\Carbon::parse($m['tanggal'])->format('d') }}</p>
                                </div>
                                <div class="w-[2px] h-8 bg-gray-200 dark:bg-gray-700"></div>
                                <p class="text-xs font-bold text-gray-600 dark:text-gray-400">{{ \Carbon\Carbon::parse($m['tanggal'])->format('l') }}</p>
                            </div>
                            <div class="text-right">
                                <p class="text-sm font-black {{ $m['hasil_produksi'] < $data['target'] ? 'text-red-500' : 'text-emerald-500' }}">
                                    {{ number_format($m['hasil_produksi']) }}
                                </p>
                                <p class="text-[8px] font-bold text-gray-400 uppercase italic">Pcs Output</p>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Footer Legend --}}
            <div class="px-8 py-4 bg-gray-50/50 dark:bg-gray-800/30 border-t border-gray-100 dark:border-gray-800 flex justify-between items-center">
                <div class="flex gap-6">
                    <div class="flex items-center gap-2">
                        <div class="w-2.5 h-2.5 rounded-full bg-emerald-500"></div>
                        <span class="text-[9px] font-black text-gray-500 uppercase tracking-tighter">On Target</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <div class="w-2.5 h-2.5 rounded-full bg-red-500"></div>
                        <span class="text-[9px] font-black text-gray-500 uppercase tracking-tighter">Below Target</span>
                    </div>
                </div>
                <span class="text-[9px] font-bold text-gray-400 uppercase tracking-widest italic text-right">Updated: {{ now()->format('H:i') }}</span>
            </div>
        </div>
    </div>
</div>
</x-filament-widgets::widget>