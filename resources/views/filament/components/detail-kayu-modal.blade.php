<div class="space-y-6 p-1">
    {{-- Summary Cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        {{-- Card Lahan --}}
        <div class="p-4 bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-white/10 text-center shadow-sm">
            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase font-bold tracking-wider mb-1">Lahan</p>
            <p class="text-2xl font-black text-gray-800 dark:text-white">
                {{ $record->lahan?->kode_lahan ?? '-' }}
            </p>
        </div>

        {{-- Card Total Batang --}}
        <div class="p-4 bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-white/10 text-center shadow-sm">
            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase font-bold tracking-wider mb-1">Total Batang</p>
            <p class="text-2xl font-black text-primary-600 dark:text-primary-500">
                {{ $totalBatang }} <span class="text-sm font-medium">Btg</span>
            </p>
        </div>

        {{-- Card Total Volume --}}
        <div class="p-4 bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-white/10 text-center shadow-sm">
            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase font-bold tracking-wider mb-1">Total Volume</p>
            <p class="text-2xl font-black text-success-600 dark:text-success-500">
                {{ number_format($totalKubikasi, 4, ',', '.') }} <span class="text-sm font-medium">m³</span>
            </p>
        </div>
    </div>

    {{-- Tabel Per Seri --}}
    <div class="space-y-3">
        {{-- Desktop: Tabel --}}
        <div class="hidden md:block overflow-hidden border border-gray-200 dark:border-white/10 rounded-xl shadow-md bg-white dark:bg-gray-900">
            <table class="w-full text-left divide-y divide-gray-200 dark:divide-white/10">
                <thead class="bg-gray-50 dark:bg-white/5 text-xs font-bold uppercase text-gray-600 dark:text-gray-400">
                    <tr>
                        <th class="px-6 py-4 text-center">Seri</th>
                        <th class="px-4 py-4 text-center">Total Batang</th>
                        <th class="px-6 py-4 text-center">Status Lunas</th>
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
                        <td class="px-6 py-4 text-center">
                            @php $lunas = $row['status_pelunasan']; @endphp
                            @if($lunas && str_contains(strtolower($lunas), 'lunas') && !str_contains(strtolower($lunas), 'belum'))
                            <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-bold bg-success-100 text-success-700 dark:bg-success-500/20 dark:text-success-400 border border-success-200 dark:border-success-500/30">
                                ✓ {{ $lunas }}
                            </span>
                            @elseif($lunas && str_contains(strtolower($lunas), 'belum'))
                            <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-bold bg-danger-100 text-danger-700 dark:bg-danger-500/20 dark:text-danger-400 border border-danger-200 dark:border-danger-500/30">
                                ✗ {{ $lunas }}
                            </span>
                            @else
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400 border border-gray-200 dark:border-gray-600">
                                - Tidak Ada Data
                            </span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-right font-mono font-bold text-gray-700 dark:text-gray-200">
                            {{ number_format($row['total_kubikasi'], 4, ',', '.') }}
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="4" class="px-6 py-12 text-center text-gray-400 dark:text-gray-500 italic">
                            <p>Data seri sudah tidak tersedia / habis terpakai.</p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Mobile: Card List --}}
        <div class="md:hidden space-y-3">
            @forelse($details as $row)
            <div class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-white/10 shadow-sm p-4 space-y-3">
                {{-- Header: Seri --}}
                <div class="flex items-center justify-between">
                    <span class="text-sm text-gray-500 dark:text-gray-400 font-medium">Seri</span>
                    <span class="px-3 py-1 bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-200 rounded font-mono text-sm border border-gray-200 dark:border-gray-700 font-bold shadow-sm">
                        {{ $row['seri'] }}
                    </span>
                </div>

                {{-- Total Batang --}}
                <div class="flex items-center justify-between">
                    <span class="text-sm text-gray-500 dark:text-gray-400 font-medium">Total Batang</span>
                    <span class="font-bold text-gray-700 dark:text-gray-200">
                        {{ $row['total_batang'] }} <span class="text-xs font-normal text-gray-400">Btg</span>
                    </span>
                </div>

                {{-- Status Lunas --}}
                <div class="flex items-center justify-between">
                    <span class="text-sm text-gray-500 dark:text-gray-400 font-medium">Status Lunas</span>
                    @php $lunas = $row['status_pelunasan']; @endphp
                    @if($lunas && str_contains(strtolower($lunas), 'lunas') && !str_contains(strtolower($lunas), 'belum'))
                    <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-bold bg-success-100 text-success-700 dark:bg-success-500/20 dark:text-success-400 border border-success-200 dark:border-success-500/30">
                        ✓ {{ $lunas }}
                    </span>
                    @elseif($lunas && str_contains(strtolower($lunas), 'belum'))
                    <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-bold bg-danger-100 text-danger-700 dark:bg-danger-500/20 dark:text-danger-400 border border-danger-200 dark:border-danger-500/30">
                        ✗ {{ $lunas }}
                    </span>
                    @else
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400 border border-gray-200 dark:border-gray-600">
                        - Tidak Ada Data
                    </span>
                    @endif
                </div>

                {{-- Total Volume --}}
                <div class="flex items-center justify-between border-t border-gray-100 dark:border-white/5 pt-3">
                    <span class="text-sm text-gray-500 dark:text-gray-400 font-medium">Total Volume</span>
                    <span class="font-mono font-bold text-gray-700 dark:text-gray-200">
                        {{ number_format($row['total_kubikasi'], 4, ',', '.') }} <span class="text-xs font-normal text-gray-400">m³</span>
                    </span>
                </div>
            </div>
            @empty
            <div class="text-center text-gray-400 dark:text-gray-500 italic py-8">
                <p>Data seri sudah tidak tersedia / habis terpakai.</p>
            </div>
            @endforelse
        </div>
    </div>
</div>