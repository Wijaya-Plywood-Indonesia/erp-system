<?php

namespace App\Filament\Pages;

use App\Models\PengajuanPotongan;
use App\Services\DashboardPengawas\DashboardPengawasService;
use App\Services\Payroll\PeriodeJumatKamisResolver;
use App\Services\Payroll\WeeklyRekapPotonganGajiService;
use BackedEnum;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;

class DashboardPengawas extends Page implements HasForms
{
    use HasPageShield;
    use InteractsWithForms;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-presentation-chart-line';

    protected static ?string $navigationLabel = 'Dashboard Pengawas';

    protected static ?string $title = 'Dashboard Pengawas';

    protected static ?string $slug = 'dashboard-pengawas';

    protected string $view = 'filament.pages.dashboard-pengawas';

    /**
     * Menu & halaman hanya untuk user yang punya akses ke minimal
     * satu divisi (atau role bypass).
     */
    public static function canAccess(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        $cfg = config('dashboard_pengawas', []);

        $bypass = $cfg['bypass_roles'] ?? [];
        if ($bypass !== [] && $user->hasAnyRole($bypass)) {
            return true;
        }

        foreach (collect($cfg['akses'] ?? [])->flatten()->unique() as $permission) {
            if ($user->can($permission)) {
                return true;
            }
        }

        return false;
    }

    public function getMaxContentWidth(): Width|string|null
    {
        return Width::Full;
    }

    #[Url(keep: true)]
    public ?string $tanggal = null;

    public function mount(): void
    {
        $this->tanggal ??= now()->format('Y-m-d');
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

    /**
     * Banner hanya tampil: Jumat pagi + user punya akses halaman
     * Rekap Potongan Gaji (atau paksa_tampil aktif untuk testing).
     */
    protected function bolehTampilPeringatanPotongan(): bool
    {
        $cfg = config('dashboard_pengawas.peringatan_potongan', []);

        if (! ($cfg['aktif'] ?? true)) {
            return false;
        }

        if ($cfg['paksa_tampil'] ?? false) {
            return true;
        }

        // Ikut filter tanggal dashboard: tanggal terpilih harus hari Jumat.
        // Jam "pagi" hanya berlaku kalau tanggal terpilih = hari ini;
        // untuk Jumat di masa lalu banner langsung tampil (berguna untuk cek).
        $acuan = Carbon::parse($this->tanggal ?: now()->toDateString());

        if (! $acuan->isFriday()) {
            return false;
        }

        return ! $acuan->isToday() || now()->hour < (int) ($cfg['jam_akhir'] ?? 12);
    }

    #[Computed]
    public function peringatanPotongan(): ?array
    {
        if (! $this->bolehTampilPeringatanPotongan()) {
            return null;
        }

        $cfg = config('dashboard_pengawas.peringatan_potongan', []);
        $batas = (int) ($cfg['batas'] ?? WeeklyRekapPotonganGajiService::BATAS_WARNING);

        // Tanggal terpilih = Jumat -> siklus Jumat-Kamis sebelumnya yang sudah selesai.
        [$mulai, $akhir] = PeriodeJumatKamisResolver::resolveDefault(
            Carbon::parse($this->tanggal ?: now()->toDateString())
        );

        // Versi data anulir: berubah setiap ada pengajuan yang di-approve/diubah
        // pada periode ini, sehingga cache otomatis tidak dipakai lagi dan
        // banner langsung mengikuti hasil anulir terbaru.
        $versiAnulir = md5((string) PengajuanPotongan::query()
            ->where('status', 'approved')
            ->whereBetween('tanggal', [$mulai, $akhir])
            ->max('updated_at'));

        // Hitungan berat (loop 7 hari x 11 lini) -> cache singkat supaya
        // ganti filter tanggal di dashboard tidak memicu hitung ulang.
        $rekap = Cache::remember(
            "dashboard_pengawas:potongan:{$mulai}:{$akhir}:{$batas}:{$versiAnulir}",
            now()->addMinutes((int) ($cfg['cache_menit'] ?? 10)),
            fn () => app(WeeklyRekapPotonganGajiService::class)
                ->getRekapPerOrang($mulai, $akhir, [], $batas)
                ->all()
        );

        return [
            'mulai' => $mulai,
            'akhir' => $akhir,
            'batas' => $batas,
            'rekap' => $rekap,
        ];
    }

    /**
     * Banner potongan untuk SATU produksi (section) di dashboard.
     * Mengembalikan null kalau banner tidak boleh tampil, produksi itu
     * tidak punya perhitungan potongan, atau tidak ada yang kena potongan.
     */
    public function bannerPotongan(string $label): ?array
    {
        $data = $this->peringatanPotongan;

        if (! $data) {
            return null;
        }

        $peta = config('dashboard_pengawas.peringatan_potongan.divisi_lini', []);
        $lini = $peta[$label] ?? null;

        if (! $lini) {
            return null;
        }

        $rows = collect($data['rekap'])
            ->map(function (array $orang) use ($lini) {
                $d = collect($orang['per_lini'])->firstWhere('lini', $lini);

                return ($d && $d['potongan'] > 0) ? [
                    'kodep' => $orang['kodep'],
                    'nama_pegawai' => $orang['nama_pegawai'],
                    'potongan_lini' => $d['potongan'],
                    'total_potongan' => $orang['total_potongan'],
                    'melebihi_batas' => $orang['melebihi_batas'],
                ] : null;
            })
            ->filter()
            ->sortBy([['melebihi_batas', 'desc'], ['potongan_lini', 'desc']])
            ->values()
            ->all();

        if ($rows === []) {
            return null;
        }

        return [
            'mulai' => $data['mulai'],
            'akhir' => $data['akhir'],
            'batas' => $data['batas'],
            'rows' => $rows,
            'jumlah_melebihi' => collect($rows)->where('melebihi_batas', true)->count(),
        ];
    }

    /**
     * Timpa kolom potongan pada tabel absensi dengan nominal_final dari
     * pengajuan yang sudah APPROVED (anulir), sama seperti di New Absensi.
     * Pending dan Rejected tidak mempengaruhi.
     *
     * @param  iterable<int|string, array>  $sections
     */
    protected function terapkanAnulirPotongan(iterable $sections, string $tanggal): array
    {
        $approved = PengajuanPotongan::query()
            ->whereDate('tanggal', $tanggal)
            ->where('status', 'approved')
            ->whereNotNull('nominal_final')
            ->get(['kode_pegawai', 'nominal_final']);

        $hasil = [];

        if ($approved->isEmpty()) {
            foreach ($sections as $key => $section) {
                $hasil[$key] = $section;
            }

            return $hasil;
        }

        // Peta kode pegawai -> nominal final; sertakan varian tanpa nol di depan
        // ("007" vs "7") seperti PotonganGajiService::resolvePotongan().
        $peta = [];
        foreach ($approved as $row) {
            $kode = (string) $row->kode_pegawai;
            $final = (int) round((float) $row->nominal_final);
            $peta[$kode] = $final;

            $trim = ltrim($kode, '0');
            if ($trim !== '' && ! isset($peta[$trim])) {
                $peta[$trim] = $final;
            }
        }

        foreach ($sections as $key => $section) {
            $absen = collect($section['absen'] ?? [])->map(function ($row) use ($peta) {
                $kode = (string) ($row['kode_pegawai'] ?? '');
                if ($kode === '' || $kode === '-') {
                    return $row;
                }

                $final = $peta[$kode] ?? $peta[ltrim($kode, '0')] ?? null;
                if ($final === null) {
                    return $row;
                }

                $awal = (int) ($row['potongan'] ?? 0);
                $label = $final > 0
                    ? 'Dianulir: Rp'.number_format($final, 0, ',', '.')
                    : 'Dianulir penuh';
                if ($awal > 0 && $awal !== $final) {
                    $label .= ' (awal Rp'.number_format($awal, 0, ',', '.').')';
                }

                $row['potongan'] = $final;
                $row['potongan_awal'] = $awal;
                $row['dianulir'] = true;

                $ket = $row['keterangan'] ?? null;
                $row['keterangan'] = (! empty($ket) && $ket !== '-')
                    ? $label.' | '.$ket
                    : $label;

                return $row;
            });

            $section['absen'] = $absen;
            $hasil[$key] = $section;
        }

        return $hasil;
    }

    protected function getViewData(): array
    {
        $service = app(DashboardPengawasService::class);
        $user = auth()->user();
        $tanggal = $this->tanggal ?: now()->toDateString();

        return [
            'dashboardData' => $this->terapkanAnulirPotongan(
                $service->getDataForUser($user, $tanggal),
                $tanggal
            ),
        ];
    }
}
