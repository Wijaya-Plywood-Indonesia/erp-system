{{-- resources/views/filament/components/detail-kayu-modal.blade.php --}}

<div class="space-y-4">

    {{-- Stats --}}
    <div class="grid grid-cols-3 gap-4">
        <div class="rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 px-4 py-3">
            <p class="text-sm text-gray-500 dark:text-gray-400">Lahan</p>
            <p class="text-xl font-semibold text-gray-950 dark:text-white mt-1">{{ $record->lahan?->kode_lahan ?? '-' }}
            </p>
        </div>
        <div class="rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 px-4 py-3">
            <p class="text-sm text-gray-500 dark:text-gray-400">Total Batang</p>
            <p class="text-xl font-semibold text-gray-950 dark:text-white mt-1">{{ number_format($totalBatang) }} <span
                    class="text-sm font-normal text-gray-500">Btg</span></p>
        </div>
        <div class="rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 px-4 py-3">
            <p class="text-sm text-gray-500 dark:text-gray-400">Total Volume</p>
            <p class="text-xl font-semibold text-gray-950 dark:text-white mt-1">{{ number_format($totalKubikasi, 4) }}
                <span class="text-sm font-normal text-gray-500">m³</span></p>
        </div>
    </div>

    {{-- Tabel --}}
    @if ($details->isEmpty())
        <p class="py-10 text-center text-sm text-gray-400">Tidak ada data kayu aktif.</p>
    @else
        <div class="overflow-hidden rounded-xl ring-1 ring-gray-950/5 dark:ring-white/10">
            <table style="width:100%; border-collapse:collapse;">

                <thead>
                    <tr class="bg-gray-50 dark:bg-white/5 border-b border-gray-200 dark:border-white/10">
                        <th style="width:12%; padding:12px 16px; text-align:left;"
                            class="text-sm font-semibold text-gray-950 dark:text-white">Seri</th>
                        <th style="width:16%; padding:12px 16px; text-align:right;"
                            class="text-sm font-semibold text-gray-950 dark:text-white">Total Batang</th>
                        <th style="width:52%; padding:12px 16px; text-align:left;"
                            class="text-sm font-semibold text-gray-950 dark:text-white">Status Lunas</th>
                        <th style="width:20%; padding:12px 16px; text-align:right;"
                            class="text-sm font-semibold text-gray-950 dark:text-white">Total Volume (m³)</th>
                    </tr>
                </thead>

                <tbody>
                    @foreach ($details as $row)
                        @php $isLunas = $row['is_lunas'] ?? false; @endphp
                        <tr
                            class="border-b border-gray-200 dark:border-white/5 {{ $isLunas ? 'bg-white dark:bg-transparent' : 'bg-red-50 dark:bg-red-950/20' }}">

                            <td style="padding:12px 16px;" class="text-sm font-semibold text-gray-950 dark:text-white">
                                {{ $row['Seri'] ?? '-' }}
                            </td>

                            <td style="padding:12px 16px; text-align:right;"
                                class="text-sm text-gray-950 dark:text-white">
                                {{ number_format($row['Batang']) }} Btg
                            </td>

                            <td style="padding:12px 16px;">
                                @if ($isLunas)
                                    <x-filament::badge color="success" icon="heroicon-m-check-circle">
                                        {{ $row['Status Pelunasan'] ?? 'Lunas' }}
                                    </x-filament::badge>
                                @else
                                    <x-filament::badge color="danger" icon="heroicon-m-x-circle">
                                        {{ $row['Status Pelunasan'] ?? 'Belum Lunas' }}
                                    </x-filament::badge>
                                @endif
                            </td>

                            <td style="padding:12px 16px; text-align:right;"
                                class="text-sm font-mono text-gray-950 dark:text-white">
                                {{ number_format($row['Kubikasi'], 4) }}
                            </td>

                        </tr>
                    @endforeach
                </tbody>

                <tfoot>
                    <tr class="bg-gray-50 dark:bg-white/5 border-t border-gray-200 dark:border-white/10">
                        <td style="padding:12px 16px;" class="text-sm font-semibold text-gray-950 dark:text-white">Total
                        </td>
                        <td style="padding:12px 16px; text-align:right;"
                            class="text-sm font-semibold text-gray-950 dark:text-white">
                            {{ number_format($totalBatang) }} Btg</td>
                        <td></td>
                        <td style="padding:12px 16px; text-align:right;"
                            class="text-sm font-semibold font-mono text-gray-950 dark:text-white">
                            {{ number_format($totalKubikasi, 4) }}</td>
                    </tr>
                </tfoot>

            </table>
        </div>
    @endif

</div>
