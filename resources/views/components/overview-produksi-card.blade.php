@props([
    'title' => 'Laporan Produksi ...',
    'totalProduksi' => 0,
    'satuanProduksi' => 'm³',
    'totalPegawai' => 0,
    'detailUkuran' => [
        // ['ukuran' => '200cm (Grade A)', 'jumlah' => 0],
        // ['ukuran' => '400cm (Grade B)', 'jumlah' => 0],
    ],
    'color' => 'blue', // Default warna biru
])

@php

    // Mapping warna Tailwind agar dinamis
    $colors = [
        'blue' => 'from-blue-500 to-blue-600',
        'green' => 'from-emerald-500 to-emerald-600',
        'red' => 'from-red-500 to-red-600',
        'orange' => 'from-orange-500 to-orange-600',
    ][$color] ?? 'from-gray-500 to-gray-600';
@endphp

<div x-data="{
    expanded: false,
    hasDetail: {{ count($detailUkuran) > 0 ? 'true' : 'false' }},
    toggle() {
        if (this.hasDetail) {
            this.expanded = !this.expanded;
        }
    }
 }" 
     class="w-full min-w-md mx-auto bg-white dark:bg-gray-900 rounded-2xl shadow-lg overflow-hidden transition-all duration-300 ease-in-out border border-gray-200 dark:border-gray-800"
     :class="expanded ? 'shadow-2xl ring-2 ring-primary-500/50 md:col-span-2' : ''">
    
    <div class="bg-gradient-to-r from-slate-800 to-slate-900 dark:from-primary-600 dark:to-primary-700 p-4 flex justify-between items-center text-white">
        <div class="flex items-center gap-2">
            <span class="p-1.5 bg-white/10 rounded-lg">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
            </span>
            <span class="font-bold tracking-wide text-sm uppercase">{{ $title }}</span>
        </div>
        <span class="text-[10px] bg-emerald-500 dark:bg-red-400 px-2 py-1 rounded-full animate-pulse font-bold">Live</span>
    </div>
    <div class="p-6 cursor-pointer" @click="toggle()">
        <div class="grid grid-cols-2 gap-4">
            <div class="flex flex-col">
                <span class="text-gray-500 dark:text-gray-400 text-xs font-medium uppercase italic">Total Produksi</span>
                <span class="text-2xl font-black text-gray-800 dark:text-gray-100">
                    {{ number_format($totalProduksi) }} 
                    <small class="text-sm font-normal text-gray-400 dark:text-gray-500">{{ $satuanProduksi }}</small>
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

        @if (count($detailUkuran) > 0)
            <div class="mt-4 flex justify-center">
                <p class="text-[10px] text-primary-600 dark:text-primary-400 font-bold uppercase flex items-center gap-1">
                    <span x-text="expanded ? 'Klik untuk Ciutkan' : 'Klik untuk Detail Ukuran'"></span>
                    <svg class="w-3 h-3 transition-transform duration-300" :class="expanded ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                </p>
            </div>
        @endif
    </div>

    @if (count($detailUkuran) > 0)
    <div x-show="expanded" 
         x-collapse 
         class="bg-gray-50 dark:bg-gray-950/50 border-t border-gray-100 dark:border-gray-800 p-6">
        
        <h4 class="text-xs font-bold text-gray-400 dark:text-gray-500 uppercase mb-4 tracking-widest">Detail Ukuran Kayu</h4>
        
        <div class="space-y-4">
            @foreach ($detailUkuran as $items)
            <div class="space-y-1">
                <div class="flex justify-between items-center">
                    <span class="text-sm text-gray-600 dark:text-gray-300">Ukuran {{ $items['ukuran'] }}</span>
                    <span class="px-2 py-0.5 bg-white dark:bg-gray-800 border dark:border-gray-700 rounded shadow-sm text-xs font-bold text-gray-700 dark:text-gray-200">
                        {{ number_format($items['jumlah']) }} Pcs
                    </span>
                </div>
                <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-1.5 overflow-hidden">
                    <div class="bg-primary-500 h-1.5 rounded-full transition-all duration-500" 
                         style="width: {{ ($items['jumlah'] / $totalProduksi) * 100 }}%"></div>
                </div>
            </div>
            @endforeach
        </div>

        <div class="mt-6 p-3 bg-yellow-50 dark:bg-yellow-900/20 rounded-lg border border-yellow-100 dark:border-yellow-900/30">
            <p class="text-[10px] text-yellow-700 dark:text-yellow-500 leading-relaxed font-medium">
                <strong>Catatan:</strong> Data ini mencakup akumulasi produksi dari semua shift kerja hari ini.
            </p>
        </div>
    </div>
    @endif
</div>