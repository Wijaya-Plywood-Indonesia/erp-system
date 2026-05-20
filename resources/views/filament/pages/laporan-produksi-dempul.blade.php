<x-filament-panels::page>
    <div class="p-4 bg-white dark:bg-zinc-900 rounded-lg shadow">
        {{ $this->form }}
    </div>

    <div wire:loading wire:target="loadAllData" class="w-full text-center py-4">
        <x-filament::loading-indicator class="w-8 h-8 mx-auto text-primary-600 mb-2" />
        <span class="text-zinc-500 italic">Memproses laporan Produksi Dempul...</span>
    </div>

    <div wire:loading.remove class="space-y-12 mt-6">
        @if(!empty($reportData['detail']))
        <div class="bg-white dark:bg-zinc-900 rounded-sm shadow-lg border border-zinc-200 dark:border-zinc-700 overflow-hidden">
            <div class="bg-zinc-800 p-4 text-white text-center">
                <h2 class="text-lg font-bold uppercase tracking-widest">
                    LAPORAN PRODUKSI DEMPUL - {{ \Carbon\Carbon::parse($this->tanggal)->format('d F Y') }}
                </h2>
            </div>

            <div class="p-4 overflow-x-auto">
                <div class="flex flex-col lg:flex-row gap-8 min-w-[1200px]">
                    
                    {{-- TABEL KIRI: DETAIL PRODUKSI --}}
                    <div class="flex-[2]">
                        <h3 class="text-sm font-bold mb-2 uppercase text-zinc-600 dark:text-zinc-400">Detail Produksi</h3>
                        <table class="w-full text-[11px] border-collapse border border-zinc-300 dark:border-zinc-700">
                            <thead>
                                <tr class="bg-zinc-100 dark:bg-zinc-800 text-zinc-800 dark:text-zinc-200 uppercase font-bold">
                                    <th class="p-2 border border-zinc-300 dark:border-zinc-700">Tanggal</th>
                                    <th class="p-2 border border-zinc-300 dark:border-zinc-700">P</th>
                                    <th class="p-2 border border-zinc-300 dark:border-zinc-700">L</th>
                                    <th class="p-2 border border-zinc-300 dark:border-zinc-700">T</th>
                                    <th class="p-2 border border-zinc-300 dark:border-zinc-700 text-left">Jenis</th>
                                    <th class="p-2 border border-zinc-300 dark:border-zinc-700 text-left">Grade</th>
                                    <th class="p-2 border border-zinc-300 dark:border-zinc-700 text-center">Byk</th>
                                    <th class="p-2 border border-zinc-300 dark:border-zinc-700 text-right bg-blue-50 dark:bg-blue-900/20">m3</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                                @foreach($reportData['detail'] as $d)
                                <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/50">
                                    <td class="p-2 border border-zinc-300 dark:border-zinc-700 text-center">{{ $d['tanggal'] }}</td>
                                    <td class="p-2 border border-zinc-300 dark:border-zinc-700 text-center">{{ $d['p'] }}</td>
                                    <td class="p-2 border border-zinc-300 dark:border-zinc-700 text-center">{{ $d['l'] }}</td>
                                    <td class="p-2 border border-zinc-300 dark:border-zinc-700 text-center">{{ $d['t'] }}</td>
                                    <td class="p-2 border border-zinc-300 dark:border-zinc-700">{{ $d['jenis'] }}</td>
                                    <td class="p-2 border border-zinc-300 dark:border-zinc-700">{{ $d['grade'] }}</td>
                                    <td class="p-2 border border-zinc-300 dark:border-zinc-700 text-center font-bold">{{ number_format($d['byk']) }}</td>
                                    <td class="p-2 border border-zinc-300 dark:border-zinc-700 text-right font-mono bg-blue-50/50 dark:bg-blue-900/10"></td>
                                </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                @php
                                    $totalByk = collect($reportData['detail'])->sum('byk');
                                @endphp
                                <tr class="bg-zinc-100 dark:bg-zinc-800 font-bold">
                                    <td colspan="6" class="p-2 text-right border border-zinc-300 dark:border-zinc-700">TOTAL:</td>
                                    <td class="p-2 text-center border border-zinc-300 dark:border-zinc-700">{{ number_format($totalByk) }}</td>
                                    <td class="p-2 border border-zinc-300 dark:border-zinc-700"></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    {{-- TABEL KANAN: REKAP HARGA/ONGKOS (Preview Column) --}}
                    <div class="flex-1">
                        <h3 class="text-sm font-bold mb-2 uppercase text-zinc-600 dark:text-zinc-400">Rekap Ongkos (Preview)</h3>
                        <table class="w-full text-[11px] border-collapse border border-zinc-300 dark:border-zinc-700">
                            <thead>
                                <tr class="bg-zinc-100 dark:bg-zinc-800 text-zinc-800 dark:text-zinc-200 uppercase font-bold">
                                    <th class="p-2 border border-zinc-300 dark:border-zinc-700">Tanggal</th>
                                    <th class="p-2 border border-zinc-300 dark:border-zinc-700 text-center">TTL PKJ</th>
                                    <th class="p-2 border border-zinc-300 dark:border-zinc-700 text-right">Total m3</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                                @foreach($reportData['summary'] as $s)
                                <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/50">
                                    <td class="p-2 border border-zinc-300 dark:border-zinc-700 text-center">{{ $s['tanggal'] }}</td>
                                    <td class="p-2 border border-zinc-300 dark:border-zinc-700 text-center font-bold">{{ $s['ttl_pkj'] }}</td>
                                    <td class="p-2 border border-zinc-300 dark:border-zinc-700 text-right font-mono"></td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                        <div class="mt-4 p-4 bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded text-[10px] text-yellow-800 dark:text-yellow-200 italic">
                            * Kolom HARGA, Total m3, ONGKOS PER M3, dan ONGKOS PER LB akan tersedia sebagai kolom kosong di Excel untuk diisi oleh Manajemen.
                        </div>
                    </div>

                </div>
            </div>
        </div>
        @else
        <div class="p-16 text-center bg-zinc-50 dark:bg-zinc-900 rounded-xl border border-dashed border-zinc-300 dark:border-zinc-700">
            <x-heroicon-o-document-magnifying-glass class="w-12 h-12 mx-auto text-zinc-400 mb-4"/>
            <p class="text-zinc-500 italic text-lg">
                Tidak ada data produksi Dempul untuk tanggal ini.
            </p>
        </div>
        @endif
    </div>
</x-filament-panels::page>
