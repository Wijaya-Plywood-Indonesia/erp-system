<x-filament-panels::page>

    {{-- ============================ FILTER ============================ --}}
    <div
        class="fi-section rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 mb-6">
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
                        <th class="p-3 font-medium text-gray-600 dark:text-gray-300">Lini Produksi</th>
                        <th class="p-3 font-medium text-gray-600 dark:text-gray-300">Periode</th>
                        <th class="p-3 font-medium text-gray-600 dark:text-gray-300 text-center">Hari Kerja</th>
                        <th class="p-3 font-medium text-gray-600 dark:text-gray-300 text-right">Target</th>
                        <th class="p-3 font-medium text-gray-600 dark:text-gray-300 text-right">Hasil</th>
                        <th class="p-3 font-medium text-gray-600 dark:text-gray-300 text-right">%</th>
                        <th class="p-3 font-medium text-gray-600 dark:text-gray-300 text-right">Kekurangan</th>
                        <th class="p-3 font-medium text-gray-600 dark:text-gray-300 text-right">Gaji</th>
                        <th class="p-3 font-medium text-gray-600 dark:text-gray-300 text-right">Potongan</th>
                        <th class="p-3"></th>
                    </tr>
                </thead>
                @forelse ($this->rekap as $row)
                    <tbody x-data="{ open: false }" wire:key="row-{{ $row['kodep'] ?? $loop->index }}-{{ $row['lini'] ?? '' }}">
                        <tr x-on:click="open = !open" class="cursor-pointer border-b border-gray-100 dark:border-gray-800 hover:bg-gray-50 dark:hover:bg-gray-800/50">
                            <td class="p-3 font-medium text-gray-900 dark:text-white">
                                {{ $row['nama_pegawai'] }}
                                <div class="text-xs text-gray-400">{{ $row['kodep'] }}</div>
                            </td>
                            <td class="p-3">
                                <x-filament::badge color="info">
                                    {{ \App\Services\Payroll\LiniProduksiResolver::label($row['lini']) }}
                                </x-filament::badge>
                            </td>
                            <td class="p-3 text-gray-600 dark:text-gray-400 whitespace-nowrap">
                                {{ \Illuminate\Support\Carbon::parse($row['periode_mulai'])->translatedFormat('d M') }}
                                &ndash;
                                {{ \Illuminate\Support\Carbon::parse($row['periode_akhir'])->translatedFormat('d M Y') }}
                            </td>
                            <td class="p-3 text-center">{{ $row['hari_kerja'] }}</td>
                            <td class="p-3 text-right">{{ number_format($row['target'], 0, ',', '.') }}</td>
                            <td class="p-3 text-right">{{ number_format($row['hasil'], 0, ',', '.') }}</td>
                            <td class="p-3 text-right">{{ $row['persentase'] }}%</td>
                            <td class="p-3 text-right text-warning-600 dark:text-warning-400">
                                {{ number_format($row['kekurangan'], 0, ',', '.') }}
                            </td>
                            <td class="p-3 text-right">
                                {{ $row['gaji'] !== null ? 'Rp ' . number_format($row['gaji'], 0, ',', '.') : '-' }}
                            </td>
                            <td class="p-3 text-right font-semibold text-danger-600 dark:text-danger-400">
                                Rp {{ number_format($row['potongan'], 0, ',', '.') }}
                            </td>
                            <td class="p-3 text-right">
                                <button type="button" x-on:click="open = !open"
                                    class="text-primary-600 dark:text-primary-400 text-xs font-medium underline">
                                    <span x-text="open ? 'Tutup' : 'Detail'"></span>
                                </button>
                            </td>
                        </tr>
                        <tr x-show="open" x-cloak>
                            <td colspan="11" class="p-0 bg-gray-50 dark:bg-gray-800/50">
                                <div class="p-4">
                                    <table class="w-full text-xs">
                                        <thead>
                                            <tr
                                                class="text-left text-gray-500 dark:text-gray-400 border-b border-gray-200 dark:border-gray-700">
                                                <th class="pb-2 pr-4">Tanggal</th>
                                                <th class="pb-2 pr-4">Lini</th>
                                                <th class="pb-2 pr-4 text-right">Target</th>
                                                <th class="pb-2 pr-4 text-right">Hasil</th>
                                                <th class="pb-2 pr-4 text-right">Kurang</th>
                                                <th class="pb-2 text-right">Potongan</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($row['detail'] as $d)
                                                <tr class="border-b border-gray-100 dark:border-gray-800 last:border-0">
                                                    <td class="py-1.5 pr-4">
                                                        {{ \Illuminate\Support\Carbon::parse($d['tanggal'])->translatedFormat('d M Y') }}
                                                    </td>
                                                    <td class="py-1.5 pr-4">
                                                        {{ \App\Services\Payroll\LiniProduksiResolver::label($d['lini']) }}
                                                    </td>
                                                    <td class="py-1.5 pr-4 text-right">
                                                        {{ number_format($d['target'], 0, ',', '.') }}</td>
                                                    <td class="py-1.5 pr-4 text-right">
                                                        {{ number_format($d['hasil'], 0, ',', '.') }}</td>
                                                    <td
                                                        class="py-1.5 pr-4 text-right text-warning-600 dark:text-warning-400">
                                                        {{ number_format($d['kekurangan'], 0, ',', '.') }}
                                                    </td>
                                                    <td
                                                        class="py-1.5 text-right font-medium text-danger-600 dark:text-danger-400">
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
