<x-filament-panels::page>

    {{-- Banner HPP Basah Placeholder --}}
    <div class="mb-6 rounded-xl bg-amber-50 p-4 ring-1 ring-amber-200
                dark:bg-amber-950 dark:ring-amber-800">
        <div class="flex items-start gap-3">
            <x-heroicon-o-information-circle class="h-5 w-5 text-amber-500 flex-shrink-0 mt-0.5"/>
            <div>
                <p class="text-sm font-semibold text-amber-800 dark:text-amber-200">
                    HPP Veneer Basah — Nilai Sementara
                </p>
                <p class="text-sm text-amber-700 dark:text-amber-300 mt-1">
                    Menggunakan placeholder <strong>Rp 1.000.000 / m³</strong>.
                    Akan diperbarui otomatis setelah resource HPP Basah selesai.
                </p>
            </div>
        </div>
    </div>

    {{-- Stats --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4 mb-6">
        @foreach ($this->getStats() as $stat)
            <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5
                        dark:bg-gray-900 dark:ring-white/10">
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">
                    {{ $stat['label'] }}
                </p>
                <p class="mt-2 text-2xl font-bold tracking-tight text-gray-950 dark:text-white">
                    {{ $stat['value'] }}
                </p>
                @if(isset($stat['description']))
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        {{ $stat['description'] }}
                    </p>
                @endif
            </div>
        @endforeach
    </div>

    {{-- Rumus HPP --}}
    <div class="mb-6 rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5
                dark:bg-gray-900 dark:ring-white/10">
        <h3 class="text-base font-semibold text-gray-950 dark:text-white mb-4">Rumus Kalkulasi HPP</h3>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
            <div class="rounded-lg bg-gray-50 dark:bg-gray-800 p-4">
                <p class="font-semibold text-gray-700 dark:text-gray-300 mb-2">① Ongkos Dryer / M3</p>
                <code class="text-xs text-blue-600 dark:text-blue-400 leading-relaxed block">
                    (Pekerja × Rp 115.000 + Mesin × Rp 335.000) ÷ Total M3
                </code>
            </div>
            <div class="rounded-lg bg-gray-50 dark:bg-gray-800 p-4">
                <p class="font-semibold text-gray-700 dark:text-gray-300 mb-2">② HPP Kering / M3</p>
                <code class="text-xs text-blue-600 dark:text-blue-400 leading-relaxed block">
                    HPP Basah / M3 + Ongkos Dryer / M3
                </code>
            </div>
            <div class="rounded-lg bg-gray-50 dark:bg-gray-800 p-4">
                <p class="font-semibold text-gray-700 dark:text-gray-300 mb-2">③ HPP Average</p>
                <code class="text-xs text-blue-600 dark:text-blue-400 leading-relaxed block">
                    (Nilai Stok Lama + Nilai Masuk) ÷ (M3 Lama + M3 Masuk)
                </code>
            </div>
        </div>
    </div>

    {{-- Tabel Rekap Stok Aktif --}}
    {{ $this->table }}

</x-filament-panels::page>