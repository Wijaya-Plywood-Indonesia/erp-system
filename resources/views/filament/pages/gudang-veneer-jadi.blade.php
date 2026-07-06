<x-filament-panels::page>
    <div class="space-y-6 font-mono">

        {{-- TAB NAVIGATION SWITCH GAYA INDUSTRIAL --}}
        <div class="flex border-b border-zinc-200 dark:border-zinc-800 bg-transparent">
            <button
                type="button"
                wire:click="$set('activeTab', 'masuk')"
                class="flex-1 sm:flex-initial px-6 py-3 text-xs sm:text-sm font-black uppercase tracking-widest border-t-2 transition-all flex items-center justify-center gap-2 {{ $activeTab === 'masuk' ? 'border-amber-500 text-amber-500 bg-amber-500/5' : 'border-transparent text-zinc-400 hover:text-zinc-200' }}">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 14l-7 7m0 0l-7-7m7 7V3" />
                </svg>
                <span>Serah Terima Veneer</span>
            </button>
            <button
                type="button"
                wire:click="$set('activeTab', 'keluar')"
                class="flex-1 sm:flex-initial px-6 py-3 text-xs sm:text-sm font-black uppercase tracking-widest border-t-2 transition-all flex items-center justify-center gap-2 {{ $activeTab === 'keluar' ? 'border-amber-500 text-amber-500 bg-amber-500/5' : 'border-transparent text-zinc-400 hover:text-zinc-200' }}">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 10l7-7m0 0l7 7m-7-7v18" />
                </svg>
                <span>Veneer Keluar</span>
            </button>
        </div>

        {{-- SECTION 1: DETAIL SALDO STOK UTAMA (KLASIFIKASI VENEER JADI) --}}
        <section class="space-y-3">
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
                <div>
                    <h2 class="text-xs font-black uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                        DETAIL STOK VENEER JADI
                    </h2>
                </div>

                {{-- Input Pencarian Livewire untuk Stok Utama --}}
                <div class="relative w-full sm:w-64">
                    <span class="absolute inset-y-0 left-0 pl-2.5 flex items-center pointer-events-none">
                        <svg class="w-3.5 h-3.5 text-zinc-400 dark:text-zinc-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </span>
                    <input
                        type="text"
                        placeholder="Cari Stok..."
                        wire:model.live.debounce.300ms="searchQuery"
                        class="w-full text-xs pl-8 pr-3 py-1.5 border rounded-none focus:outline-none focus:ring-1 focus:ring-amber-500 bg-white border-zinc-300 text-zinc-900 placeholder-zinc-400 dark:bg-zinc-950 dark:border-zinc-800 dark:text-zinc-100 dark:placeholder-zinc-600" />
                </div>
            </div>

            {{-- DUA KOLOM UTAMA: FACEBACK & CORE (DENGAN OVERFLOW SCROLL JIKA > 3 DATA) --}}
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">

                {{-- KOLOM KEBELAH KIRI: FACEBACK (< 1.0 mm) --}}
                <div class="border p-4 rounded-none bg-white border-zinc-200 shadow-sm dark:bg-zinc-900/40 dark:border-zinc-800 dark:shadow-none">
                    <div class="border-b border-zinc-200 dark:border-zinc-800 pb-2 mb-3 flex justify-between items-center">
                        <span class="text-xs font-black uppercase tracking-widest text-amber-600 dark:text-amber-500 flex items-center gap-1.5">
                            <span class="inline-block w-1.5 h-1.5 bg-amber-600 dark:bg-amber-500"></span>
                            Kategori: Faceback
                        </span>
                        <span class="text-[10px] text-zinc-500 dark:text-zinc-400">
                            {{ count($this->splitStok['faceback']) }} Item terdaftar
                        </span>
                    </div>

                    <div class="space-y-2 max-h-[135px] overflow-y-auto pr-1">
                        @forelse($this->splitStok['faceback'] as $item)
                        <div class="p-2 border flex justify-between items-center text-sm transition-all bg-zinc-50 border-zinc-200 dark:bg-zinc-950/70 dark:border-zinc-900 dark:hover:border-zinc-800 hover:border-zinc-300">
                            <div class="space-y-0.5">
                                <p class="font-bold text-zinc-800 dark:text-zinc-100">
                                    {{ ($item->panjang + 0) }}x{{ ($item->lebar + 0) }}x{{ ($item->tebal + 0) }}
                                    <span class="mx-2 text-zinc-300 dark:text-zinc-800">|</span>
                                    <span class="text-amber-600 dark:text-amber-500 font-bold">KW {{ $item->kw_grade }}</span>
                                    <span class="mx-2 text-zinc-300 dark:text-zinc-800">|</span>
                                    <span class="text-zinc-500 dark:text-zinc-400 text-xs">{{ $item->jenisKayu->nama_kayu ?? 'Sengon' }}</span>
                                </p>
                            </div>
                            <div class="text-right flex-shrink-0 ml-4">
                                <span class="bg-amber-500 border border-amber-400 text-zinc-950 text-sm font-bold px-3 py-0.5 rounded-none shadow-sm whitespace-nowrap">
                                    {{ number_format($item->stok_lembar, 0, ',', '.') }} lbr
                                </span>
                            </div>
                        </div>
                        @empty
                        <p class="text-sm text-zinc-400 dark:text-zinc-500 italic py-4 text-center">Tidak ada saldo Faceback</p>
                        @endforelse
                    </div>
                </div>

                {{-- KOLOM KEBELAH KANAN: CORE (>= 1.0 mm) --}}
                <div class="border p-4 rounded-none bg-white border-zinc-200 shadow-sm dark:bg-zinc-900/40 dark:border-zinc-800 dark:shadow-none">
                    <div class="border-b border-zinc-200 dark:border-zinc-800 pb-2 mb-3 flex justify-between items-center">
                        <span class="text-xs font-black uppercase tracking-widest text-amber-600 dark:text-amber-500 flex items-center gap-1.5">
                            <span class="inline-block w-1.5 h-1.5 bg-amber-600 dark:bg-amber-500"></span>
                            Kategori: Core
                        </span>
                        <span class="text-[10px] text-zinc-500 dark:text-zinc-400">
                            {{ count($this->splitStok['core']) }} Item terdaftar
                        </span>
                    </div>

                    <div class="space-y-2 max-h-[135px] overflow-y-auto pr-1">
                        @forelse($this->splitStok['core'] as $item)
                        <div class="p-2 border flex justify-between items-center text-sm transition-all bg-zinc-50 border-zinc-200 dark:bg-zinc-950/70 dark:border-zinc-900 dark:hover:border-zinc-800 hover:border-zinc-300">
                            <div class="space-y-0.5">
                                <p class="font-bold text-zinc-800 dark:text-zinc-100">
                                    {{ ($item->panjang + 0) }}x{{ ($item->lebar + 0) }}x{{ ($item->tebal + 0) }}
                                    <span class="mx-2 text-zinc-300 dark:text-zinc-800">|</span>
                                    <span class="text-amber-600 dark:text-amber-500 font-bold">KW {{ $item->kw_grade }}</span>
                                    <span class="mx-2 text-zinc-300 dark:text-zinc-800">|</span>
                                    <span class="text-zinc-500 dark:text-zinc-400 text-xs">{{ $item->jenisKayu->nama_kayu ?? 'Sengon' }}</span>
                                </p>
                            </div>
                            <div class="text-right flex-shrink-0 ml-4"> {{-- 🛠️ Menolak ciut & beri jarak aman dari teks kiri --}}
                                <span class="bg-amber-500 border border-amber-400 text-zinc-950 text-sm font-bold px-3 py-0.5 rounded-none shadow-sm whitespace-nowrap"> {{-- 🛠️ Mengunci teks agar anti-wrap --}}
                                    {{ number_format($item->stok_lembar, 0, ',', '.') }} lbr
                                </span>
                            </div>
                        </div>
                        @empty
                        <p class="text-sm text-zinc-400 dark:text-zinc-500 italic py-4 text-center">Tidak ada saldo Core</p>
                        @endforelse
                    </div>
                </div>

            </div>
        </section>

        {{-- SECTION 2: MEJA SERAH TERIMA BARANG (DIVISI REPAIR) --}}
        @if($activeTab === 'masuk')
        <section class="space-y-3">
            <div class="flex flex-col md:flex-row md:items-end justify-between gap-3 border-b border-zinc-200 dark:border-zinc-800 pb-2">
                <div>
                    <h2 class="text-xs font-black uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                        SERAH TERIMA VENEER JADI
                    </h2>
                </div>

                {{-- Kolom Pencarian Tabel Livewire --}}
                <div class="flex items-center gap-2 self-stretch md:self-auto justify-end">
                    <div class="relative w-full sm:w-56">
                        <span class="absolute inset-y-0 left-0 pl-2.5 flex items-center pointer-events-none">
                            <svg class="w-3.5 h-3.5 text-zinc-400 dark:text-zinc-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                        </span>
                        <input
                            type="text"
                            placeholder="Cari antrean..."
                            wire:model.live.debounce.300ms="tableSearchQuery"
                            class="text-xs pl-8 pr-3 py-1.5 border rounded-none focus:outline-none focus:ring-1 focus:ring-amber-500 bg-white border-zinc-300 text-zinc-900 placeholder-zinc-400 dark:bg-zinc-950 dark:border-zinc-800 dark:text-zinc-100 dark:placeholder-zinc-600 w-full" />
                    </div>
                </div>
            </div>

            {{-- 📱 VIEW HP / MOBILE (Hanya muncul di md:hidden atau layar < 768px) --}}
            <div class="block md:hidden space-y-3 max-h-[500px] overflow-y-auto pr-1">
                @forelse($this->antreanFiltered as $item)
                @php
                $volumeM3 = $this->hitungKubikasi($item['panjang'], $item['lebar'], $item['tebal'], $item['jumlah']);
                @endphp
                <div class="border p-4 space-y-3 bg-white border-zinc-200 dark:bg-zinc-950 dark:border-zinc-800 shadow-sm transition-all {{ $item['status_gudang'] !== 'belum diterima' ? 'opacity-70 bg-zinc-50/50 dark:bg-zinc-900/20' : '' }}">
                    <div class="flex justify-between items-start gap-2">
                        <div>
                            <h4 class="text-base font-bold text-zinc-900 dark:text-zinc-100 leading-tight">
                                {{ $item['jenis_kayu'] }}
                            </h4>
                            <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-1">
                                Ukuran: <span class="font-bold text-zinc-800 dark:text-zinc-200">{{ ($item['panjang'] + 0) }}x{{ ($item['lebar'] + 0) }}x{{ ($item['tebal'] + 0) }}</span>
                            </p>
                        </div>
                        <span class="inline-block border border-amber-400 bg-amber-500 text-zinc-950 font-black text-xs px-2 py-0.5 rounded-none shadow-sm">
                            KW {{ $item['kw'] }}
                        </span>
                    </div>

                    <div class="grid grid-cols-2 gap-2 text-xs border-t border-b border-zinc-100 dark:border-zinc-900 py-2">
                        <div>
                            <p class="text-zinc-400">Total Stok:</p>
                            <p class="font-black text-sm text-zinc-800 dark:text-zinc-200">{{ number_format($item['jumlah'], 0, ',', '.') }} lbr</p>
                        </div>
                        <div class="text-right">
                            <p class="text-zinc-400">Volume:</p>
                            <p class="font-bold text-sm text-zinc-500 dark:text-zinc-400">{{ number_format($volumeM3, 4, '.', '') }}</p>
                        </div>
                    </div>

                    <div class="text-xs space-y-1">
                        <p class="text-zinc-600 dark:text-zinc-400 truncate"><span class="text-zinc-400">Ket:</span> {{ $item['keterangan'] ?? '-' }}</p>
                        @if(!empty($item['diterima_by']))
                        <p class="text-[11px] text-zinc-500 dark:text-zinc-500">
                            <span class="text-zinc-400">Penerima:</span> {{ $item['penerima_name'] }} ({{ \Carbon\Carbon::parse($item['diterima_at'])->format('d/m H:i') }})
                        </p>
                        @endif
                    </div>

                    <div class="flex justify-between items-center pt-1 gap-2">
                        <div>
                            @if(($item['status_gudang'] ?? 'belum diterima') === 'belum diterima')
                            <span class="inline-block border border-zinc-300 bg-zinc-100 text-zinc-700 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-300 text-[10px] uppercase px-2 py-0.5 rounded-none font-bold">
                                Belum Diterima
                            </span>
                            @else
                            <span class="inline-block border border-emerald-500 bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 text-[10px] uppercase px-2 py-0.5 rounded-none font-black">
                                Sudah Diterima
                            </span>
                            @endif
                        </div>

                        <div>
                            @if(($item['status_gudang'] ?? 'belum diterima') === 'belum diterima')
                            <button
                                type="button"
                                wire:click="confirmTerima('{{ $item['id'] }}')"
                                wire:loading.attr="disabled"
                                class="inline-flex items-center gap-1 border border-amber-400 bg-amber-500 hover:bg-amber-600 text-zinc-950 transition-all text-xs font-black uppercase px-4 py-1.5 rounded-none shadow-sm active:scale-95 w-full justify-center">
                                <span>Terima</span>
                            </button>
                            @else
                            <span class="text-sm font-bold text-emerald-600 dark:text-emerald-400 flex items-center gap-1">
                                ✓ DONE
                            </span>
                            @endif
                        </div>
                    </div>
                </div>
                @empty
                <div class="border p-8 text-center text-zinc-400 dark:text-zinc-500 italic bg-white dark:bg-zinc-950 border-zinc-200 dark:border-zinc-800">
                    Tidak ada antrean kiriman aktif saat ini.
                </div>
                @endforelse
            </div>

            {{-- 🖥️ VIEW TABLE DESKTOP (Otomatis tersembunyi di HP lewat class 'hidden md:block') --}}
            <div class="hidden md:block border overflow-x-auto max-h-[450px] overflow-y-auto rounded-none bg-white border-zinc-200 shadow-sm dark:bg-zinc-950 dark:border-zinc-800 dark:shadow-none">
                <table class="w-full text-left border-collapse text-sm">
                    <thead class="sticky top-0 z-10 bg-zinc-50 text-zinc-500 dark:bg-zinc-900 dark:text-zinc-400 text-[11px] uppercase font-bold border-b border-zinc-200 dark:border-zinc-800">
                        <tr>
                            <th class="py-3 px-4 text-center w-24">Aksi</th>
                            <th class="py-3 px-4">Jenis Kayu</th>
                            <th class="py-3 px-3 text-right">Panjang</th>
                            <th class="py-3 px-3 text-right">Lebar</th>
                            <th class="py-3 px-3 text-right">Tebal</th>
                            <th class="py-3 px-3 text-center">Grade</th>
                            <th class="py-3 px-4 text-center">Stok</th>
                            <th class="py-3 px-4 text-right">Kubikasi</th>
                            <th class="py-3 px-4 text-center">Status</th>
                            <th class="py-3 px-4">Penerima</th>
                            <th class="py-3 px-4">Keterangan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                        @forelse($this->antreanFiltered as $item)
                        @php
                        $volumeM3 = $this->hitungKubikasi($item['panjang'], $item['lebar'], $item['tebal'], $item['jumlah']);
                        @endphp
                        <tr class="hover:bg-zinc-50/80 dark:hover:bg-zinc-900/40 transition-colors text-zinc-800 dark:text-zinc-100">
                            <td class="py-3 px-4 text-center">
                                @if(($item['status_gudang'] ?? 'belum diterima') === 'belum diterima')
                                <button
                                    type="button"
                                    wire:click="confirmTerima('{{ $item['id'] }}')"
                                    wire:loading.attr="disabled"
                                    class="inline-flex items-center gap-1 border border-amber-400 bg-amber-500 hover:bg-amber-600 text-zinc-950 transition-all text-[11px] font-black uppercase px-3 py-1 rounded-none shadow-sm active:scale-95">
                                    <span>Terima</span>
                                </button>
                                @else
                                <span class="text-sm font-bold text-emerald-600 dark:text-emerald-400 flex items-center justify-center gap-1">
                                    ✓ DONE
                                </span>
                                @endif
                            </td>
                            <td class="py-3 px-4 font-bold text-base">{{ $item['jenis_kayu'] }}</td>
                            <td class="py-3 px-3 text-right text-sm text-zinc-600 dark:text-zinc-300">{{ ($item['panjang'] + 0) }}</td>
                            <td class="py-3 px-3 text-right text-sm text-zinc-600 dark:text-zinc-300">{{ ($item['lebar'] + 0) }}</td>
                            <td class="py-3 px-3 text-right text-sm text-amber-600 dark:text-amber-500 font-bold">{{ ($item['tebal'] + 0) }}</td>
                            <td class="py-3 px-3 text-center">
                                <span class="inline-block border border-amber-400 bg-amber-500 text-zinc-950 font-black text-xs px-2.5 py-0.5 rounded-none shadow-sm">
                                    {{ $item['kw'] }}
                                </span>
                            </td>
                            <td class="py-3 px-4 text-center">
                                <span class="inline-block border border-amber-400 bg-amber-500 text-zinc-950 font-bold text-sm px-3 py-0.5 rounded-none shadow-sm">
                                    {{ number_format($item['jumlah'], 0, ',', '.') }}
                                </span>
                            </td>
                            <td class="py-3 px-4 text-right font-bold text-zinc-500 dark:text-zinc-400 text-sm">
                                {{ number_format($volumeM3, 4, '.', '') }}
                            </td>
                            <td class="py-3 px-4 text-center whitespace-nowrap">
                                @if(($item['status_gudang'] ?? 'belum diterima') === 'belum diterima')
                                <span class="inline-block border border-zinc-300 bg-zinc-100 text-zinc-700 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-300 text-xs uppercase px-2 py-0.5 rounded-none font-bold">
                                    Belum Diterima
                                </span>
                                @else
                                <span class="inline-block border border-emerald-500 bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 text-xs uppercase px-2 py-0.5 rounded-none font-black">
                                    Sudah Diterima
                                </span>
                                @endif
                            </td>
                            <td class="py-3 px-4 text-xs leading-tight whitespace-nowrap">
                                @if(!empty($item['diterima_by']))
                                <p class="font-bold text-zinc-800 dark:text-zinc-200">
                                    {{ $item['penerima_name'] ?? 'User ID: '.$item['diterima_by'] }}
                                </p>
                                <p class="text-[12px] text-zinc-400 dark:text-zinc-500">
                                    {{ $item['diterima_at'] ? \Carbon\Carbon::parse($item['diterima_at'])->format('d/m H:i') : '-' }}
                                </p>
                                @else
                                <span class="text-zinc-400 dark:text-zinc-600 italic">-</span>
                                @endif
                            </td>
                            <td class="py-3 px-4 text-xs text-zinc-600 dark:text-zinc-400 max-w-xs truncate wrap-normal">
                                {{ $item['keterangan'] ?? 'Tanpa keterangan' }}
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colSpan="11" class="py-8 text-center text-zinc-400 dark:text-zinc-500 italic">
                                Tidak ada antrean kiriman aktif saat ini.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

        </section>
        @else
        {{-- TAB 2: KONTEN PENGELUARAN VENEER (OUT) --}}
        <section class="space-y-3">
            <div class="flex flex-col md:flex-row md:items-end justify-between gap-3 border-b border-zinc-200 dark:border-zinc-800 pb-2">
                <div class="flex flex-col sm:flex-row sm:items-center gap-3">
                    <h2 class="text-xs font-black uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                        MUTASI KELUAR VENEER JADI
                    </h2>
                    <button
                        type="button"
                        wire:click="$set('showFormKeluarModal', true)"
                        class="inline-flex items-center gap-1.5 border border-amber-400 bg-amber-500 hover:bg-amber-600 text-zinc-950 transition-all text-xs font-black uppercase px-3 py-1.5 rounded-none shadow-sm active:scale-95 w-fit">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4" />
                        </svg>
                        <span>Catat Barang Keluar</span>
                    </button>
                </div>

                <div class="relative w-full sm:w-56">
                    <span class="absolute inset-y-0 left-0 pl-2.5 flex items-center pointer-events-none">
                        <svg class="w-3.5 h-3.5 text-zinc-400 dark:text-zinc-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </span>
                    <input
                        type="text"
                        placeholder="Cari riwayat keluar..."
                        wire:model.live.debounce.300ms="keluarSearchQuery"
                        class="w-full text-xs pl-8 pr-3 py-1.5 border rounded-none focus:outline-none focus:ring-1 focus:ring-amber-500 bg-white border-zinc-300 text-zinc-900 placeholder-zinc-400 dark:bg-zinc-950 dark:border-zinc-800 dark:text-zinc-100 dark:placeholder-zinc-600" />
                </div>
            </div>

            {{-- 📱 VIEW HP / MOBILE (MUTASI KELUAR) --}}
            <div class="block md:hidden space-y-3 max-h-[500px] overflow-y-auto pr-1">
                @forelse($this->riwayatKeluarFiltered as $item)
                <div class="border p-4 space-y-3 bg-white border-zinc-200 dark:bg-zinc-950 dark:border-zinc-800 shadow-sm transition-all">
                    <div class="flex justify-between items-start gap-2">
                        <div>
                            <h4 class="text-base font-bold text-zinc-900 dark:text-zinc-100 leading-tight">
                                {{ $item['jenis_kayu'] }}
                            </h4>
                            <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-1">
                                Ukuran: <span class="font-bold text-zinc-800 dark:text-zinc-200">{{ ($item['panjang'] + 0) }}x{{ ($item['lebar'] + 0) }}x{{ ($item['tebal'] + 0) }}</span>
                            </p>
                        </div>
                        <span class="inline-block border border-zinc-300 bg-zinc-100 text-zinc-700 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-300 font-black text-xs px-2 py-0.5 rounded-none shadow-sm">
                            KW {{ $item['kw'] }}
                        </span>
                    </div>

                    <div class="grid grid-cols-2 gap-2 text-xs border-t border-b border-zinc-100 dark:border-zinc-900 py-2">
                        <div>
                            <p class="text-zinc-400">Jumlah Keluar:</p>
                            <span class="text-sm font-bold text-red-500 dark:text-red-400 font-mono">
                                -{{ number_format($item['stok_lembar'] ?? 0, 0, ',', '.') }} lbr
                            </span>
                            <p class="text-[10px] text-zinc-400 font-bold mt-0.5">({{ $item['jumlah_palet'] }} plt)</p>
                        </div>
                        <div class="text-right">
                            <p class="text-zinc-400">Volume (m³):</p>
                            <p class="font-bold text-sm text-zinc-500 dark:text-zinc-400 font-mono">{{ number_format($item['stok_kubikasi'] ?? 0, 4, '.', '') }}</p>
                        </div>
                    </div>

                    {{-- Rincian lembar per palet --}}
                    @if(!empty($item['rincian_palet']))
                    <div class="flex flex-wrap gap-1 items-center py-1">
                        @foreach($item['rincian_palet'] as $idx => $qty)
                        <span class="text-[10px] font-mono px-1.5 py-0.5 bg-zinc-100 text-zinc-600 dark:bg-zinc-900 dark:text-zinc-400 border border-zinc-200 dark:border-zinc-800 rounded">
                            P{{ $idx + 1 }}: <strong class="text-zinc-800 dark:text-zinc-200">{{ $qty }}</strong>
                        </span>
                        @endforeach
                    </div>
                    @endif

                    <div class="flex justify-between items-center text-xs pt-1 border-t border-zinc-100 dark:border-zinc-900">
                        <span class="px-2 py-0.5 text-[10px] font-semibold rounded uppercase bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20">
                            {{ $item['tujuan'] }}
                        </span>
                        <span class="text-[11px] text-zinc-400">By: {{ $item['dikeluarkan_by'] }}</span>
                    </div>
                </div>
                @empty
                <div class="border p-8 text-center text-zinc-400 dark:text-zinc-500 italic bg-white dark:bg-zinc-950 border-zinc-200 dark:border-zinc-800">Belum ada riwayat pengeluaran yang terdaftar.</div>
                @endforelse
            </div>

            {{-- 🖥️ DESKTOP VIEW TABLE (MUTASI KELUAR - ACCORDION FULL ROW) --}}
            <div class="hidden md:block border overflow-x-auto max-h-[450px] overflow-y-auto rounded-none bg-white border-zinc-200 shadow-sm dark:bg-zinc-950 dark:border-zinc-800 dark:shadow-none">
                <table class="w-full text-left border-collapse text-sm">
                    <thead class="sticky top-0 z-10 bg-zinc-50 text-zinc-500 dark:bg-zinc-900 dark:text-zinc-400 text-[11px] uppercase font-bold border-b border-zinc-200 dark:border-zinc-800">
                        <tr>
                            <th class="py-3 px-4">Waktu Keluar</th>
                            <th class="py-3 px-4">Jenis Kayu</th>
                            <th class="py-3 px-3 text-center">Panjang</th>
                            <th class="py-3 px-3 text-center">Lebar</th>
                            <th class="py-3 px-3 text-center">Tebal</th>
                            <th class="py-3 px-3 text-center">Grade</th>
                            <th class="py-3 px-4">Jumlah Keluar</th>
                            <th class="py-3 px-4 text-right">Volume</th>
                            <th class="py-3 px-4 text-center">Tujuan</th>
                            <th class="py-3 px-4">Operator</th>
                            <th class="py-3 px-4">Keterangan</th>
                        </tr>
                    </thead>

                    @forelse($this->riwayatKeluarFiltered as $item)
                    <tbody x-data="{ open: false }" class="divide-y divide-zinc-200 dark:divide-zinc-800 text-zinc-800 dark:text-zinc-300 border-b border-zinc-200 dark:border-zinc-800">

                        {{-- BARIS UTAMA: seluruh baris jadi trigger accordion --}}
                        <tr @click="open = !open" class="cursor-pointer hover:bg-zinc-50/80 dark:hover:bg-zinc-900/40 transition-colors">
                            <td class="py-3 px-4 text-xs text-zinc-500 whitespace-nowrap">
                                <span class="inline-flex items-center gap-2">
                                    <svg class="w-3.5 h-3.5 text-zinc-400 dark:text-zinc-500 transition-transform duration-150 flex-shrink-0"
                                        :class="{ 'rotate-90': open }"
                                        fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7" />
                                    </svg>
                                    {{ $item['created_at'] }}
                                </span>
                            </td>
                            <td class="py-3 px-4 font-bold text-zinc-900 dark:text-white text-base">{{ $item['jenis_kayu'] }}</td>
                            <td class="py-3 px-3 text-center text-sm text-zinc-600 dark:text-zinc-300 font-mono">{{ ($item['panjang'] + 0) }}</td>
                            <td class="py-3 px-3 text-center text-sm text-zinc-600 dark:text-zinc-300 font-mono">{{ ($item['lebar'] + 0) }}</td>
                            <td class="py-3 px-3 text-center text-sm text-amber-600 dark:text-amber-500 font-bold font-mono">{{ ($item['tebal'] + 0) }}</td>
                            <td class="py-3 px-3 text-center">
                                <span class="inline-block border border-zinc-300 bg-zinc-100 text-zinc-700 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-300 font-black text-xs px-2.5 py-0.5 rounded-none shadow-sm">KW {{ $item['kw'] }}</span>
                            </td>

                            {{-- Ringkasan saja, rincian per palet dipindah ke baris accordion --}}
                            <td class="py-3 px-4">
                                <div class="flex items-center gap-1.5">
                                    <span class="text-sm font-bold font-mono text-red-500 dark:text-red-400">
                                        -{{ number_format(($item['stok_lembar'] ?? 0), 0, ',', '.') }}
                                    </span>
                                    <span class="text-[10px] text-zinc-400">lbr</span>
                                    <span class="text-xs px-1.5 py-0.5 bg-zinc-100 dark:bg-zinc-800 text-zinc-800 dark:text-zinc-200 border border-zinc-300 dark:border-zinc-700 rounded font-medium">
                                        {{ $item['jumlah_palet'] }} plt
                                    </span>
                                </div>
                            </td>

                            <td class="py-3 px-4 text-right font-bold text-zinc-500 dark:text-zinc-400 text-sm font-mono">{{ number_format(($item['stok_kubikasi'] ?? 0), 4, '.', '') }}</td>
                            <td class="py-3 px-4 text-center whitespace-nowrap">
                                <span class="px-2.5 py-0.5 text-xs font-semibold rounded uppercase tracking-wider bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20">
                                    {{ $item['tujuan'] }}
                                </span>
                            </td>
                            <td class="py-3 px-4 text-xs leading-tight whitespace-nowrap">
                                <p class="font-bold text-zinc-800 dark:text-zinc-200">{{ $item['dikeluarkan_by'] }}</p>
                            </td>
                            <td class="py-3 px-4 text-xs text-zinc-600 dark:text-zinc-400 max-w-xs truncate">{{ $item['keterangan'] ?? '-' }}</td>
                        </tr>

                        {{-- BARIS ACCORDION: rincian per palet dalam bentuk badge --}}
                        @if(!empty($item['rincian_palet']))
                        <tr x-show="open" x-cloak class="bg-zinc-50/60 dark:bg-zinc-900/40">
                            <td colspan="11" class="py-3 px-4 pl-12">
                                <div class="flex flex-wrap gap-2">
                                    @foreach($item['rincian_palet'] as $idx => $qty)
                                    <span class="inline-flex items-center gap-1.5 text-xs font-mono px-2.5 py-1.5 bg-zinc-100 dark:bg-zinc-900 text-zinc-500 dark:text-zinc-400 border border-zinc-200 dark:border-zinc-800 rounded">
                                        P{{ $idx + 1 }}: <strong class="text-zinc-800 dark:text-zinc-100">{{ $qty }}</strong>
                                    </span>
                                    @endforeach
                                </div>
                            </td>
                        </tr>
                        @endif
                    </tbody>
                    @empty
                    <tbody>
                        <tr>
                            <td colSpan="11" class="py-8 text-center text-zinc-400 italic">Belum ada riwayat pengeluaran yang terdaftar.</td>
                        </tr>
                    </tbody>
                    @endforelse
                </table>
            </div>
        </section>
        @endif

    </div>

    @if($showConfirmModal)
    @php
    // Ambil rincian spesifikasi barang terpilih langsung dari computed property
    $selectedItem = collect($this->antreanFiltered)->firstWhere('id', $selectedItemId);
    @endphp
    @if($selectedItem)
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-zinc-950/80 backdrop-blur-sm">
        <div class="w-full max-w-md border bg-zinc-950 border-zinc-800 rounded-none shadow-2xl p-6 font-mono text-zinc-100">
            <div class="flex items-start gap-4">
                <div class="space-y-2 flex-1">
                    <h3 class="text-base sm:text-lg font-black uppercase text-zinc-50 tracking-wider">
                        KONFIRMASI TERIMA BARANG
                    </h3>
                    <p class="text-sm sm:text-base text-zinc-400 leading-relaxed">
                        Apakah Anda yakin ingin memverifikasi data ini? Jumlah lembar akan diakumulasikan ke stok veneer jadi.

                        {{-- RETRO KEY-VALUE DETAILS BLOCK (Rincian data yang akan diserahkan) --}}
                    <div class="mt-4 p-4 border border-zinc-800 bg-zinc-900/40 text-xs sm:text-sm space-y-2">
                        <div class="flex justify-between items-center border-b border-zinc-900 pb-2">
                            <span class="text-zinc-500 uppercase font-bold text-[12px] tracking-wider">Jenis Kayu:</span>
                            <span class="font-black text-zinc-200 text-sm">{{ $selectedItem['jenis_kayu'] }}</span>
                        </div>
                        <div class="flex justify-between items-center border-b border-zinc-900 pb-2">
                            <span class="text-zinc-500 uppercase font-bold text-[12px] tracking-wider">Ukuran Dimensi:</span>
                            <span class="font-black text-zinc-200">
                                {{ ($selectedItem['panjang'] + 0) }}x{{ ($selectedItem['lebar'] + 0) }}x{{ ($selectedItem['tebal'] + 0) }}
                            </span>
                        </div>
                        <div class="flex justify-between items-center border-b border-zinc-900 pb-2">
                            <span class="text-zinc-500 uppercase font-bold text-[12px] tracking-wider">Kualitas (Grade):</span>
                            <span class="inline-block px-2 py-0.5 bg-amber-500 text-zinc-950 font-black text-xs rounded-none">
                                KW {{ $selectedItem['kw'] }}
                            </span>
                        </div>
                        <div class="flex justify-between items-center border-b border-zinc-900 pb-2">
                            <span class="text-zinc-500 uppercase font-bold text-[12px] tracking-wider">Jumlah Lembar:</span>
                            <span class="font-black text-amber-500 text-sm">
                                {{ number_format($selectedItem['jumlah'], 0, ',', '.') }} <span class="text-zinc-500 text-[12px] font-normal">lbr</span>
                            </span>
                        </div>
                        <div class="flex justify-between items-center pt-1">
                            <span class="text-zinc-500 uppercase font-bold text-[12px] tracking-wider">Volume (Kubikasi):</span>
                            <span class="font-black text-emerald-500">
                                {{ number_format($this->hitungKubikasi($selectedItem['panjang'], $selectedItem['lebar'], $selectedItem['tebal'], $selectedItem['jumlah']), 4, '.', '') }} <span class="text-zinc-500 text-[12px] font-normal">m³</span>
                            </span>
                        </div>
                    </div>

                </div>
            </div>

            {{-- Tombol Aksi Kaku Berukuran Proporsional --}}
            <div class="mt-6 flex justify-end gap-3 text-xs sm:text-sm font-black uppercase">
                <button
                    type="button"
                    wire:click="cancelConfirm"
                    class="px-4 py-2 border border-zinc-800 hover:bg-zinc-900 text-zinc-400 hover:text-zinc-200 transition-all rounded-none">
                    Batal
                </button>
                <button
                    type="button"
                    wire:click="terimaBarang"
                    class="px-5 py-2 bg-amber-500 hover:bg-amber-600 border border-amber-400 text-zinc-950 transition-all rounded-none shadow-md">
                    Ya, Terima
                </button>
            </div>
        </div>
    </div>
    @endif
    @endif

    @if($showFormKeluarModal)
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-zinc-950/80 backdrop-blur-sm">
        <div class="w-full max-w-lg border bg-white dark:bg-zinc-950 border-zinc-200 dark:border-zinc-800 rounded-none shadow-2xl p-6 font-mono text-zinc-800 dark:text-zinc-100 max-h-[90vh] overflow-y-auto">

            <h3 class="text-base sm:text-lg font-black uppercase text-amber-500 border-b border-zinc-200 dark:border-zinc-800 pb-2 mb-4 flex items-center gap-2">
                <span class="inline-block w-1.5 h-3.5 bg-amber-500"></span>
                Form Barang Keluar
            </h3>

            <form wire:submit.prevent="prosesKeluar" class="space-y-4 text-xs">

                {{-- 1. PILIH SPESIFIKASI STOK - INTERAKTIF SEARCH DROPDOWN --}}
                <div class="space-y-1.5 relative" x-data="{
                    isDropdownOpen: false,
                    searchTerm: '',
                    selectedStokId: @entangle('selectedStokId'),
                    options: [
                        @foreach($this->splitStok['faceback']->concat($this->splitStok['core']) as $s)
                        {
                            id: '{{ $s->id }}',
                            nama: '{{ $s->jenisKayu->nama_kayu }} (KW {{ $s->kw_grade }}) - Sisa: {{ $s->stok_lembar }} lbr',
                            no: '{{ ($s->panjang+0) }}x{{ ($s->lebar+0) }}x{{ ($s->tebal+0) }}'
                        },
                        @endforeach
                    ],
                    get filteredOptions() {
                        if (this.searchTerm === '') return this.options;
                        return this.options.filter(o => 
                            o.nama.toLowerCase().includes(this.searchTerm.toLowerCase()) || 
                            o.no.toLowerCase().includes(this.searchTerm.toLowerCase())
                        );
                    },
                    selectVeneer(item) {
                        this.selectedStokId = item.id;
                        this.searchTerm = item.no + ' | ' + item.nama;
                        this.isDropdownOpen = false;
                    },
                    clearVeneer() {
                        this.selectedStokId = null;
                        this.searchTerm = '';
                    },
                    init() {
                        let found = this.options.find(o => o.id == this.selectedStokId);
                        if (found) {
                            this.searchTerm = found.no + ' | ' + found.nama;
                        }
                    }
                }" @click.away="isDropdownOpen = false">
                    <label class="text-[11px] font-bold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider block">Pilih Veneer</label>
                    <div class="relative flex items-center">
                        <input type="text" x-model="searchTerm" @focus="isDropdownOpen = true" placeholder="Ketik dimensi ukuran atau KW..."
                            class="w-full px-3 py-2 bg-white dark:bg-zinc-950 border border-zinc-300 dark:border-zinc-800 rounded-none font-bold text-zinc-900 dark:text-zinc-100 outline-none pr-10 focus:border-amber-500 text-sm placeholder:text-zinc-400 dark:placeholder:text-zinc-600 placeholder:font-normal placeholder:text-xs">
                        <button type="button" x-show="searchTerm.length > 0 || selectedStokId" @click="clearVeneer()"
                            class="absolute right-3 text-zinc-400 dark:text-zinc-500 hover:text-red-500 transition-colors">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                    <div x-show="isDropdownOpen" x-cloak class="absolute z-50 w-full mt-1 bg-white dark:bg-zinc-950 border border-zinc-200 dark:border-zinc-800 rounded-none shadow-2xl max-h-48 overflow-y-auto p-1 divide-y divide-zinc-100 dark:divide-zinc-900/60">
                        <template x-for="item in filteredOptions" :key="item.id">
                            <button type="button" @click="selectVeneer(item)" class="w-full text-left px-3 py-2 hover:bg-amber-500 dark:hover:bg-amber-500 hover:text-zinc-950 dark:hover:text-zinc-950 flex flex-col transition-colors group">
                                <span class="text-[11px] text-zinc-500 dark:text-zinc-400 group-hover:text-zinc-900 dark:group-hover:text-zinc-950 font-medium" x-text="item.nama"></span>
                                <span class="font-bold text-zinc-800 dark:text-zinc-200 group-hover:text-zinc-950 dark:group-hover:text-zinc-950 text-xs" x-text="item.no"></span>
                            </button>
                        </template>
                        <div x-show="filteredOptions.length === 0" class="text-zinc-400 dark:text-zinc-600 p-3 text-center italic text-[11px]">Veneer tidak ditemukan</div>
                    </div>
                </div>

                {{-- 2A. JUMLAH PALET --}}
                <div class="space-y-1.5">
                    <label class="text-[11px] font-bold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider block">Jumlah Palet</label>
                    <input
                        type="text" inputmode='numeric'
                        wire:model.live="jumlahPalet"
                        required
                        min="1"
                        class="w-full text-sm p-2 bg-white dark:bg-zinc-950 border border-zinc-300 dark:border-zinc-800 rounded-none text-zinc-900 dark:text-zinc-100 focus:border-amber-500 focus:outline-none font-bold" />
                </div>

                {{-- 2B. DINAMIS INPUT LEMBAR PER-PALET --}}
                <div class="space-y-1.5">
                    <label class="text-[11px] font-bold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider block">Isi per palet</label>
                    <div class="grid grid-cols-2 gap-2.5 max-h-[140px] overflow-y-auto border border-zinc-200 dark:border-zinc-900 bg-zinc-50 dark:bg-zinc-950/30 p-3">
                        @for($i = 0; $i < $jumlahPalet; $i++)
                            <div class="space-y-1">
                            <span class="text-[10px] text-zinc-400 dark:text-zinc-500 font-bold uppercase block">Palet #{{ $i + 1 }}</span>
                            <div class="relative">
                                <input
                                    type="text" inputmode='numeric'
                                    wire:model="paletQuantities.{{ $i }}"
                                    placeholder="Kuantitas"
                                    required
                                    min="1"
                                    class="w-full text-xs p-2 border border-zinc-300 dark:border-zinc-800 rounded-none bg-white dark:bg-zinc-950 text-zinc-900 dark:text-zinc-100 focus:border-amber-500 focus:outline-none" />
                                <span class="absolute right-2.5 top-2 text-[12px] text-zinc-400 dark:text-zinc-500">lbr</span>
                            </div>
                    </div>
                    @endfor
                </div>
        </div>

        {{-- LIVE INDIKATOR TOTAL LEMBAR TERKUMPUL --}}
        @php
        $totalLbr = array_sum(array_map('intval', $paletQuantities));
        @endphp
        @if($totalLbr > 0)
        <div class="p-3 border border-dashed border-amber-500/20 bg-amber-500/5 text-zinc-800 dark:text-zinc-300 flex items-center justify-between">
            <div>
                <p class="text-[10px] text-zinc-500 dark:text-zinc-400 uppercase tracking-widest font-black">Total Akumulasi Lembar</p>
                <p class="text-xs text-zinc-600 dark:text-zinc-400 mt-1">
                    {{ $jumlahPalet }} Palet total: ({{ implode(' + ', array_map(fn($v) => $v ?: 0, $paletQuantities)) }} lbr)
                </p>
            </div>
            <div class="text-right">
                <span class="px-3 py-1 bg-amber-500 text-zinc-950 font-black text-base shadow-sm">
                    {{ number_format($totalLbr, 0, ',', '.') }} Lembar
                </span>
            </div>
        </div>
        @endif

        {{-- 3. TUJUAN (HOTPRESS & JUAL) --}}
        <div class="space-y-1.5">
            <label class="text-[11px] font-bold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider block">Tujuan Keluar</label>
            <select
                wire:model="tujuanKeluar"
                required
                class="w-full text-sm p-2 border border-zinc-300 dark:border-zinc-800 rounded-none bg-white dark:bg-zinc-950 text-zinc-900 dark:text-zinc-100 focus:border-amber-500 focus:outline-none font-bold">
                <option value="Hotpress">Hotpress</option>
                <option value="Jual">Jual</option>
            </select>
        </div>

        {{-- 4. KETERANGAN --}}
        <div class="space-y-1.5">
            <label class="text-[11px] font-bold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider block">Keterangan</label>
            <textarea
                wire:model="keteranganKeluar"
                placeholder="Tulis PO, atau nama mandor lapangan..."
                rows="2"
                class="w-full text-sm p-2.5 border border-zinc-300 dark:border-zinc-800 rounded-none bg-white dark:bg-zinc-950 text-zinc-900 dark:text-zinc-100 focus:border-amber-500 focus:outline-none"></textarea>
        </div>

        {{-- TOMBOL AKSI --}}
        <div class="pt-2 flex justify-end gap-3 text-xs sm:text-sm font-black uppercase">
            <button
                type="button"
                wire:click="$set('showFormKeluarModal', false)"
                class="px-4 py-2 border border-zinc-300 dark:border-zinc-800 hover:bg-zinc-100 dark:hover:bg-zinc-900 text-zinc-500 dark:text-zinc-400 hover:text-zinc-800 dark:hover:text-zinc-200 transition-all rounded-none">
                Batal
            </button>
            <button
                type="submit"
                class="px-5 py-2 bg-amber-500 hover:bg-amber-600 border border-amber-400 text-zinc-950 transition-all rounded-none shadow-md">
                Proses Barang Keluar
            </button>
        </div>

        </form>
    </div>
    </div>
    @endif
</x-filament-panels::page>