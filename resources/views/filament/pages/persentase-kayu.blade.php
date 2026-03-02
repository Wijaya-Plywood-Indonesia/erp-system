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
        <a href="/preview-excel"  
                class="px-4 py-2 text-xs font-bold rounded-lg shadow-sm transition
                    bg-green-600 text-slate-100 ring-1 ring-green-950/10
                    dark:ring-0">
            📑Preview Export Excel
        </a>
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
        <div class="flex items-center justify-between px-4 py-3 bg-white border border-gray-200 rounded-xl dark:bg-gray-900 dark:border-white/10 shadow-sm">
            {{-- Info Mobile (Sederhana) --}}
            <div class="flex flex-1 justify-between sm:hidden">
                <a href="{{ $laporan->previousPageUrl() }}" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md dark:hover:bg-gray-900 {{ $laporan->onFirstPage() ? 'opacity-50 pointer-events-none' : '' }}">
                    <
                </a>
                <a href="{{ $laporan->nextPageUrl() }}" class="ml-3 px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md dark:hover:bg-gray-900 {{ !$laporan->hasMorePages() ? 'opacity-50 pointer-events-none' : '' }}">
                    >
                </a>
            </div>

            {{-- Desktop Pagination --}}
            <div class="hidden sm:flex sm:flex-1 sm:items-center sm:justify-between">
                <div>
                    <p class="text-sm text-gray-700 dark:text-gray-400">
                        Menampilkan <span class="font-medium">{{ $laporan->firstItem() }}</span> - <span class="font-medium">{{ $laporan->lastItem() }}</span> dari <span class="font-medium">{{ $laporan->total() }}</span>
                    </p>
                </div>

                <div>
                    <nav class="isolate inline-flex -space-x-px rounded-md shadow-sm gap-1" aria-label="Pagination">
                        {{-- Tombol Previous Icon --}}
                        <a href="{{ $laporan->previousPageUrl() }}" 
                        class="relative inline-flex items-center rounded-md px-2 py-2 text-gray-400 ring-1 ring-inset ring-gray-300 dark:hover:bg-gray-900 focus:z-20 focus:outline-offset-0 dark:ring-white/10 {{ $laporan->onFirstPage() ? 'opacity-50 pointer-events-none' : '' }}">
                            <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" d="M12.79 5.23a.75.75 0 01-.02 1.06L8.832 10l3.938 3.71a.75.75 0 11-1.04 1.08l-4.5-4.25a.75.75 0 010-1.08l4.5-4.25a.75.75 0 011.06.02z" clip-rule="evenodd" />
                            </svg>
                            <span class="sr-only">Previous</span>
                        </a>

                        {{-- Nomor Halaman Manual dengan Logic Ellipsis --}}
                        @php
                            $start = max($laporan->currentPage() - 2, 1);
                            $end = min($start + 4, $laporan->lastPage());
                            if($end === $laporan->lastPage()) $start = max($end - 4, 1);
                        @endphp

                        @if($start > 1)
                            <a href="{{ $laporan->url(1) }}" class="relative rounded-md inline-flex items-center px-4 py-2 text-sm font-semibold text-gray-900 ring-1 ring-inset ring-gray-300 dark:hover:bg-gray-900 dark:text-white dark:ring-white/10">1</a>
                            @if($start > 2)
                                <span class="relative inline-flex rounded-md items-center px-4 py-2 text-sm font-semibold text-gray-700 ring-1 ring-inset ring-gray-300 dark:text-gray-400 dark:ring-white/10">...</span>
                            @endif
                        @endif

                        @foreach(range($start, $end) as $page)
                            @if($page == $laporan->currentPage())
                                <span aria-current="page" class="relative z-10 inline-flex items-center bg-primary-600 px-4 py-2 text-sm font-semibold text-white focus:z-20 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600 rounded-md">
                                    {{ $page }}
                                </span>
                            @else
                                <a href="{{ $laporan->url($page) }}" class="relative inline-flex items-center px-4 py-2 text-sm font-semibold text-gray-900 ring-1 ring-inset ring-gray-300 dark:hover:bg-gray-900 focus:z-20 focus:outline-offset-0 dark:text-white dark:ring-white/10 rounded-md">
                                    {{ $page }}
                                </a>
                            @endif
                        @endforeach

                        @if($end < $laporan->lastPage())
                            @if($end < $laporan->lastPage() - 1)
                                <span class="relative inline-flex items-center px-4 py-2 text-sm font-semibold text-gray-700 ring-1 ring-inset ring-gray-300 dark:text-gray-400 dark:ring-white/10 rounded-md">...</span>
                            @endif
                            <a href="{{ $laporan->url($laporan->lastPage()) }}" class="relative inline-flex items-center px-4 py-2 text-sm font-semibold text-gray-900 ring-1 ring-inset ring-gray-300 dark:hover:bg-gray-900 dark:text-white dark:ring-white/10 rounded-md">{{ $laporan->lastPage() }}</a>
                        @endif

                        {{-- Tombol Next Icon --}}
                        <a href="{{ $laporan->nextPageUrl() }}" 
                        class="relative inline-flex items-center rounded-md px-2 py-2 text-gray-400 ring-1 ring-inset ring-gray-300 dark:hover:bg-gray-900 focus:z-20 focus:outline-offset-0 dark:ring-white/10 {{ !$laporan->hasMorePages() ? 'opacity-50 pointer-events-none' : '' }}">
                            <span class="sr-only">Next</span>
                            <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z" clip-rule="evenodd" />
                            </svg>
                        </a>
                    </nav>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="overflow-x-auto bg-gray-100 dark:bg-gray-900 transition-colors duration-300">
    <table class="w-full border-collapse border border-gray-400 dark:border-gray-700 bg-white dark:bg-gray-800 text-sm font-sans text-gray-900 dark:text-gray-100">
        <thead>
            <tr class="bg-white dark:bg-gray-800">
                <th colspan="23" class="text-center py-4 text-xl font-bold uppercase tracking-widest border-b border-gray-400 dark:border-gray-700">
                    KAYU 130
                </th>
            </tr>

            <tr class="bg-gray-200 dark:bg-gray-700 border border-gray-400 dark:border-gray-600">
                <th rowspan="2" class="border border-gray-400 dark:border-gray-600 px-2 py-1">Tanggal</th>
                <th rowspan="2" class="border border-gray-400 dark:border-gray-600 px-2 py-1">Habis</th>
                <th colspan="5" class="border border-gray-400 dark:border-gray-600 px-2 py-1">Kayu</th>
                <th colspan="5" class="border border-gray-400 dark:border-gray-600 px-2 py-1">Veneer</th>
                <th rowspan="2" class="border border-gray-400 dark:border-gray-600 px-2 py-1">Jam Kerja</th>
                <th rowspan="2" class="border border-gray-400 dark:border-gray-600 px-2 py-1 bg-blue-200 dark:bg-blue-900/50"> % </th>
                <th rowspan="2" class="border border-gray-400 dark:border-gray-600 px-2 py-1 bg-green-500 dark:bg-green-600 text-black dark:text-white">harga veneer/m3</th>
                <th rowspan="2" class="border border-gray-400 dark:border-gray-600 px-2 py-1 bg-blue-200 dark:bg-blue-900/50">Pekerja</th>
                <th rowspan="2" class="border border-gray-400 dark:border-gray-600 px-2 py-1 bg-orange-200 dark:bg-orange-900/40">Ongkos/pkj</th>
                <th rowspan="2" class="border border-gray-400 dark:border-gray-600 px-2 py-1 bg-orange-400 dark:bg-orange-600 text-black dark:text-white">Harga Veneer + Ongkos</th>
                <th rowspan="2" class="border border-gray-400 dark:border-gray-600 px-2 py-1 bg-blue-200 dark:bg-blue-900/50">Penyusutan</th>
                <th rowspan="2" class="border border-gray-400 dark:border-gray-600 px-2 py-1 bg-yellow-300 dark:bg-yellow-600 text-black dark:text-white">Harga Veneer + Ongkos + penyusutan</th>
            </tr>

            <tr class="bg-gray-200 dark:bg-gray-700 border border-gray-400 dark:border-gray-600">
                <th class="border border-gray-400 dark:border-gray-600 px-2 py-1">Lahan</th>
                <th class="border border-gray-400 dark:border-gray-600 px-2 py-1">Batang</th>
                <th class="border border-gray-400 dark:border-gray-600 px-2 py-1">Pecah</th>
                <th class="border border-gray-400 dark:border-gray-600 px-2 py-1 bg-orange-400 dark:bg-orange-600 text-black dark:text-white">m3</th>
                <th class="border border-gray-400 dark:border-gray-600 px-2 py-1 bg-yellow-300 dark:bg-yellow-600 text-black dark:text-white">Poin</th>
                <th class="border border-gray-400 dark:border-gray-600 px-2 py-1">Panjang</th>
                <th class="border border-gray-400 dark:border-gray-600 px-2 py-1">Lebar</th>
                <th class="border border-gray-400 dark:border-gray-600 px-2 py-1">Tebal</th>
                <th class="border border-gray-400 dark:border-gray-600 px-2 py-1">Lembar</th>
                <th class="border border-gray-400 dark:border-gray-600 px-2 py-1 bg-orange-400 dark:bg-orange-600 text-black dark:text-white">m3</th>
            </tr>

            <tr class="bg-orange-400 dark:bg-orange-700 font-bold text-black dark:text-white">
                <td colspan="3" class="border border-gray-400 dark:border-gray-600 px-2 py-1 text-center italic">Total</td>
                <td class="border border-gray-400 dark:border-gray-600 px-2 py-1">42.233</td>
                <td class="border border-gray-400 dark:border-gray-600 px-2 py-1">3</td>
                <td class="border border-gray-400 dark:border-gray-600 px-2 py-1 text-center">1.326</td>
                <td class="border border-gray-400 dark:border-gray-600 px-2 py-1">1.415.148.564</td>
                <td colspan="4" class="border border-gray-400 dark:border-gray-600 px-2 py-1 bg-white dark:bg-gray-800"></td>
                <td class="border border-gray-400 dark:border-gray-600 px-2 py-1">524</td>
                <td class="border border-gray-400 dark:border-gray-600 px-2 py-1 bg-white dark:bg-gray-800 text-center uppercase">Rata-rata</td>
                <td class="border border-gray-400 dark:border-gray-600 px-2 py-1 bg-blue-200 dark:bg-blue-900/50 text-center">60</td>
                <td class="border border-gray-400 dark:border-gray-600 px-2 py-1 bg-green-500 dark:bg-green-700 text-center text-black dark:text-white border-l-2">1.757.246</td>
                <td class="border border-gray-400 dark:border-gray-600 px-2 py-1 bg-white dark:bg-gray-800"></td>
                <td class="border border-gray-400 dark:border-gray-600 px-2 py-1 bg-white dark:bg-gray-800"></td>
                <td class="border border-gray-400 dark:border-gray-600 px-2 py-1 text-center">1.787.490</td>
                <td class="border border-gray-400 dark:border-gray-600 px-2 py-1 bg-white dark:bg-gray-800"></td>
                <td class="border border-gray-400 dark:border-gray-600 px-2 py-1 text-center">1.796.529</td>
            </tr>
        </thead>
        <tbody>
            <tr class="border border-gray-400 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                <td class="border border-gray-400 dark:border-gray-600 px-2 py-1 text-xs">
                    31/12/2025<br>02/01/2026
                </td>
                <td class="border border-gray-400 dark:border-gray-600 px-2 py-1 text-center text-green-600 dark:text-green-400 font-bold">✓</td>
                <td class="border border-gray-400 dark:border-gray-600 px-2 py-1 text-center font-bold">K</td>
                <td class="border border-gray-400 dark:border-gray-600 px-2 py-1 text-center">406</td>
                <td class="border border-gray-400 dark:border-gray-600 px-2 py-1"></td>
                <td class="border border-gray-400 dark:border-gray-600 px-2 py-1 bg-blue-100 dark:bg-blue-900/30">12,6173</td>
                <td class="border border-gray-400 dark:border-gray-600 px-2 py-1 bg-blue-100 dark:bg-blue-900/30">13.504.075</td>
                <td colspan="5" class="p-0 border border-gray-400 dark:border-gray-600">
                    <table class="w-full h-full border-none text-gray-900 dark:text-gray-100">
                        <tr class="border-b border-gray-400 dark:border-gray-600">
                            <td class="px-1 py-1 border-r border-gray-400 dark:border-gray-600 w-10 text-center">122</td>
                            <td class="px-1 py-1 border-r border-gray-400 dark:border-gray-600 w-10 text-center">244</td>
                            <td class="px-1 py-1 border-r border-gray-400 dark:border-gray-600 w-10 text-center">3,7</td>
                            <td class="px-1 py-1 border-r border-gray-400 dark:border-gray-600 w-12 text-center">368</td>
                            <td class="px-1 py-1 bg-blue-100 dark:bg-blue-900/30 text-center">4,0532</td>
                        </tr>
                        <tr>
                            <td class="px-1 py-1 border-r border-gray-400 dark:border-gray-600 text-center">122</td>
                            <td class="px-1 py-1 border-r border-gray-400 dark:border-gray-600 text-center">244</td>
                            <td class="px-1 py-1 border-r border-gray-400 dark:border-gray-600 text-center">3,7</td>
                            <td class="px-1 py-1 border-r border-gray-400 dark:border-gray-600 text-center">307</td>
                            <td class="px-1 py-1 bg-blue-100 dark:bg-blue-900/30 text-center">3,3813</td>
                        </tr>
                    </table>
                </td>
                <td class="border border-gray-400 dark:border-gray-600 px-1 py-1 text-center text-xs italic">14:30-17:00<br>06:00-07:30</td>
                <td class="border border-gray-400 dark:border-gray-600 px-2 py-1 bg-blue-100 dark:bg-blue-900/30 text-center">59</td>
                <td class="border border-gray-400 dark:border-gray-600 px-2 py-1 bg-green-500 dark:bg-green-600/50 text-center font-bold">1.816.392</td>
                <td class="border border-gray-400 dark:border-gray-600 px-2 py-1 bg-blue-100 dark:bg-blue-900/30 text-center">4</td>
                <td class="border border-gray-400 dark:border-gray-600 px-2 py-1 bg-orange-100 dark:bg-orange-900/20 text-center text-xs">204.448</td>
                <td class="border border-gray-400 dark:border-gray-600 px-2 py-1 bg-orange-400 dark:bg-orange-600/50 text-center font-bold">1.843.892</td>
                <td class="border border-gray-400 dark:border-gray-600 px-2 py-1 bg-blue-100 dark:bg-blue-900/30 text-center">75.556</td>
                <td class="border border-gray-400 dark:border-gray-600 px-2 py-1 bg-yellow-300 dark:bg-yellow-600/50 text-center font-bold">1.854.055</td>
            </tr>
        </tbody>
    </table>
</div>
</x-filament::page>

