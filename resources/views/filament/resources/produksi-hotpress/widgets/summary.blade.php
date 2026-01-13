<x-filament::widget>
    <x-filament::card class="w-full space-y-6 dark:bg-gray-900 dark:border-gray-800">

        {{-- ================= HEADER: JUDUL & TOTAL PEGAWAI ================= --}}
        <div class="flex items-center justify-between border-b border-gray-100 pb-4 dark:border-gray-700">
            <div>
                <h2 class="text-lg font-bold text-gray-800 dark:text-gray-100">Hasil Produksi Hotpress</h2>
                <p class="text-xs text-gray-500">Output: Platform & Triplek</p>
            </div>
            <div class="text-right">
                <div class="text-2xl font-extrabold text-green-600 dark:text-green-500">
                    {{ number_format($summary['totalPegawai'] ?? 0) }}
                </div>
                <div class="text-xs font-medium text-gray-500 dark:text-gray-400">Total Tenaga Kerja</div>
            </div>
        </div>

        {{-- ================= GRID 2 KOLOM (PLATFORM vs TRIPLEK) ================= --}}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">

            {{-- 1. KOLOM KIRI: HASIL PLATFORM --}}
            <div class="space-y-4">
                {{-- Header Kolom --}}
                <div class="flex justify-between items-center bg-blue-50 p-3 rounded-lg dark:bg-blue-900/20">
                    <span class="font-bold text-blue-700 dark:text-blue-400">HASIL PLATFORM</span>
                    <span class="font-extrabold text-xl text-blue-700 dark:text-blue-400">
                        {{ number_format($summary['totalPlatform'] ?? 0) }} <span class="text-xs font-normal">Pcs</span>
                    </span>
                </div>

                {{-- List Detail --}}
                <div class="space-y-3">
                    @if(isset($summary['listPlatform']) && count($summary['listPlatform']) > 0)
                    @foreach ($summary['listPlatform'] as $row)
                    <div class="flex justify-between items-center border-b border-gray-100 pb-2 last:border-0 dark:border-gray-700">
                        <div>
                            <div class="text-sm font-semibold text-gray-800 dark:text-gray-200">
                                {{ $row->ukuran }}
                            </div>
                            {{-- 👇 PERUBAHAN DI SINI: Hapus background, ganti jadi teks bersih --}}
                            <div class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase mt-0.5">
                                <span class="text-blue-400 dark:text-blue-600">•</span> {{ $row->kw }}
                            </div>
                        </div>
                        <div class="font-bold text-gray-700 dark:text-gray-300">
                            {{ number_format($row->total) }}
                        </div>
                    </div>
                    @endforeach
                    @else
                    <div class="text-center text-xs text-gray-400 italic py-2">Belum ada hasil Platform</div>
                    @endif
                </div>
            </div>

            {{-- 2. KOLOM KANAN: HASIL TRIPLEK --}}
            <div class="space-y-4">
                {{-- Header Kolom --}}
                <div class="flex justify-between items-center bg-purple-50 p-3 rounded-lg dark:bg-purple-900/20">
                    <span class="font-bold text-purple-700 dark:text-purple-400">HASIL TRIPLEK</span>
                    <span class="font-extrabold text-xl text-purple-700 dark:text-purple-400">
                        {{ number_format($summary['totalTriplek'] ?? 0) }} <span class="text-xs font-normal">Pcs</span>
                    </span>
                </div>

                {{-- List Detail --}}
                <div class="space-y-3">
                    @if(isset($summary['listTriplek']) && count($summary['listTriplek']) > 0)
                    @foreach ($summary['listTriplek'] as $row)
                    <div class="flex justify-between items-center border-b border-gray-100 pb-2 last:border-0 dark:border-gray-700">
                        <div>
                            <div class="text-sm font-semibold text-gray-800 dark:text-gray-200">
                                {{ $row->ukuran }}
                            </div>
                            {{-- 👇 PERUBAHAN DI SINI: Hapus background, ganti jadi teks bersih --}}
                            <div class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase mt-0.5">
                                <span class="text-purple-400 dark:text-purple-600">•</span> {{ $row->kw }}
                            </div>
                        </div>
                        <div class="font-bold text-gray-700 dark:text-gray-300">
                            {{ number_format($row->total) }}
                        </div>
                    </div>
                    @endforeach
                    @else
                    <div class="text-center text-xs text-gray-400 italic py-2">Belum ada hasil Triplek</div>
                    @endif
                </div>
            </div>

        </div>

    </x-filament::card>
</x-filament::widget>