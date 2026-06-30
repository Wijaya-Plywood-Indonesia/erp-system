<div class="space-y-4">
    @foreach ($details as $item)
    <div
        class="p-4 border rounded-lg shadow-sm bg-white border-gray-200 dark:bg-gray-800 dark:border-gray-700"
    >
        <div class="flex items-center justify-between">
            <div class="flex-1">
                <div
                    class="font-semibold text-base text-gray-900 dark:text-gray-100"
                >
                    {{ $item->rencanaPegawai->pegawai->nama_pegawai ?? 'N/A' }}
                </div>
                <div class="text-sm text-gray-500 dark:text-gray-400">
                    Kode:
                    {{ $item->rencanaPegawai->pegawai->kode_pegawai ?? '-' }}
                </div>
            </div>
            @php $hasil = $item->hasilRepairs->sum('jumlah') ?? 0; @endphp
            <div class="flex items-ceter gap-2">
                <div
                    class="text-2xl font-bold text-blue-600 dark:text-blue-400"
                >
                    {{ $hasil }}
                </div>
                <div class="text-xs text-gray-500 dark:text-gray-400">
                    lembar
                </div>
            </div>
        </div>
    </div>
    @endforeach @if ($details->isEmpty())
    <div class="text-center py-8">
        <p class="text-gray-500 dark:text-gray-400 text-sm">
            Tidak ada pegawai pada meja ini.
        </p>
    </div>
    @endif

    <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
        <div class="flex justify-between items-center">
            <span
                class="text-sm font-semibold text-gray-700 dark:text-gray-300"
            >
                Total Produksi Meja {{ $meja }}:
            </span>
            <span class="text-2xl font-bold text-blue-600 dark:text-blue-400">
                {{ $details->sum(fn($d) => $d->hasilRepairs->sum('jumlah')) }}
                lembar
            </span>
        </div>
    </div>
</div>
