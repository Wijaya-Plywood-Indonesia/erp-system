<x-filament::widget>
    @php
    $totalPekerja = 0;
    if ($record) {
        $totalPekerja = $record->pegawaiTerimaGudangSatu()
            ->whereNotNull('id_pegawai')
            ->distinct('id_pegawai')
            ->count('id_pegawai');
    }
    @endphp

    <div class="space-y-4">
        <div class="p-4 bg-white border border-gray-200 rounded-xl shadow-sm dark:bg-gray-900 dark:border-gray-800 border-l-4 border-l-gray-300 dark:border-l-gray-600">
            <span class="text-xs font-semibold tracking-wider uppercase text-gray-500 dark:text-gray-400">Total Pekerja</span>
            <div class="text-2xl font-black text-gray-800 dark:text-gray-100">{{ number_format($totalPekerja) }} <span class="text-sm font-medium opacity-60">Orang</span></div>
        </div>
    </div>
</x-filament::widget>
