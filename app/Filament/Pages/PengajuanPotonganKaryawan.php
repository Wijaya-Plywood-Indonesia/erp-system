<?php

namespace App\Filament\Pages;

use App\Models\Pegawai;
use App\Models\PengajuanPotongan;
use App\Models\User;
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
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;

class PengajuanPotonganKaryawan extends Page implements HasForms
{
    use InteractsWithForms;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $navigationLabel = 'Pengajuan Potongan';

    protected static ?string $title = 'Pengajuan dan Persetujuan Potongan';

    protected static ?string $slug = 'pengajuan-potongan-karyawan';

    protected static string|\UnitEnum|null $navigationGroup = 'Absen dan Gaji';

    protected string $view = 'filament.pages.pengajuan-potongan-karyawan';

    public function getMaxContentWidth(): Width|string|null
    {
        return Width::Full;
    }

    #[Url(keep: true)]
    public ?string $tanggal = null;

    #[Url(keep: true)]
    public string $activeTab = 'dashboard'; // 'dashboard' or 'persetujuan'

    public ?string $filterStatusPersetujuan = 'all';

    // State untuk Modal Pengajuan
    public bool $showPengajuanModal = false;

    public ?int $modalPegawaiId = null;

    public ?string $modalPegawaiNama = null;

    public ?string $modalPegawaiKode = null;

    public ?string $modalDivisi = null;

    public ?string $modalTanggal = null;

    public float $modalPotonganAwal = 0;

    public ?float $modalNominalUsulan = null;

    public ?float $modalEstimasiGaji = null;

    public string $modalAlasan = '';

    // State untuk Modal Revisi & Approve (Admin/Absen)
    public bool $showReviewModal = false;

    public ?int $selectedPengajuanId = null;

    public ?PengajuanPotongan $selectedPengajuan = null;

    public ?float $reviewNominalFinal = null;

    public ?float $reviewEstimasiGaji = null;

    public string $reviewCatatan = '';

    public string $rejectAlasan = '';

    // State untuk Modal Buat Pengajuan Baru (Khusus Admin/Absen)
    public bool $showAdminCreateModal = false;

    public ?int $adminCreatePegawaiId = null;

    public ?string $adminCreateTanggal = null;

    public float $adminCreateNominalAwal = 0;

    public ?float $adminCreateNominalUsulan = null;

    public ?float $adminCreateEstimasiGaji = null;

    public string $adminCreateAlasan = '';

    public ?string $adminCreateDivisi = null;

    /**
     * Cache opsi dropdown pegawai agar tidak di-query berulang di blade
     */
    public function getPegawaiOptionsProperty(): Collection
    {
        return Pegawai::orderBy('nama_pegawai')->get(['id', 'nama_pegawai', 'kode_pegawai']);
    }

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
    }

    /**
     * Data baris karyawan untuk Dashboard Harian, disaring sesuai hak akses pengawas/admin.
     */
    public function getDashboardRows(): Collection
    {
        $user = auth()->user();
        $tanggal = $this->tanggal ?: now()->toDateString();

        $service = app(DashboardPengawasService::class);
        $dashboardSections = $service->getDataForUser($user, $tanggal);

        // Ambil mapping pengajuan yang sudah ada untuk tanggal ini
        $pengajuans = PengajuanPotongan::query()
            ->with(['pengaju', 'pemroses'])
            ->whereDate('tanggal', $tanggal)
            ->get()
            ->groupBy('kode_pegawai');

        // Periode mingguan Jumat - Kamis
        [$mulaiMinggu, $akhirMinggu] = PeriodeJumatKamisResolver::resolveDefault(Carbon::parse($tanggal));

        $potonganService = app(PotonganGajiService::class);

        // Pre-load potongan mingguan SEKALI (loop 7 hari) bukan per pegawai!
        $weeklyPotonganMaps = [];
        $curr = Carbon::parse($mulaiMinggu);
        $end = Carbon::parse($akhirMinggu);
        while ($curr->lte($end)) {
            $weeklyPotonganMaps[$curr->toDateString()] = $potonganService->getPotonganMap($curr->toDateString());
            $curr->addDay();
        }

        // Kumpulkan semua pegawai dari section yang boleh diakses user
        $rows = collect();
        $allKodepList = [];

        foreach ($dashboardSections as $section) {
            $divisiLabel = $section['label'];
            $absenList = $section['absen'] ?? collect();

            foreach ($absenList as $absen) {
                $kodePegawai = $absen['kode_pegawai'] ?? null;
                if (! $kodePegawai) {
                    continue;
                }

                $allKodepList[] = $kodePegawai;

                // Potongan harian saat ini (sudah memperhitungkan status approved via PotonganGajiService)
                $potonganHarian = (int) ($absen['potongan'] ?? 0);

                // Pengajuan yang ada untuk pegawai ini di tanggal ini
                $existingPengajuan = $pengajuans->get($kodePegawai)?->last();

                $rows->push([
                    'id_pegawai' => $absen['id_pegawai'] ?? null,
                    'kode_pegawai' => $kodePegawai,
                    'nama_pegawai' => $absen['nama_pegawai'] ?? '-',
                    'divisi' => $divisiLabel,
                    'tanggal' => $tanggal,
                    'potongan_harian' => $potonganHarian,
                    'keterangan_potongan' => $absen['keterangan'] ?? ($existingPengajuan?->alasan_pengajuan ?? '-'),
                    'pengajuan' => $existingPengajuan,
                    'shift' => $absen['shift'] ?? '-',
                    'jam_masuk' => $absen['jam_masuk'] ?? '-',
                    'jam_pulang' => $absen['jam_pulang'] ?? '-',
                ]);
            }
        }

        // Pre-fetch model pegawai SEKALI untuk semua kodep (hindari query N+1 di dalam loop)
        $uniqueKodep = array_unique($allKodepList);
        $pegawaiMap = Pegawai::whereIn('kode_pegawai', $uniqueKodep)->get()->keyBy('kode_pegawai');

        $estimasiGajiService = app(PengajuanPotonganService::class);

        // Hapus duplikasi jika pegawai terdaftar di multi-source tapi satukan divisi
        return $rows->groupBy('kode_pegawai')->map(function ($group) use ($potonganService, $weeklyPotonganMaps, $pegawaiMap, $estimasiGajiService) {
            $first = $group->first();
            $kodePegawai = $first['kode_pegawai'];
            $allDivisi = $group->pluck('divisi')->unique()->implode(', ');
            $first['divisi'] = $allDivisi;

            $pegawaiModel = $pegawaiMap->get($kodePegawai);
            if ($pegawaiModel) {
                $first['id_pegawai'] = $pegawaiModel->id;
                if ($first['nama_pegawai'] === '-' || empty($first['nama_pegawai'])) {
                    $first['nama_pegawai'] = $pegawaiModel->nama_pegawai;
                }
            }

            // Hitung akumulasi potongan mingguan dari preloaded maps (O(1) memory lookup)
            $totalMingguan = 0;
            foreach ($weeklyPotonganMaps as $pMap) {
                $totalMingguan += $potonganService->resolvePotongan($pMap, $kodePegawai);
            }
            $first['potongan_mingguan'] = $totalMingguan;

            // Estimasi gaji bersih harian
            $first['estimasi_gaji_diterima'] = $estimasiGajiService->hitungEstimasiGaji($pegawaiModel ?? $first['id_pegawai'], $first['potongan_harian']);

            return $first;
        })->values();
    }

    /**
     * Buka Modal Pengajuan oleh Pengawas (bisa untuk pengajuan baru atau edit yang sedang ditinjau).
     */
    public function bukaPengajuanModal(int|string|null $idPegawai, ?string $nama = null, ?string $kode = null, ?string $divisi = null, int|float|null $potonganAwal = 0): void
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

        // Lookup konsisten pakai id_pegawai (sama seperti PengajuanPotonganService::buatPengajuan)
        $existing = PengajuanPotongan::where('id_pegawai', $this->modalPegawaiId)
            ->whereDate('tanggal', $this->modalTanggal)
            ->where('status', 'pending')
            ->latest('id')
            ->first();

        if ($existing) {
            $this->modalNominalUsulan = (float) $existing->nominal_usulan;
            $this->modalAlasan = $existing->alasan_pengajuan ?? '';
            $this->modalPotonganAwal = (float) $existing->nominal_awal;
        } else {
            $this->modalNominalUsulan = 0; // Usulan default anulir = 0
            $this->modalAlasan = '';
        }

        $this->modalEstimasiGaji = app(PengajuanPotonganService::class)
            ->hitungEstimasiGaji($this->modalPegawaiId, (float) $this->modalNominalUsulan);

        $this->resetValidation();

        $this->dispatch('open-modal', id: 'modal-pengajuan-potongan');
    }

    public function updatedModalNominalUsulan(): void
    {
        $nominal = (float) ($this->modalNominalUsulan ?? 0);
        if ($this->modalPegawaiId) {
            $this->modalEstimasiGaji = app(PengajuanPotonganService::class)
                ->hitungEstimasiGaji($this->modalPegawaiId, $nominal);
        }
    }

    public function simpanPengajuan(): void
    {
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

            Notification::make()
                ->title('Pengajuan Berhasil Disimpan')
                ->body('Pengajuan perubahan potongan telah diteruskan ke Super Admin / Absen untuk ditinjau.')
                ->success()
                ->send();

            $this->dispatch('close-modal', id: 'modal-pengajuan-potongan');
            $this->showPengajuanModal = false;
            $this->modalNominalUsulan = null;
            $this->modalAlasan = '';
            $this->modalEstimasiGaji = null;
            // Action Livewire otomatis me-render ulang komponen, sehingga tabel ikut ter-refresh.
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Gagal Menyimpan Pengajuan')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    /**
     * Tab Persetujuan: Mengambil daftar pengajuan.
     */
    public function getPersetujuanRows(): Collection
    {
        if (! $this->canManageApproval()) {
            return collect();
        }

        $query = PengajuanPotongan::query()
            ->with(['pegawai', 'pengaju', 'pemroses', 'histories.user'])
            ->latest('id');

        if ($this->filterStatusPersetujuan && $this->filterStatusPersetujuan !== 'all') {
            $query->where('status', $this->filterStatusPersetujuan);
        }

        return $query->get();
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
        $this->reviewEstimasiGaji = (float) $record->estimasi_gaji;
        $this->reviewCatatan = '';
        $this->rejectAlasan = '';

        $this->dispatch('open-modal', id: 'modal-review-potongan');
    }

    public function updatedReviewNominalFinal(): void
    {
        $nominal = (float) ($this->reviewNominalFinal ?? 0);
        if ($this->selectedPengajuan) {
            $this->reviewEstimasiGaji = app(PengajuanPotonganService::class)
                ->hitungEstimasiGaji($this->selectedPengajuan->id_pegawai, $nominal);
        }
    }

    /**
     * Proses Approve oleh Admin/Absen.
     */
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

    /**
     * Proses Reject oleh Admin/Absen.
     */
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

    /**
     * Modal Pembuatan Pengajuan Baru oleh Super Admin / Absen.
     */
    public function bukaAdminCreateModal(): void
    {
        if (! $this->canManageApproval()) {
            return;
        }

        $this->adminCreatePegawaiId = null;
        $this->adminCreateTanggal = $this->tanggal ?: now()->toDateString();
        $this->adminCreateNominalAwal = 0;
        $this->adminCreateNominalUsulan = 0;
        $this->adminCreateEstimasiGaji = 0;
        $this->adminCreateAlasan = '';
        $this->adminCreateDivisi = 'Kantor / Pemeriksaan';

        $this->dispatch('open-modal', id: 'modal-admin-create-potongan');
    }

    public function updatedAdminCreatePegawaiId(): void
    {
        if ($this->adminCreatePegawaiId) {
            $pegawai = Pegawai::find($this->adminCreatePegawaiId);
            if ($pegawai) {
                // Cari potongan awal sistem
                $pMap = app(PotonganGajiService::class)->getPotonganMap($this->adminCreateTanggal);
                $this->adminCreateNominalAwal = app(PotonganGajiService::class)->resolvePotongan($pMap, $pegawai->kode_pegawai);
                $this->adminCreateEstimasiGaji = app(PengajuanPotonganService::class)->hitungEstimasiGaji($pegawai, (float) $this->adminCreateNominalUsulan);
            }
        }
    }

    public function updatedAdminCreateNominalUsulan(): void
    {
        if ($this->adminCreatePegawaiId) {
            $this->adminCreateEstimasiGaji = app(PengajuanPotonganService::class)
                ->hitungEstimasiGaji($this->adminCreatePegawaiId, (float) ($this->adminCreateNominalUsulan ?? 0));
        }
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
