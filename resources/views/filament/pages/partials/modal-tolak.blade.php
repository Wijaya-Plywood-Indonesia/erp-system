{{-- resources/views/filament/pages/partials/modal-tolak.blade.php --}}
@if ($showTolakModal)
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6 overflow-y-auto">
        {{-- Backdrop --}}
        <div class="fixed inset-0 bg-zinc-950/70 backdrop-blur-sm transition-opacity"
            wire:click="batalTolak"></div>

        {{-- Modal Box --}}
        <div class="relative bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 rounded-lg shadow-2xl max-w-md w-full p-5 sm:p-6 z-10 space-y-4">
            {{-- Header --}}
            <div class="flex items-start gap-3 border-b border-zinc-100 dark:border-zinc-800 pb-3">
                <div class="p-2 rounded-full bg-rose-100 dark:bg-rose-950/50 text-rose-600 dark:text-rose-400 shrink-0">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                </div>
                <div class="flex-1 min-w-0">
                    <h3 class="text-sm font-black uppercase tracking-wider text-zinc-900 dark:text-zinc-100">
                        {{ $tolakModalTitle ?? 'Tolak Serah Terima Barang' }}
                    </h3>
                    <p class="text-[11px] text-zinc-500 dark:text-zinc-400 mt-0.5">
                        {{ $tolakModalDescription ?? 'Barang yang ditolak akan dikembalikan/dibatalkan dari daftar antrean penerimaan.' }}
                    </p>
                </div>
            </div>

            {{-- Body / Form --}}
            <div class="space-y-3">
                <div>
                    <label class="block text-[11px] font-bold uppercase tracking-wider text-zinc-700 dark:text-zinc-300 mb-1">
                        Alasan Penolakan <span class="text-rose-500">*</span>
                    </label>
                    <textarea
                        wire:model.defer="alasanTolak"
                        rows="3"
                        placeholder="Contoh: Jumlah fisik tidak sesuai, kualitas tidak memenuhi standar, salah kirim, dll."
                        class="w-full text-xs rounded border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 text-zinc-900 dark:text-zinc-100 placeholder-zinc-400 focus:border-rose-500 focus:ring-1 focus:ring-rose-500 p-2.5 transition-colors"
                        required></textarea>
                </div>
            </div>

            {{-- Footer / Actions --}}
            <div class="flex items-center justify-end gap-2 pt-2 border-t border-zinc-100 dark:border-zinc-800">
                <button type="button"
                    wire:click="batalTolak"
                    wire:loading.attr="disabled"
                    class="px-3.5 py-1.5 rounded text-xs font-bold text-zinc-600 dark:text-zinc-300 hover:bg-zinc-100 dark:hover:bg-zinc-800 transition-colors">
                    Batal
                </button>
                <button type="button"
                    wire:click="prosesTolak"
                    wire:loading.attr="disabled"
                    class="inline-flex items-center gap-1.5 px-4 py-1.5 rounded text-xs font-black uppercase tracking-wider text-white bg-rose-600 hover:bg-rose-500 active:bg-rose-700 disabled:opacity-50 transition-colors shadow-sm">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                    <span>Konfirmasi Tolak</span>
                </button>
            </div>
        </div>
    </div>
@endif
