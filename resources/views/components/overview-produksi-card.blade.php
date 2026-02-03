@props([
    'name' => 'Laporan Produksi',
    'urlResource' => '/admin/',
    'totalProduksi' => 0,
    'satuanHasil' => 'm³',
    'totalPegawai' => 0,
    'dataRekap' => [],
    'color' => 'blue',
])

@php
    // Mapping warna Tailwind agar dinamis
    $colors = [
        'blue' => 'from-blue-500 to-blue-600',
        'green' => 'from-emerald-500 to-emerald-600',
        'red' => 'from-red-500 to-red-600',
        'orange' => 'from-orange-500 to-orange-600',
    ][$color] ?? 'from-gray-500 to-gray-600';

    // Pengecekan data yang lebih menyeluruh agar tidak error saat foreach
    
    $hasData = count($dataRekap[0]['rekap'][0]['data']) > 0;
@endphp

<div x-data="{
    expanded: false,
    hasDetail: {{ $hasData ? 'true' : 'false' }},
    toggle() {
        if (this.hasDetail) {
            this.expanded = !this.expanded;
        }
    }
 }" 
     class="w-full min-w-md mx-auto bg-white dark:bg-gray-900 rounded-2xl shadow-lg overflow-hidden transition-all duration-300 ease-in-out border border-gray-200 dark:border-gray-800"
     :class="expanded ? 'shadow-2xl ring-2 ring-primary-500/50 md:col-span-2' : ''">
    
    {{-- Top Header --}}
    <div class="bg-gradient-to-r from-slate-800 to-slate-900 dark:from-primary-600 dark:to-primary-700 p-4 flex justify-between items-center text-white">
        <div class="flex items-center gap-2">
            <span class="p-1.5 bg-white/10 rounded-lg">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                </svg>
            </span>
            <span class="font-bold tracking-wide text-sm uppercase">{{ $name }}</span>
        </div>
        <span class="text-[10px] bg-emerald-500 dark:bg-red-400 px-2 py-1 rounded-full animate-pulse font-bold">Live</span>
    </div>

    {{-- Main Summary (Trigger) --}}
    <div class="p-6 cursor-pointer transition-colors" @click="toggle()">
        <div class="grid grid-cols-2 gap-4">
            <div class="flex flex-col">
                <span class="text-gray-500 dark:text-gray-400 text-xs font-medium uppercase italic">Total Produksi</span>
                <span class="text-2xl font-black text-gray-800 dark:text-gray-100">
                    {{ number_format($totalProduksi) }} 
                    <small class="text-sm font-normal text-gray-400 dark:text-gray-500">{{ $satuanHasil }}</small>
                </span>
            </div>
            <div class="flex flex-col border-l pl-4 border-gray-100 dark:border-gray-800">
                <span class="text-gray-500 dark:text-gray-400 text-xs font-medium uppercase italic">Total Pegawai</span>
                <span class="text-2xl font-black text-gray-800 dark:text-gray-100">
                    {{ $totalPegawai }} 
                    <small class="text-sm font-normal text-gray-400 dark:text-gray-500">Orang</small>
                </span>
            </div>
        </div>

        @if ($hasData)
            <div class="mt-4 flex justify-center">
                <div class="px-4 py-1 bg-slate-100 dark:bg-gray-800 rounded-full flex items-center gap-2">
                    <span class="text-[10px] text-primary-600 dark:text-primary-400 font-bold capitalize" x-text="expanded ? 'Tutup Detail' : 'Lihat Perincian'"></span>
                    <svg class="w-3 h-3 transition-transform duration-300" :class="expanded ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                    </svg>
                </div>
            </div>
        @endif
    </div>

    {{-- Expanded Area --}}
    @if ($hasData)
    <div x-show="expanded" x-collapse x-cloak>
        @foreach ($dataRekap as $items)
        <div class="mb-4 last:mb-0">
            {{-- HEADER 1: Perincian Utama --}}
            <div class="bg-slate-800 dark:bg-primary-900 text-white p-5 mx-4 rounded-xl shadow-inner relative overflow-hidden">
                <div class="absolute right-0 top-0 opacity-10">
                    <svg class="w-24 h-24" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M13 7h-2v4H7v2h4v4h2v-4h4v-2h4V7z"/>
                    </svg>
                </div>
                
                <h3 class="text-[10px] font-bold text-slate-400 dark:text-primary-300 capitalize tracking-[0.2em] mb-1">Perincian Utama</h3>
                <h2 class="text-lg font-black leading-tight">{{ $items['name'] ?? 'Ringkasan' }}</h2>
            </div>

            {{-- HEADER 2 & CONTENT: Perincian Detail --}}
            <div class="p-6">
                @foreach ($items['rekap'] ?? [] as $item)
                <div class="mb-8 last:mb-0">
                    <div class="flex items-center justify-between mb-4 border-b border-dashed border-slate-200 dark:border-gray-700 pb-2">
                        <div>
                            <h3 class="text-[10px] font-bold text-slate-400 dark:text-gray-500 capitalize tracking-[0.2em]">Perincian Detail</h3>
                            <h4 class="text-sm font-black text-slate-700 dark:text-gray-200 capitalize">{{ $item['title'] ?? 'Tanpa Judul' }}</h4>
                        </div>
                    </div>
                    
                    <div class="space-y-4">
                        @foreach ($item['data'] ?? [] as $col)
                        <div class="space-y-1">
                            <div class="flex justify-between items-center text-xs font-bold mb-1">
                                <span class="text-slate-600 dark:text-gray-400">Ukuran {{ $col['ukuran_title'] ?? '-' }}</span>
                                <span class="text-slate-800 dark:text-gray-200">{{ number_format($col['jumlah'] ?? 0) }} {{ $satuanHasil }}</span>
                            </div>
                            <div class="w-full bg-slate-100 dark:bg-gray-800 rounded-full h-1.5 overflow-hidden">
                                <div class="bg-slate-800 dark:bg-primary-500 h-1.5 rounded-full transition-all duration-700" 
                                        :style="expanded ? 'width: {{ ($totalProduksi > 0) ? (($col['jumlah'] ?? 0) / $totalProduksi) * 100 : 0 }}%' : 'width: 0%'">
                                </div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
                @endforeach
            </div>
        </div>
        @endforeach

        {{-- Footer Catatan --}}
        <div class="px-6 pb-6">
            <div class="p-3 bg-yellow-50 dark:bg-yellow-900/20 rounded-lg border border-yellow-100 dark:border-yellow-900/30">
                <p class="text-[10px] text-yellow-700 dark:text-yellow-500 leading-relaxed font-medium italic">
                    <strong>Catatan:</strong> Data ini mencakup akumulasi produksi dari semua shift kerja hari ini.
                </p>
            </div>
        </div>
    </div>
    @endif
</div>