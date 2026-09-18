<x-filament-panels::page>
    {{--
        `relative z-20` di sini SENGAJA supaya dropdown datepicker & select
        selalu di atas tabel hasil di bawahnya. Tabel hasil dibungkus
        `isolate` (lihat bawah) supaya sticky column-nya tidak "bocor" ke
        atas dropdown ini walau browser kasih compositing khusus untuk
        elemen `position: sticky` di dalam container scroll.
    --}}
    <div class="relative z-20 rounded-xl bg-white p-4 shadow dark:bg-gray-800">
        {{ $this->filterForm }}

        <div class="mt-4 flex flex-wrap items-center gap-2">
            <x-filament::button
                wire:click="tampilkanRekap"
                wire:loading.attr="disabled"
                wire:target="tampilkanRekap"
                icon="heroicon-o-magnifying-glass"
            >
                <span wire:loading.remove wire:target="tampilkanRekap">Tampilkan</span>
                <span wire:loading wire:target="tampilkanRekap">Memuat...</span>
            </x-filament::button>

            <x-filament::button
                wire:click="exportExcel"
                wire:loading.attr="disabled"
                wire:target="exportExcel"
                color="success"
                icon="heroicon-o-arrow-down-tray"
            >
                <span wire:loading.remove wire:target="exportExcel">Export Excel</span>
                <span wire:loading wire:target="exportExcel">Menyiapkan file...</span>
            </x-filament::button>

            {{-- Indikator kecil di samping tombol, jaga-jaga user tidak sadar tombolnya lagi loading --}}
            <span wire:loading wire:target="tampilkanRekap,exportExcel" class="flex items-center gap-1.5 text-sm text-gray-500">
                <svg class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                </svg>
                Sedang memproses data, mohon tunggu...
            </span>
        </div>
    </div>

    {{-- Hasil rekap --}}
    @if ($sudahDitampilkan)
        @if ($rekap && $rekap->isNotEmpty())
            {{--
                `isolate relative z-0` = batas stacking context, supaya
                sticky column di dalam tabel ini tidak pernah menimpa
                dropdown di form filter di atas, apapun yang terjadi.
            --}}
            <div
                wire:loading.class="opacity-40 pointer-events-none"
                wire:target="tampilkanRekap"
                class="isolate relative z-0 mt-6 overflow-x-auto rounded-xl bg-white shadow transition-opacity dark:bg-gray-800"
            >
                <table class="w-max min-w-full text-sm">
                    <thead>
                        {{-- Baris 1: tanggal, tiap tanggal membentang 2 kolom --}}
                        <tr class="bg-gray-800 text-white">
                            <th class="sticky left-0 z-10 bg-gray-800 px-3 py-2" rowspan="2">Kode</th>
                            <th class="sticky left-[70px] z-10 bg-gray-800 px-3 py-2" rowspan="2">Nama</th>
                            <th class="px-3 py-2" rowspan="2">Tot Poin</th>

                            @foreach ($periode as $tanggal)
                                <th class="border-l border-gray-600 px-2 py-1 text-center" colspan="2">
                                    {{ \Illuminate\Support\Carbon::parse($tanggal)->format('d/m') }}
                                </th>
                            @endforeach
                        </tr>
                        {{-- Baris 2: sub-header jam kerja / poin --}}
                        <tr class="bg-gray-700 text-white">
                            @foreach ($periode as $tanggal)
                                <th class="border-l border-gray-600 px-2 py-1 text-center text-xs">Jam</th>
                                <th class="px-2 py-1 text-center text-xs">Poin</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rekap as $pegawai)
                            <tr wire:key="rekap-bulanan-{{ $pegawai['id_pegawai'] }}" class="odd:bg-gray-50 dark:odd:bg-gray-900/40">
                                <td class="sticky left-0 z-10 bg-inherit px-3 py-1.5 text-center">
                                    {{ $pegawai['kode_pegawai'] ?? '-' }}
                                </td>
                                <td class="sticky left-[70px] z-10 bg-inherit px-3 py-1.5 whitespace-nowrap">
                                    {{ $pegawai['nama_pegawai'] }}
                                </td>
                                <td class="px-3 py-1.5 text-center font-semibold">
                                    {{ $pegawai['total_poin'] }}
                                </td>

                                @foreach ($periode as $tanggal)
                                    @php $harian = $pegawai['harian'][$tanggal] ?? ['jam_kerja' => 0, 'poin' => 0]; @endphp
                                    <td class="border-l border-gray-200 px-2 py-1.5 text-center dark:border-gray-700">
                                        {{ $harian['jam_kerja'] }}
                                    </td>
                                    <td class="px-2 py-1.5 text-center {{ $harian['poin'] > 0 ? 'text-green-600 font-semibold' : 'text-gray-400' }}">
                                        {{ $harian['poin'] }}
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="mt-6 rounded-xl bg-white p-6 text-center text-gray-500 shadow dark:bg-gray-800">
                Tidak ada pegawai yang cocok dengan filter ini.
            </div>
        @endif
    @endif
</x-filament-panels::page>