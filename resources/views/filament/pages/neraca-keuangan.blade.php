<x-filament-panels::page>

@php
    $cardClass = "p-4 rounded-xl shadow-sm border bg-white dark:bg-gray-800 dark:border-gray-700";
    $inputClass = "w-full mt-1 rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100";
    $headerText = "font-bold text-lg dark:text-gray-100";
    $moneyPrimary = "text-blue-600 dark:text-blue-400";
    $moneyRed = "text-red-600 dark:text-red-400";
    $moneyGreen = "text-green-600 dark:text-green-400";
@endphp

<div class="space-y-6" x-data="neracaController()">

    {{-- ================= FILTER SECTION ================= --}}
<div class="p-6 rounded-xl shadow-sm border bg-white dark:bg-gray-800 dark:border-gray-700 space-y-6">

    {{-- TANGGAL --}}
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 items-end">

        <div>
            <label class="block text-sm font-semibold mb-1 dark:text-gray-200">
                Tanggal Awal
            </label>
            <input type="date"
                   wire:model.live="tanggal_awal"
                   class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100">
        </div>

        <div>
            <label class="block text-sm font-semibold mb-1 dark:text-gray-200">
                Tanggal Akhir
            </label>
            <input type="date"
                   wire:model.live="tanggal_akhir"
                   class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100">
        </div>

        <div class="md:col-span-2 flex justify-end">
            <button 
                @click="toggleAll()" 
                class="px-4 py-2 text-sm rounded-lg border bg-gray-100 dark:bg-gray-700 dark:text-gray-100 dark:border-gray-600 hover:bg-gray-200 dark:hover:bg-gray-600">
                Toggle Expand / Collapse All
            </button>
        </div>

    </div>

    {{-- FILTER INDUK AKUN --}}
    <div>
        <div class="text-sm font-semibold mb-3 dark:text-gray-200">
            Filter Induk Akun
        </div>

        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3">

            @foreach($listInduk as $kode => $nama)
                <label class="flex items-center gap-2 text-sm dark:text-gray-200">
                    <input type="checkbox"
                           wire:model="filter_induk_temp"
                           value="{{ $kode }}"
                           class="rounded border-gray-300 dark:border-gray-600">
                    {{ $kode }} — {{ $nama }}
                </label>
            @endforeach

        </div>

        {{-- ACTION BUTTON --}}
        <div class="flex gap-3 mt-4">
            <button
                wire:click="applyFilter"
                class="px-4 py-2 rounded-lg bg-blue-600 text-white text-sm hover:bg-blue-700">
                Terapkan Filter
            </button>

            <button
                wire:click="resetFilter"
                class="px-4 py-2 rounded-lg bg-gray-200 text-gray-800 text-sm hover:bg-gray-300 dark:bg-gray-700 dark:text-gray-200">
                Reset
            </button>
        </div>
    </div>

</div>

{{-- ================= LIST NERACA ================= --}}
<div class="space-y-4">

    @foreach ($neraca as $kodeInduk => $induk)

        {{-- 🔥 FIXED: Tidak hitung manual lagi --}}
        @php
            $totalInduk = $induk['saldo'] ?? 0;
        @endphp

        <div class="border rounded-xl shadow-sm bg-white dark:bg-gray-800 dark:border-gray-700"
             x-data="{ open: true }"
             x-init="$watch('allExpanded', val => open = val)">

            {{-- HEADER INDUK --}}
            <div class="flex justify-between items-center p-4 cursor-pointer select-none"
                 @click="open = !open">

                <div class="{{ $headerText }}">
                    {{ $kodeInduk }} — {{ $induk['nama'] }}
                </div>

                <div class="font-semibold {{ $moneyPrimary }}">
                    Rp {{ number_format($totalInduk, 2) }}
                </div>
            </div>

            {{-- LEVEL ANAK --}}
            <div x-show="open" x-transition class="border-t dark:border-gray-700">

                @foreach ($induk['anak'] ?? [] as $kodeAnak => $anak)

                    {{-- 🔥 FIXED --}}
                    @php
                        $totalAnak = $anak['saldo'] ?? 0;
                    @endphp

                    <div class="border-b dark:border-gray-700"
                         x-data="{ openChild: false }"
                         x-init="$watch('allExpanded', val => openChild = val)">

                        {{-- HEADER ANAK --}}
                        <div class="flex justify-between items-center px-6 py-3 cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700"
                             @click="openChild = !openChild">

                            <div class="font-medium dark:text-gray-200">
                                {{ $kodeAnak }} — {{ $anak['nama'] }}
                            </div>

                            <div class="text-gray-800 dark:text-gray-300 font-medium">
                                Rp {{ number_format($totalAnak, 2) }}
                            </div>
                        </div>

                        {{-- SUB TABLE --}}
                        <div x-show="openChild" x-transition class="bg-gray-50 dark:bg-gray-900">

                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="text-left bg-gray-100 dark:bg-gray-800 dark:text-gray-200">
                                        <th class="px-8 py-2">Kode</th>
                                        <th class="px-4 py-2">Nama Akun</th>
                                        <th class="px-4 py-2 text-right">Debit</th>
                                        <th class="px-4 py-2 text-right">Kredit</th>
                                        <th class="px-4 py-2 text-right">Saldo</th>
                                    </tr>
                                </thead>

                                <tbody class="dark:text-gray-200">
                                    @foreach ($anak['sub'] as $kodeSub => $sub)

                                        {{-- 🔥 FIXED --}}
                                        @php
                                            $saldoSub = $sub['saldo'] ?? 0;
                                        @endphp

                                        <tr class="border-b dark:border-gray-700">
                                            <td class="px-8 py-2">{{ $kodeSub }}</td>
                                            <td class="px-4 py-2">{{ $sub['nama'] }}</td>
                                            <td class="px-4 py-2 text-right text-green-700 dark:text-green-400">
                                                {{ number_format($sub['debit'] ?? 0, 2) }}
                                            </td>
                                            <td class="px-4 py-2 text-right text-red-700 dark:text-red-400">
                                                {{ number_format($sub['kredit'] ?? 0, 2) }}
                                            </td>
                                            <td class="px-4 py-2 text-right font-semibold dark:text-gray-100">
                                                Rp {{ number_format($saldoSub, 2) }}
                                            </td>
                                        </tr>

                                    @endforeach
                                </tbody>
                            </table>

                        </div>
                    </div>
                @endforeach

            </div>
        </div>
    @endforeach

</div>


{{-- ================= TOTAL SECTION ================= --}}
<div class="grid grid-cols-1 md:grid-cols-2 gap-6">

       <div class="{{ $cardClass }}">
        <div class="{{ $headerText }}">Total Neraca (1000–3000)</div>
        <div class="text-2xl font-semibold {{ $moneyGreen }} mt-2">
            Rp {{ number_format($total_aset - $total_kewajiban - $total_modal, 2) }}
        </div>
    </div>

 <div class="{{ $cardClass }}">
        <div class="{{ $headerText }}">Total Laba Rugi (4000–6000)</div>
        <div class="text-2xl font-semibold {{ $moneyRed }} mt-2">
            Rp {{ number_format($total_pendapatan - $total_beban - $total_hpp, 2) }}
        </div>
    </div>

</div>

<script>
function neracaController() {
    return {
        allExpanded: true,
        toggleAll() {
            this.allExpanded = !this.allExpanded;
        }
    }
}
</script>

</x-filament-panels::page>
