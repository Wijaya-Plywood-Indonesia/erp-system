<x-filament-panels::page>
    <div class="p-4 bg-white dark:bg-zinc-900 rounded-lg shadow">
        {{ $this->form }}
    </div>

    <div wire:loading wire:target="loadRekap,tanggalAwal,tanggalAkhir" class="w-full text-center py-4">
        <x-filament::loading-indicator class="w-8 h-8 mx-auto text-primary-600 mb-2" />
        <span class="text-zinc-500 italic">Memproses rekap Produksi Sanding...</span>
    </div>

    <div wire:loading.remove class="space-y-12 mt-6">
        @if(!empty($rekapPerMesin))

            @foreach($rekapPerMesin as $kategori => $data)
                @php
                    $rekapTanggal = $data['rekapTanggal'];
                    $rekapUkuran = $data['rekapUkuran'];
                    $daftarUkuran = $data['daftarUkuran'];
                    $grandTotal = $data['grandTotal'];
                @endphp

                <div class="space-y-6">
                    <div class="flex items-center gap-3">
                        <div class="h-8 w-1.5 rounded-full {{ $kategori === 'Besar' ? 'bg-blue-600' : 'bg-emerald-600' }}"></div>
                        <h1 class="text-xl font-extrabold uppercase tracking-wide text-zinc-800 dark:text-zinc-100">
                            Mesin Sanding {{ $kategori }}
                        </h1>
                    </div>

                    {{-- [1] REKAP PER TANGGAL --}}
                    <div class="bg-white dark:bg-zinc-900 rounded-sm shadow-lg border border-zinc-200 dark:border-zinc-700 overflow-hidden">
                        <div class="bg-zinc-800 p-4 text-white text-center">
                            <h2 class="text-lg font-bold uppercase tracking-widest">
                                Rekap Per Tanggal
                            </h2>
                            <p class="text-xs text-zinc-300 mt-1">
                                {{ \Carbon\Carbon::parse($tanggalAwal)->format('d M Y') }}
                                &mdash;
                                {{ \Carbon\Carbon::parse($tanggalAkhir)->format('d M Y') }}
                            </p>
                        </div>

                        <div class="p-4 overflow-x-auto">
                            <table class="w-full text-[12px] border-collapse border border-zinc-300 dark:border-zinc-700">
                                <thead>
                                    <tr class="bg-zinc-100 dark:bg-zinc-800 text-zinc-800 dark:text-zinc-200 uppercase font-bold">
                                        <th class="p-2 border border-zinc-300 dark:border-zinc-700 text-left">Tanggal</th>
                                        <th class="p-2 border border-zinc-300 dark:border-zinc-700">Pagi</th>
                                        <th class="p-2 border border-zinc-300 dark:border-zinc-700">Malam</th>
                                        <th class="p-2 border border-zinc-300 dark:border-zinc-700">Total</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                                    @foreach($rekapTanggal as $r)
                                    <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/50">
                                        <td class="p-2 border border-zinc-300 dark:border-zinc-700">{{ \Carbon\Carbon::parse($r['tanggal'])->format('d-m-Y') }}</td>
                                        <td class="p-2 border border-zinc-300 dark:border-zinc-700 text-center">{{ number_format($r['pagi']) }}</td>
                                        <td class="p-2 border border-zinc-300 dark:border-zinc-700 text-center">{{ number_format($r['malam']) }}</td>
                                        <td class="p-2 border border-zinc-300 dark:border-zinc-700 text-center font-bold">{{ number_format($r['total']) }}</td>
                                    </tr>
                                    @endforeach
                                </tbody>
                                <tfoot>
                                    <tr class="bg-amber-50 dark:bg-amber-900/20 font-bold">
                                        <td class="p-2 border border-zinc-300 dark:border-zinc-700">TOTAL</td>
                                        <td class="p-2 border border-zinc-300 dark:border-zinc-700 text-center">{{ number_format(collect($rekapTanggal)->sum('pagi')) }}</td>
                                        <td class="p-2 border border-zinc-300 dark:border-zinc-700 text-center">{{ number_format(collect($rekapTanggal)->sum('malam')) }}</td>
                                        <td class="p-2 border border-zinc-300 dark:border-zinc-700 text-center">{{ number_format($grandTotal) }}</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>

                    {{-- [2] REKAP PER TANGGAL, PER SHIFT, PER UKURAN --}}
                    <div class="bg-white dark:bg-zinc-900 rounded-sm shadow-lg border border-zinc-200 dark:border-zinc-700 overflow-hidden">
                        <div class="bg-zinc-800 p-4 text-white text-center">
                            <h2 class="text-lg font-bold uppercase tracking-widest">
                                Rekap Per Tanggal, Per Shift, Per Ukuran (p x l x t)
                            </h2>
                        </div>

                        <div class="p-4 overflow-x-auto">
                            <table class="w-full text-[11px] border-collapse border border-zinc-300 dark:border-zinc-700 min-w-[900px]">
                                <thead>
                                    <tr class="bg-zinc-100 dark:bg-zinc-800 text-zinc-800 dark:text-zinc-200 uppercase font-bold">
                                        <th class="p-2 border border-zinc-300 dark:border-zinc-700 text-left">Tanggal</th>
                                        <th class="p-2 border border-zinc-300 dark:border-zinc-700 text-left">Shift</th>
                                        @foreach($daftarUkuran as $u)
                                            <th class="p-2 border border-zinc-300 dark:border-zinc-700">{{ $u }}</th>
                                        @endforeach
                                        <th class="p-2 border border-zinc-300 dark:border-zinc-700 bg-amber-100 dark:bg-amber-900/30">Total</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                                    @php
                                        $rowspanPerTanggal = collect($rekapUkuran)->countBy('tanggal');
                                        $tanggalSudahDicetak = [];
                                        $groupIndex = 0;
                                        $tanggalTerakhir = null;
                                    @endphp
                                    @foreach($rekapUkuran as $r)
                                        @php
                                            if ($tanggalTerakhir !== $r['tanggal']) {
                                                $groupIndex++;
                                                $tanggalTerakhir = $r['tanggal'];
                                            }
                                            $rowBg = $groupIndex % 2 === 0 ? '' : 'bg-zinc-50 dark:bg-zinc-800/40';
                                        @endphp
                                        <tr class="hover:bg-zinc-100 dark:hover:bg-zinc-800/60 {{ $rowBg }}">
                                            @if(!isset($tanggalSudahDicetak[$r['tanggal']]))
                                                @php $tanggalSudahDicetak[$r['tanggal']] = true; @endphp
                                                <td rowspan="{{ $rowspanPerTanggal[$r['tanggal']] }}"
                                                    class="p-2 border border-zinc-300 dark:border-zinc-700 align-middle font-semibold">
                                                    {{ \Carbon\Carbon::parse($r['tanggal'])->format('d-m-Y') }}
                                                </td>
                                            @endif
                                            <td class="p-2 border border-zinc-300 dark:border-zinc-700">{{ $r['shift'] }}</td>
                                            @foreach($daftarUkuran as $u)
                                                <td class="p-2 border border-zinc-300 dark:border-zinc-700 text-center">{{ number_format($r['ukuran'][$u] ?? 0) }}</td>
                                            @endforeach
                                            <td class="p-2 border border-zinc-300 dark:border-zinc-700 text-center font-bold bg-amber-50/50 dark:bg-amber-900/10">{{ number_format($r['total']) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                                <tfoot>
                                    <tr class="bg-amber-50 dark:bg-amber-900/20 font-bold">
                                        <td colspan="2" class="p-2 border border-zinc-300 dark:border-zinc-700 text-right">TOTAL</td>
                                        @foreach($daftarUkuran as $u)
                                            <td class="p-2 border border-zinc-300 dark:border-zinc-700 text-center">
                                                {{ number_format(collect($rekapUkuran)->sum(fn($r) => $r['ukuran'][$u] ?? 0)) }}
                                            </td>
                                        @endforeach
                                        <td class="p-2 border border-zinc-300 dark:border-zinc-700 text-center">{{ number_format($grandTotal) }}</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
            @endforeach

        @else
        <div class="p-16 text-center bg-zinc-50 dark:bg-zinc-900 rounded-xl border border-dashed border-zinc-300 dark:border-zinc-700">
            <x-heroicon-o-document-magnifying-glass class="w-12 h-12 mx-auto text-zinc-400 mb-4"/>
            <p class="text-zinc-500 italic text-lg">
                Tidak ada data produksi Sanding untuk rentang tanggal ini.
            </p>
        </div>
        @endif
    </div>
</x-filament-panels::page>