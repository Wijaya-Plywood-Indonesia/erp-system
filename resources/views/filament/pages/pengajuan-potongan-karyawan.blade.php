<x-filament-panels::page>
    <style>
        [x-cloak] {
            display: none !important;
        }

        .tab-btn {
            padding: 8px 12px;
            font-weight: 600;
            font-size: 0.875rem;
            border-bottom: 2px solid transparent;
            cursor: pointer;
            transition: all 0.2s;
            white-space: nowrap;
        }

        .tab-btn-active {
            border-bottom-color: #3b82f6;
            color: #3b82f6;
        }

        .tab-btn-inactive {
            color: #6b7280;
        }

        .tab-btn-inactive:hover {
            color: #374151;
            border-bottom-color: #d1d5db;
        }

        .dark .tab-btn-active {
            border-bottom-color: #60a5fa;
            color: #60a5fa;
        }

        .dark .tab-btn-inactive {
            color: #9ca3af;
        }

        .dark .tab-btn-inactive:hover {
            color: #e5e7eb;
            border-bottom-color: #4b5563;
        }
    </style>

    {{-- Header Tabs Navigation --}}
    <div
        class="border-b border-gray-200 dark:border-gray-700 mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex space-x-2 sm:space-x-4 overflow-x-auto -mb-px">
            <button type="button" wire:click="setTab('dashboard')"
                class="tab-btn {{ $activeTab === 'dashboard' ? 'tab-btn-active' : 'tab-btn-inactive' }}">
                <span class="flex items-center gap-2">
                    <x-heroicon-o-calendar-days class="w-5 h-5 shrink-0" />
                    <span class="hidden sm:inline">Tab 1: Dashboard Potongan Harian</span>
                    <span class="sm:hidden">Dashboard</span>
                </span>
            </button>

            @if ($this->canManageApproval())
                <button type="button" wire:click="setTab('persetujuan')"
                    class="tab-btn {{ $activeTab === 'persetujuan' ? 'tab-btn-active' : 'tab-btn-inactive' }}">
                    <span class="flex items-center gap-2">
                        <x-heroicon-o-check-badge class="w-5 h-5 shrink-0" />
                        <span class="hidden sm:inline">Tab 2: Persetujuan Pengajuan</span>
                        <span class="sm:hidden">Persetujuan</span>
                    </span>
                </button>
            @endif
        </div>

        @if ($activeTab === 'persetujuan' && $this->canManageApproval())
            <div class="pb-2 sm:pb-0">
                <x-filament::button size="sm" color="primary" icon="heroicon-o-plus"
                    wire:click="bukaAdminCreateModal" class="w-full sm:w-auto">
                    Buat Pengajuan Baru (Kantor)
                </x-filament::button>
            </div>
        @endif
    </div>

    {{-- ========================================================================= --}}
    {{-- TAB 1: DASHBOARD POTONGAN HARIAN --}}
    {{-- ========================================================================= --}}
    @if ($activeTab === 'dashboard')
        <div class="space-y-6">
            {{-- Filter Tanggal, Divisi & Opsi Potongan --}}
            <div class="bg-white dark:bg-gray-800 p-4 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm space-y-3">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                    <div class="flex flex-col sm:flex-row sm:items-center gap-3 flex-wrap">
                        {{-- Filter Tanggal --}}
                        <div class="w-full sm:w-64">
                            {{ $this->form }}
                        </div>

                        {{-- Filter Divisi --}}
                        <div class="w-full sm:w-56">
                            <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">
                                Filter Divisi / Bagian
                            </label>
                            <select wire:model.live="filterDivisi"
                                class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white px-3 py-2 text-sm focus:ring-primary-500 focus:border-primary-500">
                                <option value="">Semua Divisi</option>
                                @foreach ($this->divisiOptions as $divKey => $divLabel)
                                    <option value="{{ $divKey }}">{{ $divLabel }}</option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Filter Hanya Potongan --}}
                        <div class="flex items-center pt-1 sm:pt-5">
                            <label class="inline-flex items-center gap-2 cursor-pointer select-none">
                                <input type="checkbox" wire:model.live="filterHanyaPotongan"
                                    class="rounded border-gray-300 text-primary-600 shadow-sm focus:ring-primary-500">
                                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">
                                    Hanya yang ada potongan (> Rp 0)
                                </span>
                            </label>
                        </div>
                    </div>

                    <div class="text-sm text-gray-500 dark:text-gray-400 pt-1 lg:pt-0">
                        @php
                            [
                                $mulaiMinggu,
                                $akhirMinggu,
                            ] = \App\Services\Payroll\PeriodeJumatKamisResolver::resolveDefault(
                                \Illuminate\Support\Carbon::parse($tanggal),
                            );
                        @endphp
                        <span>Periode Mingguan: <strong>{{ \Carbon\Carbon::parse($mulaiMinggu)->format('d M') }}
                                &ndash;
                                {{ \Carbon\Carbon::parse($akhirMinggu)->format('d M Y') }}</strong> (Jumat &ndash;
                            Kamis)</span>
                    </div>
                </div>
            </div>

            @php
                $rows = $this->getDashboardRows();
            @endphp

            <x-filament::card>
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between mb-4">
                    <div>
                        <h3 class="font-bold text-gray-900 dark:text-white text-base">
                            Daftar Potongan Karyawan &mdash;
                            {{ \Carbon\Carbon::parse($tanggal)->translatedFormat('l, d F Y') }}
                            <span class="text-sm font-normal text-gray-500">({{ $rows->count() }} orang)</span>
                        </h3>
                    </div>

                    @php
                        $selectedCount = $this->selectedCount;
                    @endphp

                    @if ($rows->isNotEmpty())
                        <div class="flex flex-wrap items-center gap-2">
                            <x-filament::button size="sm" color="primary" icon="heroicon-o-queue-list"
                                wire:click="bukaBulkPengajuanModal" :disabled="$selectedCount === 0">
                                Ajukan Potongan Masal (Bulk)
                                @if ($selectedCount > 0)
                                    <span class="ml-1 px-1.5 py-0.5 rounded-full bg-white/20 text-xs font-bold">
                                        {{ $selectedCount }}
                                    </span>
                                @endif
                            </x-filament::button>
                        </div>
                    @endif
                </div>

                @if ($rows->isEmpty())
                    <div class="text-center py-12 text-gray-400">
                        <x-heroicon-o-user-group class="w-12 h-12 mx-auto mb-2 text-gray-300" />
                        <p>Tidak ada data absensi/karyawan untuk divisi Anda pada tanggal ini.</p>
                    </div>
                @else
                    {{-- ===== DESKTOP: TABEL ===== --}}
                    <div class="hidden md:block overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                        <table class="w-full text-sm text-left border-collapse">
                            <thead
                                class="bg-gray-50 dark:bg-gray-800 text-xs font-semibold uppercase text-gray-600 dark:text-gray-300">
                                <tr>
                                    <th class="px-3 py-3 border-b text-center w-10">Pilih</th>
                                    <th class="px-4 py-3 border-b">Kode</th>
                                    <th class="px-4 py-3 border-b">Nama Karyawan</th>
                                    <th class="px-4 py-3 border-b">Divisi / Bagian</th>
                                    <th class="px-4 py-3 border-b text-right">Potongan Harian</th>
                                    <th class="px-4 py-3 border-b text-right bg-blue-50/50 dark:bg-blue-950/20">
                                        Akumulasi Mingguan</th>
                                    <th class="px-4 py-3 border-b text-right">Usulan</th>
                                    <th class="px-4 py-3 border-b">Status Pengajuan</th>
                                    <th class="px-4 py-3 border-b text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                @foreach ($rows as $row)
                                    @php
                                        $pengajuan = $row['pengajuan'];
                                        $safePegawaiId = (int) ($row['id_pegawai'] ?? 0);
                                        $safeKode = $row['kode_pegawai'] ?? '';
                                        $safeNama = $row['nama_pegawai'] ?? '';
                                        $safeDivisi = $row['divisi'] ?? '';
                                        $safePotongan = (float) ($row['potongan_harian'] ?? 0);
                                        $rowKey =
                                            'row-karyawan-' .
                                            $safeKode .
                                            '-' .
                                            ($pengajuan
                                                ? $pengajuan->status .
                                                    '-' .
                                                    $pengajuan->nominal_usulan .
                                                    '-' .
                                                    $pengajuan->updated_at?->timestamp
                                                : 'none');
                                    @endphp
                                    <tr wire:key="row-karyawan-{{ $safeKode }}"
                                        class="hover:bg-gray-50/80 dark:hover:bg-gray-800/60 transition-colors {{ in_array($safeKode, $selectedKodep) ? 'bg-primary-50/40 dark:bg-primary-950/20' : '' }}">
                                        <td class="px-3 py-3 text-center">
                                            <input type="checkbox" wire:model.live="selectedKodep" value="{{ $safeKode }}"
                                                class="rounded border-gray-300 text-primary-600 shadow-sm focus:ring-primary-500">
                                        </td>
                                        <td class="px-4 py-3 font-mono text-xs text-gray-600 dark:text-gray-400">
                                            {{ $safeKode }}
                                        </td>
                                        <td class="px-4 py-3 font-medium text-gray-900 dark:text-gray-100">
                                            {{ $safeNama }}
                                        </td>
                                        <td class="px-4 py-3 text-xs text-gray-500 dark:text-gray-400">
                                            {{ $safeDivisi }}
                                        </td>
                                        <td
                                            class="px-4 py-3 text-right font-medium {{ $safePotongan > 0 ? 'text-red-600 dark:text-red-400' : 'text-gray-400' }}">
                                            Rp {{ number_format($safePotongan, 0, ',', '.') }}
                                        </td>
                                        <td
                                            class="px-4 py-3 text-right font-bold bg-blue-50/30 dark:bg-blue-950/10 {{ ($row['potongan_mingguan'] ?? 0) > 50000 ? 'text-amber-600 dark:text-amber-400' : 'text-gray-700 dark:text-gray-300' }}">
                                            Rp {{ number_format($row['potongan_mingguan'] ?? 0, 0, ',', '.') }}
                                        </td>
                                        <td class="px-4 py-3 text-right whitespace-nowrap">
                                            @if ($pengajuan)
                                                <span class="font-semibold text-amber-600 dark:text-amber-400">
                                                    Rp {{ number_format($pengajuan->nominal_usulan, 0, ',', '.') }}
                                                </span>
                                            @else
                                                <span class="text-xs text-gray-400">-</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3">
                                            @if ($pengajuan)
                                                @if ($pengajuan->status === 'pending')
                                                    <span
                                                        class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400">
                                                        Pending
                                                        @if ($pengajuan->created_at->ne($pengajuan->updated_at))
                                                            &middot; direvisi
                                                        @endif
                                                    </span>
                                                @elseif($pengajuan->status === 'approved')
                                                    <span
                                                        class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-400">
                                                        Approved
                                                        (Rp{{ number_format($pengajuan->nominal_final, 0, ',', '.') }})
                                                    </span>
                                                @elseif($pengajuan->status === 'rejected')
                                                    <span
                                                        class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-rose-100 text-rose-800 dark:bg-rose-900/30 dark:text-rose-400"
                                                        title="{{ $pengajuan->alasan_penolakan }}">
                                                        Rejected
                                                    </span>
                                                @endif
                                            @else
                                                <span class="text-xs text-gray-400">-</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-center">
                                            @if ($pengajuan && $pengajuan->status === 'pending')
                                                <x-filament::button size="xs" color="warning"
                                                    icon="heroicon-o-pencil"
                                                    wire:click="bukaPengajuanModal({{ $safePegawaiId }}, '{{ addslashes($safeNama) }}', '{{ $safeKode }}', '{{ addslashes($safeDivisi) }}', {{ $safePotongan }})">
                                                    Edit Pengajuan
                                                </x-filament::button>
                                            @else
                                                <x-filament::button size="xs" color="gray"
                                                    icon="heroicon-o-pencil-square"
                                                    wire:click="bukaPengajuanModal({{ $safePegawaiId }}, '{{ addslashes($safeNama) }}', '{{ $safeKode }}', '{{ addslashes($safeDivisi) }}', {{ $safePotongan }})">
                                                    Ajukan Anulir / Ubah
                                                </x-filament::button>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    {{-- ===== MOBILE: KARTU ===== --}}
                    <div class="md:hidden space-y-3">
                        @foreach ($rows as $row)
                            @php
                                $pengajuan = $row['pengajuan'];
                                $safePegawaiId = (int) ($row['id_pegawai'] ?? 0);
                                $safeKode = $row['kode_pegawai'] ?? '';
                                $safeNama = $row['nama_pegawai'] ?? '';
                                $safeDivisi = $row['divisi'] ?? '';
                                $safePotongan = (float) ($row['potongan_harian'] ?? 0);
                                $mingguan = $row['potongan_mingguan'] ?? 0;
                                $isPending = $pengajuan && $pengajuan->status === 'pending';
                                $cardKey =
                                    'card-karyawan-' .
                                    $safeKode .
                                    '-' .
                                    ($pengajuan
                                        ? $pengajuan->status .
                                            '-' .
                                            $pengajuan->nominal_usulan .
                                            '-' .
                                            $pengajuan->updated_at?->timestamp
                                        : 'none');
                            @endphp
                            <div wire:key="card-karyawan-{{ $safeKode }}"
                                class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-3 space-y-3 {{ in_array($safeKode, $selectedKodep) ? 'ring-2 ring-primary-500 bg-primary-50/20' : '' }}">

                                <div class="flex items-start justify-between gap-2">
                                    <div class="flex items-start gap-3 min-w-0">
                                        <div class="pt-0.5">
                                            <input type="checkbox" wire:model.live="selectedKodep" value="{{ $safeKode }}"
                                                class="rounded border-gray-300 text-primary-600 shadow-sm focus:ring-primary-500">
                                        </div>
                                        <div class="min-w-0">
                                            <div class="font-semibold text-gray-900 dark:text-gray-100 break-words">
                                                {{ $safeNama }}</div>
                                            <div class="text-xs font-mono text-gray-500">{{ $safeKode }}</div>
                                            <div class="text-xs text-gray-500 dark:text-gray-400 break-words">
                                                {{ $safeDivisi }}</div>
                                        </div>
                                    </div>
                                    <div class="shrink-0">
                                        @if ($pengajuan)
                                            @if ($pengajuan->status === 'pending')
                                                <span
                                                    class="inline-flex px-2 py-0.5 rounded-full text-xs font-semibold bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400">Pending</span>
                                            @elseif ($pengajuan->status === 'approved')
                                                <span
                                                    class="inline-flex px-2 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-400">Approved</span>
                                            @elseif ($pengajuan->status === 'rejected')
                                                <span
                                                    class="inline-flex px-2 py-0.5 rounded-full text-xs font-semibold bg-rose-100 text-rose-800 dark:bg-rose-900/30 dark:text-rose-400">Rejected</span>
                                            @endif
                                        @endif
                                    </div>
                                </div>

                                <div class="grid grid-cols-2 gap-2 text-sm">
                                    <div class="rounded-lg bg-gray-50 dark:bg-gray-900/40 p-2">
                                        <div class="text-[11px] uppercase text-gray-400">Potongan Harian</div>
                                        <div
                                            class="font-semibold {{ $safePotongan > 0 ? 'text-red-600 dark:text-red-400' : 'text-gray-400' }}">
                                            Rp {{ number_format($safePotongan, 0, ',', '.') }}
                                        </div>
                                    </div>
                                    <div class="rounded-lg bg-blue-50/50 dark:bg-blue-950/20 p-2">
                                        <div class="text-[11px] uppercase text-gray-400">Akumulasi Mingguan</div>
                                        <div
                                            class="font-bold {{ $mingguan > 50000 ? 'text-amber-600 dark:text-amber-400' : 'text-gray-700 dark:text-gray-300' }}">
                                            Rp {{ number_format($mingguan, 0, ',', '.') }}
                                        </div>
                                    </div>
                                </div>

                                @if ($pengajuan && $pengajuan->status === 'pending')
                                    <div
                                        class="flex items-center justify-between rounded-lg bg-amber-50 dark:bg-amber-900/20 px-3 py-2 text-sm">
                                        <span class="text-amber-700 dark:text-amber-400">
                                            Usulan
                                            @if ($pengajuan->created_at->ne($pengajuan->updated_at))
                                                <span class="text-xs text-gray-400">(direvisi)</span>
                                            @endif
                                        </span>
                                        <span class="font-semibold text-amber-700 dark:text-amber-300">
                                            Rp {{ number_format($pengajuan->nominal_usulan, 0, ',', '.') }}
                                        </span>
                                    </div>
                                @elseif ($pengajuan && $pengajuan->status === 'approved')
                                    <div class="rounded-lg bg-gray-50 dark:bg-gray-900/40 px-3 py-2 text-sm space-y-1">
                                        <div class="flex justify-between">
                                            <span class="text-gray-500">Usulan</span>
                                            <span class="font-medium text-amber-600 dark:text-amber-400">Rp
                                                {{ number_format($pengajuan->nominal_usulan, 0, ',', '.') }}</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-gray-500">Disetujui</span>
                                            <span class="font-medium text-emerald-600 dark:text-emerald-400">Rp
                                                {{ number_format($pengajuan->nominal_final, 0, ',', '.') }}</span>
                                        </div>
                                    </div>
                                @elseif ($pengajuan && $pengajuan->status === 'rejected' && $pengajuan->alasan_penolakan)
                                    <div class="rounded-lg bg-gray-50 dark:bg-gray-900/40 px-3 py-2 text-sm space-y-1">
                                        <div class="flex justify-between">
                                            <span class="text-gray-500">Usulan</span>
                                            <span class="font-medium text-amber-600 dark:text-amber-400">Rp
                                                {{ number_format($pengajuan->nominal_usulan, 0, ',', '.') }}</span>
                                        </div>
                                        <div class="text-xs text-gray-500 break-words">Ditolak:
                                            {{ $pengajuan->alasan_penolakan }}</div>
                                    </div>
                                @endif

                                <x-filament::button size="sm" :color="$isPending ? 'warning' : 'gray'" :icon="$isPending ? 'heroicon-o-pencil' : 'heroicon-o-pencil-square'" class="w-full"
                                    wire:click="bukaPengajuanModal({{ $safePegawaiId }}, '{{ addslashes($safeNama) }}', '{{ $safeKode }}', '{{ addslashes($safeDivisi) }}', {{ $safePotongan }})">
                                    {{ $isPending ? 'Edit Pengajuan' : 'Ajukan Anulir / Ubah' }}
                                </x-filament::button>
                            </div>
                        @endforeach
                    </div>
                @endif
            </x-filament::card>
        </div>
    @endif

    {{-- ========================================================================= --}}
    {{-- TAB 2: PERSETUJUAN PENGAJUAN (Super Admin & Absen Only) --}}
    {{-- ========================================================================= --}}
    @if ($activeTab === 'persetujuan' && $this->canManageApproval())
        <div class="space-y-6">
            {{-- Filter Status --}}
            <div class="flex flex-wrap items-center gap-2 sm:gap-3">
                <span class="text-xs font-bold uppercase text-gray-500">Filter Status:</span>
                <button type="button" wire:click="$set('filterStatusPersetujuan', 'all')"
                    class="px-3 py-1 text-xs rounded-full border {{ $filterStatusPersetujuan === 'all' ? 'bg-primary-600 text-white border-primary-600' : 'bg-white text-gray-600 border-gray-300 dark:bg-gray-800 dark:text-gray-300' }}">
                    Semua
                </button>
                <button type="button" wire:click="$set('filterStatusPersetujuan', 'pending')"
                    class="px-3 py-1 text-xs rounded-full border {{ $filterStatusPersetujuan === 'pending' ? 'bg-amber-600 text-white border-amber-600' : 'bg-white text-gray-600 border-gray-300 dark:bg-gray-800 dark:text-gray-300' }}">
                    Pending
                </button>
                <button type="button" wire:click="$set('filterStatusPersetujuan', 'approved')"
                    class="px-3 py-1 text-xs rounded-full border {{ $filterStatusPersetujuan === 'approved' ? 'bg-emerald-600 text-white border-emerald-600' : 'bg-white text-gray-600 border-gray-300 dark:bg-gray-800 dark:text-gray-300' }}">
                    Approved
                </button>
                <button type="button" wire:click="$set('filterStatusPersetujuan', 'rejected')"
                    class="px-3 py-1 text-xs rounded-full border {{ $filterStatusPersetujuan === 'rejected' ? 'bg-rose-600 text-white border-rose-600' : 'bg-white text-gray-600 border-gray-300 dark:bg-gray-800 dark:text-gray-300' }}">
                    Rejected
                </button>
            </div>

            @php
                $persetujuanRows = $this->getPersetujuanRows();
            @endphp

            <x-filament::card>
                <h3 class="font-bold text-gray-900 dark:text-white text-base mb-4">
                    Daftar Pengajuan Perubahan Potongan ({{ $persetujuanRows->count() }})
                </h3>

                @if ($persetujuanRows->isEmpty())
                    <div class="text-center py-12 text-gray-400">
                        <x-heroicon-o-inbox class="w-12 h-12 mx-auto mb-2 text-gray-300" />
                        <p>Tidak ada pengajuan potongan dengan filter ini.</p>
                    </div>
                @else
                    {{-- ===== DESKTOP: TABEL ===== --}}
                    <div
                        class="hidden md:block overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                        <table class="w-full text-sm text-left border-collapse">
                            <thead
                                class="bg-gray-50 dark:bg-gray-800 text-xs font-semibold uppercase text-gray-600 dark:text-gray-300">
                                <tr>
                                    <th class="px-4 py-3 border-b">Tgl Pengajuan</th>
                                    <th class="px-4 py-3 border-b">Tgl Potongan</th>
                                    <th class="px-4 py-3 border-b">Karyawan</th>
                                    <th class="px-4 py-3 border-b">Pengaju (Pengawas)</th>
                                    <th class="px-4 py-3 border-b text-right">Awal</th>
                                    <th class="px-4 py-3 border-b text-right">Usulan</th>
                                    <th class="px-4 py-3 border-b text-right">Disetujui</th>
                                    <th class="px-4 py-3 border-b">Keterangan</th>
                                    <th class="px-4 py-3 border-b">Status</th>
                                    <th class="px-4 py-3 border-b text-center">Tindakan</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                @foreach ($persetujuanRows as $item)
                                    <tr wire:key="row-persetujuan-{{ $item->id }}-{{ $item->updated_at?->timestamp }}"
                                        class="hover:bg-gray-50/80 dark:hover:bg-gray-800/60 transition-colors">
                                        <td class="px-4 py-3 text-xs text-gray-500 whitespace-nowrap">
                                            {{ $item->created_at->format('d/m/Y H:i') }}
                                        </td>
                                        <td class="px-4 py-3 text-xs font-medium whitespace-nowrap">
                                            {{ $item->tanggal->format('d/m/Y') }}
                                        </td>
                                        <td class="px-4 py-3">
                                            <div class="font-medium text-gray-900 dark:text-gray-100">
                                                {{ $item->pegawai?->nama_pegawai ?? '-' }}</div>
                                            <div class="text-xs text-gray-400 font-mono">{{ $item->kode_pegawai }}
                                            </div>
                                        </td>
                                        <td class="px-4 py-3 text-xs text-gray-600 dark:text-gray-300">
                                            {{ $item->pengaju?->name ?? 'System' }}
                                        </td>
                                        <td class="px-4 py-3 text-right text-gray-500">
                                            Rp {{ number_format($item->nominal_awal, 0, ',', '.') }}
                                        </td>
                                        <td
                                            class="px-4 py-3 text-right font-medium text-amber-600 dark:text-amber-400">
                                            Rp {{ number_format($item->nominal_usulan, 0, ',', '.') }}
                                        </td>
                                        <td
                                            class="px-4 py-3 text-right font-bold text-emerald-600 dark:text-emerald-400">
                                            {{ $item->nominal_final !== null ? 'Rp ' . number_format($item->nominal_final, 0, ',', '.') : '-' }}
                                        </td>
                                        <td class="px-4 py-3 text-xs text-gray-600 dark:text-gray-300 max-w-xs truncate"
                                            title="{{ $item->alasan_pengajuan }}">
                                            {{ $item->alasan_pengajuan }}
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap">
                                            @if ($item->status === 'pending')
                                                <span
                                                    class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400">
                                                    Pending
                                                </span>
                                            @elseif($item->status === 'approved')
                                                <span
                                                    class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-400">
                                                    Approved
                                                </span>
                                            @elseif($item->status === 'rejected')
                                                <span
                                                    class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-rose-100 text-rose-800 dark:bg-rose-900/30 dark:text-rose-400">
                                                    Rejected
                                                </span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-center whitespace-nowrap">
                                            <x-filament::button size="xs"
                                                color="{{ $item->status === 'pending' ? 'primary' : 'gray' }}"
                                                wire:click="bukaReviewModal({{ $item->id }})">
                                                {{ $item->status === 'pending' ? 'Periksa / Proses' : 'Detail & Histori' }}
                                            </x-filament::button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    {{-- ===== MOBILE: KARTU ===== --}}
                    <div class="md:hidden space-y-3">
                        @foreach ($persetujuanRows as $item)
                            <div wire:key="card-persetujuan-{{ $item->id }}-{{ $item->updated_at?->timestamp }}"
                                class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-3 space-y-2">
                                <div class="flex items-start justify-between gap-2">
                                    <div class="min-w-0">
                                        <div class="font-semibold text-gray-900 dark:text-gray-100 break-words">
                                            {{ $item->pegawai?->nama_pegawai ?? '-' }}
                                        </div>
                                        <div class="text-xs font-mono text-gray-400">{{ $item->kode_pegawai }}</div>
                                    </div>
                                    @if ($item->status === 'pending')
                                        <span
                                            class="shrink-0 px-2 py-0.5 rounded-full text-xs font-semibold bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400">Pending</span>
                                    @elseif ($item->status === 'approved')
                                        <span
                                            class="shrink-0 px-2 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-400">Approved</span>
                                    @else
                                        <span
                                            class="shrink-0 px-2 py-0.5 rounded-full text-xs font-semibold bg-rose-100 text-rose-800 dark:bg-rose-900/30 dark:text-rose-400">Rejected</span>
                                    @endif
                                </div>

                                <div class="text-xs text-gray-500">
                                    Tgl potongan: <strong>{{ $item->tanggal->format('d/m/Y') }}</strong>
                                    &middot; Diajukan {{ $item->created_at->format('d/m/Y H:i') }}
                                    &middot; oleh {{ $item->pengaju?->name ?? 'System' }}
                                </div>

                                <div class="grid grid-cols-3 gap-2 text-center text-xs">
                                    <div class="rounded-lg bg-gray-50 dark:bg-gray-900/40 p-2">
                                        <div class="text-gray-400">Awal</div>
                                        <div class="font-medium text-gray-600 dark:text-gray-300">
                                            {{ number_format($item->nominal_awal, 0, ',', '.') }}</div>
                                    </div>
                                    <div class="rounded-lg bg-amber-50 dark:bg-amber-900/10 p-2">
                                        <div class="text-amber-600">Usulan</div>
                                        <div class="font-semibold text-amber-600">
                                            {{ number_format($item->nominal_usulan, 0, ',', '.') }}</div>
                                    </div>
                                    <div class="rounded-lg bg-emerald-50 dark:bg-emerald-900/10 p-2">
                                        <div class="text-emerald-600">Final</div>
                                        <div class="font-bold text-emerald-600">
                                            {{ $item->nominal_final !== null ? number_format($item->nominal_final, 0, ',', '.') : '-' }}
                                        </div>
                                    </div>
                                </div>

                                @if ($item->alasan_pengajuan)
                                    <div class="text-xs text-gray-600 dark:text-gray-300 break-words">
                                        {{ $item->alasan_pengajuan }}</div>
                                @endif

                                <x-filament::button size="sm" class="w-full"
                                    color="{{ $item->status === 'pending' ? 'primary' : 'gray' }}"
                                    wire:click="bukaReviewModal({{ $item->id }})">
                                    {{ $item->status === 'pending' ? 'Periksa / Proses' : 'Detail & Histori' }}
                                </x-filament::button>
                            </div>
                        @endforeach
                    </div>
                @endif
            </x-filament::card>
        </div>
    @endif

    {{-- ========================================================================= --}}
    {{-- MODAL PENGAJUAN (PENGAWAS) --}}
    {{-- ========================================================================= --}}
    <x-filament::modal id="modal-pengajuan-potongan" width="lg">
        <x-slot name="heading">
            Form Pengajuan Perubahan Potongan
        </x-slot>

        <div class="space-y-4 text-sm">
            <div
                class="grid grid-cols-1 sm:grid-cols-2 gap-3 bg-gray-50 dark:bg-gray-900/50 p-3 rounded-xl border border-gray-100 dark:border-gray-700">
                <div>
                    <span class="text-xs text-gray-400 block">Karyawan</span>
                    <span
                        class="font-semibold text-gray-800 dark:text-gray-200 break-words">{{ $modalPegawaiNama }}</span>
                    <span class="text-xs text-gray-400 font-mono block">({{ $modalPegawaiKode }})</span>
                </div>
                <div>
                    <span class="text-xs text-gray-400 block">Tanggal Potongan</span>
                    <span class="font-semibold text-gray-800 dark:text-gray-200">
                        {{ $modalTanggal ? \Carbon\Carbon::parse($modalTanggal)->format('d F Y') : '-' }}
                    </span>
                </div>
                <div>
                    <span class="text-xs text-gray-400 block">Divisi</span>
                    <span class="text-gray-700 dark:text-gray-300 break-words">{{ $modalDivisi }}</span>
                </div>
                <div>
                    <span class="text-xs text-gray-400 block">Potongan Tercatat (Awal)</span>
                    <span class="font-bold text-red-600 dark:text-red-400">Rp
                        {{ number_format($modalPotonganAwal, 0, ',', '.') }}</span>
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">
                    Nominal Potongan yang Diajukan (Rp) <span class="text-red-500">*</span>
                </label>
                <input type="number" step="500" min="0" inputmode="numeric"
                    wire:model="modalNominalUsulan"
                    class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white px-3 py-2 text-base sm:text-sm focus:ring-primary-500 focus:border-primary-500"
                    placeholder="Contoh: 0 untuk anulir penuh, atau nominal baru">
                <span class="text-xs text-gray-400 mt-1 block">Isi 0 jika ingin menganulir potongan secara
                    penuh.</span>
                @error('modalNominalUsulan')
                    <span class="text-xs text-red-600 mt-1 block">{{ $message }}</span>
                @enderror
            </div>

            <div>
                <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">
                    Keterangan / Alasan Pengajuan <span class="text-red-500">*</span>
                </label>
                <textarea rows="3" wire:model="modalAlasan"
                    class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white px-3 py-2 text-base sm:text-sm focus:ring-primary-500 focus:border-primary-500"
                    placeholder="Jelaskan alasan mengapa potongan perlu dianulir atau diubah..."></textarea>
                @error('modalAlasan')
                    <span class="text-xs text-red-600 mt-1 block">{{ $message }}</span>
                @enderror
            </div>
        </div>

        <x-slot name="footer">
            <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end w-full">
                <x-filament::button color="gray" class="w-full sm:w-auto"
                    x-on:click="$dispatch('close-modal', { id: 'modal-pengajuan-potongan' })">
                    Batal
                </x-filament::button>
                <x-filament::button color="primary" class="w-full sm:w-auto" wire:click="simpanPengajuan">
                    Kirim Pengajuan
                </x-filament::button>
            </div>
        </x-slot>
    </x-filament::modal>

    {{-- ========================================================================= --}}
    {{-- MODAL BULK PENGAJUAN (PENGAWAS) --}}
    {{-- ========================================================================= --}}
    <x-filament::modal id="modal-bulk-pengajuan-potongan" width="lg">
        <x-slot name="heading">
            Pengajuan Potongan Massal (Bulk Action)
        </x-slot>
        <x-slot name="description">
            Ajukan nominal usulan dan alasan yang sama sekaligus untuk seluruh karyawan yang dipilih.
        </x-slot>

        <div class="space-y-4 text-sm">
            <div class="bg-primary-50 dark:bg-primary-950/30 border border-primary-200 dark:border-primary-800 p-3 rounded-xl flex items-center gap-3">
                <x-heroicon-o-users class="w-6 h-6 text-primary-600 dark:text-primary-400 shrink-0" />
                <div>
                    <span class="text-xs text-primary-700 dark:text-primary-300 block font-semibold">Total Karyawan Dipilih:</span>
                    <span class="text-base font-bold text-primary-900 dark:text-primary-100">
                        {{ $this->selectedCount }} Orang
                    </span>
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">
                    Nominal Potongan Usulan yang Diajukan (Rp) <span class="text-red-500">*</span>
                </label>
                <input type="number" step="500" min="0" inputmode="numeric"
                    wire:model="bulkNominalUsulan"
                    class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white px-3 py-2 text-base sm:text-sm focus:ring-primary-500 focus:border-primary-500"
                    placeholder="Contoh: 0 untuk anulir penuh, atau nominal seragam">
                <span class="text-xs text-gray-400 mt-1 block">
                    Isi 0 jika ingin menganulir potongan secara penuh. Nominal ini akan diterapkan ke semua karyawan yang dicentang.
                </span>
                @error('bulkNominalUsulan')
                    <span class="text-xs text-red-600 mt-1 block">{{ $message }}</span>
                @enderror
            </div>

            <div>
                <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">
                    Keterangan / Alasan Pengajuan Seragam <span class="text-red-500">*</span>
                </label>
                <textarea rows="3" wire:model="bulkAlasan"
                    class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white px-3 py-2 text-base sm:text-sm focus:ring-primary-500 focus:border-primary-500"
                    placeholder="Jelaskan alasan yang berlaku sama untuk karyawan yang dipilih..."></textarea>
                @error('bulkAlasan')
                    <span class="text-xs text-red-600 mt-1 block">{{ $message }}</span>
                @enderror
            </div>
        </div>

        <x-slot name="footer">
            <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end w-full">
                <x-filament::button color="gray" class="w-full sm:w-auto"
                    x-on:click="$dispatch('close-modal', { id: 'modal-bulk-pengajuan-potongan' })">
                    Batal
                </x-filament::button>
                <x-filament::button color="primary" class="w-full sm:w-auto" wire:click="simpanBulkPengajuan"
                    wire:confirm="Yakin ingin mengirim pengajuan potongan untuk semua karyawan yang dipilih dengan nominal dan alasan ini?">
                    Kirim Pengajuan Massal
                </x-filament::button>
            </div>
        </x-slot>
    </x-filament::modal>

    {{-- ========================================================================= --}}
    {{-- MODAL REVIEW / APPROVAL / REJECT (ADMIN & ABSEN) --}}
    {{-- ========================================================================= --}}
    <x-filament::modal id="modal-review-potongan" width="2xl">
        <x-slot name="heading">
            Pemeriksaan &amp; Persetujuan Pengajuan
        </x-slot>
        <x-slot name="description">
            @if ($selectedPengajuan)
                Pengajuan #{{ $selectedPengajuan->id }} oleh {{ $selectedPengajuan->pengaju?->name ?? 'Pengawas' }}
            @endif
        </x-slot>

        @if ($selectedPengajuan)
            <div class="space-y-4">
                {{-- Detail Perbandingan --}}
                <div
                    class="grid grid-cols-1 sm:grid-cols-3 gap-3 bg-gray-50 dark:bg-gray-900/50 p-3 rounded-xl border border-gray-100 dark:border-gray-700 text-center">
                    <div class="p-2 bg-white dark:bg-gray-800 rounded-lg">
                        <span class="text-xs text-gray-400 block">Potongan Awal</span>
                        <span class="font-bold text-gray-700 dark:text-gray-300">Rp
                            {{ number_format($selectedPengajuan->nominal_awal, 0, ',', '.') }}</span>
                    </div>
                    <div
                        class="p-2 bg-white dark:bg-gray-800 rounded-lg border border-amber-200 dark:border-amber-800">
                        <span class="text-xs text-amber-600 block">Usulan Pengawas</span>
                        <span class="font-bold text-amber-600">Rp
                            {{ number_format($selectedPengajuan->nominal_usulan, 0, ',', '.') }}</span>
                    </div>
                    <div
                        class="p-2 bg-white dark:bg-gray-800 rounded-lg border border-emerald-200 dark:border-emerald-800">
                        <span class="text-xs text-emerald-600 block">Nominal Final</span>
                        <span class="font-bold text-emerald-600">
                            {{ $selectedPengajuan->nominal_final !== null ? 'Rp ' . number_format($selectedPengajuan->nominal_final, 0, ',', '.') : '-' }}
                        </span>
                    </div>
                </div>

                {{-- Informasi Karyawan & Alasan --}}
                <div class="space-y-3 sm:space-y-2 text-sm bg-gray-50 dark:bg-gray-900/30 p-3 rounded-xl">
                    <div class="flex flex-col gap-0.5 sm:flex-row sm:justify-between sm:gap-4">
                        <span class="text-gray-500">Nama Karyawan:</span>
                        <span
                            class="font-medium text-gray-800 dark:text-gray-200 sm:text-right break-words">{{ $selectedPengajuan->pegawai?->nama_pegawai }}
                            ({{ $selectedPengajuan->kode_pegawai }})</span>
                    </div>
                    <div class="flex flex-col gap-0.5 sm:flex-row sm:justify-between sm:gap-4">
                        <span class="text-gray-500">Tanggal Potongan:</span>
                        <span
                            class="font-medium text-gray-800 dark:text-gray-200 sm:text-right">{{ $selectedPengajuan->tanggal->format('d F Y') }}</span>
                    </div>
                    <div class="flex flex-col gap-0.5 sm:flex-row sm:justify-between sm:gap-4">
                        <span class="text-gray-500">Alasan Pengajuan Pengawas:</span>
                        <span
                            class="font-medium text-gray-800 dark:text-gray-200 sm:max-w-sm sm:text-right break-words">{{ $selectedPengajuan->alasan_pengajuan }}</span>
                    </div>
                </div>

                {{-- Riwayat / History Perubahan --}}
                <div>
                    <h4 class="text-xs font-bold uppercase text-gray-500 mb-2">Histori Aktivitas:</h4>
                    <div class="space-y-2 border-l-2 border-gray-200 dark:border-gray-700 pl-3 text-xs">
                        @forelse($selectedPengajuan->histories as $hist)
                            <div class="relative">
                                <div class="font-medium text-gray-800 dark:text-gray-200 break-words">
                                    <span
                                        class="capitalize px-1.5 py-0.5 rounded bg-gray-100 dark:bg-gray-700 mr-1">{{ $hist->aksi }}</span>
                                    oleh {{ $hist->user?->name ?? 'System' }}
                                    <span
                                        class="text-gray-400 font-normal ml-1">{{ $hist->created_at->format('d/m/Y H:i') }}</span>
                                </div>
                                <div class="text-gray-500 mt-0.5 break-words">{{ $hist->catatan }}</div>
                            </div>
                        @empty
                            <div class="text-gray-400">Belum ada catatan histori.</div>
                        @endforelse
                    </div>
                </div>

                {{-- Form Aksi (Hanya jika status Pending) --}}
                @if ($selectedPengajuan->status === 'pending')
                    <div class="border-t pt-3 space-y-3 dark:border-gray-700">
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">
                                Nominal Final yang Disetujui (Dapat Direvisi oleh Admin/Absen) (Rp)
                            </label>
                            <input type="number" step="500" min="0" inputmode="numeric"
                                wire:model="reviewNominalFinal"
                                class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white px-3 py-2 text-base sm:text-sm focus:ring-primary-500">
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">
                                Catatan Persetujuan (Opsional jika ada perubahan nominal)
                            </label>
                            <input type="text" wire:model="reviewCatatan"
                                class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white px-3 py-2 text-base sm:text-sm"
                                placeholder="Contoh: Disesuaikan menjadi Rp20.000 setelah cek target aktual">
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-red-600 dark:text-red-400 mb-1">
                                Alasan Penolakan (Hanya diisi jika ingin Menolak / Reject)
                            </label>
                            <input type="text" wire:model="rejectAlasan"
                                class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white px-3 py-2 text-base sm:text-sm"
                                placeholder="Wajib diisi jika menolak pengajuan">
                        </div>
                    </div>
                @endif
            </div>
        @endif

        <x-slot name="footer">
            <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-between w-full">
                <x-filament::button color="gray" class="w-full sm:w-auto"
                    x-on:click="$dispatch('close-modal', { id: 'modal-review-potongan' })">
                    Tutup
                </x-filament::button>

                @if ($selectedPengajuan && $selectedPengajuan->status === 'pending')
                    <div class="flex flex-col gap-2 sm:flex-row">
                        <x-filament::button color="danger" class="w-full sm:w-auto" wire:click="rejectPengajuan"
                            wire:confirm="Apakah Anda yakin ingin menolak pengajuan ini? Data potongan asli tetap berlaku.">
                            Tolak (Reject)
                        </x-filament::button>
                        <x-filament::button color="success" class="w-full sm:w-auto" wire:click="approvePengajuan"
                            wire:confirm="Setujui nominal potongan ini? Perubahan akan langsung tercatat dan diterapkan pada laporan dan absensi.">
                            Setujui (Approve)
                        </x-filament::button>
                    </div>
                @endif
            </div>
        </x-slot>
    </x-filament::modal>

    {{-- ========================================================================= --}}
    {{-- MODAL BUAT PENGAJUAN BARU OLEH ADMIN / ABSEN --}}
    {{-- ========================================================================= --}}
    @if ($this->canManageApproval())
        <x-filament::modal id="modal-admin-create-potongan" width="lg">
            <x-slot name="heading">
                Buat Pengajuan Potongan Baru (Kantor)
            </x-slot>

            <div class="space-y-4 text-sm">
                <div>
                    <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">
                        Pilih Karyawan <span class="text-red-500">*</span>
                    </label>
                    {{-- .live dipertahankan: perlu memuat "Potongan Tercatat" saat karyawan dipilih --}}
                    <select wire:model.live="adminCreatePegawaiId"
                        class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white px-3 py-2 text-base sm:text-sm focus:ring-primary-500">
                        <option value="">-- Pilih Karyawan --</option>
                        @foreach ($this->pegawaiOptions as $p)
                            <option value="{{ $p->id }}">{{ $p->nama_pegawai }} ({{ $p->kode_pegawai }})
                            </option>
                        @endforeach
                    </select>
                    @error('adminCreatePegawaiId')
                        <span class="text-xs text-red-600 mt-1 block">{{ $message }}</span>
                    @enderror
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">Tanggal
                            Potongan</label>
                        <input type="date" wire:model.live="adminCreateTanggal"
                            class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white px-3 py-2 text-base sm:text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">Potongan
                            Tercatat</label>
                        <div class="py-2 text-sm font-bold text-gray-700 dark:text-gray-300">
                            Rp {{ number_format($adminCreateNominalAwal, 0, ',', '.') }}
                        </div>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">
                        Nominal Potongan Usulan Baru (Rp) <span class="text-red-500">*</span>
                    </label>
                    <input type="number" step="500" min="0" inputmode="numeric"
                        wire:model="adminCreateNominalUsulan"
                        class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white px-3 py-2 text-base sm:text-sm focus:ring-primary-500">
                    @error('adminCreateNominalUsulan')
                        <span class="text-xs text-red-600 mt-1 block">{{ $message }}</span>
                    @enderror
                </div>

                <div>
                    <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">
                        Alasan / Keterangan Pengajuan <span class="text-red-500">*</span>
                    </label>
                    <textarea rows="3" wire:model="adminCreateAlasan"
                        class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white px-3 py-2 text-base sm:text-sm"
                        placeholder="Contoh: Temuan selisih target produksi saat rekap..."></textarea>
                    @error('adminCreateAlasan')
                        <span class="text-xs text-red-600 mt-1 block">{{ $message }}</span>
                    @enderror
                </div>
            </div>

            <x-slot name="footer">
                <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end w-full">
                    <x-filament::button color="gray" class="w-full sm:w-auto"
                        x-on:click="$dispatch('close-modal', { id: 'modal-admin-create-potongan' })">
                        Batal
                    </x-filament::button>
                    <x-filament::button color="primary" class="w-full sm:w-auto" wire:click="simpanAdminPengajuan">
                        Simpan Pengajuan
                    </x-filament::button>
                </div>
            </x-slot>
        </x-filament::modal>
    @endif
</x-filament-panels::page>
