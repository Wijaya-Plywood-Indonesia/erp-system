<x-filament-panels::page>
    <div class="p-4 bg-white dark:bg-zinc-900 rounded-lg shadow border border-zinc-200 dark:border-zinc-800">
        {{ $this->form }}
    </div>

    @if($isLoading)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-white/50 dark:bg-zinc-900/50">
        <x-filament::loading-indicator class="w-10 h-10 text-primary-600" />
    </div>
    @endif

    <div class="space-y-12 mt-6">
        @forelse ($laporan as $data)
        @php
        $isMencapaiTarget = $data['hasil'] >= $data['target'];
        $warnaStatus = $isMencapaiTarget ? 'text-green-400 font-bold' : 'text-red-400 font-bold';
        $tanda = $data['selisih'] >= 0 ? '+' : '';
        @endphp

        <div class="bg-white dark:bg-zinc-900 rounded-sm shadow-lg border border-zinc-200 dark:border-zinc-700 overflow-hidden">
            <div class="bg-zinc-800 p-3 text-white text-center font-bold uppercase tracking-widest text-sm">
                {{ $data['kode_nama'] }}
            </div>

            <div class="p-4">
                <div class="w-full overflow-x-auto">
                    <table class="w-full text-sm border-collapse border border-zinc-300 dark:border-zinc-600">
                        <thead>
                            <tr class="bg-zinc-200 dark:bg-zinc-700 text-zinc-800 dark:text-zinc-300 text-xs tracking-tighter font-bold">
                                <th class="p-2 text-left border-r border-zinc-300 dark:border-zinc-700 w-1/3">Kode Ukuran</th>
                                <th class="p-2 text-center border-r border-zinc-300 dark:border-zinc-700 w-20">Hasil</th>
                                <th class="p-2 text-center border-r border-zinc-300 dark:border-zinc-700 w-20">Masuk</th>
                                <th class="p-2 text-center border-r border-zinc-300 dark:border-zinc-700 w-20">Pulang</th>
                                <th class="p-2 text-right border-r border-zinc-300 dark:border-zinc-700 w-28">Potongan</th>
                                <th class="p-2 text-center border-r border-zinc-300 dark:border-zinc-700 w-16">Ijin</th>
                                <th class="p-2 text-left px-4">Keterangan</th>
                            </tr>
                        </thead>

                        <tbody>
                            @foreach ($data['rincian'] as $index => $item)
                            <tr class="border-t border-zinc-300 dark:border-zinc-700">
                                <td class="p-2 text-left text-xs border-r border-zinc-300 dark:border-zinc-700 font-medium ">
                                    {{ $item['ukuran_lengkap'] }}
                                </td>
                                <td class="p-2 text-center text-xs border-r border-zinc-300 dark:border-zinc-700 font-mono">
                                    {{ number_format($item['jumlah']) }}
                                </td>
                                <td class="p-2 text-center text-xs border-r border-zinc-300 dark:border-zinc-700">
                                    {{ $index === 0 ? $data['jam_masuk'] : '' }}
                                </td>
                                <td class="p-2 text-center text-xs border-r border-zinc-300 dark:border-zinc-700">
                                    {{ $index === 0 ? $data['jam_pulang'] : '' }}
                                </td>
                                <td class="p-2 text-right text-xs border-r border-zinc-300 dark:border-zinc-700 font-bold text-red-400 font-mono">
                                    @if($index === 0 && $data['pot_target'] > 0)
                                    Rp {{ number_format($data['pot_target']) }}
                                    @elseif($index === 0)
                                    -
                                    @endif
                                </td>
                                <td class="p-2 text-center text-xs border-r border-zinc-300 dark:border-zinc-700 text-yellow-600 font-bold">
                                    {{ $index === 0 ? $data['ijin'] : '' }}
                                </td>
                                <td class="p-2 text-left text-xs italic text-zinc-500 px-4">
                                    {{ $index === 0 ? $data['keterangan'] : '' }}
                                </td>
                            </tr>
                            @endforeach
                        </tbody>

                        <tfoot class="bg-zinc-100 dark:bg-zinc-800 border-t-2 border-zinc-400">
                            <tr>
                                <td colspan="7" class="p-3 text-center text-xs space-x-6 font-bold">
                                    <span class="text-zinc-500">Target:</span> <strong>{{ number_format($data['target']) }}</strong>
                                    <span class="text-zinc-400">|</span>
                                    <span class="text-zinc-500">Total Hasil:</span> <strong class="{{ $warnaStatus }}">{{ number_format($data['hasil']) }}</strong>
                                    <span class="text-zinc-400">|</span>
                                    <span class="text-zinc-500">Selisih:</span> <strong class="{{ $warnaStatus }}">{{ $tanda }}{{ number_format(abs($data['selisih'])) }}</strong>
                                    <span class="text-zinc-400">|</span>
                                    <span class="text-zinc-500">Tanggal:</span> <strong class="text-zinc-400">{{ $data['tanggal'] }}</strong>
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
        @empty
        <div class="text-center p-12 bg-white dark:bg-zinc-900 rounded-lg border border-dashed border-zinc-300 dark:border-zinc-700">
            <x-heroicon-o-document-magnifying-glass class="w-12 h-12 mx-auto text-zinc-400 mb-4" />
            <p class="text-lg text-zinc-500 dark:text-zinc-400 font-medium">
                Tidak ditemukan data produksi potong jelek untuk tanggal ini.
            </p>
            <p class="text-sm text-zinc-400 mt-2">
                Silakan pilih tanggal lain atau pastikan input produksi potong jelek sudah dilakukan.
            </p>
        </div>
        @endforelse
    </div>
</x-filament-panels::page>