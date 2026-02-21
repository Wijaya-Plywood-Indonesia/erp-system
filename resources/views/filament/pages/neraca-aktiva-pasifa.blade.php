<x-filament-panels::page class="!max-w-full">

    {{-- ============ FILTER PERIODE ============ --}}
    <div class="p-4 mb-6 rounded-xl shadow-sm border bg-white dark:bg-gray-800 dark:border-gray-700"
         x-data="{
            load() {
                $wire.call('loadData')
            }
         }">

        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">

            {{-- Bulan Mulai --}}
            <div>
                <label class="font-semibold text-sm dark:text-gray-200">Bulan Mulai</label>
                <select wire:model="bulan_mulai" class="mt-1 w-full border-gray-300 rounded-lg dark:bg-gray-700 dark:text-white">
                    @foreach(range(1, 12) as $b)
                        <option value="{{ $b }}">{{ \Carbon\Carbon::create()->month($b)->translatedFormat('F') }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Tahun Mulai --}}
            <div>
                <label class="font-semibold text-sm dark:text-gray-200">Tahun Mulai</label>
                <input type="number" wire:model="tahun_mulai"
                       class="mt-1 w-full border-gray-300 rounded-lg dark:bg-gray-700 dark:text-white">
            </div>

            {{-- Bulan Akhir --}}
            <div>
                <label class="font-semibold text-sm dark:text-gray-200">Bulan Akhir</label>
                <select wire:model="bulan_akhir" class="mt-1 w-full border-gray-300 rounded-lg dark:bg-gray-700 dark:text-white">
                    @foreach(range(1, 12) as $b)
                        <option value="{{ $b }}">{{ \Carbon\Carbon::create()->month($b)->translatedFormat('F') }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Tahun Akhir --}}
            <div>
                <label class="font-semibold text-sm dark:text-gray-200">Tahun Akhir</label>
                <input type="number" wire:model="tahun_akhir"
                       class="mt-1 w-full border-gray-300 rounded-lg dark:bg-gray-700 dark:text-white">
            </div>

        </div>

        <button @click="load()"
                class="mt-4 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
            Terapkan Periode
        </button>

        @error('periode')
            <p class="text-red-500 mt-2">{{ $message }}</p>
        @enderror
    </div>



    {{-- ============ LOOP PER BULAN ============ --}}
    @foreach($periodeData as $bulan)

        <div class="mb-12">

            {{-- === HEADER BULAN === --}}
            <h2 class="text-xl font-bold mb-3 text-gray-900 dark:text-white">
                {{ $bulan['label'] }}
            </h2>

            {{-- GRID 2 KOLOM --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">

                {{-- ===================== AKTIVA ===================== --}}
                <div class="rounded-xl border-2 border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 shadow-sm">

                    <div class="px-6 py-4 border-b-2 border-gray-300 dark:border-gray-600 bg-gray-100 dark:bg-gray-800">
                        <h2 class="font-bold text-lg text-gray-800 dark:text-white">
                            AKTIVA
                        </h2>
                    </div>

                    <table class="w-full text-sm border-collapse">
                        <thead class="bg-gray-200 dark:bg-gray-800 text-gray-700 dark:text-gray-200">
                            <tr>
                                <th class="border border-gray-300 dark:border-gray-600 px-4 py-2">No Akun</th>
                                <th class="border border-gray-300 dark:border-gray-600 px-4 py-2">Nama Akun</th>
                                <th class="border border-gray-300 dark:border-gray-600 px-4 py-2 text-right">Nilai</th>
                            </tr>
                        </thead>

                        <tbody>
                            @php
                                $totalAktiva = 0;
                            @endphp

                            @foreach($bulan['aktiva'] as $row)
                                @php $totalAktiva += $row['saldo']; @endphp

                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/60">
                                    <td class="border px-4 py-2 dark:border-gray-600">{{ $row['kode'] }}</td>
                                    <td class="border px-4 py-2 dark:border-gray-600">{{ $row['nama'] ?: '-' }}</td>
                                    <td class="border px-4 py-2 text-right font-semibold dark:border-gray-600">
                                        {{ $row['saldo'] != 0 ? number_format($row['saldo'],0,',','.') : '-' }}
                                    </td>
                                </tr>
                            @endforeach

                            <tr class="bg-yellow-100 dark:bg-yellow-900/40 font-bold">
                                <td colspan="2" class="border-2 px-4 py-3 text-right dark:border-gray-500">TOTAL AKTIVA</td>

                                <td class="border-2 px-4 py-3 text-right dark:border-gray-500 text-yellow-700 dark:text-yellow-300">
                                    {{ number_format($totalAktiva,0,',','.') }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>


                {{-- ===================== PASIVA ===================== --}}
                <div class="rounded-xl border-2 border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 shadow-sm">

                    <div class="px-6 py-4 border-b-2 border-gray-300 dark:border-gray-600 bg-gray-100 dark:bg-gray-800">
                        <h2 class="font-bold text-lg text-gray-800 dark:text-white">
                            PASIVA
                        </h2>
                    </div>

                    <table class="w-full text-sm border-collapse">
                        <thead class="bg-gray-200 dark:bg-gray-800 text-gray-700 dark:text-gray-200">
                            <tr>
                                <th class="border px-4 py-2 dark:border-gray-600">No Akun</th>
                                <th class="border px-4 py-2 dark:border-gray-600">Nama Akun</th>
                                <th class="border px-4 py-2 text-right dark:border-gray-600">Nilai</th>
                            </tr>
                        </thead>

                        <tbody>
                            @php
                                $totalPasiva = 0;
                            @endphp

                            @foreach($bulan['pasiva'] as $row)
                                @php $totalPasiva += $row['saldo']; @endphp

                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/60">
                                    <td class="border px-4 py-2 dark:border-gray-600">{{ $row['kode'] }}</td>
                                    <td class="border px-4 py-2 dark:border-gray-600">{{ $row['nama'] ?: '-' }}</td>
                                    <td class="border px-4 py-2 text-right font-semibold dark:border-gray-600">
                                        {{ $row['saldo'] != 0 ? number_format($row['saldo'],0,',','.') : '-' }}
                                    </td>
                                </tr>
                            @endforeach

                            <tr class="bg-yellow-100 dark:bg-yellow-900/40 font-bold">
                                <td colspan="2" class="border-2 px-4 py-3 text-right dark:border-gray-500">TOTAL PASIVA</td>
                                <td class="border-2 px-4 py-3 text-right dark:border-gray-500 text-yellow-700 dark:text-yellow-300">
                                    {{ number_format($totalPasiva,0,',','.') }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

            </div>
        </div>

    @endforeach

</x-filament-panels::page>