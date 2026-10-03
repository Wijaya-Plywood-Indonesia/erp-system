<x-filament-panels::page>
    <style>
        @keyframes loading-bar {
            0%   { transform: translateX(-100%); }
            50%  { transform: translateX(150%); }
            100% { transform: translateX(-100%); }
        }
    </style>

    {{-- Form Filter Tanggal --}}
    <form wire:submit.prevent>
        {{ $this->form }}
    </form>

    {{-- Loading bar --}}
    <div wire:loading wire:target="tanggal"
        class="h-0.5 mt-2 overflow-hidden rounded-full bg-primary-100 dark:bg-primary-900/30">
        <div class="h-full w-1/3 rounded-full bg-primary-600 animate-[loading-bar_1s_ease-in-out_infinite]"></div>
    </div>

    {{-- ==================== SECTION PER SUMBER ==================== --}}
    <div wire:loading.class="opacity-40" wire:target="tanggal" class="transition-opacity duration-200 space-y-10 mt-6">

        @forelse($dashboardData as $data)
            <div>
                {{-- Judul Section --}}
                <h2 class="text-xl font-bold mb-4 text-gray-900 dark:text-white">{{ $data['label'] }}</h2>

                {{-- ---- Cards Ringkasan ---- --}}
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">

                    <x-filament::card>
                        <div class="text-sm text-gray-500 dark:text-gray-400">Total Produksi</div>
                        <div class="text-2xl font-bold text-gray-900 dark:text-white">
                            {{ number_format($data['produksi']['total']) }}
                            <span class="text-sm font-normal text-gray-500 dark:text-gray-400">
                                {{ $data['produksi']['satuan'] }}
                            </span>
                        </div>
                    </x-filament::card>

                    <x-filament::card>
                        <div class="text-sm text-gray-500 dark:text-gray-400">Total Diserah</div>
                        <div class="text-2xl font-bold text-gray-900 dark:text-white">
                            {{ number_format($data['serah_terima']['total']) }}
                            <span class="text-sm font-normal text-gray-500 dark:text-gray-400">
                                {{ $data['serah_terima']['satuan'] }}
                            </span>
                        </div>
                    </x-filament::card>

                    <x-filament::card>
                        <div class="text-sm text-gray-500 dark:text-gray-400">Total Pegawai Hadir</div>
                        <div class="text-2xl font-bold text-gray-900 dark:text-white">
                            {{ number_format($data['pegawai']['total']) }}
                            <span class="text-sm font-normal text-gray-500 dark:text-gray-400">Orang</span>
                        </div>
                    </x-filament::card>
                </div>

                {{-- ---- Tabel Absensi + Checklog + Potongan ---- --}}
                @php
                    $rekapPerSumber = $this->getRekapPerSumber();
                    $rows = $rekapPerSumber->get($data['label'], collect());
                @endphp

                <x-filament::card>
                    <h3 class="font-semibold mb-4 text-gray-900 dark:text-white">Daftar Pegawai &amp; Absensi</h3>

                    @if($rows->isEmpty())
                        <div class="text-center py-8 text-gray-400 dark:text-gray-500 flex flex-col items-center gap-2">
                            <x-heroicon-o-inbox class="h-8 w-8 text-gray-300 dark:text-gray-600"/>
                            <span>Belum ada data absensi untuk bagian ini.</span>
                        </div>
                    @else

                        {{-- === MOBILE CARD VIEW (< sm) === --}}
                        <div class="sm:hidden space-y-3">
                            @foreach($rows as $row)
                                @php
                                    $rowKey = (string) ($row['id_pegawai'] ?? $row['nama_pegawai']);
                                    $jamMasukProduksi   = $row['jam_masuk'] ?? null;
                                    $masukFingerDipakai = $row['jam_masuk_finger'] ?? null;
                                    $telatMenit = null;
                                    if (!empty($jamMasukProduksi) && $jamMasukProduksi !== '-'
                                        && !empty($masukFingerDipakai) && $masukFingerDipakai !== '-') {
                                        try {
                                            $tP = \Illuminate\Support\Carbon::parse($jamMasukProduksi);
                                            $tF = \Illuminate\Support\Carbon::parse($masukFingerDipakai);
                                            if ($tF->gt($tP)) $telatMenit = (int) $tP->diffInMinutes($tF);
                                        } catch (\Throwable $e) {}
                                    }
                                    $shiftColors = [
                                        'pagi'  => 'border-sky-200 bg-sky-50 text-sky-700 dark:border-sky-500/20 dark:bg-sky-500/10 dark:text-sky-400',
                                        'malam' => 'border-indigo-200 bg-indigo-50 text-indigo-700 dark:border-indigo-500/20 dark:bg-indigo-500/10 dark:text-indigo-400',
                                    ];
                                    $shiftClass = $shiftColors[strtolower($row['shift'] ?? '')]
                                        ?? 'border-gray-200 bg-gray-50 text-gray-700 dark:border-gray-500/20 dark:bg-gray-500/10 dark:text-gray-400';
                                @endphp

                                <div wire:key="card-{{ $data['label'] }}-{{ $rowKey }}"
                                    class="rounded-xl border shadow-sm overflow-hidden
                                        {{ $telatMenit
                                            ? 'border-amber-300 bg-amber-50/60 dark:border-amber-700 dark:bg-amber-900/10'
                                            : 'border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800/50' }}">

                                    {{-- Header card --}}
                                    <div class="flex items-start justify-between gap-2 px-4 pt-3 pb-2">
                                        <div class="min-w-0">
                                            <p class="font-semibold text-gray-900 dark:text-gray-100 truncate">
                                                {{ $row['nama_pegawai'] }}
                                            </p>
                                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                                {{ $row['kode_pegawai'] ?? '-' }}
                                            </p>
                                        </div>
                                        @if(!empty($row['shift']))
                                            <span class="inline-flex items-center justify-center rounded-full border px-2 py-0.5 text-xs font-medium capitalize {{ $shiftClass }} shrink-0">
                                                {{ $row['shift'] }}
                                            </span>
                                        @endif
                                    </div>

                                    {{-- Grid info --}}
                                    <div class="px-4 pb-3 grid grid-cols-2 gap-x-4 gap-y-1.5 text-sm border-t border-gray-100 dark:border-gray-700 pt-2">
                                        <div>
                                            <span class="text-xs text-gray-400 block">Jam Masuk</span>
                                            <span class="text-gray-700 dark:text-gray-300 font-mono">{{ $row['jam_masuk'] ?? '-' }}</span>
                                        </div>
                                        <div>
                                            <span class="text-xs text-gray-400 block">Jam Pulang</span>
                                            <span class="text-gray-700 dark:text-gray-300 font-mono">{{ $row['jam_pulang'] ?? '-' }}</span>
                                        </div>
                                        <div>
                                            <span class="text-xs text-gray-400 block">Finger Masuk</span>
                                            <span class="{{ $telatMenit ? 'text-amber-700 dark:text-amber-400 font-medium' : 'text-gray-600 dark:text-gray-400' }} font-mono">
                                                {{ $masukFingerDipakai ?? '-' }}
                                            </span>
                                            @if($telatMenit)
                                                <span class="ml-1 inline-flex items-center rounded-full bg-amber-100 px-1.5 py-0.5 text-[10px] font-semibold text-amber-700 dark:bg-amber-500/10 dark:text-amber-400">
                                                    +{{ $telatMenit }}m
                                                </span>
                                            @endif
                                        </div>
                                        <div>
                                            <span class="text-xs text-gray-400 block">Finger Pulang</span>
                                            <span class="text-gray-600 dark:text-gray-400 font-mono">{{ $row['jam_pulang_finger'] ?? '-' }}</span>
                                        </div>

                                        {{-- Izin & Potongan --}}
                                        @if(!empty($row['izin']) || (!empty($row['potongan']) && $row['potongan'] > 0) || !empty($row['keterangan']))
                                            <div class="col-span-2 flex flex-wrap items-center gap-2 pt-1 border-t border-gray-100 dark:border-gray-700 mt-1">
                                                @if(!empty($row['izin']))
                                                    <span class="inline-flex items-center rounded-full border border-amber-200 bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-700 dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-400">
                                                        {{ $row['izin'] }}
                                                    </span>
                                                @endif
                                                @if(!empty($row['potongan']) && $row['potongan'] > 0)
                                                    <span class="text-xs font-medium text-red-600 dark:text-red-400">
                                                        Rp{{ number_format($row['potongan'], 0, ',', '.') }}
                                                    </span>
                                                @endif
                                                @if(!empty($row['keterangan']))
                                                    <span class="text-xs text-gray-500 dark:text-gray-400">{{ $row['keterangan'] }}</span>
                                                @endif
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        {{-- === DESKTOP TABLE VIEW (>= sm) === --}}
                        <div class="hidden sm:block overflow-x-auto rounded-xl border border-gray-200 shadow-sm dark:border-gray-700">
                            <table class="w-full text-sm text-left border-collapse">
                                <thead class="bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-600 dark:bg-gray-800 dark:text-gray-300">
                                    <tr>
                                        <th class="px-4 py-3 border-b border-gray-200 dark:border-gray-700">Kode</th>
                                        <th class="px-4 py-3 border-b border-gray-200 dark:border-gray-700">Nama Pegawai</th>
                                        <th class="px-4 py-3 border-b border-gray-200 dark:border-gray-700">Shift</th>
                                        <th class="px-4 py-3 border-b border-gray-200 dark:border-gray-700">Jam Masuk</th>
                                        <th class="px-4 py-3 border-b border-gray-200 dark:border-gray-700">Jam Pulang</th>
                                        <th class="px-4 py-3 border-b border-gray-200 dark:border-gray-700">Finger Masuk</th>
                                        <th class="px-4 py-3 border-b border-gray-200 dark:border-gray-700">Finger Pulang</th>
                                        <th class="px-4 py-3 border-b border-gray-200 dark:border-gray-700">Izin</th>
                                        <th class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 text-right">Potongan</th>
                                        <th class="px-4 py-3 border-b border-gray-200 dark:border-gray-700">Keterangan</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                    @foreach($rows as $row)
                                        @php
                                            $rowKey = (string) ($row['id_pegawai'] ?? $row['nama_pegawai']);
                                            $masukFingerDipakai  = $row['jam_masuk_finger'] ?? null;
                                            $pulangFingerDipakai = $row['jam_pulang_finger'] ?? null;
                                            $jamMasukProduksi    = $row['jam_masuk'] ?? null;
                                            $telatMenit = null;
                                            if (!empty($jamMasukProduksi) && $jamMasukProduksi !== '-'
                                                && !empty($masukFingerDipakai) && $masukFingerDipakai !== '-') {
                                                try {
                                                    $tProduksi = \Illuminate\Support\Carbon::parse($jamMasukProduksi);
                                                    $tFinger   = \Illuminate\Support\Carbon::parse($masukFingerDipakai);
                                                    if ($tFinger->gt($tProduksi)) {
                                                        $telatMenit = (int) $tProduksi->diffInMinutes($tFinger);
                                                    }
                                                } catch (\Throwable $e) {}
                                            }

                                            $shiftColors = [
                                                'pagi'  => 'border-sky-200 bg-sky-50 text-sky-700 dark:border-sky-500/20 dark:bg-sky-500/10 dark:text-sky-400',
                                                'malam' => 'border-indigo-200 bg-indigo-50 text-indigo-700 dark:border-indigo-500/20 dark:bg-indigo-500/10 dark:text-indigo-400',
                                            ];
                                            $shiftKey   = strtolower($row['shift'] ?? '');
                                            $shiftClass = $shiftColors[$shiftKey]
                                                ?? 'border-gray-200 bg-gray-50 text-gray-700 dark:border-gray-500/20 dark:bg-gray-500/10 dark:text-gray-400';
                                        @endphp

                                        <tr wire:key="row-{{ $data['label'] }}-{{ $rowKey }}"
                                            class="transition-colors
                                                {{ $telatMenit
                                                    ? 'bg-amber-50 hover:bg-amber-100 dark:bg-amber-500/10 dark:hover:bg-amber-500/20'
                                                    : 'hover:bg-gray-50 dark:hover:bg-gray-800/50' }}">

                                            <td class="px-4 py-2.5 text-gray-600 dark:text-gray-400 font-mono text-xs">
                                                {{ $row['kode_pegawai'] ?? '-' }}
                                            </td>

                                            <td class="px-4 py-2.5 font-medium text-gray-900 dark:text-gray-100">
                                                {{ $row['nama_pegawai'] }}
                                            </td>

                                            <td class="px-4 py-2.5">
                                                @if(!empty($row['shift']))
                                                    <span class="inline-flex min-w-[56px] items-center justify-center rounded-full border px-2.5 py-1 text-xs font-medium capitalize {{ $shiftClass }}">
                                                        {{ $row['shift'] }}
                                                    </span>
                                                @else
                                                    <span class="text-gray-400">-</span>
                                                @endif
                                            </td>

                                            <td class="px-4 py-2.5 text-gray-700 dark:text-gray-300 font-mono">
                                                {{ $row['jam_masuk'] ?? '-' }}
                                            </td>

                                            <td class="px-4 py-2.5 text-gray-700 dark:text-gray-300 font-mono">
                                                {{ $row['jam_pulang'] ?? '-' }}
                                            </td>

                                            {{-- Finger Masuk + badge telat --}}
                                            <td class="px-4 py-2.5 {{ $telatMenit ? 'text-amber-700 dark:text-amber-400' : 'text-gray-500 dark:text-gray-400' }} font-mono">
                                                <span class="{{ $telatMenit ? 'font-medium' : '' }}">
                                                    {{ $masukFingerDipakai ?? '-' }}
                                                </span>
                                                @if($telatMenit)
                                                    <span class="ml-1 inline-flex items-center rounded-full bg-amber-100 px-1.5 py-0.5 text-[10px] font-semibold text-amber-700 dark:bg-amber-500/10 dark:text-amber-400"
                                                        title="Telat {{ $telatMenit }} menit dari jadwal">
                                                        +{{ $telatMenit }}m
                                                    </span>
                                                @endif
                                            </td>

                                            <td class="px-4 py-2.5 text-gray-500 dark:text-gray-400 font-mono">
                                                {{ $pulangFingerDipakai ?? '-' }}
                                            </td>

                                            {{-- Izin --}}
                                            <td class="px-4 py-2.5">
                                                @if(!empty($row['izin']))
                                                    <span class="inline-flex items-center rounded-full border border-amber-200 bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-700 dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-400">
                                                        {{ $row['izin'] }}
                                                    </span>
                                                @else
                                                    <span class="text-gray-400">-</span>
                                                @endif
                                            </td>

                                            {{-- Potongan --}}
                                            <td class="px-4 py-2.5 text-right">
                                                @if(!empty($row['potongan']) && $row['potongan'] > 0)
                                                    <span class="font-medium text-red-600 dark:text-red-400">
                                                        Rp{{ number_format($row['potongan'], 0, ',', '.') }}
                                                    </span>
                                                @else
                                                    <span class="text-gray-400">-</span>
                                                @endif
                                            </td>

                                            {{-- Keterangan --}}
                                            <td class="px-4 py-2.5 text-gray-500 dark:text-gray-400 text-xs">
                                                {{ $row['keterangan'] ?? '-' }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                    @endif
                </x-filament::card>
            </div>
        @empty
            <x-filament::card>
                <div class="text-center text-gray-500 dark:text-gray-400 py-6">
                    Tidak ada data produksi yang dapat ditampilkan.
                    Pastikan Anda memiliki akses ke departemen terkait.
                </div>
            </x-filament::card>
        @endforelse

    </div>
</x-filament-panels::page>
