<x-filament-panels::page>
    <!-- HEADER DENGAN FORM DI KANAN -->
    <div class="p-4 bg-white dark:bg-zinc-900 rounded-lg shadow">
        {{ $this->form }}
    </div>

    <!-- Loading Indicator -->
    @if($isLoading)
    <div
        class="fixed inset-0 z-50 flex items-center justify-center bg-white bg-opacity-75 dark:bg-zinc-900 dark:bg-opacity-75"
    >
        <div class="flex items-center space-x-3">
            <x-filament::loading-indicator class="w-8 h-8 text-primary-600" />
            <span class="text-lg font-medium text-zinc-700 dark:text-zinc-300">
                Memuat data...
            </span>
        </div>
    </div>
    @endif @php $dataProduksi = $dataProduksi ?? []; $groupedByMesin =
    collect($dataProduksi)->groupBy('mesin'); // key: "PRESS 1 - PAGI", dll
    @endphp

    <div class="space-y-12 mt-6">
        @forelse ($groupedByMesin as $mesinNama => $produksiList) @php $first =
        $produksiList->first(); $pekerja = $first['pekerja'] ?? []; $kodeUkuran
        = $first['ukuran'] ?? 'TIDAK ADA UKURAN'; $totalPekerja =
        count($pekerja); $hasil = $first['hasil'] ?? 0; $target =
        $first['target'] ?? 0; $selisih = $first['selisih'] ?? 0; $warna =
        $selisih >= 0 ? 'text-green-400' : 'text-red-400'; $tanda = $selisih >=
        0 ? '+' : ''; $jamKerja = $first['jam_kerja'] ?? 0; @endphp

        <!-- CARD MESIN -->
        <div
            class="bg-white dark:bg-zinc-900 rounded-sm shadow-lg border border-zinc-200 dark:border-zinc-700 overflow-hidden"
        >
            <div class="bg-zinc-800 p-4 text-white">
                <h2 class="text-lg font-bold text-center">
                    PEKERJA MESIN: {{ strtoupper($mesinNama) }} -
                    {{ strtoupper($kodeUkuran) }}
                </h2>
            </div>

            <div class="p-4">
                <div class="w-full overflow-x-auto">
                    <div class="min-w-[800px]">
                        <table
                            class="w-full text-sm border-collapse border border-zinc-300 dark:border-zinc-600"
                        >
                            <thead>
                                <tr>
                                    <th
                                        colspan="7"
                                        class="p-4 text-xl font-bold text-center bg-zinc-700 text-white"
                                    >
                                        DATA PEKERJA
                                    </th>
                                </tr>

                                <tr
                                    class="bg-zinc-200 dark:bg-zinc-700 text-zinc-800 dark:text-zinc-300 border-t border-zinc-300 dark:border-zinc-600"
                                >
                                    <th
                                        class="p-2 text-center text-xs font-medium w-16"
                                    >
                                        ID
                                    </th>
                                    <th
                                        class="p-2 text-left text-xs font-medium w-40"
                                    >
                                        Nama
                                    </th>
                                    <th
                                        class="p-2 text-center text-xs font-medium w-20"
                                    >
                                        Masuk
                                    </th>
                                    <th
                                        class="p-2 text-center text-xs font-medium w-20"
                                    >
                                        Pulang
                                    </th>
                                    <th
                                        class="p-2 text-center text-xs font-medium w-16"
                                    >
                                        Ijin
                                    </th>
                                    <th
                                        class="p-2 text-right text-xs font-medium w-36"
                                    >
                                        Potongan Target
                                    </th>
                                    <th
                                        class="p-2 text-left text-xs font-medium"
                                    >
                                        Keterangan
                                    </th>
                                </tr>
                            </thead>

                            <tbody>
                                @forelse ($pekerja as $i => $p)
                                <tr
                                    class="{{
                                        $i % 2 === 1
                                            ? 'bg-zinc-50 dark:bg-zinc-800/50'
                                            : 'bg-white dark:bg-zinc-900'
                                    }} border-t border-zinc-300 dark:border-zinc-700"
                                >
                                    <td
                                        class="p-2 text-center text-xs border-r border-zinc-300 dark:border-zinc-700 text-zinc-700 dark:text-zinc-300"
                                    >
                                        {{ $p["id"] ?? "-" }}
                                    </td>
                                    <td
                                        class="p-2 text-left text-xs border-r border-zinc-300 dark:border-zinc-700 text-zinc-900 dark:text-zinc-100 font-medium"
                                    >
                                        {{ $p["nama"] ?? "-" }}
                                    </td>
                                    <td
                                        class="p-2 text-center text-xs border-r border-zinc-300 dark:border-zinc-700 text-zinc-700 dark:text-zinc-300"
                                    >
                                        {{ $p["jam_masuk"] ?? "-" }}
                                    </td>
                                    <td
                                        class="p-2 text-center text-xs border-r border-zinc-300 dark:border-zinc-700 text-zinc-700 dark:text-zinc-300"
                                    >
                                        {{ $p["jam_pulang"] ?? "-" }}
                                    </td>
                                    <td
                                        class="p-2 text-center text-xs border-r border-zinc-300 dark:border-zinc-700 text-yellow-600 dark:text-yellow-400"
                                    >
                                        {{ $p["ijin"] ?? "-" }}
                                    </td>
                                    <td
                                        class="p-2 text-right text-xs border-r border-zinc-300 dark:border-zinc-700 font-bold {{
                                            $selisih < 0
                                                ? 'text-red-600 dark:text-red-400'
                                                : 'text-zinc-700'
                                        }}"
                                    >
                                        Rp
                                        {{
                                            number_format($p["pot_target"] ?? 0)
                                        }}
                                    </td>
                                    <td
                                        class="p-2 text-left text-xs text-zinc-700 dark:text-zinc-300"
                                    >
                                        {{ $p["keterangan"] ?? "-" }}
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td
                                        colspan="7"
                                        class="p-4 text-center text-zinc-500 dark:text-zinc-400 text-sm"
                                    >
                                        Tidak ada data pekerja untuk mesin ini.
                                    </td>
                                </tr>
                                @endforelse
                            </tbody>

                            <tfoot
                                class="bg-zinc-100 dark:bg-zinc-800 border-t-2 border-zinc-300 dark:border-zinc-600"
                            >
                                <tr>
                                    <td
                                        colspan="7"
                                        class="p-3 text-center text-xs text-zinc-600 dark:text-zinc-400 space-x-3"
                                    >
                                        <span class="font-medium"
                                            >Pekerja:</span
                                        >
                                        <strong>{{ $totalPekerja }}</strong>

                                        <span class="text-zinc-400">|</span>

                                        <span class="font-medium">Target:</span>
                                        <strong class="font-mono">{{
                                            number_format($target, 4, ',', '.')
                                        }}</strong>

                                        <span class="text-zinc-400">|</span>

                                        <span class="font-medium"
                                            >Jam Produksi :</span
                                        >
                                        <strong class="font-mono">{{
                                            number_format($jamKerja)
                                        }}</strong>

                                        <span class="text-zinc-400">|</span>

                                        <span class="font-medium">Hasil:</span>
                                        <strong
                                            class="font-mono {{ $warna }}"
                                            >{{ number_format($hasil, 4, ',', '.') }}</strong
                                        >

                                        <span class="text-zinc-400">|</span>

                                        <span class="font-medium"
                                            >Selisih:</span
                                        >
                                        <strong class="font-mono {{ $warna }}"
                                            >{{ $tanda
                                            }}{{
                                                number_format(abs($selisih), 4, ',', '.')
                                            }}</strong
                                        >

                                        <span class="text-zinc-400">|</span>

                                        <span class="text-xs"
                                            >Tanggal:
                                            {{ $first["tanggal"] }}</span
                                        >
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
                 @if(!empty($first['kendala']) && $first['kendala'] !== '-')
                 <div class="mt-4 p-3 bg-red-50 dark:bg-red-950/20 border border-red-100 dark:border-red-900/30 rounded-lg text-xs">
                     <span class="font-bold text-red-600 dark:text-red-400">KENDALA MESIN:</span>
                     <p class="mt-1 text-red-800 dark:text-red-200 font-medium whitespace-pre-line">{{ $first['kendala'] }}</p>
                 </div>
                 @endif

                 @if(!empty($first['keterangan_global']) && $first['keterangan_global'] !== '-')
                 <div class="mt-4 p-3 bg-zinc-50 dark:bg-zinc-800/40 border border-zinc-100 dark:border-zinc-700/50 rounded-lg text-xs">
                     <span class="font-bold text-zinc-600 dark:text-zinc-400">KETERANGAN:</span>
                     <p class="mt-1 text-zinc-800 dark:text-zinc-200 font-medium whitespace-pre-line">{{ $first['keterangan_global'] }}</p>
                 </div>
                 @endif
            </div>
        </div>
        @empty
        <div class="text-center p-12 text-zinc-500 dark:text-zinc-400">
            <p class="text-lg">Tidak ada data Press Dryer untuk tanggal ini.</p>
        </div>
        @endforelse
    </div>

    {{-- ✅ SCRIPT AUTO-REFRESH --}}
    @push('scripts')
    <script>
        document.addEventListener("DOMContentLoaded", function () {
            // ✅ AUTO-REFRESH saat window/tab kembali di-focus
            window.addEventListener("focus", function () {
                console.log(
                    "Window focused - refreshing data laporan press dryer..."
                );
                // Use Livewire JS API instead of Blade @this directive inside a script
                if (typeof Livewire !== "undefined" && Livewire.emit) {
                    Livewire.emit("loadData");
                } else if (
                    typeof window.livewire !== "undefined" &&
                    window.livewire.emit
                ) {
                    window.livewire.emit("loadData");
                } else {
                    console.warn("Livewire is not available to call loadData");
                }
            });
        });
    </script>
    @endpush
</x-filament-panels::page>
