<x-filament-panels::page>
    <div class="p-4 bg-white dark:bg-zinc-900 rounded-lg shadow flex flex-wrap items-end gap-4">
        <div class="w-56">
            <label class="block text-sm font-medium mb-1">Tanggal produksi</label>
            <x-filament::input.wrapper>
                <x-filament::input type="date" wire:model.live="tanggal" wire:loading.attr="disabled"
                    wire:target="tanggal" max="{{ now()->format('Y-m-d') }}" />
            </x-filament::input.wrapper>
        </div>
        <p class="text-sm text-zinc-500 dark:text-zinc-400 max-w-xl">
            Jurnal dibuat dari data produksi saat tombol ditekan. Jika hasil produksi baru lengkap besok,
            tekan <strong>Kirim</strong> lagi: jurnal yang masih draft di akuntansi akan diperbarui.
            Jurnal yang sudah diposting tidak akan diubah.
        </p>
    </div>

    <div class="relative mt-6">
        {{-- Animasi loading saat ganti tanggal atau saat mengirim --}}
        <div wire:loading.flex wire:target="tanggal, kirim"
            class="absolute inset-0 z-10 items-start justify-center rounded-lg bg-white/70 dark:bg-zinc-900/70 pt-24">
            <div
                class="flex items-center gap-3 rounded-lg bg-white dark:bg-zinc-800 px-5 py-3 shadow border border-zinc-200 dark:border-zinc-700">
                <x-filament::loading-indicator class="h-6 w-6" />
                <span class="text-sm font-medium">
                    <span wire:loading wire:target="tanggal">Memuat jurnal...</span>
                    <span wire:loading wire:target="kirim">Mengirim ke akuntansi...</span>
                </span>
            </div>
        </div>

        <div class="space-y-6" wire:loading.class="opacity-40" wire:target="tanggal, kirim">
            @foreach ($ringkasan as $jenis => $item)
                @php
                    $adaAkunTidakValid = collect($item['jurnals'] ?? [])->contains('has_akun_tidak_valid', true);
                @endphp
                <div
                    class="bg-white dark:bg-zinc-900 rounded-lg shadow border border-zinc-200 dark:border-zinc-700 overflow-hidden">
                    <div class="flex items-center justify-between p-4 bg-zinc-800 text-white">
                        <h2 class="text-lg font-bold">{{ $item['label'] }}</h2>

                        <x-filament::button wire:click="kirim('{{ $jenis }}')" wire:loading.attr="disabled"
                            wire:target="kirim('{{ $jenis }}')"
                            wire:confirm="Kirim jurnal {{ $item['label'] }} tanggal {{ \Carbon\Carbon::parse($tanggal)->format('d/m/Y') }} ke akuntansi?"
                            icon="heroicon-o-paper-airplane" :disabled="empty($item['jurnals']) || filled($item['error']) || $adaAkunTidakValid">
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
                                <div
                                    class="mb-3 p-3 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded text-sm text-red-700 dark:text-red-400 space-y-1">
                                    <p class="font-semibold">⛔ Jurnal tidak dapat dikirim: ada akun yang tidak dikenal
                                        di COA.</p>
                                    <p>Lengkapi referensi harga / mapping COA, lalu muat ulang halaman ini.</p>
                                    @foreach ($item['jurnals'] as $j)
                                        @if ($j['has_akun_tidak_valid'])
                                            <p class="font-mono text-xs">{{ $j['no_dokumen'] }}:
                                                {{ implode(', ', $j['akun_tidak_valid']) }}</p>
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
                                            <th class="p-2"></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($item['jurnals'] as $j)
                                            <tr
                                                class="border-t border-zinc-200 dark:border-zinc-700 {{ $j['has_akun_tidak_valid'] ? 'bg-red-50 dark:bg-red-900/10' : '' }}">
                                                <td class="p-2 font-mono">{{ $j['no_dokumen'] }}</td>
                                                <td class="p-2 text-right">{{ $j['jumlah_baris'] }}</td>
                                                <td class="p-2 text-right">
                                                    {{ number_format($j['total_debit'], 0, ',', '.') }}</td>
                                                <td class="p-2 text-right">
                                                    {{ number_format($j['total_kredit'], 0, ',', '.') }}</td>
                                                <td class="p-2 space-y-1">
                                                    @if ($j['has_akun_tidak_valid'])
                                                        <div><x-filament::badge color="danger">Akun tidak
                                                                dikenal</x-filament::badge></div>
                                                    @endif
                                                    @if (!$j['is_balance'])
                                                        <div><x-filament::badge color="danger">Tidak
                                                                balance</x-filament::badge></div>
                                                    @elseif ($j['status'] === 'terkirim')
                                                        <div><x-filament::badge
                                                                color="success">Terkirim</x-filament::badge></div>
                                                    @elseif ($j['status'] === 'berubah')
                                                        <div><x-filament::badge color="warning">Data berubah, kirim
                                                                ulang</x-filament::badge></div>
                                                    @else
                                                        <div><x-filament::badge color="gray">Belum
                                                                dikirim</x-filament::badge></div>
                                                    @endif
                                                </td>
                                                <td class="p-2">{{ $j['terakhir_dikirim'] ?? '-' }}</td>
                                                <td class="p-2">{{ $j['terakhir_oleh'] ?? '-' }}</td>
                                                <td class="p-2 text-right">
                                                    <x-filament::button size="xs" color="gray"
                                                        icon="heroicon-o-eye"
                                                        wire:click="lihat('{{ $jenis }}', '{{ $j['no_dokumen'] }}')"
                                                        wire:loading.attr="disabled" wire:target="lihat">
                                                        Lihat
                                                    </x-filament::button>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif

                        @if (!empty($hasilKirim[$jenis]['dokumen'] ?? []))
                            <div class="mt-4 text-sm space-y-1">
                                <p class="font-medium">Hasil kirim terakhir:</p>
                                @foreach ($hasilKirim[$jenis]['dokumen'] as $noDok => $d)
                                    <p>
                                        <span class="font-mono">{{ $noDok }}</span>
                                        — {{ $d['status'] }}@if (filled($d['message']))
                                            : {{ $d['message'] }}
                                        @endif
                                    </p>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    {{-- Rincian jurnal (hanya melihat, tidak mengirim) --}}
    <x-filament::modal id="detail-jurnal" width="5xl">
        <x-slot name="heading">
            {{ $detail['label'] ?? 'Rincian jurnal' }}
        </x-slot>

        <x-slot name="description">
            <span class="font-mono">{{ $detail['no_dokumen'] ?? '' }}</span>
        </x-slot>

        @if ($detail)
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800 p-3">
                    <p class="text-xs text-zinc-500">Jumlah baris</p>
                    <p class="text-lg font-semibold">{{ $detail['jumlah_baris'] }}</p>
                </div>
                <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800 p-3">
                    <p class="text-xs text-zinc-500">Total debit</p>
                    <p class="text-lg font-semibold">{{ number_format($detail['total_debit'], 0, ',', '.') }}</p>
                </div>
                <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800 p-3">
                    <p class="text-xs text-zinc-500">Total kredit</p>
                    <p class="text-lg font-semibold">{{ number_format($detail['total_kredit'], 0, ',', '.') }}</p>
                </div>
            </div>

            @if (!empty($detail['has_akun_tidak_valid']))
                <div
                    class="rounded-lg border border-red-300 bg-red-50 dark:bg-red-950 p-3 text-sm text-red-700 dark:text-red-300">
                    Ada akun yang tidak dikenal di COA: {{ implode(', ', $detail['akun_tidak_valid'] ?? []) }}.
                    Jurnal ini tidak akan dikirim sampai referensi harga / mapping COA dilengkapi.
                </div>
            @endif

            @unless ($detail['is_balance'])
                <div
                    class="rounded-lg border border-red-300 bg-red-50 dark:bg-red-950 p-3 text-sm text-red-700 dark:text-red-300">
                    Debit dan kredit tidak sama (selisih
                    {{ number_format(abs($detail['total_debit'] - $detail['total_kredit']), 0, ',', '.') }}).
                    Jurnal ini tidak akan dikirim sampai datanya diperbaiki.
                </div>
            @endunless

            <div class="overflow-x-auto rounded-lg border border-zinc-200 dark:border-zinc-700">
                <table class="w-full text-sm border-collapse">
                    <thead>
                        <tr
                            class="bg-zinc-100 dark:bg-zinc-800 text-left text-xs uppercase tracking-wide text-zinc-500">
                            <th class="px-3 py-2">Akun</th>
                            <th class="px-3 py-2">Keterangan</th>
                            <th class="px-3 py-2 text-right">Banyak</th>
                            <th class="px-3 py-2 text-right">M3</th>
                            <th class="px-3 py-2 text-right">Harga</th>
                            <th class="px-3 py-2 text-right">Debit</th>
                            <th class="px-3 py-2 text-right">Kredit</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($detail['rows'] as $r)
                            <tr
                                class="border-t border-zinc-200 dark:border-zinc-700 even:bg-zinc-50/70 dark:even:bg-zinc-800/40">
                                <td class="px-3 py-2">
                                    <div class="font-medium">{{ $r['nama_akun'] }}</div>
                                    <div class="font-mono text-xs text-zinc-500">{{ $r['no_akun'] }}</div>
                                </td>
                                <td class="px-3 py-2 text-zinc-600 dark:text-zinc-300">
                                    {{ trim(($r['nama'] ?? '') . ' ' . ($r['keterangan'] ?? '')) }}</td>
                                <td class="px-3 py-2 text-right">
                                    {{ filled($r['banyak'] ?? null) ? number_format($r['banyak'], 0, ',', '.') : '' }}
                                </td>
                                <td class="px-3 py-2 text-right">
                                    {{ filled($r['m3'] ?? null) ? number_format($r['m3'], 4, ',', '.') : '' }}</td>
                                <td class="px-3 py-2 text-right">
                                    {{ filled($r['harga'] ?? null) ? number_format($r['harga'], 0, ',', '.') : '' }}
                                </td>
                                <td class="px-3 py-2 text-right font-medium">
                                    {{ $r['map'] === 'd' ? number_format($r['total'], 0, ',', '.') : '' }}</td>
                                <td class="px-3 py-2 text-right font-medium">
                                    {{ $r['map'] === 'k' ? number_format($r['total'], 0, ',', '.') : '' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr
                            class="border-t-2 border-zinc-300 dark:border-zinc-600 bg-zinc-100 dark:bg-zinc-800 font-bold">
                            <td class="px-3 py-2" colspan="5">Total</td>
                            <td class="px-3 py-2 text-right">{{ number_format($detail['total_debit'], 0, ',', '.') }}
                            </td>
                            <td class="px-3 py-2 text-right">{{ number_format($detail['total_kredit'], 0, ',', '.') }}
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        @endif

        <x-slot name="footer">
            <x-filament::button color="gray" x-on:click="$dispatch('close-modal', { id: 'detail-jurnal' })">
                Tutup
            </x-filament::button>
        </x-slot>
    </x-filament::modal>
</x-filament-panels::page>
