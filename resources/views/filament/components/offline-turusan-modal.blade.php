<div
    wire:ignore

    {{-- PERUBAHAN PENTING ADA DISINI: --}}
    {{-- Gunakan 'offlineTurusanLogic', BUKAN 'offlineDetailLogic' --}}
    x-data="offlineTurusanLogic({ 
        parentId: '{{ $parentId }}',
        lahanDefault: '{{ \App\Models\DetailTurusanKayu::where("id_kayu_masuk", $parentId)->latest("id")->value("lahan_id") ?? array_key_first($optionsLahan->toArray()) }}',
        jenisDefault: '{{ array_key_first($optionsJenis->toArray()) }}'
    })"

    class="flex flex-col gap-y-6">

    <div x-show="pendingItems.length > 0" class="rounded-lg bg-gray-50 p-4 ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-white/10">
        <div class="flex flex-wrap items-start gap-3">
            <div class="flex h-8 w-8 items-center justify-center rounded-full bg-warning-100 dark:bg-warning-500/20">
                <svg class="h-5 w-5 text-warning-600 dark:text-warning-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0z" clip-rule="evenodd" />
                </svg>
            </div>
            <div class="flex-1 min-w-[220px]">
                <h4 class="text-sm font-semibold text-gray-900 dark:text-white">
                    <span x-text="pendingItems.length"></span> Data Turusan Tersimpan
                </h4>
                <p x-show="!online" class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                    Koneksi terputus. Data aman di perangkat ini.
                </p>
                <button x-show="online" @click="syncNow()" :disabled="isSyncing" class="mt-2 text-xs font-semibold text-primary-600 hover:underline dark:text-primary-400 disabled:opacity-50">
                    Upload Sekarang →
                </button>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:gap-6">

        <!-- Perhatikan modelnya menggunakan 'lahan_id' (sesuai tabel turusan), bukan 'id_lahan' -->
        <div class="space-y-1.5">
            <label class="text-sm font-medium text-gray-900 dark:text-white">Lahan <span class="text-danger-600">*</span></label>
            <select x-model="form.lahan_id" class="w-full rounded-lg bg-white px-3 py-2 text-sm border border-gray-300 dark:bg-gray-900 dark:border-white/10">
                @foreach($optionsLahan as $id => $label)
                <option value="{{ $id }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div class="space-y-1.5">
            <label class="text-sm font-medium text-gray-900 dark:text-white">Jenis Kayu <span class="text-danger-600">*</span></label>
            <select x-model="form.jenis_kayu_id" class="w-full rounded-lg bg-white px-3 py-2 text-sm border border-gray-300 dark:bg-gray-900 dark:border-white/10">
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

        <!-- Tambahan input kuantitas jika diperlukan (atau default 1 di JS) -->
        <div class="space-y-1.5">
            <label class="text-sm font-medium text-gray-900 dark:text-white">Jumlah Batang <span class="text-danger-600">*</span></label>
            <input type="number" x-model="form.kuantitas" min="1" value="1"
                class="w-full rounded-lg bg-white px-3 py-2 text-sm border border-gray-300 dark:bg-gray-900 dark:border-white/10">
        </div>

        <div class="space-y-1.5">
            <label class="text-sm font-medium text-gray-900 dark:text-white">Diameter (cm) <span class="text-danger-600">*</span></label>
            <input
                x-ref="diameterInput"
                type="number"
                x-model="form.diameter"
                @keydown.enter.prevent="create(false)"
                class="w-full rounded-lg bg-white px-3 py-2 text-sm border border-gray-300 dark:bg-gray-900 dark:border-white/10">
        </div>

    </div>

    <div class="mt-8 pt-6 border-t border-gray-200 dark:border-white/10">
        <div class="flex flex-col gap-3 sm:flex-row-reverse">
            <!-- Tambahkan type="button" agar tidak submit form default -->
            <button type="button" @click="create(true)" class="w-full sm:w-auto rounded-lg bg-amber-600 px-4 py-2 text-sm font-semibold text-white hover:bg-amber-500">
                Simpan
            </button>

            <button type="button" @click="create(false)" class="w-full sm:w-auto rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-900 hover:bg-gray-50 dark:bg-white/5 dark:text-white dark:border-white/10 dark:hover:bg-white/10">
                Simpan & Buat Lagi
            </button>

            <button type="button" @click="$dispatch('close-modal', { id: 'modal-offline-turusan' })" class="w-full sm:w-auto text-sm font-semibold text-gray-900 hover:underline sm:mr-auto dark:text-white">
                Batal
            </button>
        </div>
    </div>
</div>