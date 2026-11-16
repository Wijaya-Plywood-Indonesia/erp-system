@php
    $lahanDefault =
        \App\Models\DetailTurusanKayu::where('id_kayu_masuk', $parentId)->latest('id')->value('lahan_id') ??
        array_key_first($optionsLahan->toArray());
    $jenisDefault =
        \App\Models\DetailTurusanKayu::where('id_kayu_masuk', $parentId)->latest('id')->value('jenis_kayu_id') ??
        array_key_first($optionsJenis->toArray());

    $inputClass =
        'w-full rounded-lg bg-white px-3 py-2.5 text-base sm:py-2 sm:text-sm border border-gray-300 dark:bg-gray-900 dark:border-white/10 dark:text-white';
    $labelClass = 'text-sm font-medium text-gray-900 dark:text-white';
@endphp

<div wire:ignore x-data="offlineTurusanLogicV2({
    parentId: @js($parentId),
    lahanDefault: @js($lahanDefault),
    jenisDefault: @js($jenisDefault),
    syncUrl: @js(route('offline.detail-turusan.sync')),
    labels: { lahan: @js($optionsLahan->toArray()), jenis: @js($optionsJenis->toArray()) },
})" class="flex flex-col gap-y-4 sm:gap-y-5">

    <div class="flex flex-wrap items-center gap-2 text-xs">
        <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 font-medium"
            :class="online ? 'bg-success-100 text-success-700 dark:bg-success-500/20 dark:text-success-400' :
                'bg-warning-100 text-warning-700 dark:bg-warning-500/20 dark:text-warning-400'">
            <span class="h-2 w-2 rounded-full" :class="online ? 'bg-success-500' : 'bg-warning-500'"></span>
            <span x-text="online ? 'Online' : 'Offline'"></span>
        </span>
        <span x-show="notice" x-text="notice"
            :class="{
                'text-danger-600': noticeType === 'error',
                'text-success-600': noticeType === 'success',
                'text-gray-500': noticeType === 'info'
            }"></span>
    </div>

    <div class="grid grid-cols-2 gap-3 sm:gap-4">
        <div class="col-span-2 space-y-1.5 sm:col-span-1">
            <label class="{{ $labelClass }}">Lahan <span class="text-danger-600">*</span></label>
            <select x-model="form.lahan_id" class="{{ $inputClass }}">
                @foreach ($optionsLahan as $id => $label)
                    <option value="{{ $id }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div class="col-span-2 space-y-1.5 sm:col-span-1">
            <label class="{{ $labelClass }}">Jenis Kayu <span class="text-danger-600">*</span></label>
            <select x-model="form.jenis_kayu_id" class="{{ $inputClass }}">
                @foreach ($optionsJenis as $id => $label)
                    <option value="{{ $id }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div class="col-span-1 space-y-1.5">
            <label class="{{ $labelClass }}">Panjang <span class="text-danger-600">*</span></label>
            <select x-model="form.panjang" class="{{ $inputClass }}">
                <option value="130">130 cm</option>
                <option value="260">260 cm</option>
                <option value="0">Tidak Diketahui</option>
            </select>
        </div>

        <div class="col-span-1 space-y-1.5">
            <label class="{{ $labelClass }}">Grade <span class="text-danger-600">*</span></label>
            <select x-model="form.grade" class="{{ $inputClass }}">
                <option value="1">Grade A</option>
                <option value="2">Grade B</option>
            </select>
        </div>

        <div class="col-span-1 space-y-1.5">
            <label class="{{ $labelClass }}">Kuantitas <span class="text-danger-600">*</span></label>
            <input type="number" x-model="form.kuantitas" min="1" inputmode="numeric"
                class="{{ $inputClass }}">
        </div>

        <div class="col-span-1 space-y-1.5">
            <label class="{{ $labelClass }}">Diameter (cm) <span class="text-danger-600">*</span></label>
            <input x-ref="diameterInput" type="number" x-model="form.diameter" inputmode="decimal"
                @keydown.enter.prevent="create(false)" class="{{ $inputClass }}">
        </div>
    </div>

    <div class="flex flex-col gap-2 sm:flex-row-reverse sm:gap-3">
        <button type="button" @click="create(true)"
            class="min-h-[44px] w-full rounded-lg bg-amber-600 px-4 py-2 text-sm font-semibold text-white hover:bg-amber-500 sm:w-auto">
            <span x-text="editingUuid ? 'Update & Tutup' : 'Simpan'"></span>
        </button>
        <button type="button" @click="create(false)"
            class="min-h-[44px] w-full rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-900 hover:bg-gray-50 dark:border-white/10 dark:bg-white/5 dark:text-white sm:w-auto">
            <span x-text="editingUuid ? 'Update & Lanjut' : 'Simpan & Buat Lagi'"></span>
        </button>
        <button type="button" x-show="editingUuid" @click="cancelEdit()"
            class="min-h-[44px] w-full text-sm font-semibold text-gray-700 hover:underline dark:text-gray-300 sm:w-auto">
            Batal Edit
        </button>
        <button type="button" @click="closeModal()"
            class="min-h-[44px] w-full text-sm font-semibold text-gray-900 hover:underline dark:text-white sm:mr-auto sm:w-auto">
            Tutup
        </button>
    </div>

    <div class="rounded-lg ring-1 ring-gray-200 dark:ring-white/10">

        <div
            class="flex flex-col gap-3 border-b border-gray-200 p-3 dark:border-white/10 sm:flex-row sm:flex-wrap sm:items-center">
            <div class="text-sm font-semibold text-gray-900 dark:text-white sm:mr-auto">
                Antrean Offline
                <span class="ml-1 font-normal text-gray-500">
                    (<span x-text="pendingItems.length"></span> data, <span x-text="totalBatang"></span> btg)
                </span>
            </div>

            <div class="grid grid-cols-3 gap-2 sm:flex sm:items-center">
                <button type="button" @click="syncNow()" :disabled="isSyncing || !online || pendingItems.length === 0"
                    class="col-span-3 min-h-[40px] rounded-lg bg-primary-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-primary-500 disabled:opacity-40 sm:col-span-1">
                    <span x-text="isSyncing ? 'Mengupload...' : 'Upload Semua'"></span>
                </button>
                <button type="button" @click="exportFile()" :disabled="pendingItems.length === 0"
                    class="min-h-[40px] rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-900 hover:bg-gray-50 disabled:opacity-40 dark:border-white/10 dark:text-white dark:hover:bg-white/10">
                    Download
                </button>
                <button type="button" @click="$refs.importInput.click()"
                    class="min-h-[40px] rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-900 hover:bg-gray-50 dark:border-white/10 dark:text-white dark:hover:bg-white/10">
                    Import
                </button>
                <button type="button" @click="clearAll()" :disabled="pendingItems.length === 0"
                    class="min-h-[40px] rounded-lg px-3 py-1.5 text-xs font-semibold text-danger-600 hover:underline disabled:opacity-40">
                    Kosongkan
                </button>
                <input type="file" x-ref="importInput" accept=".json,application/json" class="hidden"
                    @change="importFile($event)">
            </div>
        </div>

        <div x-show="pendingItems.length === 0" class="p-6 text-center text-sm text-gray-500">
            Belum ada data offline untuk nota ini.
        </div>

        {{-- HP: kartu --}}
        <div x-show="pendingItems.length > 0"
            class="max-h-[50vh] divide-y divide-gray-100 overflow-y-auto dark:divide-white/5 sm:hidden">
            <template x-for="item in pendingItems" :key="'m-' + item.uuid">
                <div class="space-y-2 p-3"
                    :class="editingUuid === item.uuid ? 'bg-amber-50 dark:bg-amber-500/10' : ''">
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            <div class="truncate text-sm font-semibold text-gray-900 dark:text-white"
                                x-text="lahanLabel(item.lahan_id)"></div>
                            <div class="text-xs text-gray-600 dark:text-gray-400">
                                <span x-text="jenisLabel(item.jenis_kayu_id)"></span>
                                <span x-text="item.panjang"></span>
                                (<span x-text="gradeLabel(item.grade)"></span>)
                            </div>
                        </div>
                        <div class="shrink-0 text-right text-xs">
                            <span x-show="!item.error" class="text-warning-600">Belum diupload</span>
                        </div>
                    </div>

                    <div class="grid grid-cols-3 gap-2 text-center text-xs">
                        <div class="rounded-md bg-gray-50 p-1.5 dark:bg-white/5">
                            <div class="text-gray-500">Diameter</div>
                            <div class="font-semibold text-gray-900 dark:text-white" x-text="item.diameter"></div>
                        </div>
                        <div class="rounded-md bg-gray-50 p-1.5 dark:bg-white/5">
                            <div class="text-gray-500">Qty</div>
                            <div class="font-semibold text-gray-900 dark:text-white" x-text="item.kuantitas"></div>
                        </div>
                        <div class="rounded-md bg-gray-50 p-1.5 dark:bg-white/5">
                            <div class="text-gray-500">m³</div>
                            <div class="font-semibold text-gray-900 dark:text-white" x-text="kubikasi(item)"></div>
                        </div>
                    </div>

                    <p x-show="item.error" class="text-xs text-danger-600" x-text="item.error"></p>

                    <div class="grid grid-cols-3 gap-2">
                        <button type="button" @click="syncNow(item)" :disabled="isSyncing || !online"
                            class="min-h-[40px] rounded-lg bg-primary-600 text-xs font-semibold text-white disabled:opacity-40">Upload</button>
                        <button type="button" @click="edit(item)"
                            class="min-h-[40px] rounded-lg border border-gray-300 text-xs font-semibold text-gray-900 dark:border-white/10 dark:text-white">Edit</button>
                        <button type="button" @click="remove(item)"
                            class="min-h-[40px] rounded-lg border border-danger-300 text-xs font-semibold text-danger-600 dark:border-danger-500/40">Hapus</button>
                    </div>
                </div>
            </template>
        </div>

        {{-- Layar besar: tabel --}}
        <div x-show="pendingItems.length > 0" class="hidden max-h-72 overflow-auto sm:block">
            <table class="w-full text-left text-xs">
                <thead class="sticky top-0 bg-gray-50 text-gray-600 dark:bg-gray-900 dark:text-gray-400">
                    <tr>
                        <th class="px-3 py-2">Lahan</th>
                        <th class="px-3 py-2">Kayu</th>
                        <th class="px-3 py-2 text-right">D</th>
                        <th class="px-3 py-2 text-right">Qty</th>
                        <th class="px-3 py-2 text-right">m³</th>
                        <th class="px-3 py-2">Status</th>
                        <th class="px-3 py-2"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                    <template x-for="item in pendingItems" :key="'d-' + item.uuid">
                        <tr :class="editingUuid === item.uuid ? 'bg-amber-50 dark:bg-amber-500/10' : ''">
                            <td class="px-3 py-2" x-text="lahanLabel(item.lahan_id)"></td>
                            <td class="px-3 py-2">
                                <span x-text="jenisLabel(item.jenis_kayu_id)"></span>
                                <span x-text="item.panjang"></span>
                                (<span x-text="gradeLabel(item.grade)"></span>)
                            </td>
                            <td class="px-3 py-2 text-right" x-text="item.diameter"></td>
                            <td class="px-3 py-2 text-right" x-text="item.kuantitas"></td>
                            <td class="px-3 py-2 text-right" x-text="kubikasi(item)"></td>
                            <td class="px-3 py-2">
                                <span x-show="!item.error" class="text-warning-600">Belum diupload</span>
                                <span x-show="item.error" class="text-danger-600" x-text="item.error"></span>
                            </td>
                            <td class="whitespace-nowrap px-3 py-2 text-right">
                                <button type="button" @click="syncNow(item)" :disabled="isSyncing || !online"
                                    class="font-semibold text-primary-600 hover:underline disabled:opacity-40">Upload</button>
                                <button type="button" @click="edit(item)"
                                    class="ml-2 font-semibold text-gray-700 hover:underline dark:text-gray-300">Edit</button>
                                <button type="button" @click="remove(item)"
                                    class="ml-2 font-semibold text-danger-600 hover:underline">Hapus</button>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>

        <p x-show="otherCount > 0" class="border-t border-gray-200 p-3 text-xs text-gray-500 dark:border-white/10">
            Ada <span x-text="otherCount"></span> data offline milik nota lain di perangkat ini. Buka nota tersebut
            untuk melihat atau mengupload.
        </p>
    </div>
</div>
