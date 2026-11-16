<?php

namespace App\Filament\Pages;

use App\Models\Pegawai;
use App\Models\PengajuanPotongan;
use App\Services\DashboardPengawas\DashboardPengawasService;
use App\Services\Payroll\PeriodeJumatKamisResolver;
use App\Services\PengajuanPotonganService;
use App\Services\PotonganGajiService;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\WithPagination;

class PengajuanPotonganKaryawan extends Page implements HasForms
{
    use InteractsWithForms;
    use WithPagination;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $navigationLabel = 'Pengajuan Potongan';

    protected static ?string $title = 'Pengajuan dan Persetujuan Potongan';

    protected static ?string $slug = 'pengajuan-potongan-karyawan';

    protected static string|\UnitEnum|null $navigationGroup = 'Absen dan Gaji';

    protected string $view = 'filament.pages.pengajuan-potongan-karyawan';

    /** TTL cache data dashboard (detik) */
    protected const CACHE_TTL = 60;

    /** Jumlah baris per halaman di tab Persetujuan */
    protected const PERSETUJUAN_PER_PAGE = 50;

    public function getMaxContentWidth(): Width|string|null
    {
        return Width::Full;
    }

    #[Url(keep: true)]
    public ?string $tanggal = null;

    #[Url(keep: true)]
    public ?string $filterDivisi = '';

    #[Url(keep: true)]
    public bool $filterHanyaPotongan = true;

    #[Url(keep: true)]
    public string $activeTab = 'dashboard'; // 'dashboard' or 'persetujuan'

    public ?string $filterStatusPersetujuan = 'all';

    /** Data dashboard dimuat setelah halaman tampil (agar skeleton muncul lebih dulu) */
    public bool $ready = false;

    // State untuk Modal Pengajuan
    public bool $showPengajuanModal = false;

    public ?int $modalPegawaiId = null;

    public ?string $modalPegawaiNama = null;

    public ?string $modalPegawaiKode = null;

    public ?string $modalDivisi = null;

    public ?string $modalTanggal = null;

    public float $modalPotonganAwal = 0;

    public ?float $modalNominalUsulan = null;

    public string $modalAlasan = '';

    // State untuk Bulk Action Pengajuan Pengawas
    /** @var array<string> */
    public array $selectedKodep = [];

    public ?float $bulkNominalUsulan = 0;

    public string $bulkAlasan = '';

    // State untuk Modal Revisi & Approve (Admin/Absen)
    public bool $showReviewModal = false;

    public ?int $selectedPengajuanId = null;

    public ?PengajuanPotongan $selectedPengajuan = null;

    public ?float $reviewNominalFinal = null;

    public string $reviewCatatan = '';

    public string $rejectAlasan = '';

    // State untuk Modal Buat Pengajuan Baru (Khusus Admin/Absen)
    public bool $showAdminCreateModal = false;

    public ?int $adminCreatePegawaiId = null;

    public ?string $adminCreateTanggal = null;

    public float $adminCreateNominalAwal = 0;

    public ?float $adminCreateNominalUsulan = null;

    public string $adminCreateAlasan = '';

    public ?string $adminCreateDivisi = null;

    public static function canAccess(): bool
    {
        $user = auth()->user();
        if (! $user) {
            return false;
        }

        // Bypass roles atau role Absen atau siapapun yang punya akses ke salah satu divisi
        $cfg = config('dashboard_pengawas', []);
        $bypass = array_merge($cfg['bypass_roles'] ?? [], ['Absen', 'absen', 'super_admin', 'Super Admin']);

        if ($user->hasAnyRole($bypass)) {
            return true;
        }

        foreach (collect($cfg['akses'] ?? [])->flatten()->unique() as $permission) {
            if ($user->can($permission)) {
                return true;
            }
        }

        return false;
    }

    public function mount(): void
    {
        $this->tanggal ??= now()->format('Y-m-d');

        // Pastikan pengawas biasa tidak membuka tab persetujuan
        if ($this->activeTab === 'persetujuan' && ! $this->canManageApproval()) {
            $this->activeTab = 'dashboard';
        }
    }

    /**
     * Dipanggil lewat wire:init setelah halaman tampil.
     */
    public function loadData(): void
    {
        $this->ready = true;
    }

    public function updatedTanggal(): void
    {
        $this->selectedKodep = [];
    }

    public function updatedFilterDivisi(): void
    {
        $this->selectedKodep = [];
    }

    public function updatedFilterHanyaPotongan(): void
    {
        $this->selectedKodep = [];
    }

    public function updatedFilterStatusPersetujuan(): void
    {
        $this->resetPage();
    }

    public function form(Schema $schema): Schema
    {
        return $schema->schema([
            DatePicker::make('tanggal')
                ->label('Filter Tanggal')
                ->default(now())
                ->live(),
        ]);
    }

    public function canManageApproval(): bool
    {
        $user = auth()->user();
        if (! $user) {
            return false;
        }

        return $user->hasAnyRole(['super_admin', 'Super Admin', 'super admin -(role&user)', 'Absen', 'absen']);
    }

    public function setTab(string $tab): void
    {
        if ($tab === 'persetujuan' && ! $this->canManageApproval()) {
            Notification::make()->title('Akses ditolak')->danger()->send();

            return;
        }

        $this->activeTab = $tab;
        $this->resetPage();
    }

    // ---------------------------------------------------------------------
    // Computed (di-memoize per request, aman dipanggil berkali-kali di blade)
    // ---------------------------------------------------------------------

    /**
     * Cache opsi dropdown pegawai agar tidak di-query berulang.
     *
     * @return array<int, array{id:int, nama_pegawai:string, kode_pegawai:string}>
     */
    #[Computed]
    public function pegawaiOptions(): array
    {
        return Cache::remember('pengajuan-potongan:pegawai-options', 300, function () {
            return Pegawai::orderBy('nama_pegawai')
                ->get(['id', 'nama_pegawai', 'kode_pegawai'])
                ->map(fn ($p) => [
                    'id' => $p->id,
                    'nama_pegawai' => $p->nama_pegawai,
                    'kode_pegawai' => $p->kode_pegawai,
                ])
                ->all();
        });
    }

    /**
     * Daftar opsi divisi unik untuk dropdown filter (dari data cache yang sama dengan tabel).
     *
     * @return array<string, string>
     */
    #[Computed]
    public function divisiOptions(): array
    {
        if (! $this->ready) {
            return [];
        }

        return $this->getBaseData($this->tanggal ?: now()->toDateString())['divisi'];
    }

    #[Computed]
    public function dashboardRows(): Collection
    {
        if (! $this->ready) {
            return collect();
        }

        return $this->getDashboardRows();
    }

    #[Computed]
    public function selectedCount(): int
    {
        return count($this->selectedKodep);
    }

    // ---------------------------------------------------------------------
    // Data dashboard
    // ---------------------------------------------------------------------

    /**
     * Data dasar (bagian paling berat) di-cache pendek per user & tanggal.
     * Hanya berisi array/skalar, tanpa model Eloquent.
     *
     * @return array{rows: array<int, array<string, mixed>>, divisi: array<string, string>}
     */
    protected function getBaseData(string $tanggal): array
    {
        $user = auth()->user();
        $version = Cache::get("potongan-ver:{$tanggal}", 1);
        $key = "pengajuan-potongan:{$user->id}:{$tanggal}:v{$version}";

        return Cache::remember($key, self::CACHE_TTL, fn () => $this->buildBaseData($user, $tanggal));
    }

    /**
     * @return array{rows: array<int, array<string, mixed>>, divisi: array<string, string>}
     */
    protected function buildBaseData($user, string $tanggal): array
    {
        $service = app(DashboardPengawasService::class);
        $dashboardSections = $service->getDataForUser($user, $tanggal);

        // Periode mingguan Jumat - Kamis
        [$mulaiMinggu, $akhirMinggu] = PeriodeJumatKamisResolver::resolveDefault(Carbon::parse($tanggal));

        $potonganService = app(PotonganGajiService::class);

        // Pre-load potongan mingguan SEKALI (loop 7 hari), bukan per pegawai
        $weeklyPotonganMaps = [];
        $curr = Carbon::parse($mulaiMinggu);
        $end = Carbon::parse($akhirMinggu);
        while ($curr->lte($end)) {
            $weeklyPotonganMaps[$curr->toDateString()] = $potonganService->getPotonganMap($curr->toDateString());
            $curr->addDay();
        }

        $rows = collect();
        $allKodepList = [];
        $divisiOptions = [];

        foreach ($dashboardSections as $section) {
            $divisiLabel = $section['label'] ?? null;
            if ($divisiLabel) {
                $divisiOptions[$divisiLabel] = $divisiLabel;
            }

            $absenList = $section['absen'] ?? collect();

            foreach ($absenList as $absen) {
                $kodePegawai = $absen['kode_pegawai'] ?? null;
                if (! $kodePegawai) {
                    continue;
                }

                $allKodepList[] = $kodePegawai;

                $rows->push([
                    'id_pegawai' => $absen['id_pegawai'] ?? null,
                    'kode_pegawai' => $kodePegawai,
                    'nama_pegawai' => $absen['nama_pegawai'] ?? '-',
                    'divisi' => $divisiLabel ?? '-',
                    'tanggal' => $tanggal,
                    // Potongan harian saat ini (sudah memperhitungkan status approved via PotonganGajiService)
                    'potongan_harian' => (int) ($absen['potongan'] ?? 0),
                    'keterangan_potongan' => $absen['keterangan'] ?? '-',
                    'shift' => $absen['shift'] ?? '-',
                    'jam_masuk' => $absen['jam_masuk'] ?? '-',
                    'jam_pulang' => $absen['jam_pulang'] ?? '-',
                ]);
            }
        }

        // Pre-fetch model pegawai SEKALI (hindari N+1)
        $pegawaiMap = Pegawai::whereIn('kode_pegawai', array_unique($allKodepList))
            ->get(['id', 'kode_pegawai', 'nama_pegawai'])
            ->keyBy('kode_pegawai');

        $mapped = $rows->groupBy('kode_pegawai')->map(function ($group) use ($potonganService, $weeklyPotonganMaps, $pegawaiMap) {
            $first = $group->first();
            $kodePegawai = $first['kode_pegawai'];
            $first['divisi'] = $group->pluck('divisi')->unique()->implode(', ');

            $pegawaiModel = $pegawaiMap->get($kodePegawai);
            if ($pegawaiModel) {
                $first['id_pegawai'] = $pegawaiModel->id;
                if ($first['nama_pegawai'] === '-' || empty($first['nama_pegawai'])) {
                    $first['nama_pegawai'] = $pegawaiModel->nama_pegawai;
                }
            }

            $totalMingguan = 0;
            foreach ($weeklyPotonganMaps as $pMap) {
                $totalMingguan += $potonganService->resolvePotongan($pMap, $kodePegawai);
            }
            $first['potongan_mingguan'] = $totalMingguan;

            return $first;
        })->values();

        return [
            'rows' => $mapped->all(),
            'divisi' => $divisiOptions,
        ];
    }

    /**
     * Data baris karyawan untuk Dashboard Harian (disaring sesuai filter),
     * ditempel data pengajuan yang selalu segar (tidak di-cache).
     */
    public function getDashboardRows(): Collection
    {
        $tanggal = $this->tanggal ?: now()->toDateString();

        $rows = collect($this->getBaseData($tanggal)['rows']);

        // Filter Divisi
        if (! empty($this->filterDivisi)) {
            $needle = strtolower($this->filterDivisi);
            $rows = $rows->filter(fn ($row) => str_contains(strtolower($row['divisi'] ?? ''), $needle));
        }

        // Filter Hanya Potongan (> 0)
        if ($this->filterHanyaPotongan) {
            $rows = $rows->filter(fn ($row) => (float) ($row['potongan_harian'] ?? 0) > 0);
        }

        if ($rows->isEmpty()) {
            return collect();
        }

        // Pengajuan segar (1 query ringan)
        $pengajuans = PengajuanPotongan::query()
            ->whereDate('tanggal', $tanggal)
            ->whereIn('kode_pegawai', $rows->pluck('kode_pegawai')->all())
            ->orderBy('id')
            ->get()
            ->groupBy('kode_pegawai');

        return $rows->map(function ($row) use ($pengajuans) {
            $row['pengajuan'] = $pengajuans->get($row['kode_pegawai'])?->last();

            return $row;
        })->values();
    }

    /**
     * Naikkan versi cache untuk seluruh periode mingguan (Jumat-Kamis) dari tanggal tertentu,
     * karena akumulasi mingguan ikut berubah.
     */
    protected function bustCache(?string $tanggal = null): void
    {
        $tanggal ??= $this->tanggal ?: now()->toDateString();

        [$mulai, $akhir] = PeriodeJumatKamisResolver::resolveDefault(Carbon::parse($tanggal));
        $curr = Carbon::parse($mulai);
        $end = Carbon::parse($akhir);

        while ($curr->lte($end)) {
            $key = 'potongan-ver:'.$curr->toDateString();
            Cache::add($key, 1, now()->addDays(2));
            Cache::increment($key);
            $curr->addDay();
        }

        unset($this->dashboardRows, $this->divisiOptions);
    }

    // ---------------------------------------------------------------------
    // Modal Pengajuan (Pengawas)
    // ---------------------------------------------------------------------

    /**
     * Buka Modal Pengajuan oleh Pengawas (bisa untuk pengajuan baru atau edit yang sedang ditinjau).
     */
    public function bukaPengajuanModal(int|string|null $idPegawai = null, ?string $nama = null, ?string $kode = null, ?string $divisi = null, int|float|null $potonganAwal = 0): void
    {
        $pegawaiModel = null;
        if ($idPegawai) {
            $pegawaiModel = Pegawai::find($idPegawai);
        }
        if (! $pegawaiModel && $kode) {
            $pegawaiModel = Pegawai::where('kode_pegawai', $kode)->first();
        }

        $this->modalPegawaiId = $pegawaiModel?->id ?? (int) $idPegawai;
        $this->modalPegawaiNama = $nama ?? $pegawaiModel?->nama_pegawai ?? '-';
        $this->modalPegawaiKode = $kode ?? $pegawaiModel?->kode_pegawai ?? '-';
        $this->modalDivisi = $divisi ?? '-';
        $this->modalTanggal = $this->tanggal ?: now()->toDateString();
        $this->modalPotonganAwal = (float) ($potonganAwal ?? 0);

        // Jika dipanggil hanya dengan 1 argumen berupa kode pegawai (fallback)
        if (! $idPegawai && $kode === null && is_string($idPegawai)) {
            $kode = $idPegawai;
        }

        // Jika informasi awal belum lengkap, ambil dari data dashboard
        if (! $this->modalPegawaiId && $this->modalPegawaiKode && $this->modalPegawaiKode !== '-') {
            $row = $this->getDashboardRows()->firstWhere('kode_pegawai', $this->modalPegawaiKode);
            if ($row) {
                $this->modalPegawaiId = (int) ($row['id_pegawai'] ?? 0) ?: null;
                $this->modalPegawaiNama = $row['nama_pegawai'] ?? '-';
                $this->modalDivisi = $row['divisi'] ?? '-';
                $this->modalPotonganAwal = (float) ($row['potongan_harian'] ?? 0);
            }
        }

        $existing = null;
        if ($this->modalPegawaiId) {
            $existing = PengajuanPotongan::where('id_pegawai', $this->modalPegawaiId)
                ->whereDate('tanggal', $this->modalTanggal)
                ->where('status', 'pending')
                ->latest('id')
                ->first();
        } elseif ($this->modalPegawaiKode && $this->modalPegawaiKode !== '-') {
            $existing = PengajuanPotongan::where('kode_pegawai', $this->modalPegawaiKode)
                ->whereDate('tanggal', $this->modalTanggal)
                ->where('status', 'pending')
                ->latest('id')
                ->first();
        }

        if ($existing) {
            $this->modalNominalUsulan = (float) $existing->nominal_usulan;
            $this->modalAlasan = $existing->alasan_pengajuan ?? '';
            $this->modalPotonganAwal = (float) $existing->nominal_awal;
        } else {
            $this->modalNominalUsulan = 0; // Usulan default anulir = 0
            $this->modalAlasan = '';
        }

        $this->resetValidation();

        $this->dispatch('open-modal', id: 'modal-pengajuan-potongan');
    }

    public function simpanPengajuan(): void
    {
        $this->modalTanggal = $this->tanggal ?: now()->toDateString();

        // Validasi keberadaan data pegawai
        if (! $this->modalPegawaiId && $this->modalPegawaiKode) {
            $pegawaiModel = Pegawai::where('kode_pegawai', $this->modalPegawaiKode)->first();
            $this->modalPegawaiId = $pegawaiModel?->id;
        }

        if (! $this->modalPegawaiId) {
            Notification::make()->title('Data karyawan tidak ditemukan')->danger()->send();

            return;
        }

        $pending = PengajuanPotongan::where('id_pegawai', $this->modalPegawaiId)
            ->whereDate('tanggal', $this->modalTanggal)
            ->where('status', 'pending')
            ->latest('id')
            ->first();

        if ($pending) {
            $this->modalPotonganAwal = (float) $pending->nominal_awal;
        }

        $this->validate([
            'modalNominalUsulan' => ['required', 'numeric', 'min:0', 'max:'.$this->modalPotonganAwal],
            'modalAlasan' => 'required|string|min:3',
        ], [
            'modalNominalUsulan.required' => 'Nominal usulan potongan wajib diisi.',
            'modalNominalUsulan.min' => 'Nominal potongan tidak boleh bernilai negatif.',
            'modalNominalUsulan.max' => 'Usulan tidak boleh lebih besar dari potongan tercatat.',
            'modalAlasan.required' => 'Keterangan alasan pengajuan wajib diisi.',
            'modalAlasan.min' => 'Alasan pengajuan minimal 3 karakter.',
        ]);

        $pegawai = Pegawai::findOrFail($this->modalPegawaiId);
        $user = auth()->user();

        try {
            app(PengajuanPotonganService::class)->buatPengajuan(
                user: $user,
                pegawai: $pegawai,
                tanggal: $this->modalTanggal,
                nominalAwal: $this->modalPotonganAwal,
                nominalUsulan: (float) $this->modalNominalUsulan,
                alasan: $this->modalAlasan,
                divisi: $this->modalDivisi,
            );

            $this->bustCache($this->modalTanggal);

            Notification::make()
                ->title('Pengajuan Berhasil Disimpan')
                ->body('Pengajuan perubahan potongan telah diteruskan ke Super Admin / Absen untuk ditinjau.')
                ->success()
                ->send();

            $this->dispatch('close-modal', id: 'modal-pengajuan-potongan');
            $this->showPengajuanModal = false;
            $this->modalNominalUsulan = null;
            $this->modalAlasan = '';
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Gagal Menyimpan Pengajuan')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    // ---------------------------------------------------------------------
    // Bulk
    // ---------------------------------------------------------------------

    public function bukaBulkPengajuanModal(): void
    {
        if (empty($this->selectedKodep)) {
            Notification::make()
                ->title('Tidak Ada Karyawan Dipilih')
                ->body('Pilih minimal satu karyawan untuk melakukan bulk action pengajuan potongan.')
                ->warning()
                ->send();

            return;
        }

        $this->bulkNominalUsulan = 0;
        $this->bulkAlasan = '';
        $this->resetValidation(['bulkNominalUsulan', 'bulkAlasan']);

        $this->dispatch('open-modal', id: 'modal-bulk-pengajuan-potongan');
    }

    public function simpanBulkPengajuan(): void
    {
        $this->validate([
            'bulkNominalUsulan' => ['required', 'numeric', 'min:0'],
            'bulkAlasan' => 'required|string|min:3',
        ], [
            'bulkNominalUsulan.required' => 'Nominal usulan potongan wajib diisi.',
            'bulkNominalUsulan.min' => 'Nominal potongan tidak boleh bernilai negatif.',
            'bulkAlasan.required' => 'Keterangan alasan pengajuan wajib diisi.',
            'bulkAlasan.min' => 'Alasan pengajuan minimal 3 karakter.',
        ]);

        $selectedCodes = $this->selectedKodep;

        if (empty($selectedCodes)) {
            Notification::make()
                ->title('Tidak Ada Karyawan Dipilih')
                ->warning()
                ->send();

            return;
        }

        $user = auth()->user();
        $tanggal = $this->tanggal ?: now()->toDateString();
        $rows = $this->dashboardRows->keyBy('kode_pegawai');
        $pegawaiModels = Pegawai::whereIn('kode_pegawai', $selectedCodes)->get()->keyBy('kode_pegawai');

        $service = app(PengajuanPotonganService::class);
        $berhasilCount = 0;
        $errors = [];

        foreach ($selectedCodes as $kode) {
            $row = $rows->get($kode);
            $pegawai = $pegawaiModels->get($kode);

            if (! $pegawai) {
                $errors[] = "Karyawan ({$kode}) tidak ditemukan di master pegawai.";

                continue;
            }

            $potonganAwal = (float) ($row['potongan_harian'] ?? 0);
            $nominalUsulan = (float) $this->bulkNominalUsulan;

            if ($nominalUsulan > $potonganAwal) {
                $errors[] = "{$pegawai->nama_pegawai} ({$kode}): Usulan (Rp ".number_format($nominalUsulan, 0, ',', '.').') melebihi potongan awal (Rp '.number_format($potonganAwal, 0, ',', '.').').';

                continue;
            }

            try {
                $service->buatPengajuan(
                    user: $user,
                    pegawai: $pegawai,
                    tanggal: $tanggal,
                    nominalAwal: $potonganAwal,
                    nominalUsulan: $nominalUsulan,
                    alasan: $this->bulkAlasan,
                    divisi: $row['divisi'] ?? null,
                );
                $berhasilCount++;
            } catch (\Throwable $e) {
                $errors[] = "{$pegawai->nama_pegawai}: ".$e->getMessage();
            }
        }

        if ($berhasilCount > 0) {
            $this->bustCache($tanggal);
        }

        $this->dispatch('close-modal', id: 'modal-bulk-pengajuan-potongan');
        $this->selectedKodep = [];
        $this->bulkNominalUsulan = 0;
        $this->bulkAlasan = '';

        if ($berhasilCount > 0) {
            Notification::make()
                ->title("Pengajuan Berhasil ({$berhasilCount} Karyawan)")
                ->body('Pengajuan potongan massal berhasil diteruskan untuk ditinjau.'.(! empty($errors) ? ' Beberapa karyawan dilewati karena validasi: '.implode(', ', array_slice($errors, 0, 3)) : ''))
                ->success()
                ->send();
        } else {
            Notification::make()
                ->title('Gagal Memproses Pengajuan')
                ->body(! empty($errors) ? implode(', ', array_slice($errors, 0, 3)) : 'Tidak ada data yang berhasil diproses.')
                ->danger()
                ->send();
        }
    }

    // ---------------------------------------------------------------------
    // Tab Persetujuan
    // ---------------------------------------------------------------------

    /**
     * Daftar pengajuan dengan paginasi. Histori TIDAK dimuat di sini (hanya di modal review).
     */
    public function getPersetujuanRows(): LengthAwarePaginator
    {
        $query = PengajuanPotongan::query();

        if (! $this->canManageApproval()) {
            return $query->whereRaw('1 = 0')->paginate(self::PERSETUJUAN_PER_PAGE);
        }

        return $query
            ->with(['pegawai:id,nama_pegawai,kode_pegawai', 'pengaju:id,name'])
            ->when(
                $this->filterStatusPersetujuan && $this->filterStatusPersetujuan !== 'all',
                fn ($q) => $q->where('status', $this->filterStatusPersetujuan)
            )
            // Urutan: pending (belum di-acc) > approved > rejected, lalu data terbaru di atas
            ->orderByRaw("CASE status WHEN 'pending' THEN 1 WHEN 'approved' THEN 2 WHEN 'rejected' THEN 3 ELSE 4 END")
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(self::PERSETUJUAN_PER_PAGE);
    }

    /**
     * Buka Modal Review / Approval untuk Super Admin & Absen.
     */
    public function bukaReviewModal(int $pengajuanId): void
    {
        if (! $this->canManageApproval()) {
            return;
        }

        $record = PengajuanPotongan::with(['pegawai', 'pengaju', 'pemroses', 'histories.user'])->findOrFail($pengajuanId);

        $this->selectedPengajuanId = $record->id;
        $this->selectedPengajuan = $record;
        $this->reviewNominalFinal = (float) $record->nominal_usulan;
        $this->reviewCatatan = '';
        $this->rejectAlasan = '';

        $this->dispatch('open-modal', id: 'modal-review-potongan');
    }

    public function approvePengajuan(): void
    {
        if (! $this->canManageApproval()) {
            return;
        }

        $this->validate([
            'reviewNominalFinal' => 'required|numeric|min:0',
        ]);

        $pengajuan = PengajuanPotongan::findOrFail($this->selectedPengajuanId);
        $user = auth()->user();

        try {
            app(PengajuanPotonganService::class)->approve(
                pengajuan: $pengajuan,
                user: $user,
                nominalFinal: (float) $this->reviewNominalFinal,
                catatan: $this->reviewCatatan ?: 'Disetujui oleh '.$user->name,
            );

            $this->bustCache($pengajuan->tanggal->toDateString());

            Notification::make()
                ->title('Pengajuan Disetujui')
                ->body('Nominal potongan berhasil diperbarui dan diterapkan ke perhitungan absensi & gaji.')
                ->success()
                ->send();

            $this->dispatch('close-modal', id: 'modal-review-potongan');
            $this->showReviewModal = false;
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Proses Persetujuan Gagal')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function rejectPengajuan(): void
    {
        if (! $this->canManageApproval()) {
            return;
        }

        $this->validate([
            'rejectAlasan' => 'required|string|min:3',
        ], [
            'rejectAlasan.required' => 'Wajib mengisi alasan penolakan pengajuan.',
            'rejectAlasan.min' => 'Alasan penolakan minimal 3 karakter.',
        ]);

        $pengajuan = PengajuanPotongan::findOrFail($this->selectedPengajuanId);
        $user = auth()->user();

        try {
            app(PengajuanPotonganService::class)->reject(
                pengajuan: $pengajuan,
                user: $user,
                alasanPenolakan: $this->rejectAlasan,
            );

            $this->bustCache($pengajuan->tanggal->toDateString());

            Notification::make()
                ->title('Pengajuan Ditolak')
                ->body('Pengajuan ditolak. Data potongan asli tetap berlaku.')
                ->warning()
                ->send();

            $this->dispatch('close-modal', id: 'modal-review-potongan');
            $this->showReviewModal = false;
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Proses Penolakan Gagal')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    // ---------------------------------------------------------------------
    // Modal Buat Pengajuan Baru (Super Admin / Absen)
    // ---------------------------------------------------------------------

    public function bukaAdminCreateModal(): void
    {
        if (! $this->canManageApproval()) {
            return;
        }

        $this->adminCreatePegawaiId = null;
        $this->adminCreateTanggal = $this->tanggal ?: now()->toDateString();
        $this->adminCreateNominalAwal = 0;
        $this->adminCreateNominalUsulan = 0;
        $this->adminCreateAlasan = '';
        $this->adminCreateDivisi = 'Kantor / Pemeriksaan';

        $this->dispatch('open-modal', id: 'modal-admin-create-potongan');
    }

    /**
     * Hitung ulang potongan tercatat (awal) berdasarkan pegawai & tanggal terpilih.
     */
    protected function refreshAdminCreateNominalAwal(): void
    {
        if (! $this->adminCreatePegawaiId || ! $this->adminCreateTanggal) {
            $this->adminCreateNominalAwal = 0;

            return;
        }

        $pegawai = Pegawai::find($this->adminCreatePegawaiId);
        if (! $pegawai) {
            $this->adminCreateNominalAwal = 0;

            return;
        }

        $service = app(PotonganGajiService::class);
        $pMap = $service->getPotonganMap($this->adminCreateTanggal);
        $this->adminCreateNominalAwal = (float) $service->resolvePotongan($pMap, $pegawai->kode_pegawai);
    }

    public function updatedAdminCreatePegawaiId(): void
    {
        $this->refreshAdminCreateNominalAwal();
    }

    public function updatedAdminCreateTanggal(): void
    {
        $this->refreshAdminCreateNominalAwal();
    }

    public function simpanAdminPengajuan(): void
    {
        if (! $this->canManageApproval()) {
            return;
        }

        $this->validate([
            'adminCreatePegawaiId' => 'required|exists:pegawais,id',
            'adminCreateNominalUsulan' => 'required|numeric|min:0',
            'adminCreateAlasan' => 'required|string|min:3',
        ], [
            'adminCreatePegawaiId.required' => 'Pilih karyawan terlebih dahulu.',
            'adminCreateNominalUsulan.required' => 'Nominal usulan potongan wajib diisi.',
            'adminCreateAlasan.required' => 'Keterangan pengajuan wajib diisi.',
        ]);

        $pegawai = Pegawai::findOrFail($this->adminCreatePegawaiId);
        $user = auth()->user();

        try {
            app(PengajuanPotonganService::class)->buatPengajuan(
                user: $user,
                pegawai: $pegawai,
                tanggal: $this->adminCreateTanggal,
                nominalAwal: $this->adminCreateNominalAwal,
                nominalUsulan: (float) $this->adminCreateNominalUsulan,
                alasan: $this->adminCreateAlasan,
                divisi: $this->adminCreateDivisi,
            );

            $this->bustCache($this->adminCreateTanggal);

            Notification::make()
                ->title('Pengajuan Berhasil Dibuat')
                ->success()
                ->send();

            $this->dispatch('close-modal', id: 'modal-admin-create-potongan');
            $this->showAdminCreateModal = false;
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Gagal Membuat Pengajuan')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }
}
