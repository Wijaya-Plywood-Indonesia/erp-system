<x-filament-panels::page>
    <style>
        @keyframes loading-bar {
            0% {
                transform: translateX(-100%);
            }

            50% {
                transform: translateX(150%);
            }

            100% {
                transform: translateX(-100%);
            }
        }
    </style>
    <div x-data="{ activeTab: @entangle('activeTab') }">

        <x-filament::tabs label="Content tabs">

            {{-- TAB 1: DATA ABSENSI --}}
            <x-filament::tabs.item alpine-active="activeTab === 'data'" wire:click="$set('activeTab', 'data')">
                Data Absensi
            </x-filament::tabs.item>

            {{-- TAB 2: UPLOAD --}}
            <x-filament::tabs.item alpine-active="activeTab === 'upload'" wire:click="$set('activeTab', 'upload')">
                Upload Finger
            </x-filament::tabs.item>

            {{-- TAB 3: RIWAYAT --}}
            <x-filament::tabs.item alpine-active="activeTab === 'riwayat'" wire:click="$set('activeTab', 'riwayat')">
                Riwayat Upload
            </x-filament::tabs.item>

        </x-filament::tabs>

        {{-- Filter Tanggal — selalu tampil di semua tab --}}
        <div class="mt-6 max-w-xs">
            <label class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-1 block">Tanggal</label>
            <input type="date" wire:model.live="tanggal"
                class="fi-input block w-full rounded-lg border-none bg-white px-3 py-2 text-sm shadow-sm ring-1 ring-gray-950/10 focus:ring-2 focus:ring-primary-600 dark:bg-white/5 dark:text-white dark:ring-white/20" />
        </div>

        {{-- ================= TAB CONTENT: DATA ABSENSI ================= --}}
        <div x-show="activeTab === 'data'" x-cloak wire:key="tab-data" class="mt-6 space-y-6">

            {{-- Loading bar tipis di atas, muncul saat tanggal berubah --}}
            <div wire:loading wire:target="tanggal"
                class="h-0.5 -mt-2 mb-2 overflow-hidden rounded-full bg-primary-100 dark:bg-primary-900/30">
                <div class="h-full w-1/3 rounded-full bg-primary-600 animate-[loading-bar_1s_ease-in-out_infinite]">
                </div>
            </div>

            <div wire:loading.class="opacity-40" wire:target="tanggal"
                class="transition-opacity duration-200 space-y-6">

                {{-- Aksi: Export Excel --}}
                <div class="flex flex-wrap items-center gap-3">
                    <x-filament::button wire:click="exportExcel" color="success" icon="heroicon-o-table-cells">
                        Export Excel
                    </x-filament::button>

                    {{-- Export format baru (Rumus Gaji Wijaya) — berdampingan dengan
                         Export Excel di atas, tidak menggantikan. Soft transition:
                         user bisa pilih pakai format lama atau format baru. Sebelum
                         file di-download, sistem otomatis cek kelengkapan target
                         (lihat NewAbsensi::exportRumusGajiWijaya()) dan menampilkan
                         peringatan kalau ada yang belum lengkap — export tetap
                         jalan. --}}
                    <x-filament::button wire:click="exportRumusGajiWijaya" color="warning"
                        icon="heroicon-o-currency-dollar" wire:loading.attr="disabled"
                        wire:target="exportRumusGajiWijaya">
                        Export Format Baru
                    </x-filament::button>

                    {{-- Tombol cek target TANPA export — buat user yang mau review
                         dulu sebelum benar-benar mengunduh filenya. --}}
                    <x-filament::button wire:click="cekTargetProduksi" color="gray" icon="heroicon-o-magnifying-glass"
                        wire:loading.attr="disabled" wire:target="cekTargetProduksi">
                        Cek Kelengkapan Target
                    </x-filament::button>
                </div>

                {{-- Tabel/peringatan hasil pengecekan target — hanya tampil kalau
                     sudah pernah dicek (baik lewat tombol manual maupun otomatis
                     saat export). TIDAK memblokir export sama sekali, murni
                     informasional. --}}
                @if ($sudahDicekTarget && count($missingTargetItems) > 0)
                    <div
                        class="rounded-xl border border-amber-300 bg-amber-50 p-4 dark:border-amber-800 dark:bg-amber-900/20">
                        <div class="flex items-start gap-3">
                            <x-heroicon-o-exclamation-triangle
                                class="h-6 w-6 shrink-0 text-amber-600 dark:text-amber-400" />
                            <div class="flex-1">
                                <h4 class="text-sm font-semibold text-amber-800 dark:text-amber-300">
                                    {{ count($missingTargetItems) }} item produksi belum punya target di Master
                                    Target
                                </h4>
                                <p class="text-sm text-amber-700 dark:text-amber-400 mt-1">
                                    Kamu tetap bisa export "Format Baru" — potongan untuk item di bawah ini akan
                                    otomatis dianggap <strong>Rp 0</strong> karena target-nya tidak ditemukan.
                                    Segera lengkapi Master Target kalau ini memang seharusnya ada targetnya.
                                </p>

                                <div
                                    class="mt-3 overflow-x-auto rounded-lg border border-amber-200 dark:border-amber-800">
                                    <table class="w-full text-sm text-left border-collapse">
                                        <thead
                                            class="bg-amber-100 dark:bg-amber-900/40 text-xs font-semibold uppercase text-amber-800 dark:text-amber-300">
                                            <tr>
                                                <th class="px-3 py-2">Divisi</th>
                                                <th class="px-3 py-2">Mesin / Meja</th>
                                                <th class="px-3 py-2">Ukuran</th>
                                            </tr>
                                        </thead>
                                        <tbody
                                            class="divide-y divide-amber-200 dark:divide-amber-800 bg-white dark:bg-transparent">
                                            @foreach ($missingTargetItems as $item)
                                                <tr>
                                                    <td
                                                        class="px-3 py-2 text-amber-900 dark:text-amber-200 font-medium">
                                                        {{ $item['divisi'] }}
                                                    </td>
                                                    <td class="px-3 py-2 text-amber-800 dark:text-amber-300">
                                                        {{ $item['mesin'] }}
                                                    </td>
                                                    <td class="px-3 py-2 text-amber-800 dark:text-amber-300">
                                                        {{ $item['ukuran'] }}
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                @elseif ($sudahDicekTarget && count($missingTargetItems) === 0)
                    <div
                        class="rounded-xl border border-green-300 bg-green-50 p-3 text-sm text-green-700 dark:border-green-800 dark:bg-green-900/20 dark:text-green-400">
                        ✔ Semua item produksi tanggal ini sudah punya target.
                    </div>
                @endif

                {{-- Tabel Rekap Utama --}}
                <div class="overflow-x-auto rounded-xl border border-gray-200 shadow-sm dark:border-gray-700">
                    <table class="w-full text-sm text-left border-collapse">
                        <thead
                            class="bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-600 dark:bg-gray-800 dark:text-gray-300">
                            <tr>
                                <th class="px-2 py-3 border-b border-gray-200 dark:border-gray-700 w-8"></th>
                                <th class="px-4 py-3 border-b border-gray-200 dark:border-gray-700">Sumber</th>
                                <th class="px-4 py-3 border-b border-gray-200 dark:border-gray-700">Kode Pegawai</th>
                                <th class="px-4 py-3 border-b border-gray-200 dark:border-gray-700">Nama Pegawai</th>
                                <th class="px-4 py-3 border-b border-gray-200 dark:border-gray-700">Shift</th>
                                <th class="px-4 py-3 border-b border-gray-200 dark:border-gray-700">Jam Kerja (Masuk)
                                </th>
                                <th class="px-4 py-3 border-b border-gray-200 dark:border-gray-700">Jam Kerja (Pulang)
                                </th>
                                <th class="px-4 py-3 border-b border-gray-200 dark:border-gray-700">Jam Masuk (Finger)
                                </th>
                                <th class="px-4 py-3 border-b border-gray-200 dark:border-gray-700">Jam Pulang (Finger)
                                </th>
                                <th class="px-4 py-3 border-b border-gray-200 dark:border-gray-700">Izin</th>
                                <th class="px-4 py-3 border-b border-gray-200 dark:border-gray-700">Keterangan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            @forelse ($this->getRekap() as $row)
                                @php
                                    // Key yang sama dengan yang dipakai groupBy() di
                                    // gabungkanMultiSumber() service: id_pegawai, fallback
                                    // nama_pegawai kalau id kosong. Dipakai sebagai wire:key
                                    // & identifier expand/collapse (toggleRow).
                                    $rowKey = (string) ($row['id_pegawai'] ?? $row['nama_pegawai']);
                                    $preview = $row['_finger_preview'] ?? null;
                                    $adaPreview = $preview && ($preview['hari_ini'] || $preview['besok']);
                                    $isMalam = strtolower($row['shift'] ?? '') === 'malam';
                                    // GANTI: default expand SEKARANG true (row otomatis
                                    // terbuka tanpa perlu klik), kecuali user sudah pernah
                                    // toggle row ini secara eksplisit (baik buka maupun
                                    // tutup) — statenya dibaca langsung dari
                                    // $this->expandedRows (public property di komponen),
                                    // bukan lewat isRowExpanded() lagi (yang defaultnya
                                    // false kalau key belum ada). Tombol toggle tetap
                                    // berfungsi normal untuk collapse manual per row.
                                    $isExpanded = array_key_exists($rowKey, $this->expandedRows)
                                        ? $this->expandedRows[$rowKey]
                                        : true;
                                    // Nilai FINAL yang beneran dipakai di kolom "Jam Masuk
// (Finger)" / "Jam Pulang (Finger)" tabel utama — hasil
                                    // enrichWithFinger() di service (baik lewat swap shift
                                    // malam / Haram #1, maupun lewat resolveJamFingerNonMalam
                                    // untuk shift pagi/siang). Dipakai di bawah cuma buat
                                    // MENCOCOKKAN raw preview ke nilai final ini (badge), TIDAK
                                    // menghitung ulang apapun — jadi berlaku sama untuk semua
                                    // shift tanpa perlu logic baru di service.
                                    $masukFingerDipakai = $row['jam_masuk_finger'] ?? null;
                                    $pulangFingerDipakai = $row['jam_pulang_finger'] ?? null;
                                    // Status telat: bandingkan jam_masuk_finger FINAL (yang
                                    // beneran dipakai, sudah lewat semua logic Haram #1/#6) ke
                                    // jam_masuk jadwal produksi. Purely tampilan, tidak
                                    // mempengaruhi data/perhitungan apapun di service.
                                    $telatMenit = null;
                                    $jamMasukProduksi = $row['jam_masuk'] ?? null;
                                    if (
                                        !empty($jamMasukProduksi) &&
                                        $jamMasukProduksi !== '-' &&
                                        !empty($masukFingerDipakai) &&
                                        $masukFingerDipakai !== '-'
                                    ) {
                                        try {
                                            $tProduksi = \Illuminate\Support\Carbon::parse($jamMasukProduksi);
                                            $tFinger = \Illuminate\Support\Carbon::parse($masukFingerDipakai);
                                            if ($tFinger->gt($tProduksi)) {
                                                $telatMenit = (int) $tProduksi->diffInMinutes($tFinger);
                                            }
                                        } catch (\Throwable $e) {
                                            // gagal parse, biarkan null (gak dianggap telat)
                                        }
                                    }
                                @endphp
                                <tr wire:key="row-{{ $rowKey }}"
                                    class="transition-colors {{ $telatMenit ? 'bg-amber-50 hover:bg-amber-100 dark:bg-amber-500/10 dark:hover:bg-amber-500/20' : 'hover:bg-gray-50 dark:hover:bg-gray-800/50' }}">
                                    <td class="px-2 py-2.5 text-center">
                                        @if ($adaPreview)
                                            <button type="button" wire:click="toggleRow('{{ $rowKey }}')"
                                                title="Preview absen"
                                                class="inline-flex h-6 w-6 items-center justify-center rounded-full text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-700">
                                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"
                                                    fill="currentColor"
                                                    class="h-4 w-4 transition-transform duration-150 {{ $isExpanded ? 'rotate-90' : '' }}">
                                                    <path fill-rule="evenodd"
                                                        d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z"
                                                        clip-rule="evenodd" />
                                                </svg>
                                            </button>
                                        @endif
                                    </td>
                                    <td class="px-4 py-2.5">
                                        <div class="flex flex-col gap-1">
                                            @forelse ((array) $row['sumber_label'] as $sumber)
                                                @php
                                                    $parts = explode(':', $sumber, 2);
                                                    $mainDiv = trim($parts[0]);
                                                    $detailDiv = isset($parts[1]) ? trim($parts[1]) : '';
                                                @endphp
                                                <div class="flex flex-wrap items-center gap-1.5">
                                                    <span
                                                        class="inline-flex min-w-[64px] items-center justify-center gap-1 rounded-full border border-primary-200 bg-primary-50 px-2.5 py-1 text-xs font-medium text-primary-700 dark:border-primary-500/20 dark:bg-primary-500/10 dark:text-primary-400">
                                                        {{ $mainDiv }}
                                                    </span>
                                                    @if ($detailDiv !== '')
                                                        <span class="text-xs text-gray-500 dark:text-gray-400">
                                                            {{ $detailDiv }}
                                                        </span>
                                                    @endif
                                                </div>
                                            @empty
                                                <span class="text-gray-400">-</span>
                                            @endforelse
                                        </div>
                                    </td>
                                    <td class="px-4 py-2.5 text-gray-700 dark:text-gray-300">
                                        {{ $row['kode_pegawai'] ?? '-' }}
                                    </td>
                                    <td class="px-4 py-2.5 font-medium text-gray-900 dark:text-gray-100">
                                        {{ $row['nama_pegawai'] }}
                                    </td>
                                    <td class="px-4 py-2.5">
                                        {{-- Badge shift cuma dirender kalau shift-nya beneran ada isinya.
                                             Pegawai hasil lengkapiSemuaPegawai() punya shift = null
                                             (tidak ada data source hari itu), jadi harus jatuh ke
                                             tampilan "-" polos tanpa border/pill, bukan badge kosong. --}}
                                        @if (!empty($row['shift']))
                                            @php
                                                $shiftColors = [
                                                    'pagi' =>
                                                        'border-sky-200 bg-sky-50 text-sky-700 dark:border-sky-500/20 dark:bg-sky-500/10 dark:text-sky-400',
                                                    'siang' =>
                                                        'border-orange-200 bg-orange-50 text-orange-700 dark:border-orange-500/20 dark:bg-orange-500/10 dark:text-orange-400',
                                                    'malam' =>
                                                        'border-indigo-200 bg-indigo-50 text-indigo-700 dark:border-indigo-500/20 dark:bg-indigo-500/10 dark:text-indigo-400',
                                                ];
                                                $shiftKey = strtolower($row['shift']);
                                                $shiftClass =
                                                    $shiftColors[$shiftKey] ??
                                                    'border-gray-200 bg-gray-50 text-gray-700 dark:border-gray-500/20 dark:bg-gray-500/10 dark:text-gray-400';
                                            @endphp
                                            <span
                                                class="inline-flex min-w-[64px] items-center justify-center rounded-full border px-2.5 py-1 text-xs font-medium capitalize {{ $shiftClass }}">
                                                {{ $row['shift'] }}
                                            </span>
                                        @else
                                            <span class="text-gray-400">-</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-2.5 text-gray-700 dark:text-gray-300">
                                        {{ $row['jam_masuk'] ?? '-' }}
                                    </td>
                                    <td class="px-4 py-2.5 text-gray-700 dark:text-gray-300">
                                        {{ $row['jam_pulang'] ?? '-' }}
                                    </td>
                                    <td
                                        class="px-4 py-2.5 {{ $telatMenit ? 'text-amber-700 dark:text-amber-400' : 'text-gray-500 dark:text-gray-400' }}">
                                        <span
                                            class="{{ $telatMenit ? 'font-medium' : '' }}">{{ $row['jam_masuk_finger'] ?? '-' }}</span>
                                        @if ($telatMenit)
                                            <span
                                                class="ml-1 inline-flex items-center rounded-full bg-amber-100 px-1.5 py-0.5 text-[10px] font-semibold text-amber-700 dark:bg-amber-500/10 dark:text-amber-400"
                                                title="Telat {{ $telatMenit }} menit dari jadwal">
                                                +{{ $telatMenit }}m
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-2.5 text-gray-500 dark:text-gray-400">
                                        {{ $row['jam_pulang_finger'] ?? '-' }}
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
                                    <td class="px-4 py-2.5 text-gray-500 dark:text-gray-400">
                                        {{ $row['keterangan'] ?? '-' }}
                                    </td>
                                </tr>

                                {{-- Expandable preview row — hanya dirender kalau ada data
                                     finger buat dipreview & lagi di-expand (SEKARANG default
                                     terbuka untuk semua row, lihat perhitungan $isExpanded
                                     di atas). Panel "Simulasi pagi / Simulasi malam" yang
                                     dulu ada di sini SUDAH DIHAPUS — sekarang cuma tersisa
                                     panel "Raw finger" di bawah, yang menampilkan hasil
                                     simulasi_pagi (hari ini) / simulasi_pagi_besok (besok)
                                     — resolveJamFingerNonMalam(): toleransi 15 menit, dedupe
                                     arah per-pasangan, fallback jadwal default shift pagi.
                                     Sumbernya SAMA PERSIS dengan kolom J-M di
                                     NewRekapAbsensiExport, supaya preview UI konsisten
                                     dengan hasil Excel. PURELY tampilan, tidak dipakai
                                     logic apapun, tidak menyentuh enrichWithFinger()
                                     ataupun urutan pipeline di rekap.md. --}}
                                @if ($adaPreview && $isExpanded)
                                    @php
                                        $hi = $preview['hari_ini'] ?? null;
                                        // Sumber value panel "Raw finger" hari ini —
                                        // simulasi_pagi (resolveJamFingerNonMalam()).
                                        $simPagiHariIni = $preview['simulasi_pagi'] ?? null;
                                        $hiMasukRaw = $simPagiHariIni['jam_masuk_finger'] ?? null;
                                        $hiPulangRaw = $simPagiHariIni['jam_pulang_finger'] ?? null;

                                        // Sumber value panel "Raw finger" besok —
                                        // simulasi_pagi_besok (logic sama, raw diambil dari
                                        // finger tanggal besok).
                                        $besok = $preview['besok'] ?? null;
                                        $simPagiBesok = $preview['simulasi_pagi_besok'] ?? null;
                                        $besokMasukRaw = $simPagiBesok['jam_masuk_finger'] ?? null;
                                        $besokPulangRaw = $simPagiBesok['jam_pulang_finger'] ?? null;
                                    @endphp
                                    <tr wire:key="row-preview-{{ $rowKey }}"
                                        class="bg-gray-50/70 dark:bg-gray-800/40">
                                        <td colspan="11" class="px-6 py-3">
                                            <div class="flex flex-col gap-3 text-sm">

                                                {{-- Panel "Raw finger" — menampilkan hasil
                                                     simulasi_pagi (hari ini) & simulasi_pagi_besok
                                                     (besok), bukan raw db mentah. Dipisah per
                                                     tanggal (hari ini / besok) karena scan shift
                                                     malam nyebrang tanggal (lihat Haram #1/#2). --}}
                                                <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:gap-6">
                                                    <span
                                                        class="shrink-0 text-xs font-medium uppercase tracking-wide text-gray-400">
                                                        Raw finger
                                                    </span>

                                                    <div class="flex flex-wrap items-center gap-x-6 gap-y-2">
                                                        <span class="text-xs text-gray-400">
                                                            {{ $hi['tanggal'] ?? null ? \Illuminate\Support\Carbon::parse($hi['tanggal'])->format('d/m') : '-' }}
                                                        </span>
                                                        <div class="flex items-center gap-1.5">
                                                            <span class="text-gray-400">Masuk</span>
                                                            <span
                                                                class="font-mono text-gray-600 dark:text-gray-400">{{ $hiMasukRaw ?? '-' }}</span>
                                                        </div>
                                                        <div class="flex items-center gap-1.5">
                                                            <span class="text-gray-400">Pulang</span>
                                                            <span
                                                                class="font-mono text-gray-600 dark:text-gray-400">{{ $hiPulangRaw ?? '-' }}</span>
                                                        </div>
                                                    </div>

                                                    @if ($besok)
                                                        <span
                                                            class="hidden h-4 w-px bg-gray-200 dark:bg-gray-700 lg:block"></span>
                                                        <div class="flex flex-wrap items-center gap-x-6 gap-y-2">
                                                            <span class="text-xs text-gray-400">
                                                                {{ $besok['tanggal'] ?? null ? \Illuminate\Support\Carbon::parse($besok['tanggal'])->format('d/m') : '-' }}
                                                            </span>
                                                            <div class="flex items-center gap-1.5">
                                                                <span class="text-gray-400">Masuk</span>
                                                                <span
                                                                    class="font-mono text-gray-600 dark:text-gray-400">{{ $besokMasukRaw ?? '-' }}</span>
                                                            </div>
                                                            <div class="flex items-center gap-1.5">
                                                                <span class="text-gray-400">Pulang</span>
                                                                <span
                                                                    class="font-mono text-gray-600 dark:text-gray-400">{{ $besokPulangRaw ?? '-' }}</span>
                                                            </div>
                                                        </div>
                                                    @endif
                                                </div>

                                            </div>
                                        </td>
                                    </tr>
                                @endif
                            @empty
                                <tr>
                                    <td colspan="11"
                                        class="px-4 py-10 text-center text-gray-400 dark:text-gray-500">
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

                {{-- Absensi Lain-lain --}}
                <div>
                    <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100 mb-1">
                        Absensi Lain-lain (Checklog tanpa Data Produksi)
                    </h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">
                        Pegawai yang tercatat absen fingerprint pada tanggal ini, tetapi tidak memiliki data pekerjaan
                        di
                        Press
                        Dryer maupun Rotary.
                    </p>

                    <div class="overflow-x-auto rounded-xl border border-amber-200 shadow-sm dark:border-amber-800">
                        <table class="w-full text-sm text-left border-collapse">
                            <thead
                                class="bg-amber-50 text-xs font-semibold uppercase tracking-wide text-amber-800 dark:bg-amber-900/20 dark:text-amber-300">
                                <tr>
                                    <th class="px-4 py-3 border-b border-amber-200 dark:border-amber-800">Kode Pegawai
                                    </th>
                                    <th class="px-4 py-3 border-b border-amber-200 dark:border-amber-800">Nama Pegawai
                                    </th>
                                    <th class="px-4 py-3 border-b border-amber-200 dark:border-amber-800">Jam Masuk
                                    </th>
                                    <th class="px-4 py-3 border-b border-amber-200 dark:border-amber-800">Jam Pulang
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                @forelse ($this->getAbsensiLainLain() as $row)
                                    <tr class="transition-colors hover:bg-amber-50/50 dark:hover:bg-amber-900/10">
                                        <td class="px-4 py-2.5 text-gray-700 dark:text-gray-300">
                                            {{ $row['kode_pegawai'] }}
                                        </td>
                                        <td class="px-4 py-2.5 font-medium text-gray-900 dark:text-gray-100">
                                            {{ $row['nama_pegawai'] }}</td>
                                        <td class="px-4 py-2.5 text-gray-700 dark:text-gray-300">
                                            {{ $row['jam_masuk'] ?? '-' }}
                                        </td>
                                        <td class="px-4 py-2.5 text-gray-700 dark:text-gray-300">
                                            {{ $row['jam_pulang'] ?? '-' }}
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4"
                                            class="px-4 py-8 text-center text-gray-400 dark:text-gray-500">
                                            Tidak ada checklog tanpa data produksi pada tanggal ini.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </div>

        {{-- ================= TAB CONTENT: UPLOAD ================= --}}
        <div x-show="activeTab === 'upload'" x-cloak wire:key="tab-upload" class="mt-6 space-y-4">
            <form wire:submit.prevent>
                {{ $this->uploadForm }}
            </form>

            <div class="flex flex-wrap items-center gap-3">
                <x-filament::button wire:click="uploadFinger" icon="heroicon-o-arrow-up-tray">
                    Proses Upload Finger
                </x-filament::button>

                <x-filament::button wire:click="downloadFingerForSelectedDate" color="gray"
                    icon="heroicon-o-arrow-down-tray">
                    Download Finger Tanggal Ini
                </x-filament::button>
            </div>
        </div>

        {{-- ================= TAB CONTENT: RIWAYAT UPLOAD ================= --}}
        <div x-show="activeTab === 'riwayat'" x-cloak wire:key="tab-riwayat" class="mt-6">
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
                                        wire:click="downloadUpload({{ $item->id }})"
                                        icon="heroicon-o-arrow-down-tray">
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

    </div>
</x-filament-panels::page>
