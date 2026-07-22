<div class="space-y-6">

    {{-- HEADER PILIH JENIS STOK (collapsible) --}}
    <div class="rounded-2xl border border-gray-200 dark:border-gray-700/50 bg-white dark:bg-gradient-to-br dark:from-gray-800/80 dark:to-gray-900/80 p-6 shadow-sm dark:shadow-xl">

        {{-- Header bar (klik untuk expand/collapse) --}}
        <div class="flex items-center justify-between cursor-pointer select-none" wire:click="toggleHeader">
            <div class="flex items-center gap-4">
                <div class="flex items-center justify-center w-10 h-10 rounded-xl bg-orange-100 dark:bg-orange-500/20 border border-orange-300 dark:border-orange-500/30">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-orange-500 dark:text-orange-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                    </svg>
                </div>
                <div>
                    <h2 class="text-gray-900 dark:text-white font-semibold text-lg">Stock Opname</h2>
                    <p class="text-gray-500 dark:text-gray-400 text-sm">
                        @if($jenisStok && $headerCollapsed)
                            Jenis stok:
                            <span class="text-orange-600 dark:text-orange-400 font-medium">
                                {{ \App\Livewire\OpnameStokTable::JENIS_STOK_LABELS[$jenisStok] ?? $jenisStok }}
                            </span>
                            <span class="text-gray-400 dark:text-gray-500">— klik untuk ganti</span>
                        @else
                            Pilih jenis stok untuk memulai opname
                        @endif
                    </p>
                </div>
            </div>
            <svg xmlns="http://www.w3.org/2000/svg"
                 class="h-5 w-5 text-gray-400 transition-transform duration-200 {{ $headerCollapsed ? '' : 'rotate-180' }}"
                 fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
        </div>

        {{-- GRID JENIS STOK --}}
        @unless($headerCollapsed)
        <div class="grid grid-cols-3 sm:grid-cols-5 gap-3 mt-6">
            @foreach([
    'veneer_basah'  => ['label' => 'Veneer Basah',      'icon' => '💧'],
    'veneer_kering' => ['label' => 'Veneer Kering',     'icon' => '☀️'],
    'veneer_jadi'   => ['label' => 'Veneer Jadi',       'icon' => '📄'],
    'platform_mth'  => ['label' => 'Platform MTH',      'icon' => '🧱'],
    'triplek_mth'   => ['label' => 'Triplek MTH',       'icon' => '🗂️'],
    'platform_jadi' => ['label' => 'Platform Jadi',     'icon' => '🟫'],
    'triplek_jadi'  => ['label' => 'Triplek Jadi',      'icon' => '📚'],
    'gudang_satu'   => ['label' => 'Gudang Satu',       'icon' => '🏬'],
    'plywood'       => ['label' => 'Plywood Siap Jual', 'icon' => '✨'],
] as $value => $item)
            <button
                wire:click="$set('jenisStok', '{{ $value }}')"
                class="relative flex flex-col items-center gap-2 p-4 rounded-xl border transition-all duration-200 text-center
                    {{ $jenisStok === $value
                        ? 'border-orange-500 bg-orange-50 dark:bg-orange-500/15 shadow-lg shadow-orange-500/10'
                        : 'border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50 hover:border-gray-400 dark:hover:border-gray-500 hover:bg-gray-100 dark:hover:bg-gray-700/50' }}"
            >
                @if($jenisStok === $value)
                <span class="absolute top-2 right-2 w-2 h-2 rounded-full bg-orange-500 dark:bg-orange-400"></span>
                @endif
                <span class="text-2xl">{{ $item['icon'] }}</span>
                <span class="text-xs font-medium {{ $jenisStok === $value ? 'text-orange-600 dark:text-orange-300' : 'text-gray-700 dark:text-gray-300' }} leading-tight">
                    {{ $item['label'] }}
                </span>
            </button>
            @endforeach
        </div>
        @endunless
    </div>

    {{-- TABEL --}}
    @if($jenisStok && count($rows) > 0)
    <div class="rounded-2xl border border-gray-200 dark:border-gray-700/50 bg-white dark:bg-gray-900/80 shadow-sm dark:shadow-xl">

        {{-- TABEL HEADER INFO --}}
        <div class="flex items-center justify-between px-4 py-3 border-b border-gray-200 dark:border-gray-700/50 bg-gray-50 dark:bg-gray-800/50 rounded-t-2xl">
            <div class="flex items-center gap-2">
                <span class="text-gray-900 dark:text-white font-medium text-sm">
                    {{ \App\Livewire\OpnameStokTable::JENIS_STOK_LABELS[$jenisStok] ?? $jenisStok }}
                </span>
                <span class="px-2 py-0.5 rounded-full bg-orange-100 dark:bg-orange-500/20 text-orange-600 dark:text-orange-400 text-xs font-mono">
                    {{ count($rows) }} barang
                </span>
            </div>
            <span class="text-gray-400 dark:text-gray-500 text-xs">Isi kolom Stok Fisik &amp; Kbk Fisik untuk barang yang ingin disesuaikan</span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left">
                <thead class="bg-gray-100 dark:bg-gray-800/80 text-gray-500 dark:text-gray-400 text-xs uppercase tracking-wider">
                    <tr>
                        <th class="px-3 py-3 w-8 text-center">#</th>
                        <th class="px-3 py-3">{{ $jenisStok === 'platform_jadi' ? 'Jenis Barang' : 'Jenis Kayu' }}</th>
                        <th class="px-3 py-3">Ukuran</th>
                        <th class="px-3 py-3">Grade</th>
                        <th class="px-3 py-3 text-right">Stok Sistem</th>
                        <th class="px-3 py-3 text-right">Kbk Sistem</th>
                        <th class="px-3 py-3">Stok Fisik</th>
                        <th class="px-3 py-3">Kbk Fisik</th>
                        <th class="px-3 py-3">Catatan</th>
                        <th class="px-3 py-3 w-8"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700/50">
                    @foreach($rows as $i => $row)
                    @php $diisi = isset($row['stok_fisik']) && $row['stok_fisik'] !== null && $row['stok_fisik'] !== ''; @endphp
                    <tr wire:key="row-{{ $i }}" class="transition-colors {{ $diisi
                        ? 'bg-green-50 hover:bg-green-100 dark:bg-green-900/10 dark:hover:bg-green-900/20'
                        : 'bg-white hover:bg-gray-50 dark:bg-gray-900/50 dark:hover:bg-gray-800/30' }}">

                        <td class="px-3 py-2 text-center text-gray-400 dark:text-gray-500 text-xs">{{ $i + 1 }}</td>

                        {{-- Jenis Kayu / Barang --}}
                        <td class="px-3 py-2">
                            @if($jenisStok === 'platform_jadi')
                                <div x-data="searchSelect({
                                    options: {{ json_encode(collect($jenisBarangOptions)->map(fn($v,$k) => ['id'=>$k,'label'=>$v])->values()) }},
                                    selected: {{ $row['id_jenis_barang'] ?? 'null' }},
                                    onChange: (val) => $wire.set('rows.{{ $i }}.id_jenis_barang', val)
                                })">
                                    @include('livewire.partials.search-select')
                                </div>
                            @else
                                <div x-data="searchSelect({
                                    options: {{ json_encode(collect($jenisKayuOptions)->map(fn($v,$k) => ['id'=>$k,'label'=>$v])->values()) }},
                                    selected: {{ $row['id_jenis_kayu'] ?? 'null' }},
                                    onChange: (val) => $wire.set('rows.{{ $i }}.id_jenis_kayu', val)
                                })">
                                    @include('livewire.partials.search-select')
                                </div>
                            @endif
                        </td>

                        {{-- Ukuran --}}
                        <td class="px-3 py-2">
                            <div x-data="searchSelect({
                                options: {{ json_encode(collect($ukuranOptions)->map(fn($v,$k) => ['id'=>$k,'label'=>$v])->values()) }},
                                selected: {{ $row['id_ukuran'] ?? 'null' }},
                                onChange: (val) => $wire.set('rows.{{ $i }}.id_ukuran', val)
                            })">
                                @include('livewire.partials.search-select')
                            </div>
                        </td>

                        {{-- Grade --}}
                        <td class="px-3 py-2">
                            <div x-data="searchSelect({
                                options: {{ json_encode(collect($gradeOptions)->map(fn($v,$k) => ['id'=>$k,'label'=>$v])->values()) }},
                                selected: '{{ $row['kw'] ?? '' }}',
                                onChange: (val) => $wire.set('rows.{{ $i }}.kw', val)
                            })">
                                @include('livewire.partials.search-select')
                            </div>
                        </td>

                        {{-- Stok Sistem --}}
                        <td class="px-3 py-2 text-right">
                            <span class="font-mono text-orange-600 dark:text-orange-400 font-semibold text-sm">
                                {{ number_format($row['stok_sistem'] ?? 0) }}
                            </span>
                            <span class="text-gray-400 dark:text-gray-500 text-xs ml-1">lbr</span>
                        </td>

                        {{-- Kbk Sistem --}}
                        <td class="px-3 py-2 text-right">
                            <span class="font-mono text-blue-600 dark:text-blue-400 text-xs">
                                {{ number_format($row['kubikasi_sistem'] ?? 0, 4) }}
                            </span>
                            <span class="text-gray-400 dark:text-gray-500 text-xs ml-1">m³</span>
                        </td>

                        {{-- Stok Fisik --}}
                        <td class="px-3 py-2">
                            <input
                                type="number"
                                wire:model.lazy="rows.{{ $i }}.stok_fisik"
                                placeholder="-"
                                class="w-24 rounded-lg border {{ $diisi
                                    ? 'border-green-400 bg-green-50 dark:border-green-500/50 dark:bg-green-900/20'
                                    : 'border-gray-300 bg-white dark:border-gray-600 dark:bg-gray-700/80' }}
                                    text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500
                                    px-2 py-1.5 text-xs text-right focus:outline-none focus:ring-1 focus:ring-green-500 focus:border-green-500 transition-colors"
                            />
                        </td>

                        {{-- Kbk Fisik --}}
                        <td class="px-3 py-2">
                            <input
                                type="number"
                                step="0.0001"
                                wire:model.lazy="rows.{{ $i }}.kubikasi_fisik"
                                placeholder="-"
                                class="w-28 rounded-lg border {{ $diisi
                                    ? 'border-green-400 bg-green-50 dark:border-green-500/50 dark:bg-green-900/20'
                                    : 'border-gray-300 bg-white dark:border-gray-600 dark:bg-gray-700/80' }}
                                    text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500
                                    px-2 py-1.5 text-xs text-right focus:outline-none focus:ring-1 focus:ring-green-500 focus:border-green-500 transition-colors"
                            />
                        </td>

                        {{-- Catatan --}}
                        <td class="px-3 py-2">
                            <input
                                type="text"
                                wire:model.lazy="rows.{{ $i }}.catatan"
                                placeholder="Opsional"
                                class="w-36 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700/80
                                    text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500
                                    px-2 py-1.5 text-xs focus:outline-none focus:ring-1 focus:ring-gray-400 dark:focus:ring-gray-500 transition-colors"
                            />
                        </td>

                        {{-- Hapus --}}
                        <td class="px-3 py-2">
                            <button
                                wire:click="hapusBaris({{ $i }})"
                                wire:confirm="Hapus baris ini?"
                                class="text-gray-400 dark:text-gray-600 hover:text-red-500 dark:hover:text-red-400 transition-colors p-1 rounded"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                </svg>
                            </button>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- FOOTER --}}
        <div class="flex items-center justify-between px-4 py-3 border-t border-gray-200 dark:border-gray-700/50 bg-gray-50 dark:bg-gray-800/30 rounded-b-2xl">
            <button
                wire:click="tambahBaris"
                class="flex items-center gap-2 text-sm text-orange-600 dark:text-orange-400 hover:text-orange-500 dark:hover:text-orange-300 border border-orange-300 dark:border-orange-500/30 hover:border-orange-400 dark:hover:border-orange-400/60 rounded-lg px-3 py-2 transition-all"
            >
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                Tambah Baris Baru
            </button>

            <button
                wire:click="submit"
                wire:loading.attr="disabled"
                class="flex items-center gap-2 bg-orange-500 hover:bg-orange-600 disabled:opacity-50 text-white font-semibold px-6 py-2 rounded-xl transition-colors shadow-lg shadow-orange-500/20"
            >
                <span wire:loading.remove wire:target="submit">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 inline mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                    Sesuaikan Stok Sekarang
                </span>
                <span wire:loading wire:target="submit" class="flex items-center gap-2">
                    <svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 12 0 12 0v4a8 8 0 00-8 8H0z"></path>
                    </svg>
                    Memproses...
                </span>
            </button>
        </div>
    </div>

    @elseif($jenisStok)
    <div class="rounded-2xl border border-gray-200 dark:border-gray-700/50 bg-white dark:bg-gray-900/50 p-12 text-center">
        <p class="text-gray-400 dark:text-gray-500 mb-3">Tidak ada data stok untuk jenis ini.</p>
        <button wire:click="tambahBaris" class="text-orange-600 dark:text-orange-400 hover:text-orange-500 dark:hover:text-orange-300 text-sm border border-orange-300 dark:border-orange-500/30 px-4 py-2 rounded-lg">
            + Tambah baris baru
        </button>
    </div>
    @endif

</div>

{{-- Alpine.js search select component --}}
<script>
function searchSelect({ options, selected, onChange }) {
    return {
        options,
        selected,
        search: '',
        open: false,
        onChange,
        dropdownStyle: '',
        get filtered() {
            if (!this.search) return this.options;
            const q = this.search.toLowerCase();
            return this.options.filter(o => o.label.toLowerCase().includes(q));
        },
        get selectedLabel() {
            const found = this.options.find(o => String(o.id) === String(this.selected));
            return found ? found.label : null;
        },
        toggle() {
            this.open = !this.open;
            if (this.open) {
                this.$nextTick(() => this.positionDropdown());
            }
        },
        positionDropdown() {
            const btn = this.$refs.trigger;
            if (!btn) return;
            const rect = btn.getBoundingClientRect();
            const dropdownHeight = 280;
            const spaceBelow = window.innerHeight - rect.bottom;

            const top = spaceBelow < dropdownHeight
                ? Math.max(8, rect.top - dropdownHeight - 4)
                : rect.bottom + 4;

            this.dropdownStyle = `position:fixed; top:${top}px; left:${rect.left}px; min-width:${rect.width}px; z-index:9999;`;
        },
        select(id) {
            this.selected = id;
            this.onChange(id);
            this.open = false;
            this.search = '';
        }
    }
}
</script>