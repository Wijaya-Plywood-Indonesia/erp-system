<x-filament-panels::page>

    {{-- ============================ FILTER ============================ --}}
    <div
        class="fi-section rounded-xl bg-white p-4 md:p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 mb-6">
        {{ $this->form }}

        @unless ($this->isPeriodeValid())
            <div class="mt-4 text-sm font-medium text-danger-600 dark:text-danger-400">
                ⚠️ Tanggal mulai tidak boleh lebih besar dari tanggal akhir.
            </div>
        @endunless
    </div>

    {{-- ============================ TABLE ============================ --}}
    <div
        class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 dark:bg-gray-800">
                    <tr class="text-left border-b border-gray-200 dark:border-gray-700">
                        <th class="p-3 font-medium text-gray-600 dark:text-gray-300">Pegawai</th>
                        <th class="p-3 font-medium text-gray-600 dark:text-gray-300 hidden md:table-cell">Lini Produksi</th>
                        <th class="p-3 font-medium text-gray-600 dark:text-gray-300 hidden lg:table-cell">Periode</th>
                        <th class="p-3 font-medium text-gray-600 dark:text-gray-300 text-center hidden md:table-cell">Hari Kerja</th>
                        <th class="p-3 font-medium text-gray-600 dark:text-gray-300 text-right hidden sm:table-cell">Target</th>
                        <th class="p-3 font-medium text-gray-600 dark:text-gray-300 text-right hidden sm:table-cell">Hasil</th>
                        <th class="p-3 font-medium text-gray-600 dark:text-gray-300 text-right hidden lg:table-cell">%</th>
                        <th class="p-3 font-medium text-gray-600 dark:text-gray-300 text-right hidden md:table-cell">Kekurangan</th>
                        <th class="p-3 font-medium text-gray-600 dark:text-gray-300 text-right hidden lg:table-cell">Gaji</th>
                        <th class="p-3 font-medium text-gray-600 dark:text-gray-300 text-right">Potongan</th>
                        <th class="p-3"></th>
                    </tr>
                </thead>
                @forelse ($this->rekap as $row)
                    <tbody x-data="{ open: false }" wire:key="row-{{ $row['kodep'] ?? $loop->index }}-{{ $row['lini'] ?? '' }}">
                        <tr x-on:click="open = !open" class="cursor-pointer border-b border-gray-100 dark:border-gray-800 hover:bg-gray-50 dark:hover:bg-gray-800/50">
                            <td class="p-3 font-medium text-gray-900 dark:text-white">
                                {{ $row['nama_pegawai'] }}
                                <div class="flex items-center gap-2 mt-1">
                                    <span class="text-xs text-gray-400">{{ $row['kodep'] }}</span>
                                    <div class="md:hidden">
                                        <x-filament::badge color="info" size="sm">
                                            {{ \App\Services\Payroll\LiniProduksiResolver::label($row['lini']) }}
                                        </x-filament::badge>
                                    </div>
                                </div>
                            </td>
                            <td class="p-3 hidden md:table-cell">
                                <x-filament::badge color="info">
                                    {{ \App\Services\Payroll\LiniProduksiResolver::label($row['lini']) }}
                                </x-filament::badge>
                            </td>
                            <td class="p-3 text-gray-600 dark:text-gray-400 whitespace-nowrap hidden lg:table-cell">
                                {{ \Illuminate\Support\Carbon::parse($row['periode_mulai'])->translatedFormat('d M') }}
                                &ndash;
                                {{ \Illuminate\Support\Carbon::parse($row['periode_akhir'])->translatedFormat('d M Y') }}
                            </td>
                            <td class="p-3 text-center hidden md:table-cell">{{ $row['hari_kerja'] }}</td>
                            <td class="p-3 text-right hidden sm:table-cell">{{ number_format($row['target'], 0, ',', '.') }}</td>
                            <td class="p-3 text-right hidden sm:table-cell">{{ number_format($row['hasil'], 0, ',', '.') }}</td>
                            <td class="p-3 text-right hidden lg:table-cell">{{ $row['persentase'] }}%</td>
                            <td class="p-3 text-right text-warning-600 dark:text-warning-400 hidden md:table-cell">
                                {{ number_format($row['kekurangan'], 0, ',', '.') }}
                            </td>
                            <td class="p-3 text-right hidden lg:table-cell">
                                {{ $row['gaji'] !== null ? 'Rp ' . number_format($row['gaji'], 0, ',', '.') : '-' }}
                            </td>
                            <td class="p-3 text-right font-semibold text-danger-600 dark:text-danger-400">
                                Rp {{ number_format($row['potongan'], 0, ',', '.') }}
                            </td>
                            <td class="p-3 text-right">
                                <button type="button" x-on:click.stop="open = !open"
                                    class="text-primary-600 dark:text-primary-400 text-xs font-medium underline">
                                    <span x-text="open ? 'Tutup' : 'Detail'"></span>
                                </button>
                            </td>
                        </tr>
                        <tr x-show="open" x-cloak>
                            <td colspan="11" class="p-0 bg-gray-50 dark:bg-gray-800/50">
                                <div class="p-3 md:p-4">
                                    <table class="w-full text-xs">
                                        <thead>
                                            <tr
                                                class="text-left text-gray-500 dark:text-gray-400 border-b border-gray-200 dark:border-gray-700">
                                                <th class="pb-2 pr-2 md:pr-4">Tanggal</th>
                                                <th class="pb-2 pr-2 md:pr-4 hidden md:table-cell">Lini</th>
                                                <th class="pb-2 pr-2 md:pr-4 text-right hidden sm:table-cell">Target</th>
                                                <th class="pb-2 pr-2 md:pr-4 text-right hidden sm:table-cell">Hasil</th>
                                                <th class="pb-2 pr-2 md:pr-4 text-right">Kurang</th>
                                                <th class="pb-2 text-right">Potongan</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($row['detail'] as $d)
                                                <tr class="border-b border-gray-100 dark:border-gray-800 last:border-0">
                                                    <td class="py-2 md:py-1.5 pr-2 md:pr-4 whitespace-nowrap">
                                                        {{ \Illuminate\Support\Carbon::parse($d['tanggal'])->translatedFormat('d M Y') }}
                                                    </td>
                                                    <td class="py-2 md:py-1.5 pr-2 md:pr-4 hidden md:table-cell">
                                                        {{ \App\Services\Payroll\LiniProduksiResolver::label($d['lini']) }}
                                                    </td>
                                                    <td class="py-2 md:py-1.5 pr-2 md:pr-4 text-right hidden sm:table-cell">
                                                        {{ number_format($d['target'], 0, ',', '.') }}</td>
                                                    <td class="py-2 md:py-1.5 pr-2 md:pr-4 text-right hidden sm:table-cell">
                                                        {{ number_format($d['hasil'], 0, ',', '.') }}</td>
                                                    <td
                                                        class="py-2 md:py-1.5 pr-2 md:pr-4 text-right text-warning-600 dark:text-warning-400">
                                                        {{ number_format($d['kekurangan'], 0, ',', '.') }}
                                                    </td>
                                                    <td
                                                        class="py-2 md:py-1.5 text-right font-medium text-danger-600 dark:text-danger-400">
                                                        Rp {{ number_format($d['potongan'], 0, ',', '.') }}
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                @empty
                    <tbody>
                        <tr>
                            <td colspan="11" class="p-8 text-center text-gray-500 dark:text-gray-400">
                                Tidak ada pegawai dengan potongan target produksi pada periode &amp; lini yang dipilih.
                            </td>
                        </tr>
                    </tbody>
                @endforelse
            </table>
        </div>
    </div>

</x-filament-panels::page>
