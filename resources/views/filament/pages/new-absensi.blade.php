<x-filament-panels::page>
    <form wire:submit.prevent>
        {{ $this->form }}
    </form>

    <div class="mt-4 flex flex-wrap items-center gap-3">
        <x-filament::button wire:click="uploadFinger" icon="heroicon-o-arrow-up-tray">
            Proses Upload Finger
        </x-filament::button>

        <x-filament::button wire:click="downloadFingerForSelectedDate" color="gray" icon="heroicon-o-arrow-down-tray">
            Download Finger Tanggal Ini
        </x-filament::button>
    </div>

    <div class="mt-6 overflow-x-auto rounded-xl border border-gray-200 shadow-sm dark:border-gray-700">
        <table class="w-full text-sm text-left border-collapse">
            <thead
                class="bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-600 dark:bg-gray-800 dark:text-gray-300">
                <tr>
                    <th class="px-4 py-3 border-b border-gray-200 dark:border-gray-700">Sumber</th>
                    <th class="px-4 py-3 border-b border-gray-200 dark:border-gray-700">Kode Pegawai</th>
                    <th class="px-4 py-3 border-b border-gray-200 dark:border-gray-700">Nama Pegawai</th>
                    <th class="px-4 py-3 border-b border-gray-200 dark:border-gray-700">Shift</th>
                    <th class="px-4 py-3 border-b border-gray-200 dark:border-gray-700">Jam Masuk (Input)</th>
                    <th class="px-4 py-3 border-b border-gray-200 dark:border-gray-700">Jam Pulang (Input)</th>
                    <th class="px-4 py-3 border-b border-gray-200 dark:border-gray-700">Jam Masuk (Finger)</th>
                    <th class="px-4 py-3 border-b border-gray-200 dark:border-gray-700">Jam Pulang (Finger)</th>
                    <th class="px-4 py-3 border-b border-gray-200 dark:border-gray-700">Izin</th>
                    <th class="px-4 py-3 border-b border-gray-200 dark:border-gray-700">Keterangan</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                @forelse ($this->getRekap() as $row)
                    <tr class="transition-colors hover:bg-gray-50 dark:hover:bg-gray-800/50">
                        <td class="px-4 py-2.5 whitespace-nowrap">
                            <div class="flex flex-wrap gap-1">
                                @foreach ((array) $row['sumber_label'] as $sumber)
                                    <span
                                        class="inline-flex min-w-[64px] items-center justify-center gap-1 rounded-full border border-primary-200 bg-primary-50 px-2.5 py-1 text-xs font-medium text-primary-700 dark:border-primary-500/20 dark:bg-primary-500/10 dark:text-primary-400">
                                        {{ $sumber }}
                                    </span>
                                @endforeach
                            </div>
                        </td>
                        <td class="px-4 py-2.5 text-gray-700 dark:text-gray-300">{{ $row['kode_pegawai'] ?? '-' }}</td>
                        <td class="px-4 py-2.5 font-medium text-gray-900 dark:text-gray-100">{{ $row['nama_pegawai'] }}
                        </td>
                        <td class="px-4 py-2.5">
                            @php
                                $shiftColors = [
                                    'pagi' =>
                                        'border-sky-200 bg-sky-50 text-sky-700 dark:border-sky-500/20 dark:bg-sky-500/10 dark:text-sky-400',
                                    'siang' =>
                                        'border-orange-200 bg-orange-50 text-orange-700 dark:border-orange-500/20 dark:bg-orange-500/10 dark:text-orange-400',
                                    'malam' =>
                                        'border-indigo-200 bg-indigo-50 text-indigo-700 dark:border-indigo-500/20 dark:bg-indigo-500/10 dark:text-indigo-400',
                                ];
                                $shiftKey = strtolower($row['shift'] ?? '');
                                $shiftClass =
                                    $shiftColors[$shiftKey] ??
                                    'border-gray-200 bg-gray-50 text-gray-700 dark:border-gray-500/20 dark:bg-gray-500/10 dark:text-gray-400';
                            @endphp
                            <span
                                class="inline-flex min-w-[64px] items-center justify-center rounded-full border px-2.5 py-1 text-xs font-medium capitalize {{ $shiftClass }}">
                                {{ $row['shift'] }}
                            </span>
                        </td>
                        <td class="px-4 py-2.5 text-gray-700 dark:text-gray-300">{{ $row['jam_masuk'] ?? '-' }}</td>
                        <td class="px-4 py-2.5 text-gray-700 dark:text-gray-300">{{ $row['jam_pulang'] ?? '-' }}</td>
                        <td class="px-4 py-2.5 text-gray-500 dark:text-gray-400">{{ $row['jam_masuk_finger'] ?? '-' }}
                        </td>
                        <td class="px-4 py-2.5 text-gray-500 dark:text-gray-400">{{ $row['jam_pulang_finger'] ?? '-' }}
                        </td>
                        <td class="px-4 py-2.5">
                            @if (!empty($row['izin']))
                                <span
                                    class="inline-flex items-center rounded-full border border-amber-200 bg-amber-50 px-2.5 py-1 text-xs font-medium text-amber-700 dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-400">
                                    {{ $row['izin'] }}
                                </span>
                            @else
                                <span class="text-gray-400">-</span>
                            @endif
                        </td>
                        <td class="px-4 py-2.5 text-gray-500 dark:text-gray-400">{{ $row['keterangan'] ?? '-' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="10" class="px-4 py-10 text-center text-gray-400 dark:text-gray-500">
                            <div class="flex flex-col items-center gap-2">
                                <x-heroicon-o-inbox class="h-8 w-8 text-gray-300 dark:text-gray-600" />
                                <span>Tidak ada data absensi pada tanggal ini.</span>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-8">
        <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100 mb-1">
            Absensi Lain-lain (Checklog tanpa Data Produksi)
        </h3>
        <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">
            Pegawai yang tercatat absen fingerprint pada tanggal ini, tetapi tidak memiliki data pekerjaan di Press
            Dryer maupun Rotary.
        </p>

        <div class="overflow-x-auto rounded-xl border border-amber-200 shadow-sm dark:border-amber-800">
            <table class="w-full text-sm text-left border-collapse">
                <thead
                    class="bg-amber-50 text-xs font-semibold uppercase tracking-wide text-amber-800 dark:bg-amber-900/20 dark:text-amber-300">
                    <tr>
                        <th class="px-4 py-3 border-b border-amber-200 dark:border-amber-800">Kode Pegawai</th>
                        <th class="px-4 py-3 border-b border-amber-200 dark:border-amber-800">Nama Pegawai</th>
                        <th class="px-4 py-3 border-b border-amber-200 dark:border-amber-800">Jam Masuk</th>
                        <th class="px-4 py-3 border-b border-amber-200 dark:border-amber-800">Jam Pulang</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                    @forelse ($this->getAbsensiLainLain() as $row)
                        <tr class="transition-colors hover:bg-amber-50/50 dark:hover:bg-amber-900/10">
                            <td class="px-4 py-2.5 text-gray-700 dark:text-gray-300">{{ $row['kode_pegawai'] }}</td>
                            <td class="px-4 py-2.5 font-medium text-gray-900 dark:text-gray-100">
                                {{ $row['nama_pegawai'] }}</td>
                            <td class="px-4 py-2.5 text-gray-700 dark:text-gray-300">{{ $row['jam_masuk'] ?? '-' }}
                            </td>
                            <td class="px-4 py-2.5 text-gray-700 dark:text-gray-300">{{ $row['jam_pulang'] ?? '-' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-8 text-center text-gray-400 dark:text-gray-500">
                                Tidak ada checklog tanpa data produksi pada tanggal ini.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-8">
        <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100 mb-1">
            Riwayat Upload Finger
        </h3>
        <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">
            20 upload terakhir dari semua tanggal.
        </p>

        <div class="overflow-x-auto rounded-xl border border-gray-200 shadow-sm dark:border-gray-700">
            <table class="w-full text-sm text-left border-collapse">
                <thead
                    class="bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-600 dark:bg-gray-800 dark:text-gray-300">
                    <tr>
                        <th class="px-4 py-3 border-b border-gray-200 dark:border-gray-700">Batch #</th>
                        <th class="px-4 py-3 border-b border-gray-200 dark:border-gray-700">Tanggal</th>
                        <th class="px-4 py-3 border-b border-gray-200 dark:border-gray-700">File</th>
                        <th class="px-4 py-3 border-b border-gray-200 dark:border-gray-700">Diupload Oleh</th>
                        <th class="px-4 py-3 border-b border-gray-200 dark:border-gray-700">Waktu Upload</th>
                        <th class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                    @forelse ($this->getUploadHistory() as $item)
                        <tr class="transition-colors hover:bg-gray-50 dark:hover:bg-gray-800/50">
                            <td class="px-4 py-2.5 text-gray-700 dark:text-gray-300">#{{ $item->id }}</td>
                            <td class="px-4 py-2.5 text-gray-700 dark:text-gray-300">
                                {{ \Illuminate\Support\Carbon::parse($item->tanggal)->format('d/m/Y') }}
                            </td>
                            <td class="px-4 py-2.5 text-gray-500 dark:text-gray-400">
                                @foreach ($item->file_names as $fileName)
                                    <div>{{ $fileName }}</div>
                                @endforeach
                            </td>
                            <td class="px-4 py-2.5 text-gray-700 dark:text-gray-300">{{ $item->uploaded_by }}</td>
                            <td class="px-4 py-2.5 text-gray-500 dark:text-gray-400">
                                {{ $item->created_at->format('d/m/Y H:i') }}
                            </td>
                            <td class="px-4 py-2.5 text-right">
                                <x-filament::button size="sm" color="gray"
                                    wire:click="downloadUpload({{ $item->id }})" icon="heroicon-o-arrow-down-tray">
                                    Download
                                </x-filament::button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-8 text-center text-gray-400 dark:text-gray-500">
                                Belum ada riwayat upload.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-filament-panels::page>
