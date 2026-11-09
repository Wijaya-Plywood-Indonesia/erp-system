<x-filament-panels::page>
    <style>
        [x-cloak] {
            display: none !important;
        }

        .tab-btn {
            padding: 8px 16px;
            font-weight: 600;
            font-size: 0.875rem;
            border-bottom: 2px solid transparent;
            cursor: pointer;
            transition: all 0.2s;
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
    <div class="border-b border-gray-200 dark:border-gray-700 mb-6 flex items-center justify-between">
        <div class="flex space-x-4">
            <button type="button" wire:click="setTab('dashboard')"
                class="tab-btn {{ $activeTab === 'dashboard' ? 'tab-btn-active' : 'tab-btn-inactive' }}">
                <span class="flex items-center gap-2">
                    <x-heroicon-o-calendar-days class="w-5 h-5" />
                    Tab 1: Dashboard Potongan Harian
                </span>
            </button>

            @if ($this->canManageApproval())
                <button type="button" wire:click="setTab('persetujuan')"
                    class="tab-btn {{ $activeTab === 'persetujuan' ? 'tab-btn-active' : 'tab-btn-inactive' }}">
                    <span class="flex items-center gap-2">
                        <x-heroicon-o-check-badge class="w-5 h-5" />
                        Tab 2: Persetujuan Pengajuan
                    </span>
                </button>
            @endif
        </div>

        @if ($activeTab === 'persetujuan' && $this->canManageApproval())
            <div>
                <x-filament::button size="sm" color="primary" icon="heroicon-o-plus"
                    wire:click="bukaAdminCreateModal">
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
            {{-- Filter Tanggal --}}
            <div
                class="bg-white dark:bg-gray-800 p-4 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm flex flex-wrap items-center justify-between gap-4">
                <div class="flex items-center gap-4">
                    <div class="w-64">
                        {{ $this->form }}
                    </div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">
                        @php
                            [
                                $mulaiMinggu,
                                $akhirMinggu,
                            ] = \App\Services\Payroll\PeriodeJumatKamisResolver::resolveDefault(
                                \Illuminate\Support\Carbon::parse($tanggal),
                            );
                        @endphp
                        <span>Periode Mingguan: <strong>{{ \Carbon\Carbon::parse($mulaiMinggu)->format('d M') }} &ndash;
                                {{ \Carbon\Carbon::parse($akhirMinggu)->format('d M Y') }}</strong> (Jumat &ndash;
                            Kamis)</span>
                    </div>
                </div>
            </div>

            {{-- Tabel Daftar Karyawan --}}
            @php
                $rows = $this->getDashboardRows();
            @endphp

            <x-filament::card>
                <div class="flex items-center justify-between mb-4">
                    <h3 class="font-bold text-gray-900 dark:text-white text-base">
                        Daftar Potongan Karyawan &mdash;
                        {{ \Carbon\Carbon::parse($tanggal)->translatedFormat('l, d F Y') }}
                        <span class="text-sm font-normal text-gray-500">({{ $rows->count() }} orang)</span>
                    </h3>
                </div>

                @if ($rows->isEmpty())
                    <div class="text-center py-12 text-gray-400">
                        <x-heroicon-o-user-group class="w-12 h-12 mx-auto mb-2 text-gray-300" />
                        <p>Tidak ada data absensi/karyawan untuk divisi Anda pada tanggal ini.</p>
                    </div>
                @else
                    <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                        <table class="w-full text-sm text-left border-collapse">
                            <thead
                                class="bg-gray-50 dark:bg-gray-800 text-xs font-semibold uppercase text-gray-600 dark:text-gray-300">
                                <tr>
                                    <th class="px-4 py-3 border-b">Kode</th>
                                    <th class="px-4 py-3 border-b">Nama Karyawan</th>
                                    <th class="px-4 py-3 border-b">Divisi / Bagian</th>
                                    <th class="px-4 py-3 border-b text-right">Potongan Harian</th>
                                    <th class="px-4 py-3 border-b text-right bg-blue-50/50 dark:bg-blue-950/20">
                                        Akumulasi Mingguan</th>
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
                                        // Key includes pengajuan state so Livewire re-renders when status/nominal changes
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
                                    <tr wire:key="{{ $rowKey }}"
                                        class="hover:bg-gray-50/80 dark:hover:bg-gray-800/60 transition-colors">
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
                                        <td class="px-4 py-3">
                                            @if ($pengajuan)
                                                @if ($pengajuan->status === 'pending')
                                                    <span
                                                        class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400">
                                                        Pending (Usul:
                                                        Rp{{ number_format($pengajuan->nominal_usulan, 0, ',', '.') }})
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
                                            @elseif(!$pengajuan || $pengajuan->status !== 'pending')
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
            <div class="flex items-center gap-3">
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

            {{-- Tabel Daftar Seluruh Pengajuan --}}
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
                    <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
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
                class="grid grid-cols-2 gap-3 bg-gray-50 dark:bg-gray-900/50 p-3 rounded-xl border border-gray-100 dark:border-gray-700">
                <div>
                    <span class="text-xs text-gray-400 block">Karyawan</span>
                    <span class="font-semibold text-gray-800 dark:text-gray-200">{{ $modalPegawaiNama }}</span>
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
                    <span class="text-gray-700 dark:text-gray-300">{{ $modalDivisi }}</span>
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
                <input type="number" step="500" min="0" wire:model="modalNominalUsulan"
                    class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white px-3 py-2 text-sm focus:ring-primary-500 focus:border-primary-500"
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
                <textarea rows="3" wire:model.defer="modalAlasan"
                    class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white px-3 py-2 text-sm focus:ring-primary-500 focus:border-primary-500"
                    placeholder="Jelaskan alasan mengapa potongan perlu dianulir atau diubah..."></textarea>
                @error('modalAlasan')
                    <span class="text-xs text-red-600 mt-1 block">{{ $message }}</span>
                @enderror
            </div>
        </div>

        <x-slot name="footer">
            <x-filament::button color="gray"
                x-on:click="$dispatch('close-modal', { id: 'modal-pengajuan-potongan' })">
                Batal
            </x-filament::button>
            <x-filament::button color="primary" wire:click="simpanPengajuan">
                Kirim Pengajuan
            </x-filament::button>
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
                    class="grid grid-cols-3 gap-3 bg-gray-50 dark:bg-gray-900/50 p-3 rounded-xl border border-gray-100 dark:border-gray-700 text-center">
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
                <div class="space-y-2 text-sm bg-gray-50 dark:bg-gray-900/30 p-3 rounded-xl">
                    <div class="flex justify-between">
                        <span class="text-gray-500">Nama Karyawan:</span>
                        <span
                            class="font-medium text-gray-800 dark:text-gray-200">{{ $selectedPengajuan->pegawai?->nama_pegawai }}
                            ({{ $selectedPengajuan->kode_pegawai }})</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500">Tanggal Potongan:</span>
                        <span
                            class="font-medium text-gray-800 dark:text-gray-200">{{ $selectedPengajuan->tanggal->format('d F Y') }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500">Alasan Pengajuan Pengawas:</span>
                        <span
                            class="font-medium text-gray-800 dark:text-gray-200 max-w-sm text-right">{{ $selectedPengajuan->alasan_pengajuan }}</span>
                    </div>
                </div>

                {{-- Riwayat / History Perubahan --}}
                <div>
                    <h4 class="text-xs font-bold uppercase text-gray-500 mb-2">Histori Aktivitas:</h4>
                    <div class="space-y-2 border-l-2 border-gray-200 dark:border-gray-700 pl-3 text-xs">
                        @forelse($selectedPengajuan->histories as $hist)
                            <div class="relative">
                                <div class="font-medium text-gray-800 dark:text-gray-200">
                                    <span
                                        class="capitalize px-1.5 py-0.5 rounded bg-gray-100 dark:bg-gray-700 mr-1">{{ $hist->aksi }}</span>
                                    oleh {{ $hist->user?->name ?? 'System' }}
                                    <span
                                        class="text-gray-400 font-normal ml-1">{{ $hist->created_at->format('d/m/Y H:i') }}</span>
                                </div>
                                <div class="text-gray-500 mt-0.5">{{ $hist->catatan }}</div>
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
                            <input type="number" step="500" min="0"
                                wire:model.defer="reviewNominalFinal"
                                class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white px-3 py-2 text-sm focus:ring-primary-500">
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">
                                Catatan Persetujuan (Opsional jika ada perubahan nominal)
                            </label>
                            <input type="text" wire:model.defer="reviewCatatan"
                                class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white px-3 py-2 text-sm"
                                placeholder="Contoh: Disesuaikan menjadi Rp20.000 setelah cek target aktual">
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-red-600 dark:text-red-400 mb-1">
                                Alasan Penolakan (Hanya diisi jika ingin Menolak / Reject)
                            </label>
                            <input type="text" wire:model.defer="rejectAlasan"
                                class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white px-3 py-2 text-sm"
                                placeholder="Wajib diisi jika menolak pengajuan">
                        </div>
                    </div>
                @endif
            </div>
        @endif

        <x-slot name="footer">
            <x-filament::button color="gray" x-on:click="$dispatch('close-modal', { id: 'modal-review-potongan' })">
                Tutup
            </x-filament::button>

            @if ($selectedPengajuan && $selectedPengajuan->status === 'pending')
                <div class="flex gap-2">
                    <x-filament::button color="danger" wire:click="rejectPengajuan"
                        wire:confirm="Apakah Anda yakin ingin menolak pengajuan ini? Data potongan asli tetap berlaku.">
                        Tolak (Reject)
                    </x-filament::button>
                    <x-filament::button color="success" wire:click="approvePengajuan"
                        wire:confirm="Setujui nominal potongan ini? Perubahan akan langsung tercatat dan diterapkan pada laporan dan absensi.">
                        Setujui (Approve)
                    </x-filament::button>
                </div>
            @endif
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
                    <select wire:model.live="adminCreatePegawaiId"
                        class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white px-3 py-2 text-sm focus:ring-primary-500">
                        <option value="">-- Pilih Karyawan --</option>
                        @foreach ($this->pegawaiOptions as $p)
                            <option value="{{ $p->id }}">{{ $p->nama_pegawai }} ({{ $p->kode_pegawai }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">Tanggal
                            Potongan</label>
                        <input type="date" wire:model.live="adminCreateTanggal"
                            class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white px-3 py-2 text-sm">
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
                    <input type="number" step="500" min="0" wire:model.defer="adminCreateNominalUsulan"
                        class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white px-3 py-2 text-sm focus:ring-primary-500">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">
                        Alasan / Keterangan Pengajuan <span class="text-red-500">*</span>
                    </label>
                    <textarea rows="3" wire:model.defer="adminCreateAlasan"
                        class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white px-3 py-2 text-sm"
                        placeholder="Contoh: Temuan selisih target produksi saat rekap..."></textarea>
                </div>
            </div>

            <x-slot name="footer">
                <x-filament::button color="gray"
                    x-on:click="$dispatch('close-modal', { id: 'modal-admin-create-potongan' })">
                    Batal
                </x-filament::button>
                <x-filament::button color="primary" wire:click="simpanAdminPengajuan">
                    Simpan Pengajuan
                </x-filament::button>
            </x-slot>
        </x-filament::modal>
    @endif
</x-filament-panels::page>
