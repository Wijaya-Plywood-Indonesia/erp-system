<div class="space-y-6 p-1">
    {{-- Summary Cards --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        {{-- Card Lahan --}}
        <div class="p-4 bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-white/10 text-center shadow-sm transition duration-300">
            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase font-bold tracking-wider mb-1">Lahan</p>
            <p class="text-2xl font-black text-gray-800 dark:text-white">
                {{ $record->lahan?->kode_lahan ?? '-' }}
            </p>
        </div>

        {{-- Card Total Batang --}}
        <div class="p-4 bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-white/10 text-center shadow-sm transition duration-300">
            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase font-bold tracking-wider mb-1">Total Batang</p>
            <p class="text-2xl font-black text-primary-600 dark:text-primary-500">
                {{ $totalBatang }} <span class="text-sm font-medium">Btg</span>
            </p>
        </div>

        {{-- Card Total Volume --}}
        <div class="p-4 bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-white/10 text-center shadow-sm transition duration-300">
            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase font-bold tracking-wider mb-1">Total Volume</p>
            <p class="text-2xl font-black text-success-600 dark:text-success-500">
                {{ number_format($totalKubikasi, 4, ',', '.') }} <span class="text-sm font-medium">m³</span>
            </p>
        </div>
    </div>

    {{-- Tabel Per Seri --}}
    <div class="space-y-6 p-1">

        {{-- Tabel Per Seri --}}
        <div class="overflow-hidden border border-gray-200 dark:border-white/10 rounded-xl shadow-md bg-white dark:bg-gray-900">
            <table class="w-full text-left divide-y divide-gray-200 dark:divide-white/10">
                <thead class="bg-gray-50 dark:bg-white/5 text-xs font-bold uppercase text-gray-600 dark:text-gray-400">
                    <tr>
                        <th class="px-6 py-4 text-center">Seri</th>
                        <th class="px-4 py-4 text-center">Total Batang</th>
                        <th class="px-6 py-4 text-right">Total Volume (m³)</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-white/5">
                    @forelse($details as $row)
                    <tr class="hover:bg-primary-50/50 dark:hover:bg-white/5 transition duration-150">
                        <td class="px-6 py-4 text-center">
                            <span class="px-3 py-1 bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-200 rounded font-mono text-sm border border-gray-200 dark:border-gray-700 font-bold shadow-sm">
                                {{ $row['seri'] }}
                            </span>
                        </td>
                        <td class="px-4 py-4 text-center font-bold text-gray-700 dark:text-gray-200">
                            {{ $row['total_batang'] }} <span class="text-xs font-normal text-gray-400">Btg</span>
                        </td>
                        <td class="px-6 py-4 text-right font-mono font-bold text-gray-700 dark:text-gray-200">
                            {{ number_format($row['total_kubikasi'], 4, ',', '.') }}
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="3" class="px-6 py-12 text-center text-gray-400 dark:text-gray-500 italic bg-gray-50/50 dark:bg-transparent">
                            <div class="flex flex-col items-center">
                                <p>Data seri sudah tidak tersedia / habis terpakai.</p>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>