<x-filament::page>
    <div class="min-h-screen -m-8 p-3 sm:p-6 bg-gray-100 dark:bg-gray-950 flex flex-col gap-3 pb-28 sm:pb-8">

        {{-- HEADER --}}
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-2">
                @if ($selectedNota)
                    <button type="button" wire:click="batalPilihNota"
                        class="p-2 rounded-xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 text-gray-600 dark:text-gray-300 shrink-0">
                        <x-heroicon-o-arrow-left class="w-5 h-5" />
                    </button>
                @else
                    <a href="{{ \App\Filament\Resources\ReturPenjualans\ReturPenjualanResource::getUrl('index') }}"
                        class="p-2 rounded-xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 text-gray-600 dark:text-gray-300 shrink-0">
                        <x-heroicon-o-arrow-left class="w-5 h-5" />
                    </a>
                @endif
                <h1 class="text-base font-black text-gray-900 dark:text-white">Retur Penjualan</h1>
            </div>
            <span class="text-xs font-bold text-gray-400">
                {{ $selectedNota ? 'Langkah 2/2' : 'Langkah 1/2' }}
            </span>
        </div>

        @if (!$selectedNota)
            {{-- ===================== STEP 1: CARI NOTA ===================== --}}
            <div class="relative">
                <div class="absolute inset-y-0 left-3.5 flex items-center pointer-events-none text-gray-400">
                    <x-heroicon-o-magnifying-glass class="w-5 h-5" />
                </div>
                <input type="text" wire:model.live.debounce.300ms="searchNota"
                    placeholder="Cari no. nota / nama customer..." autofocus
                    class="w-full bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl !pl-11 pr-4 py-3.5 text-sm shadow-sm focus:ring-2 focus:ring-primary-500/20 text-gray-900 dark:text-white" />
            </div>

            <div class="flex flex-col gap-2">
                @forelse ($this->notaResults as $nota)
                    <button type="button"
                        @if (!$nota->is_retur_habis) wire:click="pilihNota({{ $nota->id }})" @endif
                        @if ($nota->is_retur_habis) disabled @endif
                        class="w-full text-left bg-white dark:bg-gray-900 rounded-2xl border border-gray-200/80 dark:border-gray-800 px-4 py-3.5 flex items-center gap-3 {{ $nota->is_retur_habis ? 'opacity-50' : 'active:bg-gray-50 dark:active:bg-gray-800' }}">
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-1.5 flex-wrap">
                                <span class="font-mono text-sm font-bold text-primary-600 dark:text-primary-400">{{ $nota->no_nota }}</span>
                                @if ($nota->is_retur_habis)
                                    <span class="text-[9px] font-black px-1.5 py-0.5 rounded bg-red-100 text-red-700 dark:bg-red-950/60 dark:text-red-400">SUDAH DIRETUR</span>
                                @elseif ($nota->pernah_diretur ?? false)
                                    <span class="text-[9px] font-black px-1.5 py-0.5 rounded bg-amber-100 text-amber-700 dark:bg-amber-950/60 dark:text-amber-400">DIRETUR SEBAGIAN</span>
                                @endif
                            </div>
                            <div class="text-xs text-gray-500 dark:text-gray-400 truncate mt-0.5">
                                {{ $nota->tujuan_nota }} &bull; {{ $nota->tanggal?->format('d M Y') }}
                            </div>
                        </div>
                        <div class="text-right shrink-0">
                            <div class="text-sm font-black text-gray-900 dark:text-white">Rp {{ number_format($nota->total, 0, ',', '.') }}</div>
                            <div class="text-[10px] font-bold {{ $nota->pernah_diretur ? 'text-amber-500' : 'text-emerald-500' }}">
                                {{ $nota->is_retur_habis ? 'Habis diretur' : ($nota->pernah_diretur ? 'Sebagian diretur' : 'Belum diretur') }}
                            </div>
                        </div>
                        @if (!$nota->is_retur_habis)
                            <x-heroicon-o-chevron-right class="w-4 h-4 text-gray-300 shrink-0" />
                        @endif
                    </button>
                @empty
                    <div class="py-16 text-center text-gray-400">
                        <x-heroicon-o-document-magnifying-glass class="w-9 h-9 mx-auto mb-2 opacity-40" />
                        <p class="text-xs font-semibold">Nota tidak ditemukan</p>
                        <p class="text-[11px] mt-1">Coba ketik nomor nota atau nama customer</p>
                    </div>
                @endforelse
            </div>

        @else
            {{-- ===================== STEP 2: PILIH BARANG ===================== --}}

            {{-- Info Nota Terpilih (collapsible) --}}
            <details class="bg-white dark:bg-gray-900 rounded-2xl border border-gray-200/80 dark:border-gray-800 shadow-sm">
                <summary class="list-none flex items-center justify-between gap-3 px-4 py-3 cursor-pointer">
                    <div class="min-w-0 flex-1 flex items-center gap-2">
                        <x-heroicon-o-receipt-percent class="w-4 h-4 text-primary-500 shrink-0" />
                        <span class="font-mono text-sm font-bold text-gray-900 dark:text-white truncate">{{ $selectedNota->no_nota }}</span>
                        <span class="text-xs text-gray-400 truncate">{{ $selectedNota->tujuan_nota }}</span>
                    </div>
                    <span class="text-[11px] font-bold text-primary-600 shrink-0">Detail ▾</span>
                </summary>
                <div class="px-4 pb-4 pt-2 grid grid-cols-2 gap-3 text-xs border-t border-gray-100 dark:border-gray-800">
                    <div>
                        <span class="text-[10px] text-gray-400 font-bold uppercase block">Total Belanja</span>
                        <span class="font-black text-gray-900 dark:text-white">Rp {{ number_format($selectedNota->total, 0, ',', '.') }}</span>
                    </div>
                    <div>
                        <span class="text-[10px] text-gray-400 font-bold uppercase block">Metode Bayar</span>
                        <span class="font-bold text-gray-700 dark:text-gray-300">{{ $selectedNota->metode_pembayaran ?? '-' }}</span>
                    </div>
                    <div>
                        <span class="text-[10px] text-gray-400 font-bold uppercase block">Tanggal Retur</span>
                        <input type="date" wire:model.live="tanggal"
                            class="w-full bg-transparent border-0 p-0 text-xs font-bold text-gray-700 dark:text-gray-300 focus:ring-0" />
                    </div>
                </div>
            </details>

            {{-- Aksi Cepat --}}
            <div class="flex items-center justify-between px-1">
                <p class="text-xs text-gray-500 dark:text-gray-400">Ketuk barang untuk memilih</p>
                <div class="flex items-center gap-2">
                    <button type="button" wire:click="toggleReturSemua(true)"
                        @if (!$this->hasSisaBarang) disabled @endif
                        class="text-[11px] font-bold text-emerald-600 disabled:opacity-40">Pilih Semua</button>
                    <span class="text-gray-300">|</span>
                    <button type="button" wire:click="toggleReturSemua(false)"
                        class="text-[11px] font-bold text-gray-400">Reset</button>
                </div>
            </div>

            @if (!$this->hasSisaBarang)
                <div class="p-3.5 rounded-xl bg-red-50 dark:bg-red-950/40 border border-red-200 dark:border-red-800 text-xs text-red-900 dark:text-red-200">
                    Semua barang sudah pernah diretur. Ketuk <strong>tombol kembali</strong> untuk pilih nota lain.
                </div>
            @endif

            {{-- Daftar Barang --}}
            <div class="flex flex-col gap-2">
                @foreach ($items as $index => $item)
                    @php
                        $isVeneer    = $item['is_veneer'] ?? false;
                        $isAvailable = !$isVeneer && ($item['sisa_qty'] ?? 0) > 0;
                        $isSelected  = $item['selected'] ?? false;
                        $isM3        = ($item['hit_kbk'] ?? 'b') === 'm';
                    @endphp
                    <div wire:key="retur-item-{{ $item['detail_id'] }}" data-row-index="{{ $index }}"
                        data-available="{{ $isAvailable ? '1' : '0' }}"
                        class="js-retur-row rounded-2xl border shadow-sm transition-colors
                            {{ $isSelected ? 'bg-primary-50/60 border-primary-200 dark:bg-primary-950/20 dark:border-primary-900' : 'bg-white dark:bg-gray-900 border-gray-200/80 dark:border-gray-800' }}
                            {{ $isAvailable ? 'cursor-pointer' : 'opacity-50' }}">

                        <div class="px-4 py-3 flex items-center gap-3">
                            {{-- Checkbox visual --}}
                            <div class="w-5 h-5 rounded-md border-2 flex items-center justify-center shrink-0
                                {{ $isSelected ? 'bg-primary-600 border-primary-600' : 'border-gray-300 dark:border-gray-600' }}
                                {{ !$isAvailable ? 'opacity-40' : '' }}">
                                @if ($isSelected)
                                    <x-heroicon-o-check class="w-3.5 h-3.5 text-white" />
                                @endif
                                <input type="checkbox" wire:click="toggleItem({{ $index }})"
                                    {{ $isSelected ? 'checked' : '' }}
                                    @if (!$isAvailable) disabled @endif class="hidden" />
                            </div>

                            {{-- Info Barang --}}
                            <div class="min-w-0 flex-1">
                                <div class="text-sm font-bold truncate {{ !$isAvailable ? ($isVeneer ? 'text-gray-400' : 'line-through text-gray-400') : 'text-gray-900 dark:text-white' }}">
                                    {{ $item['nama_barang'] }}
                                </div>
                                <div class="flex items-center gap-1.5 flex-wrap mt-0.5">
                                    @if ($isVeneer)
                                        <span class="text-[10px] font-bold px-1.5 py-0.5 rounded bg-red-100 text-red-600 dark:bg-red-950/40 dark:text-red-400">
                                            Tidak Boleh Diretur (Veneer)
                                        </span>
                                    @else
                                        <span class="text-[10px] font-bold px-1.5 py-0.5 rounded
                                            {{ $item['qty_teretur'] > 0 ? 'bg-amber-50 text-amber-600 dark:bg-amber-950/40 dark:text-amber-400' : 'bg-gray-50 text-gray-400 dark:bg-gray-800' }}">
                                            Diretur {{ $item['qty_teretur'] }}/{{ $item['qty_beli'] }} {{ $item['satuan'] }}
                                        </span>
                                        @if (!$isAvailable)
                                            <span class="text-[10px] font-bold text-red-500">Sudah habis diretur</span>
                                        @else
                                            <span class="text-[10px] text-gray-400">
                                                Sisa {{ $item['sisa_qty'] }} {{ $item['satuan'] }}
                                                &bull; Rp {{ number_format($item['harga'], 0, ',', '.') }}
                                                @if ($isM3) &bull; <span class="text-blue-500 font-bold">M3</span> @endif
                                            </span>
                                        @endif
                                    @endif
                                </div>
                            </div>

                            @if ($isAvailable)
                                <div class="text-right shrink-0">
                                    <div class="text-xs font-black text-gray-900 dark:text-white">
                                        Rp {{ number_format($item['subtotal'] ?? 0, 0, ',', '.') }}
                                    </div>
                                </div>
                            @endif
                        </div>

                        {{-- Stepper Qty (muncul jika selected) --}}
                        @if ($isAvailable && $isSelected)
                            <div class="px-4 pb-3.5 flex items-center gap-3" data-qty-wrapper>
                                <div class="flex items-center border border-gray-200 dark:border-gray-700 rounded-xl overflow-hidden bg-white dark:bg-gray-800 w-full max-w-[200px]">
                                    <button type="button"
                                        class="js-qty-minus flex-1 h-11 flex items-center justify-center text-gray-500 active:bg-gray-100 dark:active:bg-gray-700">
                                        <x-heroicon-o-minus class="w-4 h-4" />
                                    </button>
                                    <input type="number"
                                        wire:model.live.debounce.300ms="items.{{ $index }}.qty_retur"
                                        x-on:blur="$wire.finalizeQty({{ $index }})"
                                        min="0" max="{{ $item['sisa_qty'] }}" step="any"
                                        class="js-qty-input w-20 text-center font-black text-base bg-transparent border-0 focus:ring-0 text-primary-600 dark:text-primary-400" />
                                    <button type="button"
                                        class="js-qty-plus flex-1 h-11 flex items-center justify-center text-gray-500 active:bg-gray-100 dark:active:bg-gray-700">
                                        <x-heroicon-o-plus class="w-4 h-4" />
                                    </button>
                                </div>
                                <span class="text-xs text-gray-400">{{ $item['satuan'] }}</span>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>

            {{-- ===================== RINGKASAN & SIMPAN ===================== --}}
            @php $ringkasan = $this->ringkasan; @endphp
            <div id="ringkasan-retur"
                class="bg-white dark:bg-gray-900 rounded-2xl border border-gray-200/80 dark:border-gray-800 shadow-sm p-4 flex flex-col gap-3 scroll-mt-4">

                {{-- Total --}}
                <div class="flex items-center justify-between">
                    <div>
                        <span class="text-sm font-bold text-gray-800 dark:text-gray-200">Total Dikembalikan</span>
                        @if ($ringkasan['jumlah_item'] > 0)
                            <span class="ml-1.5 text-[11px] font-semibold text-gray-400">({{ $ringkasan['jumlah_item'] }} item)</span>
                        @endif
                    </div>
                    <span class="text-xl font-black text-primary-600 dark:text-primary-400">Rp {{ number_format($ringkasan['total'], 0, ',', '.') }}</span>
                </div>

                @if ($ringkasan['jumlah_item'] > 0)
                    {{-- Rincian singkat --}}
                    <div class="flex flex-col gap-1 text-xs bg-gray-50 dark:bg-gray-800/60 rounded-xl p-3">
                        @foreach ($items as $item)
                            @if (($item['selected'] ?? false) && ($item['qty_retur'] ?? 0) > 0)
                                <div class="flex justify-between text-gray-600 dark:text-gray-400">
                                    <span class="truncate max-w-[55%]">{{ $item['nama_barang'] }} &times; {{ $item['qty_retur'] }} {{ $item['satuan'] }}</span>
                                    <span class="font-semibold text-gray-900 dark:text-white">Rp {{ number_format($item['subtotal'], 0, ',', '.') }}</span>
                                </div>
                            @endif
                        @endforeach
                    </div>
                @endif

                {{-- Alasan Retur --}}
                <div class="flex flex-col gap-1.5">
                    <label class="text-xs font-bold text-gray-700 dark:text-gray-300">
                        Alasan Retur <span class="text-red-500">*</span>
                    </label>
                    <textarea wire:model.live.debounce.300ms="keterangan_retur" rows="2"
                        placeholder="Barang cacat, salah ukuran, kelebihan kiriman, dsb..." required
                        class="w-full bg-gray-50 dark:bg-gray-800 border rounded-xl p-3 text-sm text-gray-900 dark:text-white {{ $errors->has('keterangan_retur') ? 'border-red-400 focus:ring-2 focus:ring-red-300' : 'border-gray-200 dark:border-gray-700' }}"></textarea>
                    @error('keterangan_retur')
                        <span class="text-[11px] font-semibold text-red-500">{{ $message }}</span>
                    @enderror
                </div>

                {{-- Tombol Simpan --}}
                <button type="button" wire:click="simpanRetur"
                    wire:confirm="Simpan retur ini sebagai draft? Anda perlu validasi setelahnya untuk dikirim ke Akuntansi."
                    wire:loading.attr="disabled" wire:target="simpanRetur"
                    @if ($ringkasan['total'] <= 0 || trim($keterangan_retur) === '') disabled @endif
                    class="w-full py-3.5 rounded-xl bg-primary-600 disabled:opacity-50 disabled:cursor-not-allowed text-white text-sm font-black shadow-md flex items-center justify-center gap-2">
                    <span wire:loading.remove wire:target="simpanRetur" class="flex items-center gap-2">
                        <x-heroicon-o-check-circle class="w-5 h-5" /> Simpan Retur (Draft)
                    </span>
                    <span wire:loading wire:target="simpanRetur" class="flex items-center gap-2">
                        <svg class="animate-spin w-4 h-4" viewBox="0 0 24 24" fill="none">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" />
                        </svg>
                        Menyimpan...
                    </span>
                </button>

                @if ($ringkasan['total'] <= 0)
                    <p class="text-[11px] text-center text-gray-400">Pilih barang di atas dulu</p>
                @elseif (trim($keterangan_retur) === '')
                    <p class="text-[11px] text-center text-red-400">Isi alasan retur dulu</p>
                @endif
            </div>
        @endif
    </div>

    {{-- BAR TOTAL MENGAMBANG (mobile) --}}
    @if ($selectedNota)
        @php $ringkasan = $this->ringkasan; @endphp
        <div class="fixed bottom-0 inset-x-0 z-40 bg-white dark:bg-gray-900 border-t border-gray-200 dark:border-gray-800 px-4 py-3 flex items-center justify-between gap-3 shadow-[0_-4px_12px_rgba(0,0,0,0.06)] sm:hidden">
            <div>
                <div class="text-[10px] text-gray-400 font-bold uppercase">Total Retur</div>
                <div class="text-base font-black text-primary-600 dark:text-primary-400">Rp {{ number_format($ringkasan['total'], 0, ',', '.') }}</div>
            </div>
            <a href="#ringkasan-retur"
                class="px-4 py-2.5 rounded-xl bg-primary-600 text-white text-xs font-black">Simpan ↓</a>
        </div>
    @endif

    {{-- Script stepper qty + klik baris --}}
    <script>
        (function () {
            function dispatchInput(el) {
                el.dispatchEvent(new Event('input', { bubbles: true }));
            }

            document.addEventListener('click', function (e) {
                var minus = e.target.closest('.js-qty-minus');
                var plus  = e.target.closest('.js-qty-plus');
                if (minus || plus) {
                    var wrapper = e.target.closest('[data-qty-wrapper]');
                    var input   = wrapper && wrapper.querySelector('.js-qty-input');
                    if (!input) return;
                    var min     = parseFloat(input.min || '0');
                    var max     = parseFloat(input.max || 'Infinity');
                    var current = parseFloat(input.value || '0');
                    var step    = parseFloat(input.step === 'any' ? '1' : (input.step || '1'));
                    var next    = current + (plus ? step : -step);
                    if (next < min) next = min;
                    if (max && next > max) next = max;
                    input.value = next;
                    dispatchInput(input);
                    return;
                }

                var row = e.target.closest('.js-retur-row');
                if (row && row.dataset.available === '1' && !e.target.closest('[data-qty-wrapper]')) {
                    var checkbox = row.querySelector('input[type="checkbox"]');
                    if (checkbox) checkbox.click();
                }
            });
        })();
    </script>
</x-filament::page>
