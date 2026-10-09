<x-filament-widgets::widget>
    <x-filament::section heading="Rekap Produksi Pot Jelek Harian">

        {{ $this->form }}

        @if (! ($summary['ada_data'] ?? false))
        <p class="mt-4 text-sm text-gray-500">Tidak ada produksi Pot Jelek pada tanggal ini.</p>
        @else

        {{-- Ringkasan: 2 Kartu --}}
        <div class="mt-4 grid grid-cols-2 gap-3">
            <div class="rounded-lg bg-gray-50 p-3 text-center dark:bg-white/5">
                <div class="text-2xl font-extrabold text-warning-500 sm:text-3xl">{{ number_format($summary['totalAll']) }}</div>
                <div class="mt-1 text-xs text-gray-500">Total Hasil (Tinggi)</div>
            </div>
            <div class="rounded-lg bg-gray-50 p-3 text-center dark:bg-white/5">
                <div class="text-2xl font-extrabold text-success-500 sm:text-3xl">{{ $summary['totalPegawai'] }}</div>
                <div class="mt-1 text-xs text-gray-500">Total Pekerja</div>
            </div>
        </div>

        <h3 class="mb-2 mt-6 text-base font-semibold">Rincian Hasil (Jenis Kayu, Ukuran & KW)</h3>

        {{-- Desktop: Tabel --}}
        <div class="hidden overflow-x-auto rounded-lg border border-gray-200 dark:border-white/10 md:block">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left bg-gray-50/50 dark:bg-white/5">
                        <th class="px-3 py-2">Jenis Kayu</th>
                        <th class="px-3 py-2">Ukuran (P x L x T)</th>
                        <th class="px-3 py-2">KW</th>
                        <th class="px-3 py-2 text-right">Hasil (Tinggi)</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($summary['globalJenisKayuUkuran'] as $r)
                    <tr class="border-t border-gray-200 dark:border-white/10">
                        <td class="px-3 py-2 font-medium">{{ $r->jenis_kayu }}</td>
                        <td class="px-3 py-2">{{ $r->ukuran }}</td>
                        <td class="px-3 py-2"><x-filament::badge size="sm">{{ $r->kw }}</x-filament::badge></td>
                        <td class="px-3 py-2 text-right font-semibold">{{ number_format($r->total) }}</td>
                    </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="border-t border-gray-200 font-bold dark:border-white/10 bg-gray-50/50 dark:bg-white/5">
                        <td colspan="3" class="px-3 py-2 text-right">Total Keseluruhan</td>
                        <td class="px-3 py-2 text-right">{{ number_format($summary['totalAll']) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>

        {{-- Mobile (HP): Kartu Responsif --}}
        <div class="space-y-2 md:hidden">
            @foreach ($summary['globalJenisKayuUkuran'] as $r)
            <div class="rounded-lg border border-gray-200 p-3 dark:border-white/10">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <div class="text-sm font-semibold">{{ $r->jenis_kayu }}</div>
                        <div class="text-xs text-gray-500">{{ $r->ukuran }}</div>
                        <div class="mt-1"><x-filament::badge size="sm">{{ $r->kw }}</x-filament::badge></div>
                    </div>
                    <div class="text-lg font-bold">{{ number_format($r->total) }}</div>
                </div>
            </div>
            @endforeach
            <div class="flex items-center justify-between rounded-lg bg-gray-50 p-3 font-bold dark:bg-white/5">
                <span class="text-sm">Total Keseluruhan</span>
                <span class="text-lg">{{ number_format($summary['totalAll']) }}</span>
            </div>
        </div>

        {{-- Daftar Pekerja --}}
        <div class="mt-6">
            <h3 class="mb-2 text-base font-semibold">Pekerja</h3>
            <div class="flex flex-wrap gap-2">
                @forelse ($summary['namaPegawai'] as $nama)
                <x-filament::badge>{{ $nama }}</x-filament::badge>
                @empty
                <span class="text-sm text-gray-500">Belum ada pekerja tercatat.</span>
                @endforelse
            </div>
        </div>
        @endif

    </x-filament::section>
</x-filament-widgets::widget>