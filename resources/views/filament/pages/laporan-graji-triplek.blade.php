<x-filament-panels::page>
    <x-filament::section>
        {{ $this->form }}
    </x-filament::section>

    <div class="fi-ta-ctn border border-gray-200 shadow-sm rounded-xl overflow-hidden dark:border-white/10">
        <div class="relative overflow-x-auto">
            <table class="w-full text-sm text-left text-gray-500 dark:text-gray-400">
                <thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-800 dark:text-gray-400">
                    <tr>
                        <th class="px-6 py-3 border-b">Tanggal</th>
                        <th class="px-6 py-3 border-b">Shift</th>
                        <th class="px-6 py-3 border-b">P</th>
                        <th class="px-6 py-3 border-b">L</th>
                        <th class="px-6 py-3 border-b">T</th>
                        <th class="px-6 py-3 border-b">Jenis</th>
                        <th class="px-6 py-3 border-b text-center">Banyak (Pcs)</th>
                        <th class="px-6 py-3 border-b text-center">Volume (M3)</th>
                        <th class="px-6 py-3 border-b text-center">Pekerja</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-white/5">
                    @forelse ($laporan as $item)
                    <tr class="hover:bg-gray-50 dark:hover:bg-white/5">
                        <td class="px-6 py-4">{{ $item['tanggal'] }}</td>
                        <td class="px-6 py-4">{{ $item['shift'] }}</td>
                        <td class="px-6 py-4">{{ $item['p'] }}</td>
                        <td class="px-6 py-4">{{ $item['l'] }}</td>
                        <td class="px-6 py-4">{{ $item['t'] }}</td>
                        <td class="px-6 py-4">{{ $item['jenis'] }}</td>
                        <td class="px-6 py-4 text-center font-bold text-primary-600">{{ number_format($item['byk']) }}</td>
                        <td class="px-6 py-4 text-center">{{ number_format($item['m3'], 4) }}</td>
                        <td class="px-6 py-4 text-center">{{ $item['ttl_pkj'] }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="9" class="px-6 py-10 text-center text-gray-400">
                            <div class="flex flex-col items-center">
                                <x-filament::icon icon="heroicon-o-circle-stack" class="w-10 h-10 mb-2" />
                                <span>Tidak ada data untuk ditampilkan</span>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if(!empty($kendalaList))
    <div class="mt-6 border border-gray-200 shadow-sm rounded-xl overflow-hidden bg-white dark:bg-gray-900 dark:border-white/10">
        <div class="p-4 bg-gray-50 dark:bg-gray-800 border-b border-gray-200 dark:border-white/10">
            <h4 class="font-bold text-red-600 dark:text-red-400">Downtime & Kendala Mesin</h4>
        </div>
        <div class="relative overflow-x-auto">
            <table class="w-full text-sm text-left text-gray-500 dark:text-gray-400">
                <thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-800 dark:text-gray-400">
                    <tr>
                        <th class="px-6 py-3 border-b text-center w-12">No</th>
                        <th class="px-6 py-3 border-b">Tanggal</th>
                        <th class="px-6 py-3 border-b">Mesin</th>
                        <th class="px-6 py-3 border-b text-center">Waktu Mulai</th>
                        <th class="px-6 py-3 border-b text-center">Waktu Selesai</th>
                        <th class="px-6 py-3 border-b text-center">Durasi</th>
                        <th class="px-6 py-3 border-b">Keterangan Kendala</th>
                        <th class="px-6 py-3 border-b text-center">Status</th>
                        <th class="px-6 py-3 border-b text-center">Bukti Foto</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-white/5">
                    @foreach ($kendalaList as $index => $item)
                    <tr class="hover:bg-gray-50 dark:hover:bg-white/5">
                        <td class="px-6 py-4 text-center">{{ $index + 1 }}</td>
                        <td class="px-6 py-4">{{ $item['tanggal'] }}</td>
                        <td class="px-6 py-4">{{ $item['mesin'] }}</td>
                        <td class="px-6 py-4 text-center">{{ $item['waktu_mulai'] }}</td>
                        <td class="px-6 py-4 text-center">{{ $item['waktu_selesai'] }}</td>
                        <td class="px-6 py-4 text-center font-semibold text-amber-600 dark:text-amber-400">
                            {{ $item['durasi_menit'] ? $item['durasi_menit'] . ' menit' : '-' }}
                        </td>
                        <td class="px-6 py-4 font-medium text-red-600 dark:text-red-400">{{ $item['kendala'] }}</td>
                        <td class="px-6 py-4 text-center">
                            @if($item['status'] === 'pending')
                                <span class="inline-flex items-center gap-x-1.5 rounded-md bg-red-100 px-2 py-1 text-xs font-medium text-red-700 dark:bg-red-900/30 dark:text-red-400">
                                    Pending
                                </span>
                            @elseif($item['status'] === 'selesai')
                                <span class="inline-flex items-center gap-x-1.5 rounded-md bg-green-100 px-2 py-1 text-xs font-medium text-green-700 dark:bg-green-900/30 dark:text-green-400">
                                    Selesai
                                </span>
                            @else
                                <span class="inline-flex items-center gap-x-1.5 rounded-md bg-gray-100 px-2 py-1 text-xs font-medium text-gray-700 dark:bg-gray-900/30 dark:text-gray-400">
                                    {{ ucfirst($item['status'] ?? '-') }}
                                </span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-center">
                            <div class="flex justify-center gap-2">
                                @if(!empty($item['foto_kendala']))
                                    <a href="{{ asset('storage/' . $item['foto_kendala']) }}" target="_blank" class="text-xs text-primary-600 hover:underline">
                                        Bukti Mulai
                                    </a>
                                @endif
                                @if(!empty($item['foto_selesai']))
                                    <span class="text-gray-300">|</span>
                                    <a href="{{ asset('storage/' . $item['foto_selesai']) }}" target="_blank" class="text-xs text-success-600 hover:underline">
                                        Bukti Selesai
                                    </a>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif
</x-filament-panels::page>