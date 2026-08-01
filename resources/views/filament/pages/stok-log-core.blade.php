<x-filament-panels::page>
    @php
    $grouped = $this->groupedSummaries;
    @endphp

    <div class="flex flex-col gap-8">
        <div class="space-y-8">
            @forelse($grouped as $panjang => $rows)
            <div class="space-y-3">
                <div class="flex items-center gap-3">
                    <span class="bg-gray-800 dark:bg-gray-100 text-white dark:text-gray-900 text-[10px] font-black px-4 py-1.5 rounded uppercase tracking-widest shadow-sm">
                        Ukuran Panjang {{ $panjang }}
                    </span>
                    <div class="h-px flex-1 bg-gray-100 dark:bg-gray-900"></div>
                </div>

                <div class="bg-white dark:bg-gray-900 rounded border border-gray-200 dark:border-gray-800 shadow-sm overflow-hidden">
                    <table class="w-full text-sm text-left border-separate border-spacing-0">
                        <thead>
                            <tr class="text-gray-400 uppercase text-[9px] tracking-widest font-black bg-gray-50/50 dark:bg-gray-800/50">
                                <th class="px-6 py-3 text-center border-b border-gray-100 dark:border-gray-800 w-16">No</th>
                                <th class="px-6 py-3 border-b border-gray-100 dark:border-gray-800">Kode</th>
                                <th class="px-6 py-3 border-b border-gray-100 dark:border-gray-800">Jenis Kayu</th>
                                <th class="px-6 py-3 border-b border-gray-100 dark:border-gray-800">Panjang</th>
                                <th class="px-6 py-3 text-center border-b border-gray-100 dark:border-gray-800">Qty LogCore</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50 dark:divide-gray-800">
                            @foreach($rows as $row)
                            <tr class="group hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors">
                                <td class="px-6 py-4 text-center text-gray-300 dark:text-gray-600 font-mono text-xs">{{ $loop->iteration }}</td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex items-center bg-amber-50 dark:bg-amber-950/30 text-amber-700 dark:text-amber-400 text-[10px] font-black px-2 py-1 rounded border border-amber-200/50 dark:border-amber-900/50 uppercase tracking-wider">
                                        LOGCORE
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="font-bold text-gray-700 dark:text-gray-300 uppercase tracking-tight">
                                        {{ $row->jenisKayu->nama_kayu }}
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="font-black text-gray-800 dark:text-gray-200 text-base">{{ $row->panjang }}</span>
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <span class="font-black text-gray-700 dark:text-gray-300 tabular-nums text-lg">
                                        {{ number_format($row->stok_qty) }}
                                    </span>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            @empty
            <div class="py-12 text-center text-gray-400 dark:text-gray-600 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded">
                Tidak ada data stok LogCore tersedia
            </div>
            @endforelse
        </div>
    </div>
</x-filament-panels::page>