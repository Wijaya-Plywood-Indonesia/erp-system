<x-filament-panels::page>
    <div class="p-4 bg-white dark:bg-zinc-900 rounded-lg shadow flex flex-wrap items-end gap-4">
        <div class="w-56">
            <label class="block text-sm font-medium mb-1">Tanggal produksi</label>
            <x-filament::input.wrapper>
                <x-filament::input type="date" wire:model.live="tanggal" max="{{ now()->format('Y-m-d') }}" />
            </x-filament::input.wrapper>
        </div>
        <p class="text-sm text-zinc-500 dark:text-zinc-400 max-w-xl">
            Jurnal dibuat dari data produksi saat tombol ditekan. Jika hasil produksi baru lengkap besok,
            tekan <strong>Kirim</strong> lagi: jurnal yang masih draft di akuntansi akan diperbarui.
            Jurnal yang sudah diposting tidak akan diubah.
        </p>
    </div>

    <div class="space-y-6 mt-6">
        @foreach ($ringkasan as $jenis => $item)
            @php
                $adaAkunTidakValid = collect($item['jurnals'] ?? [])->contains('has_akun_tidak_valid', true);
            @endphp
            <div class="bg-white dark:bg-zinc-900 rounded-lg shadow border border-zinc-200 dark:border-zinc-700 overflow-hidden">
                <div class="flex items-center justify-between p-4 bg-zinc-800 text-white">
                    <h2 class="text-lg font-bold">{{ $item['label'] }}</h2>

                    <x-filament::button
                        wire:click="kirim('{{ $jenis }}')"
                        wire:loading.attr="disabled"
                        wire:target="kirim('{{ $jenis }}')"
                        wire:confirm="Kirim jurnal {{ $item['label'] }} tanggal {{ \Carbon\Carbon::parse($tanggal)->format('d/m/Y') }} ke akuntansi?"
                        icon="heroicon-o-paper-airplane"
                        :disabled="empty($item['jurnals']) || filled($item['error']) || $adaAkunTidakValid"
                    >
                        Kirim
                    </x-filament::button>
                </div>

                <div class="p-4">
                    @if (filled($item['error']))
                        <p class="text-sm text-red-500">{{ $item['error'] }}</p>
                    @elseif (empty($item['jurnals']))
                        <p class="text-sm text-zinc-500">Belum ada data produksi pada tanggal ini.</p>
                    @else
                        @if ($adaAkunTidakValid)
                            <div class="mb-3 p-3 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded text-sm text-red-700 dark:text-red-400 space-y-1">
                                <p class="font-semibold">⛔ Jurnal tidak dapat dikirim: ada akun yang tidak dikenal di COA.</p>
                                <p>Lengkapi referensi harga / mapping COA, lalu muat ulang halaman ini.</p>
                                @foreach ($item['jurnals'] as $j)
                                    @if ($j['has_akun_tidak_valid'])
                                        <p class="font-mono text-xs">{{ $j['no_dokumen'] }}: {{ implode(', ', $j['akun_tidak_valid']) }}</p>
                                    @endif
                                @endforeach
                            </div>
                        @endif

                        <div class="overflow-x-auto">
                            <table class="w-full text-sm border-collapse">
                                <thead>
                                    <tr class="text-left bg-zinc-100 dark:bg-zinc-800">
                                        <th class="p-2">No. Jurnal</th>
                                        <th class="p-2 text-right">Baris</th>
                                        <th class="p-2 text-right">Debit</th>
                                        <th class="p-2 text-right">Kredit</th>
                                        <th class="p-2">Status</th>
                                        <th class="p-2">Terakhir dikirim</th>
                                        <th class="p-2">Dikirim oleh</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($item['jurnals'] as $j)
                                        <tr class="border-t border-zinc-200 dark:border-zinc-700 {{ $j['has_akun_tidak_valid'] ? 'bg-red-50 dark:bg-red-900/10' : '' }}">
                                            <td class="p-2 font-mono">{{ $j['no_dokumen'] }}</td>
                                            <td class="p-2 text-right">{{ $j['jumlah_baris'] }}</td>
                                            <td class="p-2 text-right">{{ number_format($j['total_debit'], 0, ',', '.') }}</td>
                                            <td class="p-2 text-right">{{ number_format($j['total_kredit'], 0, ',', '.') }}</td>
                                            <td class="p-2 space-y-1">
                                                @if ($j['has_akun_tidak_valid'])
                                                    <div><x-filament::badge color="danger">Akun tidak dikenal</x-filament::badge></div>
                                                @endif
                                                @if (! $j['is_balance'])
                                                    <div><x-filament::badge color="danger">Tidak balance</x-filament::badge></div>
                                                @elseif ($j['status'] === 'terkirim')
                                                    <div><x-filament::badge color="success">Terkirim</x-filament::badge></div>
                                                @elseif ($j['status'] === 'berubah')
                                                    <div><x-filament::badge color="warning">Data berubah, kirim ulang</x-filament::badge></div>
                                                @else
                                                    <div><x-filament::badge color="gray">Belum dikirim</x-filament::badge></div>
                                                @endif
                                            </td>
                                            <td class="p-2">{{ $j['terakhir_dikirim'] ?? '-' }}</td>
                                            <td class="p-2">{{ $j['terakhir_oleh'] ?? '-' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif

                    @if (! empty($hasilKirim[$jenis]['peringatan'] ?? []))
                        <div class="mt-4 text-sm text-amber-600 space-y-1">
                            @foreach ($hasilKirim[$jenis]['peringatan'] as $w)
                                <p>⚠ {{ $w }}</p>
                            @endforeach
                        </div>
                    @endif

                    @if (! empty($hasilKirim[$jenis]['dokumen'] ?? []))
                        <div class="mt-4 text-sm space-y-1">
                            <p class="font-medium">Hasil kirim terakhir:</p>
                            @foreach ($hasilKirim[$jenis]['dokumen'] as $noDok => $d)
                                <p>
                                    <span class="font-mono">{{ $noDok }}</span>
                                    — {{ $d['status'] }}@if (filled($d['message'])): {{ $d['message'] }}@endif
                                </p>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
</x-filament-panels::page>
