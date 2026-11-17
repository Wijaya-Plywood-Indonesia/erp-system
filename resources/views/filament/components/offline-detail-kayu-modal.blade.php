@php
    $lahanDefault = \App\Models\DetailKayuMasuk::where('id_kayu_masuk', $parentId)->latest('id')->value('id_lahan')
        ?? array_key_first($optionsLahan->toArray());
@endphp

<div
    wire:ignore
    x-data="offlineDetailLogic({
        parentId: @js($parentId),
        lahanDefault: @js($lahanDefault),
        jenisDefault: @js(array_key_first($optionsJenis->toArray())),
        syncUrl: @js(route('offline.detail-kayu.sync')),
        labels: { lahan: @js($optionsLahan->toArray()), jenis: @js($optionsJenis->toArray()) },
    })"
    class="flex flex-col gap-y-5">

    {{-- Status koneksi + notifikasi --}}
    <div class="flex flex-wrap items-center gap-2 text-xs">
        <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 font-medium"
              :class="online ? 'bg-success-100 text-success-700 dark:bg-success-500/20 dark:text-success-400'
                             : 'bg-warning-100 text-warning-700 dark:bg-warning-500/20 dark:text-warning-400'">
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

    {{-- FORM --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div class="space-y-1.5">
            <label class="text-sm font-medium text-gray-900 dark:text-white">Lahan <span class="text-danger-600">*</span></label>
            <select x-model="form.id_lahan" class="w-full rounded-lg bg-white px-3 py-2 text-sm border border-gray-300 dark:bg-gray-900 dark:border-white/10">
                @foreach($optionsLahan as $id => $label)
                    <option value="{{ $id }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div class="space-y-1.5">
            <label class="text-sm font-medium text-gray-900 dark:text-white">Jenis Kayu <span class="text-danger-600">*</span></label>
            <select x-model="form.id_jenis_kayu" class="w-full rounded-lg bg-white px-3 py-2 text-sm border border-gray-300 dark:bg-gray-900 dark:border-white/10">
                @foreach($optionsJenis as $id => $label)
                    <option value="{{ $id }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div class="space-y-1.5">
            <label class="text-sm font-medium text-gray-900 dark:text-white">Panjang <span class="text-danger-600">*</span></label>
            <select x-model="form.panjang" class="w-full rounded-lg bg-white px-3 py-2 text-sm border border-gray-300 dark:bg-gray-900 dark:border-white/10">
                <option value="130">130 cm</option>
                <option value="260">260 cm</option>
                <option value="0">Tidak Diketahui</option>
            </select>
        </div>

        <div class="space-y-1.5">
            <label class="text-sm font-medium text-gray-900 dark:text-white">Grade <span class="text-danger-600">*</span></label>
            <select x-model="form.grade" class="w-full rounded-lg bg-white px-3 py-2 text-sm border border-gray-300 dark:bg-gray-900 dark:border-white/10">
                <option value="1">Grade A</option>
                <option value="2">Grade B</option>
            </select>
        </div>

        <div class="space-y-1.5">
            <label class="text-sm font-medium text-gray-900 dark:text-white">Jumlah Batang <span class="text-danger-600">*</span></label>
            <input type="number" x-model="form.jumlah_batang" min="1" inputmode="numeric"
                   class="w-full rounded-lg bg-white px-3 py-2 text-sm border border-gray-300 dark:bg-gray-900 dark:border-white/10">
        </div>

        <div class="space-y-1.5">
            <label class="text-sm font-medium text-gray-900 dark:text-white">Diameter (cm) <span class="text-danger-600">*</span></label>
            <input x-ref="diameterInput" type="number" x-model="form.diameter" inputmode="decimal"
                   @keydown.enter.prevent="create(false)"
                   class="w-full rounded-lg bg-white px-3 py-2 text-sm border border-gray-300 dark:bg-gray-900 dark:border-white/10">
        </div>
    </div>

    <div class="flex flex-col gap-3 sm:flex-row-reverse">
        <button type="button" @click="create(true)"
                class="w-full sm:w-auto rounded-lg bg-amber-600 px-4 py-2 text-sm font-semibold text-white hover:bg-amber-500">
            <span x-text="editingUuid ? 'Update & Tutup' : 'Simpan'"></span>
        </button>
        <button type="button" @click="create(false)"
                class="w-full sm:w-auto rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-900 hover:bg-gray-50 dark:bg-white/5 dark:text-white dark:border-white/10">
            <span x-text="editingUuid ? 'Update & Lanjut' : 'Simpan & Buat Lagi'"></span>
        </button>
        <button type="button" x-show="editingUuid" @click="cancelEdit()"
                class="w-full sm:w-auto text-sm font-semibold text-gray-700 hover:underline dark:text-gray-300">
            Batal Edit
        </button>
        <button type="button" @click="closeModal()"
                class="w-full sm:w-auto text-sm font-semibold text-gray-900 hover:underline sm:mr-auto dark:text-white">
            Tutup
        </button>
    </div>

    {{-- TABEL ANTREAN --}}
    <div class="rounded-lg ring-1 ring-gray-200 dark:ring-white/10">
        <div class="flex flex-wrap items-center gap-2 border-b border-gray-200 p-3 dark:border-white/10">
            <div class="mr-auto text-sm font-semibold text-gray-900 dark:text-white">
                Antrean Offline
                <span class="ml-1 font-normal text-gray-500">
                    (<span x-text="pendingItems.length"></span> data, <span x-text="totalBatang"></span> btg)
                </span>
            </div>

            <button type="button" @click="syncNow()" :disabled="isSyncing || !online || pendingItems.length === 0"
                    class="rounded-lg bg-primary-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-primary-500 disabled:opacity-40">
                <span x-text="isSyncing ? 'Mengupload...' : 'Upload Semua'"></span>
            </button>
            <button type="button" @click="exportFile()" :disabled="pendingItems.length === 0"
                    class="rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-900 hover:bg-gray-50 disabled:opacity-40 dark:border-white/10 dark:text-white dark:hover:bg-white/10">
                Download
            </button>
            <button type="button" @click="$refs.importInput.click()"
                    class="rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-900 hover:bg-gray-50 dark:border-white/10 dark:text-white dark:hover:bg-white/10">
                Import
            </button>
            <input type="file" x-ref="importInput" accept=".json,application/json" class="hidden" @change="importFile($event)">
            <button type="button" @click="clearAll()" x-show="pendingItems.length > 0"
                    class="rounded-lg px-3 py-1.5 text-xs font-semibold text-danger-600 hover:underline">
                Kosongkan
            </button>
        </div>

        <div x-show="pendingItems.length === 0" class="p-6 text-center text-sm text-gray-500">
            Belum ada data offline untuk nota ini.
        </div>

        <div x-show="pendingItems.length > 0" class="max-h-72 overflow-auto">
            <table class="w-full text-left text-xs">
                <thead class="sticky top-0 bg-gray-50 text-gray-600 dark:bg-gray-900 dark:text-gray-400">
                    <tr>
                        <th class="px-3 py-2">Lahan</th>
                        <th class="px-3 py-2">Kayu</th>
                        <th class="px-3 py-2 text-right">Diameter</th>
                        <th class="px-3 py-2 text-right">Btg</th>
                        <th class="px-3 py-2 text-right">m³</th>
                        <th class="px-3 py-2">Status</th>
                        <th class="px-3 py-2"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                    <template x-for="item in pendingItems" :key="item.uuid">
                        <tr :class="editingUuid === item.uuid ? 'bg-amber-50 dark:bg-amber-500/10' : ''">
                            <td class="px-3 py-2" x-text="lahanLabel(item.id_lahan)"></td>
                            <td class="px-3 py-2">
                                <span x-text="jenisLabel(item.id_jenis_kayu)"></span>
                                <span x-text="item.panjang"></span>
                                (<span x-text="gradeLabel(item.grade)"></span>)
                            </td>
                            <td class="px-3 py-2 text-right" x-text="item.diameter"></td>
                            <td class="px-3 py-2 text-right" x-text="item.jumlah_batang"></td>
                            <td class="px-3 py-2 text-right" x-text="kubikasi(item)"></td>
                            <td class="px-3 py-2">
                                <span x-show="!item.error" class="text-warning-600">Belum diupload</span>
                                <span x-show="item.error" class="text-danger-600" x-text="item.error"></span>
                            </td>
                            <td class="whitespace-nowrap px-3 py-2 text-right">
                                <button type="button" @click="syncNow(item)" :disabled="isSyncing || !online" class="font-semibold text-primary-600 hover:underline disabled:opacity-40">Upload</button>
                                <button type="button" @click="edit(item)" class="ml-2 font-semibold text-gray-700 hover:underline dark:text-gray-300">Edit</button>
                                <button type="button" @click="remove(item)" class="ml-2 font-semibold text-danger-600 hover:underline">Hapus</button>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>

        <p x-show="otherCount > 0" class="border-t border-gray-200 p-3 text-xs text-gray-500 dark:border-white/10">
            Ada <span x-text="otherCount"></span> data offline milik nota lain di perangkat ini. Buka nota tersebut untuk melihat atau mengupload.
        </p>
    </div>
</div>
