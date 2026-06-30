<x-filament-panels::page>
    @php
        $fieldClass = 'flex flex-col gap-1';
        $labelClass = 'text-sm font-medium leading-6 text-gray-950 dark:text-white';
    @endphp

    <div class="space-y-6">

        {{-- ═══════════════════════════════════════════
             HEADER DOKUMEN
        ═══════════════════════════════════════════ --}}
        <x-filament::section heading="Informasi Dokumen BM">
            <div class="grid grid-cols-1 gap-5 md:grid-cols-2">

                <div class="{{ $fieldClass }}">
                    <label class="{{ $labelClass }}">Tanggal <span class="text-danger-500">*</span></label>
                    <x-filament::input.wrapper>
                        <x-filament::input type="date" wire:model.live="tanggal" />
                    </x-filament::input.wrapper>
                    @error('tanggal') <p class="text-xs text-danger-500">{{ $message }}</p> @enderror
                </div>

                <div class="{{ $fieldClass }}">
                    <label class="{{ $labelClass }}">No. Nota BM <span class="text-danger-500">*</span></label>
                    <x-filament::input.wrapper>
                        <x-filament::input type="text" wire:model.live="no_nota" placeholder="BM-001..." />
                    </x-filament::input.wrapper>
                    @error('no_nota') <p class="text-xs text-danger-500">{{ $message }}</p> @enderror
                </div>

                <div class="{{ $fieldClass }}">
                    <label class="{{ $labelClass }}">Supplier / Pengirim <span class="text-danger-500">*</span></label>
                    <x-filament::input.wrapper>
                        <x-filament::input type="text" wire:model.live="tujuan_nota" placeholder="Nama supplier..." />
                    </x-filament::input.wrapper>
                    @error('tujuan_nota') <p class="text-xs text-danger-500">{{ $message }}</p> @enderror
                </div>

                <div class="{{ $fieldClass }}">
                    <label class="{{ $labelClass }}">Keterangan</label>
                    <x-filament::input.wrapper>
                        <x-filament::input type="text" wire:model.live="keterangan" placeholder="Opsional..." />
                    </x-filament::input.wrapper>
                </div>

            </div>
        </x-filament::section>

        {{-- ═══════════════════════════════════════════
             INPUT TAMBAH BARANG
        ═══════════════════════════════════════════ --}}
        <x-filament::section heading="Tambah Barang">
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">

                {{-- Tipe Veneer --}}
                <div class="{{ $fieldClass }}">
                    <label class="{{ $labelClass }}">Tipe Veneer <span class="text-danger-500">*</span></label>
                    <x-filament::input.wrapper>
                        <x-filament::input.select wire:model.live="item_tipe_veneer">
                            <option value="">-- Pilih --</option>
                            <option value="basah">Veneer Basah</option>
                            <option value="kering">Veneer Kering</option>
                        </x-filament::input.select>
                    </x-filament::input.wrapper>
                    @error('item_tipe_veneer') <p class="text-xs text-danger-500">{{ $message }}</p> @enderror
                </div>

                {{-- Jenis Kayu --}}
                <div class="{{ $fieldClass }}">
                    <label class="{{ $labelClass }}">Jenis Kayu <span class="text-danger-500">*</span></label>
                    <x-filament::input.wrapper>
                        <x-filament::input.select wire:model.live="item_id_jenis_kayu">
                            <option value="">-- Pilih Tipe dulu --</option>
                            @foreach($this->jenisKayuOptions as $id => $nama)
                                <option value="{{ $id }}">{{ $nama }}</option>
                            @endforeach
                        </x-filament::input.select>
                    </x-filament::input.wrapper>
                    @error('item_id_jenis_kayu') <p class="text-xs text-danger-500">{{ $message }}</p> @enderror
                </div>

                {{-- KW / Grade --}}
                <div class="{{ $fieldClass }}">
                    <label class="{{ $labelClass }}">KW / Grade <span class="text-danger-500">*</span></label>
                    <x-filament::input.wrapper>
                        <x-filament::input.select wire:model.live="item_kw">
                            <option value="">-- Pilih Jenis dulu --</option>
                            @foreach($this->kwOptions as $val => $label)
                                <option value="{{ $val }}">{{ $label }}</option>
                            @endforeach
                        </x-filament::input.select>
                    </x-filament::input.wrapper>
                    @error('item_kw') <p class="text-xs text-danger-500">{{ $message }}</p> @enderror
                </div>

                {{-- Ukuran --}}
                <div class="{{ $fieldClass }}">
                    <label class="{{ $labelClass }}">Ukuran Barang (P × L × T) <span class="text-danger-500">*</span></label>
                    <div wire:key="ukuran-select-{{ $item_tipe_veneer ?? 'null' }}-{{ $item_id_jenis_kayu ?? 'null' }}-{{ $item_kw ?? 'null' }}"
                        x-data="{ 
                            open: false, 
                            search: '', 
                            selected: @entangle('item_id_ukuran').live,
                            get items() {
                                return @js(collect($this->ukuranOptions)->map(fn($dim, $id) => ['id' => (string)$id, 'label' => $dim])->values()->toArray());
                            },
                            get filteredItems() {
                                if (this.search.trim() === '') return this.items;
                                return this.items.filter(i => i.label.toLowerCase().includes(this.search.toLowerCase()));
                            }
                        }" class="relative w-full">

                        <x-filament::input.wrapper>
                            <button 
                                @click="open = !open" 
                                type="button" 
                                class="w-full flex items-center justify-between px-3 py-2 text-sm text-left bg-white dark:bg-gray-900 border-0 focus:ring-0 focus:outline-none rounded-lg"
                            >
                                <span class="truncate" x-text="selected ? (items.find(i => i.id == selected)?.label ?? '-- Pilih Ukuran --') : (items.length > 0 ? '-- Pilih Ukuran --' : '-- Pilih KW dulu --')"></span>
                                <span class="text-gray-400 dark:text-gray-500">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                    </svg>
                                </span>
                            </button>
                        </x-filament::input.wrapper>

                        <div 
                            x-show="open" 
                            x-transition 
                            @click.away="open = false" 
                            class="absolute z-50 mt-1 w-full bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 shadow-lg rounded-lg overflow-hidden" 
                            style="display: none;"
                        >
                            <div class="p-2 border-b border-gray-100 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 flex items-center relative">
                                <input 
                                    x-model="search" 
                                    type="text" 
                                    placeholder="Cari ukuran..." 
                                    class="w-full bg-white dark:bg-gray-800 text-xs border border-gray-200 dark:border-gray-600 rounded-lg p-1.5 pr-7 outline-none focus:border-primary-500 focus:ring-1 focus:ring-primary-500"
                                />
                                <button 
                                    x-show="search.length > 0" 
                                    @click="search = ''" 
                                    type="button" 
                                    class="absolute right-4 text-gray-400 hover:text-red-500 font-bold"
                                >
                                    ×
                                </button>
                            </div>
                            <div class="max-h-60 overflow-y-auto font-sans text-xs">
                                <div x-show="filteredItems.length === 0" class="px-3 py-3 text-gray-400 text-center">
                                    Tidak ada ukuran yang cocok
                                </div>
                                <template x-for="item in filteredItems" :key="item.id">
                                    <div 
                                        @click="selected = item.id; open = false; search = ''" 
                                        class="px-3 py-2.5 hover:bg-gray-100 dark:hover:bg-zinc-700 cursor-pointer border-b border-gray-50 dark:border-gray-700 last:border-0 transition-colors flex items-center justify-between"
                                        :class="selected == item.id ? 'bg-primary-50 dark:bg-primary-950/20 text-primary-600 dark:text-primary-400 font-semibold' : 'text-gray-800 dark:text-gray-200'"
                                    >
                                        <span x-text="item.label"></span>
                                        <span x-show="selected == item.id" class="text-primary-600 dark:text-primary-400">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                            </svg>
                                        </span>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>
                    @error('item_id_ukuran') <p class="text-xs text-danger-500">{{ $message }}</p> @enderror
                </div>

                {{-- Stok Saat Ini (read-only, same height as inputs) --}}
                <div class="{{ $fieldClass }}">
                    <label class="{{ $labelClass }}">Stok Saat Ini (Sistem)</label>
                    <x-filament::input.wrapper>
                        <div class="flex w-full items-center justify-between px-3 py-2 text-sm font-semibold
                            {{ $stok_sistem > 0 ? 'text-primary-600 dark:text-primary-400' : 'text-gray-400 dark:text-gray-500' }}">
                            <span>{{ number_format($stok_sistem) }}</span>
                            <span class="text-xs font-normal opacity-70">Lembar</span>
                        </div>
                    </x-filament::input.wrapper>
                </div>

                {{-- Qty Masuk --}}
                <div class="{{ $fieldClass }}">
                    <label class="{{ $labelClass }}">Jumlah Masuk (Lembar) <span class="text-danger-500">*</span></label>
                    <x-filament::input.wrapper>
                        <x-filament::input
                            type="number"
                            wire:model.blur="item_qty"
                            min="1"
                            placeholder="0"
                        />
                        <x-slot name="suffix">lbr</x-slot>
                    </x-filament::input.wrapper>
                    @error('item_qty') <p class="text-xs text-danger-500">{{ $message }}</p> @enderror
                </div>

            </div>

            <div class="mt-5 border-t border-gray-100 dark:border-gray-700 pt-4">
                <x-filament::button wire:click="tambahBarang" icon="heroicon-o-plus-circle" color="primary" size="md">
                    Tambah Barang
                </x-filament::button>
            </div>
        </x-filament::section>

        {{-- ═══════════════════════════════════════════
             DAFTAR BARANG VENEER
        ═══════════════════════════════════════════ --}}
        @if(count($items) > 0)
        <x-filament::section heading="Daftar Barang Veneer ({{ count($items) }} item)">
            <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-800 text-xs uppercase text-gray-500 dark:text-gray-400">
                        <tr>
                            <th class="px-4 py-3 text-left">#</th>
                            <th class="px-4 py-3 text-left">Tipe</th>
                            <th class="px-4 py-3 text-left">Jenis Kayu</th>
                            <th class="px-4 py-3 text-left">KW</th>
                            <th class="px-4 py-3 text-left">Ukuran</th>
                            <th class="px-4 py-3 text-right">Stok Sebelum</th>
                            <th class="px-4 py-3 text-right">Qty Masuk</th>
                            <th class="px-4 py-3 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @foreach($items as $i => $item)
                        <tr class="bg-white dark:bg-gray-900 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">
                            <td class="px-4 py-3 text-gray-500">{{ $i + 1 }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium
                                    {{ $item['tipe_veneer'] === 'basah' ? 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300' : 'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300' }}">
                                    {{ ucfirst($item['tipe_veneer']) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 font-medium">{{ $item['nama_jenis_kayu'] }}</td>
                            <td class="px-4 py-3">KW {{ $item['kw'] }}</td>
                            <td class="px-4 py-3 font-mono text-xs">{{ $item['dimensi'] }}</td>
                            <td class="px-4 py-3 text-right text-gray-500">{{ number_format($item['stok_sebelum']) }} lbr</td>
                            <td class="px-4 py-3 text-right font-semibold text-success-600 dark:text-success-400">
                                +{{ number_format($item['qty']) }} lbr
                            </td>
                            <td class="px-4 py-3 text-center">
                                <button wire:click="hapusBarang({{ $i }})"
                                    class="inline-flex items-center justify-center rounded p-1 text-danger-500 hover:bg-danger-50 hover:text-danger-700 dark:hover:bg-danger-900 transition-colors"
                                    wire:confirm="Hapus barang ini dari daftar?">
                                    <x-heroicon-o-trash class="w-4 h-4" />
                                </button>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-filament::section>
        @endif

        {{-- ═══════════════════════════════════════════
             INPUT TAMBAH BAHAN LAIN (NON-VENEER)
        ═══════════════════════════════════════════ --}}
        <x-filament::section heading="Tambah Bahan / Barang Lain (Selain Veneer - Opsional)">
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
                {{-- Nama Barang --}}
                <div class="{{ $fieldClass }}">
                    <label class="{{ $labelClass }}">Nama Barang <span class="text-danger-500">*</span></label>
                    <x-filament::input.wrapper>
                        <x-filament::input type="text" wire:model.live="nv_nama_barang" placeholder="Paku, Solasi, dll..." />
                    </x-filament::input.wrapper>
                    @error('nv_nama_barang') <p class="text-xs text-danger-500">{{ $message }}</p> @enderror
                </div>

                {{-- Jumlah --}}
                <div class="{{ $fieldClass }}">
                    <label class="{{ $labelClass }}">Jumlah <span class="text-danger-500">*</span></label>
                    <x-filament::input.wrapper>
                        <x-filament::input type="number" wire:model.live="nv_jumlah" placeholder="0" min="1" />
                    </x-filament::input.wrapper>
                    @error('nv_jumlah') <p class="text-xs text-danger-500">{{ $message }}</p> @enderror
                </div>

                {{-- Satuan --}}
                <div class="{{ $fieldClass }}">
                    <label class="{{ $labelClass }}">Satuan <span class="text-danger-500">*</span></label>
                    <x-filament::input.wrapper>
                        <x-filament::input type="text" wire:model.live="nv_satuan" placeholder="Pcs, Kg, Box, dll..." />
                    </x-filament::input.wrapper>
                    @error('nv_satuan') <p class="text-xs text-danger-500">{{ $message }}</p> @enderror
                </div>

                {{-- Keterangan --}}
                <div class="{{ $fieldClass }}">
                    <label class="{{ $labelClass }}">Keterangan</label>
                    <x-filament::input.wrapper>
                        <x-filament::input type="text" wire:model.live="nv_keterangan" placeholder="Opsional..." />
                    </x-filament::input.wrapper>
                </div>
            </div>

            <div class="mt-5 border-t border-gray-100 dark:border-gray-700 pt-4">
                <x-filament::button wire:click="tambahBahanLain" icon="heroicon-o-plus-circle" color="warning" size="md">
                    Tambah Bahan Lain
                </x-filament::button>
            </div>
        </x-filament::section>

        {{-- ═══════════════════════════════════════════
             DAFTAR BAHAN / BARANG LAIN
        ═══════════════════════════════════════════ --}}
        @if(count($non_veneer_items) > 0)
        <x-filament::section heading="Daftar Bahan / Barang Lain ({{ count($non_veneer_items) }} item)">
            <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-800 text-xs uppercase text-gray-500 dark:text-gray-400">
                        <tr>
                            <th class="px-4 py-3 text-left">#</th>
                            <th class="px-4 py-3 text-left">Nama Barang</th>
                            <th class="px-4 py-3 text-right">Jumlah</th>
                            <th class="px-4 py-3 text-left">Satuan</th>
                            <th class="px-4 py-3 text-left">Keterangan</th>
                            <th class="px-4 py-3 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @foreach($non_veneer_items as $i => $item)
                        <tr class="bg-white dark:bg-gray-900 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">
                            <td class="px-4 py-3 text-gray-500">{{ $i + 1 }}</td>
                            <td class="px-4 py-3 font-medium">{{ $item['nama_barang'] }}</td>
                            <td class="px-4 py-3 text-right font-semibold">{{ number_format($item['jumlah']) }}</td>
                            <td class="px-4 py-3">{{ $item['satuan'] }}</td>
                            <td class="px-4 py-3 text-gray-500">{{ $item['keterangan'] ?: '-' }}</td>
                            <td class="px-4 py-3 text-center">
                                <button wire:click="hapusBahanLain({{ $i }})"
                                    class="inline-flex items-center justify-center rounded p-1 text-danger-500 hover:bg-danger-50 hover:text-danger-700 dark:hover:bg-danger-900 transition-colors"
                                    wire:confirm="Hapus barang ini dari daftar?">
                                    <x-heroicon-o-trash class="w-4 h-4" />
                                </button>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-filament::section>
        @endif

        @if(count($items) === 0 && count($non_veneer_items) === 0)
        <x-filament::section>
            <div class="flex flex-col items-center justify-center py-10 text-gray-400 dark:text-gray-600">
                <x-heroicon-o-inbox-arrow-down class="w-12 h-12 mb-3 opacity-30" />
                <p class="text-sm">Belum ada barang yang ditambahkan.</p>
                <p class="text-xs mt-1 opacity-70">Isi form di atas lalu klik "Tambah Barang" atau "Tambah Bahan Lain".</p>
            </div>
        </x-filament::section>
        @endif

        {{-- SUBMIT --}}
        <div class="flex flex-wrap items-center justify-end gap-3" x-data="{ openConfirm: false }">
            <x-filament::button tag="a" href="/admin/nota-barang-masuks" color="gray" icon="heroicon-o-arrow-left">
                Batal
            </x-filament::button>

            <x-filament::button wire:click="simpanDraft" color="warning" icon="heroicon-o-document-text">
                Simpan Draft
            </x-filament::button>

            <x-filament::button @click="openConfirm = true" color="success" icon="heroicon-o-paper-airplane">
                Kirim &amp; Posting ke BM
            </x-filament::button>

            <!-- Custom Confirmation Modal -->
            <div 
                x-show="openConfirm" 
                class="fixed inset-0 z-50 flex items-center justify-center bg-zinc-950/60 backdrop-blur-sm"
                x-transition
                style="display: none;"
            >
                <div 
                    @click.away="openConfirm = false" 
                    class="w-full max-w-md bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-xl shadow-2xl p-6 text-left"
                >
                    <div class="flex items-center gap-3 text-amber-600 dark:text-amber-400 mb-4">
                        <span class="p-2 bg-amber-50 dark:bg-amber-950/30 rounded-lg">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                            </svg>
                        </span>
                        <h3 class="text-lg font-bold text-zinc-900 dark:text-white">Kirim Dokumen BM?</h3>
                    </div>

                    <div class="text-sm text-zinc-600 dark:text-zinc-400 space-y-2 mb-6">
                        <p>Apakah Anda yakin ingin mengirim dokumen ini?</p>
                        <p class="font-medium text-zinc-800 dark:text-zinc-200">
                            Dokumen akan masuk ke draf Nota BM dan <span class="text-primary-600 dark:text-primary-400 font-semibold">menunggu validasi</span> sebelum stok sistem ditambahkan.
                        </p>
                    </div>

                    <div class="flex justify-end gap-3">
                        <x-filament::button 
                            @click="openConfirm = false" 
                            color="gray" 
                            size="sm"
                        >
                            Batal
                        </x-filament::button>

                        <x-filament::button 
                            @click="openConfirm = false; $wire.kirim()" 
                            color="success" 
                            size="sm"
                            icon="heroicon-o-paper-airplane"
                        >
                            Ya, Kirim Dokumen
                        </x-filament::button>
                    </div>
                </div>
            </div>
        </div>

    </div>
</x-filament-panels::page>
