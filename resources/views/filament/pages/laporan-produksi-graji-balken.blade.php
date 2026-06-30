<x-filament-panels::page>
    <div class="p-4 bg-white dark:bg-zinc-900 rounded-lg shadow">
        {{ $this->form }}
    </div>

    <div wire:loading wire:target="loadAllData" class="w-full text-center py-4">
        <x-filament::loading-indicator class="w-8 h-8 mx-auto text-primary-600 mb-2" />
        <span class="text-zinc-500 italic">Memproses laporan Gergaji Balken...</span>
    </div>

    <div wire:loading.remove class="mt-6">
        <div class="bg-white dark:bg-zinc-900 rounded-sm shadow-lg border border-zinc-200 dark:border-zinc-700 overflow-hidden">
            <div class="bg-zinc-800 p-4 text-white text-center">
                <h2 class="text-lg font-bold uppercase tracking-widest">
                    LAPORAN PRODUKSI GERGAJI BALKEN: {{ \Carbon\Carbon::parse($tanggal)->format('d F Y') }}
                </h2>
            </div>

            <div class="p-4 overflow-x-auto">
                <table class="w-full text-sm border-collapse border border-zinc-300 dark:border-zinc-700">
                    <thead>
                        <tr class="bg-zinc-100 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300 font-bold uppercase">
                            <th class="border border-zinc-300 dark:border-zinc-700 p-2 text-center">Tanggal</th>
                            <th class="border border-zinc-300 dark:border-zinc-700 p-2 text-center">P</th>
                            <th class="border border-zinc-300 dark:border-zinc-700 p-2 text-center">L</th>
                            <th class="border border-zinc-300 dark:border-zinc-700 p-2 text-center">T</th>
                            <th class="border border-zinc-300 dark:border-zinc-700 p-2 text-center bg-zinc-200 dark:bg-zinc-700">Jenis</th>
                            <th class="border border-zinc-300 dark:border-zinc-700 p-2 text-center bg-zinc-200 dark:bg-zinc-700">Banyak</th>
                            <th class="border border-zinc-300 dark:border-zinc-700 p-2 text-center">m3</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($dataBalken as $row)
                        <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/50 transition-colors">
                            <td class="border border-zinc-300 dark:border-zinc-700 p-2 text-center">{{ $row['tanggal'] }}</td>
                            <td class="border border-zinc-300 dark:border-zinc-700 p-2 text-center">{{ $row['p'] }}</td>
                            <td class="border border-zinc-300 dark:border-zinc-700 p-2 text-center">{{ $row['l'] }}</td>
                            <td class="border border-zinc-300 dark:border-zinc-700 p-2 text-center">{{ $row['t'] }}</td>
                            <td class="border border-zinc-300 dark:border-zinc-700 p-2 text-center bg-zinc-50 dark:bg-zinc-800/30">{{ $row['jenis'] }}</td>
                            <td class="border border-zinc-300 dark:border-zinc-700 p-2 text-center bg-zinc-50 dark:bg-zinc-800/30 font-bold">{{ number_format($row['banyak']) }}</td>
                            <td class="border border-zinc-300 dark:border-zinc-700 p-2 text-center text-primary-600 font-mono">{{ number_format($row['m3'], 4) }}</td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="p-8 text-center text-zinc-500 italic">
                                Tidak ada data produksi pada tanggal ini.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                    @if(!empty($dataBalken))
                    <tfoot>
                        <tr class="bg-zinc-50 dark:bg-zinc-800 font-bold">
                            <td colspan="5" class="border border-zinc-300 dark:border-zinc-700 p-2 text-right uppercase">Total</td>
                            <td class="border border-zinc-300 dark:border-zinc-700 p-2 text-center">{{ number_format(collect($dataBalken)->sum('banyak')) }}</td>
                            <td class="border border-zinc-300 dark:border-zinc-700 p-2 text-center text-primary-600 font-mono">{{ number_format(collect($dataBalken)->sum('m3'), 4) }}</td>
                        </tr>
                    </tfoot>
                    @endif
                </table>
            </div>
        </div>
    </div>
</x-filament-panels::page>
