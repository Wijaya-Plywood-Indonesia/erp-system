<x-filament::page>
<style>
    [x-cloak] { display: none !important; }
</style>
<div x-data="{ 
    selected: [], 
    // Menggunakan count dari koleksi laporan yang sedang tampil di halaman ini
    openAll(count) {
        this.selected = Array.from({length: count}, (_, i) => i);
    },
    closeAll() {
        this.selected = [];
    },
    toggleRow(idx) {
        if (this.selected.includes(idx)) {
            this.selected = this.selected.filter(i => i !== idx);
        } else {
            this.selected.push(idx);
        }
    },
    routeToPreview() {
    
    }
}" class="space-y-4">
    <div class="p-4 bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-white/10 shadow-sm">
        <div class="flex flex-col md:flex-row items-center justify-between gap-4">
            
            <div class="flex justify-start w-full items-center gap-3">
                <div class="p-2 bg-primary-50 dark:bg-primary-500/10 rounded-lg">
                    <x-heroicon-o-funnel class="w-5 h-5 text-primary-600 dark:text-primary-400" />
                </div>
                <div class="hidden sm:block">
                    <h3 class="text-xs font-bold text-gray-900 dark:text-white uppercase tracking-tight">Filter Laporan</h3>
                </div>
            </div>

            <div class="flex  items-center gap-3 w-full md:w-auto">
                
                <div class="min-w-[180px] flex-1 md:flex-none">
                    <select wire:model.live="nama_lahan" 
                        class="block w-full px-3 py-1.5 text-xs font-semibold rounded-lg border-none ring-1 ring-gray-200 dark:ring-white/10 bg-gray-50 dark:bg-white/5 text-gray-900 dark:text-white focus:ring-2 focus:ring-primary-500 transition-all appearance-none">
                        <option value="Semua Lahan" class="dark:bg-gray-800">Semua Lahan</option>
                        @foreach($listLahan as $lahan)
                            <option value="{{ $lahan }}" class="dark:bg-gray-800">{{ $lahan }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="min-w-[120px] flex-1 md:flex-none">
                    <select wire:model.live="month" 
                        class="block w-full px-3 py-1.5 text-xs font-semibold rounded-lg border-none ring-1 ring-gray-200 dark:ring-white/10 bg-gray-50 dark:bg-white/5 text-gray-900 dark:text-white focus:ring-2 focus:ring-primary-500 transition-all appearance-none">
                        @foreach(range(1, 12) as $m)
                            <option value="{{ sprintf('%02d', $m) }}" class="dark:bg-gray-800">
                                {{ \Carbon\Carbon::create()->month($m)->translatedFormat('F') }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="min-w-[100px] flex-1 md:flex-none">
                    <select wire:model.live="year" 
                        class="block w-full px-3 py-1.5 text-xs font-semibold rounded-lg border-none ring-1 ring-gray-200 dark:ring-white/10 bg-gray-50 dark:bg-white/5 text-gray-900 dark:text-white focus:ring-2 focus:ring-primary-500 transition-all appearance-none">
                        @foreach(range(date('Y')-3, date('Y')) as $y)
                            <option value="{{ $y }}" class="dark:bg-gray-800">{{ $y }}</option>
                        @endforeach
                    </select>
                </div>

                <div wire:loading wire:target="month, year, nama_lahan" class="flex items-center ml-1">
                    <div class="animate-spin rounded-full h-4 w-4 border-2 border-primary-500 border-t-transparent"></div>
                </div>
            </div>
        </div>
    </div>
    <div class="flex sm:flex-row flex-col justify-between gap-2 mb-4">
        <div class="flex gap-2">
            <button @click="openAll({{ count($laporan->items()) }})" 
                    type="button"
                    class="px-3 py-2 text-xs font-bold rounded-lg shadow-sm transition
                        bg-primary-500 text-white hover:bg-primary-600 
                        dark:text-black
                        dark:bg-primary-500 dark:hover:bg-primary-400
                        ring-1 ring-primary-400 dark:ring-0">
                🔓 Buka Semua Baris
            </button>
            <button @click="closeAll()" 
                    type="button"
                    class="px-3 py-2 text-xs font-bold rounded-lg shadow-sm transition
                        bg-white text-gray-950 dark:hover:bg-gray-900 ring-1 ring-gray-950/10
                        dark:bg-white/10 dark:text-white dark:ring-0">
                🔒 Tutup Semua Baris
            </button>
        </div>
        <a 
            href="{{ route('filament.admin.pages.persentase-kayu.preview', request()->query()) }}" 
            target="_blank"
            class="px-4 py-2 text-xs font-bold rounded-lg shadow-sm transition
                bg-green-600 text-slate-100 ring-1 ring-green-950/10
                dark:ring-0">
        📑Preview Export Excel
        </a>
    </div>
    {{-- SECTION SUMMARY STATS --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-6">
        
        <div class="p-4 bg-white dark:bg-gray-900 border border-gray-200 dark:border-white/10 rounded-xl shadow-sm transition hover:ring-1 hover:ring-primary-500">
            <div class="flex items-center gap-3">
                <div class="p-2 bg-emerald-50 dark:bg-emerald-500/10 rounded-lg">
                    <x-heroicon-m-arrow-down-tray class="w-5 h-5 text-emerald-600 dark:text-emerald-400" />
                </div>
                <span class="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Input Kayu</span>
            </div>
            <div class="mt-3 flex flex-col">
                <span class="text-2xl font-black text-gray-900 dark:text-white">
                    {{ number_format($rekap['total_kayu_masuk'], 0, ',', '.') }} <span class="text-xs font-medium text-gray-400">Btg</span>
                </span>
                <span class="text-xs font-semibold text-emerald-600 dark:text-emerald-400 mt-1">
                    {{ number_format($rekap['total_kubikasi_kayu_masuk'], 4, ',', '.') }} m³
                </span>
            </div>
        </div>

        <div class="p-4 bg-white dark:bg-gray-900 border border-gray-200 dark:border-white/10 rounded-xl shadow-sm transition hover:ring-1 hover:ring-primary-500">
            <div class="flex items-center gap-3">
                <div class="p-2 bg-blue-50 dark:bg-blue-500/10 rounded-lg">
                    <x-heroicon-m-arrow-up-tray class="w-5 h-5 text-blue-600 dark:text-blue-400" />
                </div>
                <span class="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Output Veneer</span>
            </div>
            <div class="mt-3 flex flex-col">
                <span class="text-2xl font-black text-gray-900 dark:text-white">
                    {{ number_format($rekap['total_kubikasi_veneer'], 4, ',', '.') }} <span class="text-xs font-medium text-gray-400">m³</span>
                </span>
                <span class="text-xs font-bold px-2 py-0.5 bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300 rounded-full w-fit mt-1">
                    Rendemen: {{ $rekap['rata_rata_rendemen'] }}
                </span>
            </div>
        </div>

        <div class="p-4 bg-white dark:bg-gray-900 border border-gray-200 dark:border-white/10 rounded-xl shadow-sm transition hover:ring-1 hover:ring-primary-500">
            <div class="flex items-center gap-3">
                <div class="p-2 bg-amber-50 dark:bg-amber-500/10 rounded-lg">
                    <x-heroicon-m-banknotes class="w-5 h-5 text-amber-600 dark:text-amber-400" />
                </div>
                <span class="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Total Nilai Poin</span>
            </div>
            <div class="mt-3">
                <span class="text-2xl font-black text-gray-900 dark:text-white">
                    Rp {{ number_format($rekap['total_poin_masuk'], 0, ',', '.') }}
                </span>
                <p class="text-[10px] text-gray-400 mt-1 uppercase leading-tight font-medium">Berdasarkan akumulasi poin log masuk</p>
            </div>
        </div>

        <div class="p-4 bg-primary-600 dark:bg-primary-600 border border-transparent rounded-xl shadow-md transition transform hover:scale-[1.02]">
            <div class="flex items-center p-2 gap-3 text-white ">
                <x-heroicon-m-presentation-chart-line class=" w-5 h-5" />
                <span class="text-xs font-bold uppercase tracking-wider">Harga Veneer Rata Rata </span>
            </div>
            <div class="mt-3">
                <span class="text-2xl font-black text-white">
                    Rp {{ number_format($rekap['total_harga_veneer'], 0, ',', '.') }}
                </span>
                <div class="flex items-center gap-1.5 mt-1">
                    <div class="h-1.5 w-1.5 rounded-full bg-white animate-pulse"></div>
                    <span class="text-[10px] text-white/90 font-medium uppercase italic">Final Price / m³</span>
                </div>
            </div>
        </div>
        <div class="p-4 bg-primary-600 dark:bg-primary-600 border border-transparent rounded-xl shadow-md transition transform hover:scale-[1.02]">
            <div class="flex items-center p-2 gap-3 text-white ">
                <x-heroicon-m-presentation-chart-line class=" w-5 h-5" />
                <span class="text-xs font-bold uppercase tracking-wider">Harga Veneer + Ongkos Rata Rata </span>
            </div>
            <div class="mt-3">
                <span class="text-2xl font-black text-white">
                    Rp {{ number_format($rekap['total_harga_v_ongkos'], 0, ',', '.') }}
                </span>
                <div class="flex items-center gap-1.5 mt-1">
                    <div class="h-1.5 w-1.5 rounded-full bg-white animate-pulse"></div>
                    <span class="text-[10px] text-white/90 font-medium uppercase italic">Final Price / m³ + Ongkos Pekerja</span>
                </div>
            </div>
        </div>
        <div class="p-4 bg-primary-600 dark:bg-primary-600 border border-transparent rounded-xl shadow-md transition transform hover:scale-[1.02]">
            <div class="flex items-center p-2 gap-3 text-white ">
                <x-heroicon-m-presentation-chart-line class=" w-5 h-5" />
                <span class="text-xs font-bold uppercase tracking-wider">Harga Veneer + Ongkos + Penyusutan Rata Rata </span>
            </div>
            <div class="mt-3">
                <span class="text-2xl font-black text-white">
                    Rp {{ number_format($rekap['total_harga_vop'], 0, ',', '.') }}
                </span>
                <div class="flex items-center gap-1.5 mt-1">
                    <div class="h-1.5 w-1.5 rounded-full bg-white animate-pulse"></div>
                    <span class="text-[10px] text-white/90 font-medium uppercase italic">Final Price / m³ + Ongkos Pekerja + Biaya Penyusutan</span>
                </div>
            </div>
        </div>

    </div>


    <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900">
        <table class="w-full text-left text-sm table-auto border-separate border-spacing-0">
            <thead>
                <tr class="bg-gray-50 border-b border-gray-900 dark:bg-white/5 dark:border-white/10">
                    {{-- <th class="px-4 py-3 font-semibold">Tgl Masuk</th> --}}
                    <th class="px-4 py-3 font-semibold whitespace-nowrap ">Lahan</th>
                    <th class="px-4 py-3 font-semibold whitespace-nowrap  text-center">Batang</th>
                    <th class="px-4 py-3 font-semibold whitespace-nowrap ">Kubikasi (In)</th>
                    <th class="px-4 py-3 font-semibold whitespace-nowrap  text-green-600 dark:text-green-400">Poin</th>
                    <th class="px-4 py-3 font-semibold whitespace-nowrap  text-blue-600 dark:text-blue-400">Kubikasi (Out)</th>
                    <th class="px-4 py-3 font-semibold whitespace-nowrap ">Persentase</th>
                    <th class="px-4 py-3 font-semibold whitespace-nowrap  text-green-600 dark:text-green-400">Veneer</th>
                    <th class="px-4 py-3 font-semibold whitespace-nowrap  text-green-600 dark:text-green-400">Veneer+Ongkos</th>
                    <th class="px-4 py-3 font-semibold whitespace-nowrap  text-green-600 dark:text-green-400">Veneer+Ongkos+Susut</th>
                </tr>
            </thead>
            
            <tbody>
                @forelse($laporan as $index => $row)
                    <tr @click="toggleRow({{ $index }})" 
                        class="cursor-pointer border-b border-gray-100 hover:bg-gray-400 dark:border-white/5 dark:hover:bg-white/5 transition-colors"
                        :class="selected.includes({{ $index }}) ? 'bg-gray-50 dark:bg-white/5' : ''">
                        {{-- <td class="px-4 py-4 whitespace-nowrap">{{ $data['tgl_masuk'] ?? '2026-02-19' }}</td> --}}
                        <td class="px-4 py-4 font-bold whitespace-nowrap">
                            <span class="text-primary-600 dark:text-primary-500 me-0.5">{{ $row['batch_info']['kode'] }}</span> 
                            {{ $row['batch_info']['lahan'] }} 
                            <span class="ms-0.5 text-primary-700 dark:text-primary-300 text-xs">
                                {{ $row['batch_info']['kode_kayu'] }}
                            </span>

                        </td>
                        <td class="px-4 py-4 whitespace-nowrap text-center">{{ $row['summary']['total_kayu_masuk'] ?? 0 }}</td>
                        <td class="px-4 py-4 whitespace-nowrap">{{ number_format($row['summary']['total_masuk_m3'] ?? 0, 4) }} m³</td>
                        <td class="px-4 py-4 whitespace-nowrap text-right font-bold text-green-600">Rp {{ $row['summary']['total_poin'] }}</td>
                        <td class="px-4 py-4 whitespace-nowrap text-right font-bold text-blue-600">{{ number_format($row['summary']['total_keluar_m3'], 4) }} m³</td>
                        <td class="px-4 py-4 whitespace-nowrap text-center">
                            <span class="px-2 py-1 rounded bg-green-100 text-green-700 dark:text-green-300 dark:bg-green-900/40 font-bold text-xs">
                                {{ $row['summary']['rendemen'] }}
                            </span>
                        </td>
                        <td class="px-4 py-4 whitespace-nowrap font-bold text-green-600 dark:text-green-400">Rp {{ number_format($row['summary']['harga_veneer'] ?? 0, 2, ',', '.') }}</td>
                        <td class="px-4 py-4 whitespace-nowrap font-bold text-green-600 dark:text-green-400">{{ $row['summary']['harga_v_ongkos'] ? 'Rp ' . number_format($row['summary']['harga_v_ongkos'] ?? 0, 2, ',', '.') : 'Belum Tersedia'}} </td>
                        <td class="px-4 py-4 whitespace-nowrap font-bold text-green-600 dark:text-green-400">{{ $row['summary']['harga_vop'] ? 'Rp ' . number_format($row['summary']['harga_vop'] ?? 0, 2, ',', '.') : 'Belum Tersedia'}} </td>
                    </tr>

                    <tr x-show="selected.includes({{ $index }})" x-cloak x-transition>
                        <td colspan="10" class="bg-gray-50/50 p-4 dark:bg-white/5">
                            <div class="space-y-4" x-data="{ openMasuk: true, openKeluar: true }">
                                <div class="rounded-lg border border-gray-200 dark:border-white/10 overflow-hidden">
                                    <button @click="openMasuk = !openMasuk" 
                                                class="w-full flex justify-between items-center px-4 py-2 bg-white dark:bg-gray-800 font-bold text-sm">
                                            <span>📦 KAYU MASUK</span>
                                            <svg class="w-4 h-4 transition-transform" :class="openMasuk ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                                    </button>
                                    <div x-show="openMasuk" class="p-2 overflow-x-auto">
                                        <table class="w-full text-xs text-left">
                                            <thead class="bg-gray-100 dark:bg-gray-700">
                                                <tr>
                                                    <th class="px-2 py-2">Tanggal Masuk</th>
                                                    <th class="px-2 py-2">Seri</th>
                                                    <th class="px-2 py-2">Banyak</th>
                                                    <th class="px-2 py-2">Kubikasi</th>
                                                    <th class="px-2 py-2 text-green-600 dark:text-green-400">Poin</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($row['inflow'] ?? [1] as $km)
                                                <tr class="border-b dark:border-white/5">
                                                    <td class="px-2 py-2">{{ ($km['tanggal'] ?? '2026-02-19') }}</td>
                                                    <td class="px-2 py-2">{{ $km['seri'] ?? 'SR-001' }}</td>
                                                    <td class="px-2 py-2">{{ $km['banyak'] ?? 10 }}</td>
                                                    <td class="px-2 py-2">{{ number_format($km['kubikasi'] ?? 0, 4) }} m³</td>
                                                    <td class="px-2 py-2 text-green-600 dark:text-green-400 font-bold">Rp. {{ number_format($km['poin'] ?? 0, 2) }}</td>
                                                </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                <div class="rounded-lg border border-gray-200 dark:border-white/10 overflow-hidden">
                                    <button @click="openKeluar = !openKeluar" 
                                            class="w-full flex justify-between items-center px-4 py-2 bg-white dark:bg-gray-800 font-bold text-sm">
                                        {{-- <span>🪵KAYU KELUAR - {{ $row['summary']['jenis_kayu'] }}</span> --}}
                                        <span>🪵KAYU KELUAR</span>
                                        <svg class="w-4 h-4 transition-transform" :class="openKeluar ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                                    </button>
                                    <div x-show="openKeluar" class="p-2 overflow-x-auto">
                                        <table class="w-full text-xs text-left">
                                            <thead class="bg-gray-100 dark:bg-gray-700">
                                                <tr>
                                                    <th class="px-2 py-2 whitespace-nowrap">Tanggal Produksi</th>
                                                    <th class="px-2 py-2">Mesin</th>
                                                    <th class="px-2 py-2 whitespace-nowrap">Jam Kerja</th>
                                                    <th class="px-2 py-2">Ukuran</th>
                                                    <th class="px-2 py-2">Banyak</th>
                                                    <th class="px-2 py-2">Kubikasi</th>
                                                    <th class="px-2 py-2">Pekerja</th>
                                                    <th class="px-2 py-2 text-green-600 dark:text-green-400">Ongkos / Pekerja</th>
                                                    <th class="px-2 py-2">Penyusutan</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($row['outflow'] ?? [1] as $kk)
                                                <tr class="border-b dark:border-white/5">
                                                    <td class="px-2 py-2 whitespace-nowrap">{{ $kk['tgl'] ?? '-' }}</td>
                                                    <td class="px-2 py-2">{{ $kk['mesin'] ?? '-' }}</td>
                                                    <td class="px-2 py-2">{{ $kk['jam_kerja'] ?? '-' }}</td>
                                                    <td class="px-2 py-2 whitespace-nowrap">{{ $kk['ukuran'] ?? '-' }}</td>
                                                    <td class="px-2 py-2">{{ $kk['total_banyak'] ?? 0 }}</td>
                                                    <td class="px-2 py-2">{{ number_format($kk['total_kubikasi'] ?? 0, 4) }} m³</td>
                                                    <td class="px-2 py-2">{{ $kk['pekerja'] ?? '-' }}</td>
                                                    <td class="px-2 py-2 font-bold {{ $kk['ongkos'] > 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                                                        {{ $kk['ongkos'] > 0 ? 'Rp ' . number_format($kk['ongkos']) : "0 ( Belum Diatur )" }}
                                                    </td>
                                                    <td class="px-2 py-2 {{ $kk['penyusutan'] == 0  && 'text-red-600 dark:text-red-400'  }}">{{ $kk['penyusutan'] != 0 ? 'Rp ' . number_format($kk['penyusutan'] ?? 0) : "0 ( Belum Diatur )" }}</td>
                                                </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="10" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">
                            Data produksi belum tersedia.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        {{-- MANUAL PAGINATION UI --}}
        <div class="flex items-center justify-between px-4 py-3 bg-white border border-gray-200 rounded-xl dark:bg-gray-900/50 dark:border-white/10 dark:backdrop-blur-md shadow-sm">
            
            {{-- Info Mobile --}}
            <div class="flex flex-1 justify-between sm:hidden">
                <a href="{{ $laporan->previousPageUrl() }}" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg dark:bg-gray-800 dark:border-white/10 dark:text-gray-300 {{ $laporan->onFirstPage() ? 'opacity-50 pointer-events-none' : '' }}"> 
                    <x-heroicon-m-chevron-left class="w-5 h-5" />
                </a>
                <a href="{{ $laporan->nextPageUrl() }}" class="ml-3 px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg dark:bg-gray-800 dark:border-white/10 dark:text-gray-300 {{ !$laporan->hasMorePages() ? 'opacity-50 pointer-events-none' : '' }}"> 
                    <x-heroicon-m-chevron-right class="w-5 h-5" />
                </a>
            </div>

            {{-- Desktop Pagination --}}
            <div class="hidden sm:flex sm:flex-1 sm:items-center sm:justify-between">
                
                {{-- Sisi Kiri: Info Data --}}
                <div class="flex items-center gap-4">
                    <p class="text-xs font-medium text-gray-600 dark:text-gray-400 tracking-wide">
                        Menampilkan <span class="font-black text-gray-900 dark:text-white">{{ $laporan->firstItem() }}</span> 
                        <span class="text-gray-400 mx-0.5">-</span> 
                        <span class="font-black text-gray-900 dark:text-white">{{ $laporan->lastItem() }}</span> 
                        <span class="lowercase">dari</span> 
                        <span class="font-black text-gray-900 dark:text-white">{{ $laporan->total() }}</span> <span class="text-[10px] uppercase font-bold text-gray-400">Data</span>
                    </p>
                </div>

                {{-- TENGAH: Per Page Selector (Sleek Dark Mode) --}}
                <div class="flex items-center gap-2 group">
                    <label class="text-[10px] font-black text-gray-400 uppercase tracking-widest dark:text-gray-500">Baris</label>
                    <div class="relative">
                        <select wire:model.live="perPage" 
                            class="block w-full pl-3 pr-8 py-1.5 text-xs font-bold rounded-lg border-none ring-1 ring-gray-200 dark:ring-white/10 bg-gray-50 dark:bg-white/5 text-gray-900 dark:text-white focus:ring-2 focus:ring-primary-500 transition-all cursor-pointer appearance-none hover:ring-gray-300 dark:hover:ring-white/20">
                            <option value="10" class="dark:bg-gray-900">10</option>
                            <option value="25" class="dark:bg-gray-900">25</option>
                            <option value="50" class="dark:bg-gray-900">50</option>
                            <option value="100" class="dark:bg-gray-900">100</option>
                        </select>
                        <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2 text-gray-400">
                            <x-heroicon-m-chevron-down class="w-3 h-3" />
                        </div>
                    </div>
                </div>

                {{-- Sisi Kanan: Navigation Buttons --}}
                <div>
                    <nav class="flex items-center gap-1.5" aria-label="Pagination">
                        {{-- Previous --}}
                        <a href="{{ $laporan->previousPageUrl() }}" 
                        class="p-2 transition-all rounded-lg ring-1 ring-gray-200 dark:ring-white/10 hover:bg-gray-50 dark:hover:bg-white/5 text-gray-500 dark:text-gray-400 {{ $laporan->onFirstPage() ? 'opacity-30 pointer-events-none' : 'hover:text-primary-600' }}">
                            <x-heroicon-m-chevron-left class="h-4 w-4" />
                        </a>

                        {{-- Nomor Halaman --}}
                        @php
                            $start = max($laporan->currentPage() - 1, 1);
                            $end = min($start + 2, $laporan->lastPage());
                        @endphp

                        @if($start > 1)
                            <a href="{{ $laporan->url(1) }}" class="px-3 py-1.5 text-xs font-bold transition-all rounded-lg ring-1 ring-gray-200 dark:ring-white/10 hover:bg-gray-50 dark:hover:bg-white/5 dark:text-white">1</a>
                            <span class="text-gray-300 dark:text-gray-600">...</span>
                        @endif

                        @foreach(range($start, $end) as $page)
                            @if($page == $laporan->currentPage())
                                <span class="px-3 py-1.5 text-xs font-black text-white bg-primary-600 rounded-lg shadow-sm shadow-primary-500/20 ring-1 ring-primary-500">
                                    {{ $page }}
                                </span>
                            @else
                                <a href="{{ $laporan->url($page) }}" class="px-3 py-1.5 text-xs font-bold transition-all rounded-lg ring-1 ring-gray-200 dark:ring-white/10 hover:bg-gray-50 dark:hover:bg-white/5 dark:text-gray-300">
                                    {{ $page }}
                                </a>
                            @endif
                        @endforeach

                        @if($end < $laporan->lastPage())
                            <span class="text-gray-300 dark:text-gray-600">...</span>
                            <a href="{{ $laporan->url($laporan->lastPage()) }}" class="px-3 py-1.5 text-xs font-bold transition-all rounded-lg ring-1 ring-gray-200 dark:ring-white/10 hover:bg-gray-50 dark:hover:bg-white/5 dark:text-white">{{ $laporan->lastPage() }}</a>
                        @endif

                        {{-- Next --}}
                        <a href="{{ $laporan->nextPageUrl() }}" 
                        class="p-2 transition-all rounded-lg ring-1 ring-gray-200 dark:ring-white/10 hover:bg-gray-50 dark:hover:bg-white/5 text-gray-500 dark:text-gray-400 {{ !$laporan->hasMorePages() ? 'opacity-30 pointer-events-none' : 'hover:text-primary-600' }}">
                            <x-heroicon-m-chevron-right class="h-4 w-4" />
                        </a>
                    </nav>
                </div>
            </div>
        </div>


    </div>
</div>

</x-filament::page>

