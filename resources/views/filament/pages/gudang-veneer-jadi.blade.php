<x-filament-panels::page>
    <div class="space-y-6 font-mono">

        {{-- SECTION 1: DETAIL SALDO STOK UTAMA (KLASIFIKASI VENEER JADI) --}}
        <section class="space-y-3">
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
                <div>
                    <h2 class="text-xs font-black uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                        DETAIL STOK
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
                        placeholder="Cari saldo ukuran/KW..."
                        wire:model.live.debounce.300ms="searchQuery"
                        class="w-full text-[11px] pl-8 pr-3 py-1.5 border rounded-none focus:outline-none focus:ring-1 focus:ring-amber-500 bg-white border-zinc-300 text-zinc-900 placeholder-zinc-400 dark:bg-zinc-950 dark:border-zinc-800 dark:text-zinc-100 dark:placeholder-zinc-600" />
                </div>
            </div>

            {{-- DUA KOLOM UTAMA: FACEBACK & CORE (DENGAN OVERFLOW SCROLL JIKA > 5 DATA) --}}
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

                    {{-- Scrollbar otomatis aktif jika item melebihi 5 data --}}
                    <div class="space-y-2 max-h-[220px] overflow-y-auto pr-1">
                        @forelse($this->splitStok['faceback'] as $item)
                        <div class="p-2 border flex justify-between items-center text-xs transition-all bg-zinc-50 border-zinc-200 dark:bg-zinc-950/70 dark:border-zinc-900 dark:hover:border-zinc-800 hover:border-zinc-300">
                            <div class="space-y-0.5">
                                <p class="font-bold text-zinc-800 dark:text-zinc-100">
                                    {{ $item->panjang }}x{{ $item->lebar }}x{{ $item->tebal }} <span class="text-[10px] text-zinc-400 dark:text-zinc-500">mm</span>
                                    <span class="mx-2 text-zinc-300 dark:text-zinc-800">|</span>
                                    <span class="text-amber-600 dark:text-amber-500 font-bold">KW {{ $item->kw_grade }}</span>
                                    <span class="mx-2 text-zinc-300 dark:text-zinc-800">|</span>
                                    <span class="text-zinc-500 dark:text-zinc-400 text-[10px]">{{ $item->jenisKayu->nama ?? 'Sengon' }}</span>
                                </p>
                            </div>
                            <div class="text-right">
                                <span class="bg-amber-500 border border-amber-400 text-zinc-950 text-[11px] font-bold px-2 py-0.5 rounded-none shadow-sm">
                                    {{ number_format($item->stok_lembar, 0, ',', '.') }} lbr
                                </span>
                            </div>
                        </div>
                        @empty
                        <p class="text-xs text-zinc-400 dark:text-zinc-500 italic py-4 text-center">Tidak ada saldo Faceback</p>
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

                    {{-- Scrollbar otomatis aktif jika item melebihi 5 data --}}
                    <div class="space-y-2 max-h-[220px] overflow-y-auto pr-1">
                        @forelse($this->splitStok['core'] as $item)
                        <div class="p-2 border flex justify-between items-center text-xs transition-all bg-zinc-50 border-zinc-200 dark:bg-zinc-950/70 dark:border-zinc-900 dark:hover:border-zinc-800 hover:border-zinc-300">
                            <div class="space-y-0.5">
                                <p class="font-bold text-zinc-800 dark:text-zinc-100">
                                    {{ $item->panjang }}x{{ $item->lebar }}x{{ $item->tebal }} <span class="text-[10px] text-zinc-400 dark:text-zinc-500">mm</span>
                                    <span class="mx-2 text-zinc-300 dark:text-zinc-800">|</span>
                                    <span class="text-amber-600 dark:text-amber-500 font-bold">KW {{ $item->kw_grade }}</span>
                                    <span class="mx-2 text-zinc-300 dark:text-zinc-800">|</span>
                                    <span class="text-zinc-500 dark:text-zinc-400 text-[10px]">{{ $item->jenisKayu->nama ?? 'Sengon' }}</span>
                                </p>
                            </div>
                            <div class="text-right">
                                <span class="bg-amber-500 border border-amber-400 text-zinc-950 text-[11px] font-bold px-2 py-0.5 rounded-none shadow-sm">
                                    {{ number_format($item->stok_lembar, 0, ',', '.') }} lbr
                                </span>
                            </div>
                        </div>
                        @empty
                        <p class="text-xs text-zinc-400 dark:text-zinc-500 italic py-4 text-center">Tidak ada saldo Core</p>
                        @endforelse
                    </div>
                </div>

            </div>
        </section>

        {{-- SECTION 2: MEJA SERAH TERIMA BARANG (DIVISI REPAIR) --}}
        <section class="space-y-3">
            <div class="flex flex-col md:flex-row md:items-end justify-between gap-3 border-b border-zinc-200 dark:border-zinc-800 pb-2">
                <div>
                    <h2 class="text-xs font-black uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                        SERAH TERIMA
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
                            placeholder="Search antrean..."
                            wire:model.live.debounce.300ms="tableSearchQuery"
                            class="text-[11px] pl-8 pr-3 py-1.5 border rounded-none focus:outline-none focus:ring-1 focus:ring-amber-500 bg-white border-zinc-300 text-zinc-900 placeholder-zinc-400 dark:bg-zinc-950 dark:border-zinc-800 dark:text-zinc-100 dark:placeholder-zinc-600 w-full" />
                    </div>
                </div>
            </div>

            {{-- TABEL SERAH TERIMA --}}
            <div class="border overflow-x-auto rounded-none bg-white border-zinc-200 shadow-sm dark:bg-zinc-950 dark:border-zinc-800 dark:shadow-none">
                <table class="w-full text-left border-collapse text-xs">

                    {{-- Header Tabel --}}
                    {{-- Header Tabel — tambahkan kolom Meja --}}
                    <thead class="bg-zinc-50 text-zinc-500 dark:bg-zinc-900/80 dark:text-zinc-400 text-[10px] uppercase font-bold border-b border-zinc-200 dark:border-zinc-800">
                        <tr>
                            <th class="py-3 px-3 w-8">
                                <input type="checkbox" disabled class="accent-amber-500 opacity-50 cursor-not-allowed" />
                            </th>
                            <th class="py-3 px-4 text-center w-24">Aksi</th>
                            <th class="py-3 px-4">Jenis Kayu</th>
                            <th class="py-3 px-3 text-right">Panjang</th>
                            <th class="py-3 px-3 text-right">Lebar</th>
                            <th class="py-3 px-3 text-right">Tebal</th>
                            <th class="py-3 px-3 text-center">Grade</th>
                            <th class="py-3 px-4 text-center">Stok (Lembar)</th>
                            <th class="py-3 px-4 text-right">Stok (m³)</th>
                        </tr>
                    </thead>

                    {{-- Body Tabel --}}
                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                        @forelse($this->antreanFiltered as $item)
                        @php
                        $volumeM3 = $this->hitungKubikasi($item['panjang'], $item['lebar'], $item['tebal'], $item['jumlah']);
                        @endphp
                        <tr class="hover:bg-zinc-50/80 dark:hover:bg-zinc-900/40 transition-colors text-zinc-800 dark:text-zinc-100">

                            <td class="py-3 px-3">
                                <input type="checkbox" disabled class="accent-amber-500 opacity-50 cursor-not-allowed" />
                            </td>

                            {{-- Aksi Terima — parameter disesuaikan dengan signature method baru --}}
                            <td class="py-3 px-4 text-center">
                                <button
                                    type="button"
                                    wire:click="terimaBarang({{ $item['id'] }})"
                                    wire:loading.attr="disabled"
                                    wire:target="terimaBarang({{ $item['id'] }})"
                                    class="inline-flex items-center gap-1 border border-amber-400 bg-amber-500 hover:bg-amber-600 text-zinc-950 transition-all text-[10px] font-black uppercase px-2.5 py-1 rounded-none shadow-sm active:scale-95">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                                    </svg>
                                    <span>Terima</span>
                                </button>
                            </td>
                            <td class="py-3 px-4 font-bold text-sm">{{ $item['jenis_kayu'] }}</td>
                            <td class="py-3 px-3 text-right text-sm text-zinc-600 dark:text-zinc-300">{{ $item['panjang'] }} cm</td>
                            <td class="py-3 px-3 text-right text-sm text-zinc-600 dark:text-zinc-300">{{ $item['lebar'] }} cm</td>
                            <td class="py-3 px-3 text-right text-sm text-amber-600 dark:text-amber-500 font-bold">{{ $item['tebal'] }} mm</td>

                            <td class="py-3 px-3 text-center">
                                <span class="inline-block border border-amber-400 bg-amber-500 text-zinc-950 font-black text-xs px-2 py-0.5 rounded-none shadow-sm">
                                    {{ $item['kw'] }}
                                </span>
                            </td>

                            <td class="py-3 px-4 text-center">
                                <span class="inline-block border border-amber-400 bg-amber-500 text-zinc-950 font-bold text-xs px-2.5 py-0.5 rounded-none shadow-sm">
                                    {{ number_format($item['jumlah'], 0, ',', '.') }}
                                </span>
                            </td>

                            <td class="py-3 px-4 text-right font-bold text-zinc-500 dark:text-zinc-400 text-sm">
                                {{ number_format($volumeM3, 4, '.', '') }}
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colSpan="10" class="py-8 text-center text-zinc-400 dark:text-zinc-500 italic">
                                Tidak ada antrean kiriman aktif saat ini.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Footer Pagination Sederhana --}}
            <div class="flex flex-col sm:flex-row items-center justify-between gap-3 pt-1 text-xs text-zinc-500 dark:text-zinc-400">
                <div>
                    Showing <span class="text-zinc-700 dark:text-zinc-200 font-bold">{{ count($this->antreanFiltered) }}</span> result(s)
                </div>

                <div class="flex items-center gap-2">
                    <span>Per page</span>
                    <select
                        disabled
                        class="text-xs p-1 border rounded-none bg-white border-zinc-300 text-zinc-700 dark:bg-zinc-900 dark:border-zinc-800 dark:text-zinc-400 opacity-60 cursor-not-allowed">
                        <option value="10">10</option>
                    </select>
                </div>
            </div>
        </section>

    </div>
</x-filament-panels::page>