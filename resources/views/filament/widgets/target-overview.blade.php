<x-filament-widgets::widget>
    <div class="w-full grid sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-6 gap-4">
        @foreach ($full_data as $data)
        @php
        $mingguIni = $data['data_minggu_ini'] ?? null;
        $dataProduksi = $mingguIni['data'] ?? [];
        $targetMingguanRataRata = 6000;

        $hariIni = $data['data_hari_ini'] ?? [
        'total_harian' => 0,
        'progress_harian' => 0,
        'target_harian' => 0
        ];

        $hariDinamis = [];
        for ($i = 6; $i >= 0; $i--) { $hariDinamis[] = now()->subDays($i)->format('D'); }

        $mappedData = [];
        foreach ($hariDinamis as $index => $hari) {
        $found = collect($dataProduksi)->first(fn($item) => \Carbon\Carbon::parse($item['tanggal_produksi'])->format('D') === $hari);
        $nilai = $found ? $found['total_harian'] : null;
        $mappedData[] = [
        'hari' => $hari,
        'nilai' => $nilai,
        'x' => ($index / 6) * 1000,
        'is_low' => ($nilai !== null && $nilai < $targetMingguanRataRata)
            ];
            }

            $maxProduksi=collect($dataProduksi)->max('total_harian') ?? 0;
            $maxChart = max($maxProduksi, $targetMingguanRataRata) * 1.25;
            $maxChart = $maxChart <= 0 ? 10000 : $maxChart;

                $pointsArray=[];
                foreach ($mappedData as $item) {
                if ($item['nilai'] !==null) {
                $y=400 - (($item['nilai'] / $maxChart) * 400);
                $pointsArray[]=$item['x'] . "," . $y;
                }
                }
                $points=implode(' ', $pointsArray);
    $yTarget = 400 - (($targetMingguanRataRata / $maxChart) * 400);
    $lastValidNilai = !empty($dataProduksi) ? collect($dataProduksi)->last()[' total_harian'] : 0;
                $isTrendLow=$lastValidNilai < $targetMingguanRataRata;
                $primaryRGB=$isTrendLow ? '239, 68, 68' : '16, 185, 129' ;
                $strokeHex=$isTrendLow ? '#ef4444' : '#10b981' ;
                @endphp

                <div class="w-full  antialiased mb-4 transition-all duration-500 ease-in-out" x-data="{ viewMode: 'stat' }">
                {{-- SINGLE UNIFIED CARD --}}
                <div class=" bg-white dark:bg-gray-900 transition-all duration-500 ease-in-out border border-gray-200 dark:border-gray-800 rounded-2xl shadow-sm overflow-hidden">

                    <div class="flex flex-col md:flex-row transition-all duration-500 ease-in-out">

                        {{-- LEFT/TOP PART: KPI MINI --}}
                        <div class="w-full md:w-72 md:min-w-72 p-5 border-b md:border-b-0 md:border-r border-gray-100 dark:border-gray-800 bg-gray-50/50 dark:bg-gray-900">
                            <div class="flex items-center justify-between mb-4 gap-3">
                                {{-- Sisi Kiri: Icon & Nama Produksi --}}
                                <div class="flex items-center gap-3 min-w-0">
                                    {{-- <div class="p-2 bg-emerald-500/10 rounded-lg shrink-0">
                            <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                            </svg>
                        </div> --}}
                                    <div class="min-w-0">
                                        <h2 class="text-[10px] font-black text-gray-400 uppercase tracking-widest leading-none mb-1">Produksi Hari Ini</h2>
                                        <p class="text-sm font-black text-gray-800 dark:text-white uppercase">{{ $data['nama_produksi'] ?? 'Unit' }}</p>
                                    </div>
                                </div>

                                {{-- Sisi Kanan: Info Mesin (Kreatif & Clean) --}}
                                <div class="flex flex-col items-end shrink-0 pl-3 border-l border-gray-100 dark:border-gray-800">
                                    <span class="text-[9px] font-bold text-gray-400 uppercase tracking-tighter">Machine</span>
                                    <div class="flex items-center gap-1.5">
                                        <span class="relative flex h-2 w-2">
                                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                                            <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
                                        </span>
                                        <span class="text-xs font-black text-gray-700 dark:text-gray-300 font-mono">{{ $data['mesin'] ?? 'N/A' }}</span>
                                    </div>
                                </div>
                            </div>
                            <div class="space-y-4">
                                <div class="flex justify-between items-end">
                                    <div>
                                        <p class="text-[9px] font-bold text-gray-400 uppercase">Output</p>
                                        <p class="text-xl font-black text-gray-800 dark:text-white">{{ $hariIni['total_harian'] ?? 0}}</p>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-[9px] font-bold text-gray-400 uppercase">Efficiency</p>
                                        <p class="text-xl font-black text-emerald-500">{{ $hariIni['progress_harian'] ?? 0 }}%</p>
                                    </div>
                                </div>
                                <div class="space-y-1.5">
                                    <div class="flex justify-between text-[9px] font-bold text-gray-500 uppercase">
                                        <span>Progress</span>
                                        <span>Target: {{ $hariIni["target_harian"]  }}</span>
                                    </div>
                                    <div class="h-2 w-full bg-gray-200 dark:bg-gray-800 rounded-full overflow-hidden">
                                        <div style="width: {{ $hariIni['progress_harian'] ?? 0 }}%" class="h-full bg-emerald-500 transition-all duration-1000"></div>
                                    </div>
                                </div>

                            </div>
                        </div>

                        {{-- RIGHT/BOTTOM PART: CHART --}}
                        <div class="grow flex flex-col min-h-[220px] transition-all duration-500 ease-in-out">
                            {{-- Header Mini --}}
                            <div class="px-5 py-3 border-b border-gray-100 dark:border-gray-800 flex justify-between items-center">
                                <span class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Weekly Analytics</span>
                                <div class="flex bg-gray-100 dark:bg-gray-800 p-0.5 rounded-lg border border-gray-200 dark:border-gray-700">
                                    <button @click="viewMode = 'stat'" :class="viewMode === 'stat' ? 'bg-white dark:bg-gray-700 shadow-xs text-gray-900 dark:text-white' : 'text-gray-500'" class="px-3 py-1 text-[9px] font-bold uppercase rounded-md transition-all">Graph</button>
                                    <button @click="viewMode = 'list'" :class="viewMode === 'list' ? 'bg-white dark:bg-gray-700 shadow-xs text-gray-900 dark:text-white' : 'text-gray-500'" class="px-3 py-1 text-[9px] font-bold uppercase rounded-md transition-all">List</button>
                                </div>
                            </div>

                            {{-- Chart Area dipangkas heightnya --}}
                            <div class="p-4 grow relative min-h-[150px] transition-all duration-500 ease-in-out">
                                @if(empty($dataProduksi))
                                <div class="flex items-center justify-center h-full text-[10px] font-bold text-gray-400 uppercase italic">No Data</div>
                                @else
                                {{-- GRAPH VIEW --}}
                                <div x-show="viewMode === 'stat'" x-transition class="relative h-full flex flex-col transition-all duration-500 ease-in-out">
                                    <div class="flex grow items-stretch">
                                        {{-- Y-Axis --}}
                                        <div class="flex flex-col justify-between text-[9px] font-black text-gray-400 pr-4 border-r border-gray-100 dark:border-gray-800 relative w-16">
                                            <span class="absolute right-3" style="top: 0%">{{ $maxChart }}</span>

                                            @foreach($mappedData as $item)
                                            @if($item['nilai'] !== null)
                                            <div class="absolute right-0 flex items-center" style="top: {{ (($maxChart - $item['nilai']) / $maxChart) * 100 }}%; transform: translateY(-50%);">
                                                <span class="{{ $item['is_low'] ? 'text-red-500 bg-red-50 dark:bg-red-500/10' : 'text-emerald-600 bg-emerald-50 dark:bg-emerald-500/10' }} px-2 py-1 rounded-md mr-3 border {{ $item['is_low'] ? 'border-red-100 dark:border-red-500/20' : 'border-emerald-100 dark:border-emerald-500/20' }} font-bold shadow-sm">
                                                    {{ $item['nilai'] }}
                                                </span>
                                                <div class="w-2 h-0.5px {{ $item['is_low'] ? 'bg-red-400' : 'bg-emerald-500' }} rounded-lg"></div>
                                            </div>
                                            @endif
                                            @endforeach

                                            <div class="absolute right-0 z-10 flex items-center" style="top: {{ (($maxChart - $targetMingguanRataRata) / $maxChart) * 100 }}%; transform: translateY(-50%);">
                                                <span class="whitespace-nowrap text-gray-900 bg-slate-50 font-bold px-2 py-1 rounded-md mr-3 border border-gray-900 italic shadow-sm tracking-tighter">
                                                    {{ $targetMingguanRataRata }}
                                                </span>
                                                <div class="w-2 h-px bg-slate-900 z-10 rounded-lg"></div>
                                            </div>

                                            <span class="absolute right-3 bottom-0">0</span>
                                        </div>

                                        {{-- SVG Chart --}}
                                        <div class="grow relative ml-4">
                                            <div class="absolute w-full border-t border-dashed  border-slate-900 dark:border-slate-50 z-0" style="top: {{ ($yTarget/400)*100 }}%"></div>
                                            <svg viewBox="0 0 1000 400" class="w-full h-full overflow-visible">
                                                <defs>
                                                    <linearGradient id="chartFill-{{ $loop->index }}" x1="0%" y1="0%" x2="0%" y2="100%">
                                                        <stop offset="0%" style="stop-color:rgb({{ $primaryRGB }});stop-opacity:0.2" />
                                                        <stop offset="100%" style="stop-color:rgb({{ $primaryRGB }});stop-opacity:0" />
                                                    </linearGradient>
                                                </defs>

                                                @if(!empty($points))
                                                <polyline fill="url(#chartFill-{{ $loop->index }})" points="0,400 {{ $points }} 1000,400" />
                                                <polyline fill="none" stroke="{{ $strokeHex }}" stroke-width="8" stroke-linecap="round" stroke-linejoin="round" points="{{ $points }}" class="transition-all duration-500" />

                                                @foreach(explode(' ', trim($points)) as $pIndex => $p)
                                                @php
                                                $c = explode(',', $p);
                                                $validData = collect($mappedData)->where('nilai', '!==', null)->values();
                                                $pointData = $validData[$pIndex] ?? null;
                                                $pointColor = ($pointData && $pointData['is_low']) ? '#ef4444' : '#10b981';
                                                @endphp
                                                <g>
                                                    <circle cx="{{ $c[0] }}" cy="{{ $c[1] }}" r="24" fill="white" class="dark:fill-gray-900" />
                                                    <circle cx="{{ $c[0] }}" cy="{{ $c[1] }}" r="12" fill="{{ $pointColor }}" />
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
                                            <span class="{{ $item['nilai'] ? 'text-gray-900 dark:text-gray-200' : 'opacity-40 text-slate-900 dark:text-slate-200' }}">{{ $item['hari'] }}</span>
                                            <div class="w-1 h-1 rounded-full {{ $item['nilai'] ? ($item['is_low'] ? 'bg-red-500' : 'bg-emerald-500') : 'opacity-40 bg-slate-900 dark:bg-slate-200' }}"></div>
                                        </div>
                                        @endforeach
                                    </div>
                                </div>

                                <div x-show="viewMode === 'list'" class="grid grid-cols-2 gap-2 transition-all duration-500 ease-in-out">
                                    @foreach(collect($dataProduksi)->take(4) as $m)
                                    <div class="flex justify-between items-center p-2 rounded-lg bg-gray-50 dark:bg-gray-800/50 border border-gray-100 dark:border-gray-800 text-[10px]">
                                        <span class="font-bold text-gray-500">{{ \Carbon\Carbon::parse($m['tanggal_produksi'])->format('D, d') }}</span>
                                        <span class="font-black {{ $m['total_harian'] < $targetMingguanRataRata ? 'text-red-500' : 'text-emerald-500' }}">{{ $m['total_harian'] }}</span>
                                    </div>
                                    @endforeach
                                </div>
                                @endif
                            </div>
                        </div>
                    </div>

                    {{-- Footer Sangat Tipis --}}
                    <div class="px-5 py-2 bg-gray-50 dark:bg-gray-800/50 border-t border-gray-100 dark:border-gray-800 flex justify-between items-center text-[8px] font-bold text-gray-400 uppercase">
                        <div class="flex gap-4">
                            <span class="flex items-center gap-1"><span class="w-1.5 h-1.5 rounded-full bg-slate-900 dark:bg-slate-50"></span> Target</span>
                            <span class="flex items-center gap-1"><span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Ok</span>
                            <span class="flex items-center gap-1"><span class="w-1.5 h-1.5 rounded-full bg-red-500"></span> Low</span>
                        </div>
                        <span>{{ now()->format('H:i') }}</span>
                    </div>
                </div>
    </div>
    @endforeach
    </div>
</x-filament-widgets::widget>