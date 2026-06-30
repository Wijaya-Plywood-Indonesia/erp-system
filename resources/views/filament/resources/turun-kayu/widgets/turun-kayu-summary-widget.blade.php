<x-filament::widget>
    <x-filament::card>
        <div class="space-y-6">
            {{-- HEADER: TOTAL UTAMA --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 border-b border-gray-100 dark:border-gray-800 pb-6">
                <div class="flex items-center gap-4">
                    <div>
                        <p class="text-sm font-medium text-gray-500 uppercase tracking-wider">Total Pegawai</p>
                        <h2 class="text-3xl font-black text-gray-950 dark:text-white">{{ $summary['totalPegawai'] }} <span class="text-lg font-normal text-gray-400">Orang</span></h2>
                    </div>
                </div>

                <div class="flex items-center gap-4">
                    <div>
                        <p class="text-sm font-medium text-gray-500 uppercase tracking-wider">Total Kendaraan</p>
                        <h2 class="text-3xl font-black text-gray-950 dark:text-white">{{ $summary['totalKendaraan'] }} <span class="text-lg font-normal text-gray-400">Unit</span></h2>
                    </div>
                </div>
            </div>

            {{-- REKAPAN DETAIL KENDARAAN --}}
            <div>
                <p class="text-xs font-bold text-gray-400 uppercase mb-3">Rincian Jenis Kendaraan:</p>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    @forelse ($summary['details'] as $item)
                    @php
                    $nama = strtolower($item->jenis_kendaraan);
                    // Warna dinamis
                    $color = str_contains($nama, 'fuso') ? 'danger' : (str_contains($nama, 'truk') ? 'warning' : 'success');
                    @endphp

                    <div class="relative flex items-center justify-between p-4 bg-gray-50 dark:bg-gray-800/50 rounded-xl border-l-4 border-{{ $color }}-500 shadow-sm">
                        <span class="font-bold text-gray-700 dark:text-gray-300 uppercase text-sm">{{ $item->jenis_kendaraan }}</span>
                        <div class="flex items-baseline gap-1">
                            <span class="text-2xl font-black text-gray-900 dark:text-white">{{ $item->jumlah }}</span>
                            <span class="text-xs text-gray-400">Unit</span>
                        </div>
                    </div>
                    @empty
                    <div class="col-span-full text-center py-4 bg-gray-50 dark:bg-gray-800 rounded-xl text-gray-400 italic text-sm">
                        Belum ada data kendaraan yang masuk untuk sesi turun kayu ini.
                    </div>
                    @endforelse
                </div>
            </div>
        </div>
    </x-filament::card>
</x-filament::widget>