<x-filament-widgets::widget>
    <x-filament::section heading="Rekap Produksi Graji Harian">

        {{ $this->form }}

        @if (! ($summary['ada_data'] ?? false))
        <p class="mt-4 text-sm text-gray-500">Tidak ada produksi pada tanggal ini.</p>
        @else
        {{-- Ringkasan Kartu --}}
        <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3">
            <div class="rounded-lg bg-gray-50 p-3 text-center dark:bg-white/5">
                <div class="text-2xl font-extrabold text-warning-500 sm:text-3xl">{{ number_format($summary['totalAll']) }}</div>
                <div class="mt-1 text-xs text-gray-500">Total Produksi (Isi)</div>
            </div>
            <div class="rounded-lg bg-gray-50 p-3 text-center dark:bg-white/5">
                <div class="text-2xl font-extrabold text-success-500 sm:text-3xl">{{ $summary['totalPegawai'] }}</div>
                <div class="mt-1 text-xs text-gray-500">Total Pekerja</div>
            </div>
            <div class="col-span-2 rounded-lg bg-gray-50 p-3 text-center dark:bg-white/5 sm:col-span-1">
                <div class="text-2xl font-extrabold text-primary-500 sm:text-3xl">{{ $summary['globalProgress'] }}%</div>
                <div class="mt-1 text-xs text-gray-500">Progress dari Target</div>
            </div>
        </div>

        <h3 class="mb-2 mt-6 text-base font-semibold">Rincian Hasil & Ukuran</h3>

        {{-- Desktop: Tabel --}}
        <div class="hidden overflow-x-auto rounded-lg border border-gray-200 dark:border-white/10 md:block">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left">
                        <th class="px-3 py-2">Ukuran</th>
                        <th class="px-3 py-2">KW / Kategori</th>
                        <th class="px-3 py-2 text-right">Total Hasil</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($summary['rows'] as $r)
                    <tr class="border-t border-gray-200 dark:border-white/10">
                        <td class="px-3 py-2">{{ $r->ukuran }}</td>
                        <td class="px-3 py-2">{{ $r->kw }}</td>
                        <td class="px-3 py-2 text-right font-semibold">{{ number_format($r->total) }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- HP: Kartu Responsif --}}
        <div class="space-y-2 md:hidden">
            @foreach ($summary['rows'] as $r)
            <div class="rounded-lg border border-gray-200 p-3 dark:border-white/10">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <div class="text-sm font-semibold">{{ $r->ukuran }}</div>
                        <div class="mt-1"><x-filament::badge size="sm">{{ $r->kw }}</x-filament::badge></div>
                    </div>
                    <div class="text-right">
                        <div class="text-lg font-bold">{{ number_format($r->total) }}</div>
                    </div>
                </div>
            </div>
            @endforeach
        </div>

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