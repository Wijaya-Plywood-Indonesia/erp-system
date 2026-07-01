<x-filament-panels::page>
    <div class="flex flex-col gap-4" x-data="{
        changes: {},
        open: false,
        action: null,
        selected: [],
    
        confirm(action) {
            this.selected = [
                ...document.querySelectorAll('.pending-checkbox:checked')
            ].map(el => el.value);
    
            if (this.selected.length === 0) {
                alert('Pilih data terlebih dahulu');
                return;
            }
    
            this.action = action;
            this.open = true;
        },
    
        submit() {
            if (this.action === 'approve') {
                $wire.approve(this.selected);
            }
            if (this.action === 'reject') {
                $wire.reject(this.selected);
            }
            this.open = false;
        }
    }">






        @if ($hasRejected || $hasPending)
            @php
                $isSubmitter =
                    $this->pendingPrices->count() &&
                    $this->pendingPrices->first()?->updated_by === auth()->user()->name;
            @endphp

            <x-filament::section>
                <x-slot name="heading">
                    <div class="flex items-center justify-between w-full">
                        <span>Pengajuan Aktif</span>
                        @if ($isSubmitter)
                            <x-filament::button color="danger" size="sm" outlined wire:click="cancelSubmission"
                                wire:confirm="Batalkan seluruh pengajuan harga?" icon="heroicon-m-x-circle">
                                Batalkan Pengajuan
                            </x-filament::button>
                        @endif
                    </div>
                </x-slot>
                <x-slot name="description">
                    {{ $isSubmitter ? 'Pengajuan Anda sedang menunggu persetujuan.' : 'Tinjau dan setujui atau tolak pengajuan berikut.' }}
                </x-slot>

                <div class="overflow-scroll rounded-lg border border-gray-200 dark:border-white/10">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="bg-gray-50 dark:bg-white/5 border-b border-gray-200 dark:border-white/10">
                                @if (!$isSubmitter)
                                    <th class="w-10 px-4 py-3">
                                        <input type="checkbox" x-data
                                            class="rounded border-gray-300 dark:border-gray-600 text-primary-600"
                                            @change="
                                                document.querySelectorAll('.pending-checkbox')
                                                .forEach(el => el.checked = $event.target.checked)
                                            ">
                                    </th>
                                @endif
                                <th
                                    class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">
                                    Jenis Kayu</th>
                                <th
                                    class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">
                                    Panjang</th>
                                <th
                                    class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">
                                    Grade</th>
                                <th
                                    class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">
                                    Diameter</th>
                                <th
                                    class="px-4 py-3 text-right text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">
                                    Harga Lama</th>
                                <th
                                    class="px-4 py-3 text-right text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">
                                    Harga Baru</th>
                                <th
                                    class="px-4 py-3 text-center text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">
                                    Status</th>
                                <th
                                    class="px-4 py-3 text-center text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">
                                    Catatan</th>
                            </tr>
                        </thead>

                        <tbody>
                            @foreach ($this->pendingPrices as $price)
                                <tr
                                    class="border-b border-gray-100 dark:border-white/5 last:border-0 hover:bg-gray-50/50 dark:hover:bg-white/[0.02] transition-colors">
                                    @if (!$isSubmitter)
                                        <td class="px-4 py-3 text-center">
                                            <input type="checkbox"
                                                class="pending-checkbox rounded border-gray-300 dark:border-gray-600 text-primary-600"
                                                value="{{ $price->id }}">
                                        </td>
                                    @endif

                                    <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">
                                        {{ $price->jenisKayu->nama_kayu }}</td>
                                    <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ $price->panjang }}m</td>
                                    <td class="px-4 py-3 text-gray-600 dark:text-gray-300">Grade
                                        {{ $price->grade == 1 ? 'A' : 'B' }}</td>
                                    <td class="px-4 py-3 text-gray-600 dark:text-gray-300 font-mono text-xs">
                                        {{ $price->diameter_terkecil }} – {{ $price->diameter_terbesar }}</td>
                                    <td
                                        class="px-4 py-3 text-right text-gray-400 dark:text-gray-500 text-xs line-through">
                                        Rp {{ number_format($price->harga_beli, 0, ',', '.') }}
                                    </td>
                                    <td
                                        class="px-4 py-3 text-right font-semibold text-warning-600 dark:text-warning-400">
                                        Rp {{ number_format($price->harga_baru, 0, ',', '.') }}
                                    </td>

                                    {{-- Status --}}
                                    <td class="px-4 py-3 text-center">
                                        @if ($price->status === 'ditolak')
                                            <span
                                                class="inline-flex items-center gap-1 px-2 py-1 rounded-md text-xs font-medium bg-danger-50 text-danger-700 ring-1 ring-danger-200 dark:bg-danger-950 dark:text-danger-300 dark:ring-danger-800">
                                                <x-heroicon-m-arrow-path class="w-3 h-3" />
                                                Perlu Revisi
                                            </span>
                                        @else
                                            <span
                                                class="inline-flex items-center gap-1 px-2 py-1 rounded-md text-xs font-medium bg-warning-50 text-warning-700 ring-1 ring-warning-200 dark:bg-warning-950 dark:text-warning-300 dark:ring-warning-800">
                                                <x-heroicon-m-clock class="w-3 h-3" />
                                                Menunggu
                                            </span>
                                        @endif
                                    </td>

                                    {{-- Catatan --}}
                                    <td class="px-4 py-3 text-center">
                                        @if ($price->catatan_penolakan)
                                            <button type="button"
                                                wire:click="openRevisionNoteModal({{ $price->id }})"
                                                class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-md text-xs font-medium bg-danger-50 text-danger-700 hover:bg-danger-100 dark:bg-danger-950 dark:text-danger-300 dark:hover:bg-danger-900 transition-colors">
                                                <x-heroicon-m-chat-bubble-left-ellipsis class="w-3.5 h-3.5" />
                                                {{ $isSubmitter ? 'Lihat' : 'Lihat / Edit' }}
                                            </button>
                                        @elseif (!$isSubmitter)
                                            <button type="button" wire:click="openRevisionModal({{ $price->id }})"
                                                class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-md text-xs font-medium bg-gray-100 text-gray-600 hover:bg-warning-50 hover:text-warning-700 dark:bg-white/5 dark:text-gray-400 dark:hover:bg-warning-950 dark:hover:text-warning-300 transition-colors">
                                                <x-heroicon-m-pencil-square class="w-3.5 h-3.5" />
                                                Tulis Catatan
                                            </button>
                                        @else
                                            <span class="text-gray-300 dark:text-gray-600 text-xs">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- Bulk actions untuk approver --}}
                @if (!$isSubmitter)
                    <div
                        class="flex items-center justify-between pt-4 mt-4 border-t border-gray-100 dark:border-white/5">
                        <p class="text-xs text-gray-400 dark:text-gray-500">Centang baris lalu pilih aksi</p>
                        <div class="flex gap-2">
                            <x-filament::button color="danger" size="sm" outlined icon="heroicon-m-x-mark"
                                x-on:click="confirm('reject')">
                                Tolak Terpilih
                            </x-filament::button>
                            <x-filament::button color="success" size="sm" icon="heroicon-m-check"
                                x-on:click="confirm('approve')">
                                Setujui Terpilih
                            </x-filament::button>
                        </div>
                    </div>
                @endif
            </x-filament::section>
        @endif

        {{-- ACTION BAR --}}
        <div class="flex items-center justify-between">
            @if ($hasPending)
                <div class="flex items-center gap-2 text-sm font-medium text-warning-600 dark:text-warning-400">
                    <x-heroicon-m-clock class="w-4 h-4" />
                    Terdapat pengajuan harga yang menunggu persetujuan
                </div>
            @else
                <div></div>
            @endif

            @if (!$hasPending)
                <x-filament::button wire:click="submit" icon="heroicon-m-paper-airplane">
                    Ajukan Perubahan
                </x-filament::button>
            @endif
        </div>

        {{-- MATRIX --}}
        <x-filament::section>
            <x-slot name="heading">Matriks Harga Kayu</x-slot>
            <x-slot name="description">
                {{ $hasPending ? 'Harga tidak dapat diubah selama ada pengajuan aktif.' : 'Ubah nilai harga lalu klik Ajukan Perubahan.' }}
            </x-slot>

            <div class="overflow-x-auto">
                <table class="w-full text-sm border-collapse">
                    <thead>
                        <tr>
                            <th rowspan="3"
                                class="border border-gray-200 dark:border-white/10 bg-gray-50 dark:bg-white/5 px-3 py-2 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">
                                Min
                            </th>
                            <th rowspan="3"
                                class="border border-gray-200 dark:border-white/10 bg-gray-50 dark:bg-white/5 px-3 py-2 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">
                                Max
                            </th>

                            @foreach ($this->matrixHeader as $woodName => $lengths)
                                @php $colspan = $lengths->sum(fn($grades) => $grades->count()); @endphp
                                <th colspan="{{ $colspan }}"
                                    class="border border-gray-200 dark:border-white/10 bg-primary-50 dark:bg-primary-950 px-3 py-2 text-xs font-semibold text-primary-700 dark:text-primary-300 uppercase tracking-wide text-center">
                                    {{ $woodName }}
                                </th>
                            @endforeach
                        </tr>

                        <tr>
                            @foreach ($this->matrixHeader as $lengths)
                                @foreach ($lengths as $length => $grades)
                                    <th colspan="{{ $grades->count() }}"
                                        class="border border-gray-200 dark:border-white/10 bg-gray-50 dark:bg-white/5 px-3 py-2 text-xs font-medium text-gray-600 dark:text-gray-300 text-center">
                                        {{ $length }}m
                                    </th>
                                @endforeach
                            @endforeach
                        </tr>

                        <tr>
                            @foreach ($this->matrixHeader as $lengths)
                                @foreach ($lengths as $grades)
                                    @foreach ($grades as $grade)
                                        <th
                                            class="border border-gray-200 dark:border-white/10 bg-gray-50 dark:bg-white/5 px-3 py-2 text-xs font-medium text-gray-500 dark:text-gray-400 text-center">
                                            Grade {{ $grade == 1 ? 'A' : 'B' }}
                                        </th>
                                    @endforeach
                                @endforeach
                            @endforeach
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                        @foreach ($this->diameterRanges as $range)
                            <tr class="hover:bg-gray-50/50 dark:hover:bg-white/[0.02] transition-colors">
                                <td
                                    class="border border-gray-200 dark:border-white/10 px-3 py-2 text-center text-xs font-mono text-gray-600 dark:text-gray-300">
                                    {{ $range->min }}
                                </td>
                                <td
                                    class="border border-gray-200 dark:border-white/10 px-3 py-2 text-center text-xs font-mono text-gray-600 dark:text-gray-300">
                                    {{ $range->max }}
                                </td>

                                @foreach ($this->matrixHeader as $wood => $lengths)
                                    @foreach ($lengths as $length => $grades)
                                        @foreach ($grades as $grade)
                                            @php
                                                $record = $this->getPriceRecord(
                                                    $wood,
                                                    $length,
                                                    $grade,
                                                    $range->min,
                                                    $range->max,
                                                );
                                            @endphp

                                            <td class="border border-gray-200 dark:border-white/10 p-1">
                                                @if ($record)
                                                    <div x-data="{
                                                        original: {{ $record->harga_beli }},
                                                        value: @entangle('inputs.' . $record->id)
                                                    }">
                                                        <input type="number" x-model="value"
                                                            @disabled($hasPending)
                                                            @input="
                                                                if (Number(value) !== Number(original)) {
                                                                    changes[{{ $record->id }}] = {
                                                                        jenisKayu: '{{ $record->jenisKayu->nama_kayu }}',
                                                                        panjang: '{{ $record->panjang }}',
                                                                        grade: '{{ $record->grade == 1 ? 'A' : 'B' }}',
                                                                        diameter: '{{ $record->diameter_terkecil }} - {{ $record->diameter_terbesar }}',
                                                                        lama: original,
                                                                        baru: value
                                                                    };
                                                                } else {
                                                                    delete changes[{{ $record->id }}];
                                                                }
                                                            "
                                                            class="w-full text-center text-xs rounded-md border px-2 py-1.5 transition-all duration-150 bg-white dark:bg-white/5 disabled:opacity-50 disabled:cursor-not-allowed focus:outline-none focus:ring-2 focus:ring-primary-500"
                                                            :class="Number(value) !== Number(original) ?
                                                                'border-primary-400 bg-primary-50 dark:bg-primary-950/50 text-primary-700 dark:text-primary-300 font-semibold ring-1 ring-primary-300' :
                                                                'border-gray-200 dark:border-white/10 text-gray-700 dark:text-gray-200'">
                                                    </div>
                                                @else
                                                    <div
                                                        class="text-center text-gray-300 dark:text-gray-600 text-xs py-1">
                                                        —</div>
                                                @endif
                                            </td>
                                        @endforeach
                                    @endforeach
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-filament::section>

        @if (!$hasPending)
            {{-- RINGKASAN PERUBAHAN --}}
            <div x-show="Object.keys(changes).length > 0" x-cloak class="flex flex-col gap-4">
                <div class="flex justify-end"> <x-filament::button wire:click="submit" icon="heroicon-m-paper-airplane">
                        Ajukan Perubahan
                    </x-filament::button> </div>

                <x-filament::section>
                    <x-slot name="heading">Ringkasan Perubahan</x-slot>
                    <x-slot name="description">Harga berikut akan diajukan untuk persetujuan.</x-slot>

                    <div class="overflow-scroll rounded-lg border border-gray-200 dark:border-white/10">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="bg-gray-50 dark:bg-white/5 border-b border-gray-200 dark:border-white/10">
                                    <th
                                        class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">
                                        Jenis Kayu</th>
                                    <th
                                        class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">
                                        Panjang</th>
                                    <th
                                        class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">
                                        Grade</th>
                                    <th
                                        class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">
                                        Diameter</th>
                                    <th
                                        class="px-4 py-3 text-right text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">
                                        Harga Lama</th>
                                    <th
                                        class="px-4 py-3 text-right text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">
                                        Harga Baru</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-for="(change, id) in changes" :key="id">
                                    <tr
                                        class="border-b border-gray-100 dark:border-white/5 last:border-0 hover:bg-gray-50/50 dark:hover:bg-white/[0.02] transition-colors">
                                        <td class="px-4 py-3 font-medium text-gray-900 dark:text-white"
                                            x-text="change.jenisKayu"></td>
                                        <td class="px-4 py-3 text-gray-600 dark:text-gray-300"
                                            x-text="change.panjang + 'm'"></td>
                                        <td class="px-4 py-3 text-gray-600 dark:text-gray-300"
                                            x-text="'Grade ' + change.grade"></td>
                                        <td class="px-4 py-3 text-gray-600 dark:text-gray-300 font-mono text-xs"
                                            x-text="change.diameter"></td>
                                        <td class="px-4 py-3 text-right text-gray-400 dark:text-gray-500 line-through text-xs"
                                            x-text="'Rp ' + Number(change.lama).toLocaleString('id-ID')"></td>
                                        <td class="px-4 py-3 text-right font-semibold text-primary-600 dark:text-primary-400"
                                            x-text="'Rp ' + Number(change.baru).toLocaleString('id-ID')"></td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </x-filament::section>
            </div>
        @endif

        {{-- CONFIRMATION MODAL --}}
        <div x-show="open" x-cloak
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm">
            <div
                class="bg-white dark:bg-gray-900 rounded-xl shadow-xl border border-gray-200 dark:border-white/10 p-6 w-[400px]">
                <div class="flex items-center gap-3 mb-4">
                    <div class="p-2 rounded-lg"
                        :class="action === 'approve' ? 'bg-success-100 dark:bg-success-950' :
                            'bg-danger-100 dark:bg-danger-950'">
                        <template x-if="action === 'approve'">
                            <x-heroicon-m-check-circle class="w-5 h-5 text-success-600 dark:text-success-400" />
                        </template>
                        <template x-if="action === 'reject'">
                            <x-heroicon-m-x-circle class="w-5 h-5 text-danger-600 dark:text-danger-400" />
                        </template>
                    </div>
                    <h2 class="text-base font-semibold text-gray-900 dark:text-white">Konfirmasi Aksi</h2>
                </div>

                <p class="text-sm text-gray-500 dark:text-gray-400 mb-6">
                    Anda akan
                    <span class="font-semibold text-gray-700 dark:text-gray-200"
                        x-text="action === 'approve' ? 'menyetujui' : 'menolak'"></span>
                    data yang dipilih. Tindakan ini tidak dapat dibatalkan.
                </p>

                <div class="flex justify-end gap-2">
                    <x-filament::button color="gray" outlined x-on:click="open = false">
                        Batal
                    </x-filament::button>
                    <div x-show="action === 'approve'">
                        <x-filament::button color="success" x-on:click="submit()">
                            Ya, Setujui
                        </x-filament::button>
                    </div>
                    <div x-show="action === 'reject'">
                        <x-filament::button color="danger" x-on:click="submit()">
                            Ya, Tolak
                        </x-filament::button>
                    </div>
                </div>
            </div>
        </div>

        {{-- REVISION REQUEST MODAL --}}
        @if ($showRevisionModal)
            <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm">
                <div
                    class="bg-white dark:bg-gray-900 rounded-xl shadow-xl border border-gray-200 dark:border-white/10 p-6 w-[500px]">
                    <div class="flex items-center gap-3 mb-1">
                        <div class="p-2 rounded-lg bg-warning-100 dark:bg-warning-950">
                            <x-heroicon-m-pencil-square class="w-5 h-5 text-warning-600 dark:text-warning-400" />
                        </div>
                        <h2 class="text-base font-semibold text-gray-900 dark:text-white">Tulis Catatan Revisi</h2>
                    </div>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mb-4 ml-11">
                        Catatan ini akan dikirim ke pengaju sebagai panduan perbaikan harga.
                    </p>

                    <textarea wire:model.defer="rejectionNote" rows="4"
                        class="w-full rounded-lg border border-gray-300 dark:border-white/10 bg-white dark:bg-white/5 px-3 py-2.5 text-sm text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-primary-500 resize-none"
                        placeholder="Contoh: Harga untuk kayu Meranti 4m grade A terlalu tinggi..."></textarea>

                    <div class="flex justify-end gap-2 mt-4">
                        <x-filament::button color="gray" outlined wire:click="$set('showRevisionModal', false)">
                            Batal
                        </x-filament::button>
                        <x-filament::button color="warning" wire:click="revise" icon="heroicon-m-paper-airplane">
                            Kirim Catatan
                        </x-filament::button>
                    </div>
                </div>
            </div>
        @endif

        {{-- REVISION NOTE VIEW / EDIT MODAL --}}
        @if ($showRevisionNoteModal)
            <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm">
                <div
                    class="bg-white dark:bg-gray-900 rounded-xl shadow-xl border border-gray-200 dark:border-white/10 p-6 w-[500px]">
                    @php
                        $isSubmitterModal = $revisionUpdatedBy === auth()->user()->name;
                    @endphp

                    <div class="flex items-center gap-3 mb-1">
                        <div class="p-2 rounded-lg bg-danger-100 dark:bg-danger-950">
                            <x-heroicon-m-chat-bubble-left-ellipsis
                                class="w-5 h-5 text-danger-600 dark:text-danger-400" />
                        </div>
                        <h2 class="text-base font-semibold text-gray-900 dark:text-white">Catatan Revisi</h2>
                    </div>

                    @if ($isSubmitterModal)
                        <p class="text-sm text-gray-500 dark:text-gray-400 mb-4 ml-11">
                            Perbaiki harga sesuai catatan di bawah ini.
                        </p>
                        <div
                            class="rounded-lg bg-danger-50 dark:bg-danger-950/50 border border-danger-200 dark:border-danger-800 px-4 py-3 text-sm text-danger-800 dark:text-danger-200 whitespace-pre-wrap leading-relaxed">
                            {{ $viewingRevisionNote ?? '—' }}
                        </div>
                        <div class="flex justify-end mt-4">
                            <x-filament::button color="gray" outlined
                                wire:click="$set('showRevisionNoteModal', false)">
                                Tutup
                            </x-filament::button>
                        </div>
                    @else
                        <p class="text-sm text-gray-500 dark:text-gray-400 mb-4 ml-11">
                            Edit catatan revisi yang sudah dikirim ke pengaju.
                        </p>
                        <textarea wire:model.defer="viewingRevisionNote" rows="4"
                            class="w-full rounded-lg border border-gray-300 dark:border-white/10 bg-white dark:bg-white/5 px-3 py-2.5 text-sm text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-primary-500 resize-none"
                            placeholder="Tuliskan catatan revisi..."></textarea>
                        <div class="flex justify-end gap-2 mt-4">
                            <x-filament::button color="gray" outlined
                                wire:click="$set('showRevisionNoteModal', false)">
                                Batal
                            </x-filament::button>
                            <x-filament::button color="warning" wire:click="updateRevisionNote"
                                icon="heroicon-m-check">
                                Simpan Perubahan
                            </x-filament::button>
                        </div>
                    @endif
                </div>
            </div>
        @endif

    </div>
</x-filament-panels::page>
