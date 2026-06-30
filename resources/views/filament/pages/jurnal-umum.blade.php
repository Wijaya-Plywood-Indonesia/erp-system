@push('styles')
<link href="https://cdn.jsdelivr.net/npm/tom-select/dist/css/tom-select.css" rel="stylesheet">
@endpush
<x-filament::page>
    @push('styles')
    <link href="https://cdn.jsdelivr.net/npm/tom-select/dist/css/tom-select.css" rel="stylesheet">
    <style>
        /* Container Utama */
        .ts-wrapper.single .ts-control {
            background-color: white !important;
            border-color: #d1d5db !important;
            color: #1f2937 !important;
            border-radius: 0.5rem !important;
            padding: 0.5rem !important;
            box-shadow: none !important;
        }

        .dark .ts-wrapper.single .ts-control {
            background-color: #111827 !important;
            border-color: #374151 !important;
            color: #f3f4f6 !important;
        }

        /* Input Pencarian */
        .ts-wrapper.single .ts-control input {
            color: inherit !important;
        }

        /* Dropdown List */
        .ts-dropdown {
            background-color: white !important;
            border-color: #d1d5db !important;
            color: #1f2937 !important;
            border-radius: 0.5rem !important;
            margin-top: 5px !important;
        }

        /* Dark Mode - Dropdown */
        .dark .ts-dropdown {
            background-color: #111827 !important;
            border-color: #374151 !important;
            color: #f3f4f6 !important;
        }

        /* Dropdown Options */
        .ts-dropdown .option {
            padding: 8px 12px !important;
        }

        /* Hover/Active Option */
        .ts-dropdown .active {
            background-color: #fbbf24 !important;
            /* Primary / Amber (Filament style) */
            color: black !important;
        }

        .dark .ts-dropdown .active {
            background-color: #f59e0b !important;
            color: white !important;
        }
    </style>
    @endpush


    {{-- HEADER --}}
    <div class="grid grid-cols-3 gap-4">
        <input type="date" wire:model="tanggal" class="border rounded p-2
                   bg-white dark:bg-gray-900
                   text-gray-800 dark:text-gray-100
                   border-gray-300 dark:border-gray-700">

        <input wire:model="kode_jurnal" placeholder="Jurnal" class="border rounded p-2
                   bg-white dark:bg-gray-900
                   text-gray-800 dark:text-gray-100
                   border-gray-300 dark:border-gray-700">
        <input wire:model="no_dokumen" placeholder="No" class="border rounded p-2
                   bg-white dark:bg-gray-900
                   text-gray-800 dark:text-gray-100
                   border-gray-300 dark:border-gray-700">
    </div>

    {{-- FORM --}}
    <div id="form-jurnal" class="scroll-mt-10 mt-6 border rounded-lg p-4 grid grid-cols-2 gap-4
                bg-white dark:bg-gray-900
                border-gray-200 dark:border-gray-700
                text-gray-800 dark:text-gray-100">

        {{-- NO AKUN --}}
        <div wire:ignore class="col-span-1">
            <label class="text-sm font-medium">No Akun</label>
            <select id="no_akun" class="w-full"> {{-- Hapus class border/bg di sini --}}
                <option value="">-- Pilih Akun --</option>
                @foreach ($akunList as $a)
<option value="{{ $a->kode }}">
    {{ $a->kode }} - {{ $a->nama }}
</option>
@endforeach
            </select>
        </div>

        <div>
            <label class="text-sm font-medium">Nama Akun</label>
            <input wire:model="form.nama_akun" readonly class="border rounded p-2 w-full
                       bg-gray-100 dark:bg-gray-800
                       text-gray-700 dark:text-gray-300
                       border-gray-300 dark:border-gray-600">
        </div>

        {{-- NAMA --}}
        <div>
            <label class="text-sm font-medium">Nama</label>
            <input wire:model="form.nama" class="border rounded p-2 w-full
                       bg-white dark:bg-gray-800
                       text-gray-800 dark:text-gray-100
                       border-gray-300 dark:border-gray-600">
        </div>

        {{-- MM --}}
        <div>
            <label class="text-sm font-medium">MM (Tebal Plywood)</label>
            <input wire:model="form.mm" class="border rounded p-2 w-full
                       bg-white dark:bg-gray-800
                       text-gray-800 dark:text-gray-100
                       border-gray-300 dark:border-gray-600">
        </div>

        {{-- KETERANGAN --}}
        <div class="col-span-2">
            <label class="text-sm font-medium">Keterangan</label>
            <textarea wire:model="form.keterangan" class="border rounded p-2 w-full
                       bg-white dark:bg-gray-800
                       text-gray-800 dark:text-gray-100
                       border-gray-300 dark:border-gray-600"></textarea>
        </div>

        {{-- POSISI --}}
        <div class="col-span-2">
            <label class="text-sm font-medium">Posisi</label>
            <div class="flex gap-6 mt-1">
                <label class="flex items-center gap-2">
                    <input type="radio" wire:model="form.map" value="D"> Debit
                </label>
                <label class="flex items-center gap-2">
                    <input type="radio" wire:model="form.map" value="K"> Kredit
                </label>
            </div>
        </div>

        {{-- HIT KBK --}}
        <div class="col-span-2">
            <label class="text-sm font-medium">Hit KBK <span class="text-red-500">*</span></label>
            <select wire:model.live="form.hit_kbk" class="border rounded p-2 w-full
               bg-white dark:bg-gray-800
               text-gray-800 dark:text-gray-100
               border-gray-300 dark:border-gray-600" required>
                <option value="">-- Pilih --</option>
                <option value="banyak">Banyak (Pcs/Lbr)</option>
                <option value="m3">Kubikasi (M3)</option>
            </select>

            {{-- Menampilkan pesan error jika belum dipilih --}}
            @error('form.hit_kbk')
            <span class="text-sm text-red-400 mt-1">{{ $message }}</span>
            @enderror
        </div>

        {{-- BANYAK --}}
        <div>
            <label class="text-sm font-medium">Banyak</label>
            <input type="number" wire:model="form.banyak" class="border rounded p-2 w-full
                       bg-white dark:bg-gray-800
                       text-gray-800 dark:text-gray-100
                       border-gray-300 dark:border-gray-600">
        </div>

        {{-- M3 --}}
        <div>
            <label class="text-sm font-medium">Kubikasi (M3)</label>
            <input type="number" step="0.0001" wire:model="form.m3" class="border rounded p-2 w-full
                       bg-white dark:bg-gray-800
                       text-gray-800 dark:text-gray-100
                       border-gray-300 dark:border-gray-600">
        </div>

        {{-- HARGA --}}
        <div class="col-span-2">
            <label class="text-sm font-medium">Harga</label>
            <input type="number" wire:model="form.harga" class="border rounded p-2 w-full
                       bg-white dark:bg-gray-800
                       text-gray-800 dark:text-gray-100
                       border-gray-300 dark:border-gray-600">
        </div>

        @if ($editingId)
        <div class="col-span-2 flex gap-3">
            <button wire:click="updateJurnal" class="flex-1 bg-yellow-500 hover:bg-yellow-600 text-white rounded p-2">
                💾 Update Jurnal
            </button>

            <button wire:click="cancelEdit" class="flex-1 bg-gray-500 hover:bg-gray-600 text-white rounded p-2">
                ✖ Batal
            </button>
        </div>
        @else
        <button wire:click="addItem" class="col-span-2 bg-primary-600 hover:bg-primary-700 text-white rounded p-2">
            + Tambah ke Draft
        </button>
        @endif

    </div>

    {{-- DRAFT JURNAL --}}
    <h3 class="mt-8 font-bold">Draft Jurnal</h3>

    <div class="overflow-x-auto border rounded-lg mt-2
                border-gray-200 dark:border-gray-700">
        <table class="min-w-[1200px] w-full text-sm border-collapse">
            <thead class="bg-gray-100 dark:bg-gray-800 sticky top-0">
                <tr class="border-b border-gray-200 dark:border-gray-700">
                    <th class="px-2 py-1 w-[120px]">No Akun</th>
                    <th class="px-2 py-1 w-[220px]">Nama Akun</th>
                    <th class="px-2 py-1 w-[150px]">Nama</th>
                    <th class="px-2 py-1 w-[60px]">D/K</th>
                    <th class="px-2 py-1 w-[80px]">Hit Kbk</th>
                    <th class="px-2 py-1 w-[90px] text-right">Banyak</th>
                    <th class="px-2 py-1 w-[90px] text-right">M3</th>
                    <th class="px-2 py-1 w-[120px] text-right">Harga</th>
                    <th class="px-2 py-1 w-[130px] text-right">Total</th>
                    <th class="px-2 py-1 w-[40px]"></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($items as $i => $row)
                <tr class="border-b
                               hover:bg-gray-50 dark:hover:bg-gray-800">
                    <td class="px-2 py-1">{{ $row['no_akun'] }}</td>
                    <td class="px-2 py-1">{{ $row['nama_akun'] }}</td>
                    <td class="px-2 py-1">{{ $row['nama'] }}</td>
                    <td class="px-2 py-1 text-center">
                        {{ strtolower($row['map']) === 'd' ? 'Debit' : 'Kredit' }}
                    </td>

                    <td class="px-2 py-1">
                        {{ strtolower($row['hit_kbk']) === 'b' || $row['hit_kbk'] === 'banyak'
                        ? 'Banyak'
                        : 'Kubikasi'
                        }}
                    </td>
                    <td class="px-2 py-1 text-right">{{ $row['banyak'] }}</td>
                    <td class="px-2 py-1 text-right">{{ $row['m3'] }}</td>
                    <td class="px-2 py-1 text-right">{{ number_format($row['harga']) }}</td>
                    <td class="px-2 py-1 text-right font-semibold">
                        {{ number_format($row['total']) }}
                    </td>
                    <td class="px-2 py-1 text-center">
                        <button wire:click="removeItem({{ $i }})" class="text-red-600 dark:text-red-400 font-bold">
                            ✕
                        </button>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{-- TOTAL --}}
    <div class="mt-4 flex gap-10 font-bold">
        <div>Total Debit: {{ number_format($this->totalDebit) }}</div>
        <div>Total Kredit: {{ number_format($this->totalKredit) }}</div>
    </div>

    <button wire:click="saveJurnal" class="mt-4 bg-green-600 hover:bg-green-700 text-white rounded px-4 py-2"
        @disabled($this->totalDebit !== $this->totalKredit)>
        Simpan Jurnal
    </button>

    <hr class="my-10">

    {{-- JURNAL FINAL --}}
    <h3 class="font-bold text-lg mb-3">📘 Jurnal Umum (Final)</h3>

    @if ($jurnals->where('status', 'belum sinkron')->count())
    <x-filament::button wire:click="mountAction('syncJurnal')" color="success" icon="heroicon-o-arrow-path"
        class="mb-4">
        Sinkronisasi Jurnal
    </x-filament::button>

    @endif

    <div x-ref="scrollContainer" class="overflow-y-auto max-h-[75vh] border rounded-lg
                border-gray-200 dark:border-gray-700">
        <table class="min-w-[1800px] w-full text-sm border-collapse">
            <thead class="bg-gray-100 dark:bg-gray-800 sticky top-0">
                <tr class="border-b border-gray-200 dark:border-gray-700">
                    <th class="px-2 py-1 w-[220px]">Nama Akun</th>
                    <th class="px-2 py-1 w-[100px]">Tgl</th>
                    <th class="px-2 py-1 w-[80px]">Jurnal</th>
                    <th class="px-2 py-1 w-[120px]">No Akun</th>
                    <th class="px-2 py-1 w-[120px]">No Dok</th>
                    <th class="px-2 py-1 w-[150px]">Nama</th>
                    <th class="px-2 py-1 w-[350px]">Keterangan</th>
                    <th class="px-2 py-1 w-[80px] text-center">Map</th>
                    <th class="px-2 py-1 w-[60px]">MM</th>
                    <th class="px-2 py-1 w-[80px]">Hit KBK</th>
                    <th class="px-2 py-1 w-[90px] text-right">Banyak</th>
                    <th class="px-2 py-1 w-[90px] text-right">M3</th>
                    <th class="px-2 py-1 w-[120px] text-right">Harga</th>
                    <th class="px-2 py-1 w-[130px] text-right">Total</th>
                    <th class="px-2 py-1 w-[120px]">Dibuat Oleh</th>
                    <th class="px-2 py-1 w-[90px]">Status</th>
                    <th class="px-2 py-1 w-[160px]">Disinkron Pada</th>
                    <th class="px-2 py-1 w-[140px]">Disinkron Oleh</th>
                    <th class="px-2 py-1 w-[90px] text-center">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($jurnals as $j)
                <tr class="border-b
                               hover:bg-gray-50 dark:hover:bg-gray-800 text-center">
                    <td class="px-2 py-1">{{ $j->nama_akun }}</td>
                    <td class="px-2 py-1">{{ $j->tgl?->format('Y-m-d') }}</td>
                    <td class="px-2 py-1">{{ $j->jurnal }}</td>
                    <td class="px-2 py-1">{{ $j->no_akun }}</td>
                    <td class="px-2 py-1">{{ $j->no_dokumen }}</td>
                    <td class="px-2 py-1">{{ $j->nama }}</td>
                    <td class="px-2 py-1">{{ $j->keterangan }}</td>
                    <td class="px-2 py-1 font-semibold">
                        {{ strtolower($j->map) === 'd' ? 'Debit' : 'Kredit' }}
                    </td>
                    <td class="px-2 py-1">{{ $j->mm }}</td>
                    <td class="px-2 py-1">{{ $j->hit_kbk }}</td>
                    <td class="px-2 py-1">{{ $j->banyak }}</td>
                    <td class="px-2 py-1">{{ $j->m3 }}</td>
                    <td class="px-2 py-1">{{ number_format($j->harga) }}</td>
                    <td class="px-2 py-1 font-semibold">
                        @php
                        if ($j->hit_kbk === 'b') {
                        $total = ($j->banyak ?? 0) * ($j->harga ?? 0);
                        } elseif ($j->hit_kbk === 'k') {
                        $total = ($j->m3 ?? 0) * ($j->harga ?? 0);
                        } else {
                        $total = $j->harga ?? 0;
                        }
                        @endphp

                        {{ number_format($total) }}
                    </td>

                    <td class="px-2 py-1">{{ $j->created_by }}</td>
                    <td class="px-2 py-1 font-semibold text-green-600">{{ $j->status }}</td>
                    <td class="px-2 py-1">{{ $j->synced_at?->format('d/m/Y H:i') }}</td>
                    <td class="px-2 py-1">{{ $j->synced_by }}</td>
                    <td class="px-2 py-1 text-center">
                        <div class="flex justify-center gap-2">
                            <button wire:click="editJurnal({{ $j->id }})" class="px-2 py-1 text-xs rounded
                                           bg-yellow-500 hover:bg-yellow-600 text-white">
                                Edit
                            </button>
                            <button wire:click="confirmDelete({{ $j->id }})"
                                onclick="confirm('Yakin hapus jurnal ini?') || event.stopImmediatePropagation()"
                                class="px-2 py-1 text-xs rounded bg-red-600 hover:bg-red-700 text-white">
                                Hapus
                            </button>
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @if($hasMore)
        <div wire:loading.flex class="justify-center items-center py-6">
            <div class="flex items-center gap-3 text-primary-600 text-lg font-semibold">
                <svg class="animate-spin h-6 w-6" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z">
                    </path>
                </svg>
                Memuat data jurnal...
            </div>
        </div>
        @endif
        @if(!$hasMore)
        <div class="text-center py-6 text-gray-500 text-sm">
            Semua data sudah ditampilkan
        </div>
        @endif
        <div x-data="{
            observe() {
                let observer = new IntersectionObserver((entries) => {
                    entries.forEach(entry => {
                        if (entry.isIntersecting) {
                            @this.loadMore()
                        }
                    })
                }, {
                    root: this.$refs.scrollContainer,
                    rootMargin: '200px'
                })

                observer.observe(this.$el)
            }
        }" x-init="observe" class="h-10"></div>
    </div>
    <script>
        document.addEventListener('livewire:init', () => {
            Livewire.on('scroll-to-form', (event) => {
                const element = document.getElementById('form-jurnal');
                if (element) {
                    element.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });
    </script>
    @push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/tom-select/dist/js/tom-select.complete.min.js"></script>
    <script>
        function initTomSelect() {
            const el = document.getElementById('no_akun');
            if (!el) return;

            // Hapus instansi lama jika ada (mencegah double render)
            if (el.tomselect) {
                el.tomselect.destroy();
            }

            new TomSelect(el, {
                create: false,
                searchField: ['text'],
                placeholder: 'Cari / pilih no akun...',
                allowEmptyOption: true,
                onChange(value) {
                    @this.set('form.no_akun', value);
                }
            });
        }

        document.addEventListener('DOMContentLoaded', initTomSelect);
        // Jika menggunakan Livewire 3/Filament Navigasi
        document.addEventListener('livewire:navigated', initTomSelect);

        // Listener jika field harus berubah saat Anda klik tombol "Edit" di tabel
        window.addEventListener('set-no-akun', event => {
            const ts = document.getElementById('no_akun').tomselect;
            if (ts) ts.setValue(event.detail.value);
        });
    </script>
    @endpush

</x-filament::page>