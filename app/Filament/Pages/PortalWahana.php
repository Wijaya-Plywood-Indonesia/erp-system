<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use BackedEnum;

class PortalWahana extends Page
{
    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-squares-2x2';
    protected static ?int $navigationSort = -1; // paling atas
    protected string $view = 'filament.pages.portal-wahana';

    public static function getNavigationLabel(): string
    {
        $brand = \Filament\Facades\Filament::getCurrentPanel()?->getBrandName() ?? 'Wahana';

        return 'Portal ' . $brand;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->hasAnyRole(['super_admin', 'portal admin']) ?? false;
    }

    public static function canAccess(): bool
    {
        return static::shouldRegisterNavigation();
    }

    public function getTitle(): string
    {
        $brand = \Filament\Facades\Filament::getCurrentPanel()?->getBrandName() ?? 'Wahana';

        return 'Portal ' . $brand;
    }

    protected function groups(): array
    {
        return [
            [
                'label' => 'Master Data & Kayu',
                'color' => 'amber',
                'icon' => 'heroicon-o-circle-stack',
                'sections' => [
                    'Kayu' => [
                        \App\Filament\Pages\HargaKayu::class,
                        \App\Filament\Pages\UpdateHargaKayu::class,
                        \App\Filament\Resources\DokumenKayus\DokumenKayuResource::class,
                        \App\Filament\Resources\HargaKayus\HargaKayuResource::class,
                        \App\Filament\Resources\KayuMasuks\KayuMasukResource::class,
                        \App\Filament\Resources\KendaraanSupplierKayus\KendaraanSupplierKayuResource::class,
                        \App\Filament\Resources\NotaKayus\NotaKayuResource::class,
                        \App\Filament\Resources\RiwayatKayus\RiwayatKayuResource::class,
                        \App\Filament\Resources\SupplierKayus\SupplierKayuResource::class,
                        \App\Filament\Resources\TempatKayus\TempatKayuResource::class,
                        \App\Filament\Resources\TurunKayus\TurunKayuResource::class,
                        \App\Filament\Resources\TurusanKayus\TurusanKayuResource::class,
                    ],
                    'Master Data' => [
                        \App\Filament\Resources\BahanPenolongProduksis\BahanPenolongProduksiResource::class,
                        \App\Filament\Resources\BarangUmums\BarangUmumResource::class,
                        \App\Filament\Resources\JenisKayus\JenisKayuResource::class,
                        \App\Filament\Resources\RekapKayuMasuks\RekapKayuMasukResource::class,
                        \App\Filament\Pages\MonitoringKayuMasuk::class,
                        \App\Filament\Resources\JenisBarangs\JenisBarangResource::class,
                        \App\Filament\Resources\KategoriBarangs\KategoriBarangResource::class,
                        \App\Filament\Resources\Ukurans\UkuranResource::class,
                        \App\Filament\Resources\UkuranBarangSetengahJadis\UkuranBarangSetengahJadiResource::class,
                        \App\Filament\Resources\Komposisis\KomposisiResource::class,
                        \App\Filament\Resources\DetailKomposisis\DetailKomposisiResource::class,
                        \App\Filament\Resources\Mesins\MesinResource::class,
                        \App\Filament\Resources\KategoriMesins\KategoriMesinResource::class,
                        \App\Filament\Resources\Grades\GradeResource::class,
                        \App\Filament\Resources\GradeRules\GradeRuleResource::class,
                        \App\Filament\Resources\Criterias\CriteriaResource::class,
                        \App\Filament\Resources\HargaSolasis\HargaSolasiResource::class,
                        \App\Filament\Resources\HargaVeneers\HargaVeneerResource::class,
                        \App\Filament\Resources\Lahans\LahanResource::class,
                        \App\Filament\Resources\Targets\TargetResource::class,
                        \App\Filament\Resources\TotalSolasis\TotalSolasiResource::class,
                    ],
                ],
            ],
            [
                'label' => 'Produksi & Operasional',
                'color' => 'orange',
                'icon' => 'heroicon-o-beaker',
                'sections' => [
                    'Produksi' => [
                        \App\Filament\Resources\ProduksiRotaries\ProduksiRotaryResource::class,
                        \App\Filament\Resources\ProduksiPressDryers\ProduksiPressDryerResource::class,
                        \App\Filament\Resources\ProduksiKedis\ProduksiKediResource::class,
                        \App\Filament\Resources\ProduksiStiks\ProduksiStikResource::class,
                        \App\Filament\Resources\ProduksiRepairs\ProduksiRepairResource::class,
                        \App\Filament\Resources\ProduksiPotSikus\ProduksiPotSikuResource::class,
                        \App\Filament\Resources\ProduksiPotJeleks\ProduksiPotJelekResource::class,
                        \App\Filament\Resources\ProduksiJoints\ProduksiJointResource::class,
                        \App\Filament\Resources\ProduksiHotPresses\ProduksiHotPressResource::class,
                        \App\Filament\Resources\ProduksiGrajiTripleks\ProduksiGrajiTriplekResource::class,
                        \App\Filament\Resources\ProduksiSandings\ProduksiSandingResource::class,
                        \App\Filament\Resources\ProduksiSandingJoints\ProduksiSandingJointResource::class,
                        \App\Filament\Resources\ProduksiPilihPlywoods\ProduksiPilihPlywoodResource::class,
                        \App\Filament\Resources\ProduksiDempuls\ProduksiDempulResource::class,
                        \App\Filament\Resources\ProduksiGuellotines\ProduksiGuellotineResource::class,
                        \App\Filament\Resources\ProduksiGrajiBalkens\ProduksiGrajiBalkenResource::class,
                        \App\Filament\Resources\GrajiStiks\GrajiStikResource::class,
                        \App\Filament\Resources\ProduksiPilihVeneers\ProduksiPilihVeneerResource::class,
                        \App\Filament\Resources\ProduksiNyusups\ProduksiNyusupResource::class,
                        \App\Filament\Resources\ProduksiPotAfJoints\ProduksiPotAfJointResource::class,
                        \App\Filament\Resources\ProduksiTembelTripleks\ProduksiTembelTriplekResource::class,
                        \App\Filament\Resources\ProduksiTerimaGudangSatus\ProduksiTerimaGudangSatuResource::class,
                        \App\Filament\Pages\GradingPage::class,
                    ],
                    'Operasional & Penjualan' => [
                        \App\Filament\Resources\DetailLainLains\DetailLainLainResource::class,
                        \App\Filament\Resources\NotaBarangMasuks\NotaBarangMasukResource::class,
                        \App\Filament\Resources\NotaBarangKeluars\NotaBarangKeluarResource::class,
                        \App\Filament\Resources\Customers\CustomerResource::class,
                        \App\Filament\Resources\PurchaseOrders\PurchaseOrderResource::class,
                        \App\Filament\Resources\PengajuanBarangs\PengajuanBarangResource::class,
                        \App\Filament\Resources\Perusahaans\PerusahaanResource::class,
                    ],
                ],
            ],
            [
                'label' => 'Gudang, Stok & Log',
                'color' => 'blue',
                'icon' => 'heroicon-o-cube',
                'sections' => [
                    'Gudang' => [
                        \App\Filament\Pages\GudangTriplekJadi::class,
                        \App\Filament\Pages\GudangTriplekMth::class,
                        \App\Filament\Pages\GudangVeneerJadi::class,
                        \App\Filament\Pages\GudangVeneerKering::class,
                        \App\Filament\Pages\GudangPlatformJadi::class,
                        \App\Filament\Pages\GudangPlatformMth::class,
                    ],
                    'Stok' => [
                        \App\Filament\Pages\PusatStok::class,
                        \App\Filament\Pages\StokPlywoodSiapJual::class,
                        \App\Filament\Pages\StokKayu::class,
                        \App\Filament\Pages\StokLogCore::class,
                        \App\Filament\Pages\StokTriplekJadi::class,
                        \App\Filament\Pages\StokTriplekMth::class,
                        \App\Filament\Pages\StokVeneerBasah::class,
                        \App\Filament\Pages\StokVeneerJadi::class,
                        \App\Filament\Pages\StokVeneerKering::class,
                        \App\Filament\Resources\StokVeneerKerings\StokVeneerKeringResource::class,
                        \App\Filament\Pages\StokPlatformJadi::class,
                        \App\Filament\Pages\StokPlatformMth::class,
                        \App\Filament\Pages\StokBarangUmum::class,
                        \App\Filament\Pages\StokGudangSatu::class,
                        \App\Filament\Pages\OpnameStokKayu::class,
                        \App\Filament\Pages\OpnameStokPage::class,
                        \App\Filament\Resources\OpnameStoks\OpnameStokResource::class,
                        \App\Filament\Resources\VeneerMasuks\VeneerMasukResource::class,
                        \App\Filament\Resources\VeneerKeluars\VeneerKeluarResource::class,
                    ],
                    'Log' => [
                        \App\Filament\Pages\HppAveragePage::class,
                        \App\Filament\Resources\HppLogHarians\HppLogHarianResource::class,
                        \App\Filament\Pages\HppTriplekJadiPage::class,
                        \App\Filament\Pages\HppTriplekMthPage::class,
                        \App\Filament\Pages\HppVeneerBasahPage::class,
                        \App\Filament\Pages\HppVeneerKeringPage::class,
                        \App\Filament\Pages\HppVeneerJadiPage::class,
                        \App\Filament\Pages\HppPlatformJadiPage::class,
                        \App\Filament\Pages\HppPlatformMthPage::class,
                        \App\Filament\Pages\HppPlywoodSiapJualPage::class,
                        \App\Filament\Pages\LogLogCorePage::class,
                        \App\Filament\Pages\GudangSatuLogPage::class,
                        \App\Filament\Pages\LogHargaKayu::class,
                        \App\Filament\Pages\LogBarangUmum::class,
                        \App\Filament\Pages\DashboardHppDryer::class,
                        \App\Filament\Resources\OngkosProduksiDryers\OngkosProduksiDryerResource::class,
                    ],
                ],
            ],
            [
                'label' => 'Keuangan & Akuntansi',
                'color' => 'emerald',
                'icon' => 'heroicon-o-banknotes',
                'items' => [
                    \App\Filament\Pages\BukuBesar::class,
                    \App\Filament\Pages\NeracaKeuangan::class,
                    \App\Filament\Pages\NeracaAktivaPasifa::class,
                    \App\Filament\Resources\Neracas\NeracaResource::class,
                    \App\Filament\Pages\LabaRugi::class,
                    \App\Filament\Pages\JurnalUmumPage::class,
                    \App\Filament\Resources\JurnalUmums\JurnalUmumResource::class,
                    \App\Filament\Resources\Jurnal1sts\Jurnal1stResource::class,
                    \App\Filament\Resources\Jurnal2s\Jurnal2Resource::class,
                    \App\Filament\Resources\JurnalTigas\JurnalTigaResource::class,
                    \App\Filament\Pages\TreeAkunPage::class,
                    \App\Filament\Resources\IndukAkuns\IndukAkunResource::class,
                    \App\Filament\Resources\AnakAkuns\AnakAkunResource::class,
                    \App\Filament\Resources\SubAnakAkuns\SubAnakAkunResource::class,
                    \App\Filament\Pages\ManageAkunGroup::class,
                    \App\Filament\Resources\AkunGroups\AkunGroupResource::class,
                    \App\Filament\Resources\ReferensiHargaProduksis\ReferensiHargaProduksiResource::class,
                ],
            ],
            [
                'label' => 'Laporan',
                'color' => 'cyan',
                'icon' => 'heroicon-o-document-chart-bar',
                'items' => [
                    \App\Filament\Pages\LaporanHub::class,
                    \App\Filament\Pages\Absen::class,
                    \App\Filament\Pages\LaporanHarian::class,
                    \App\Filament\Pages\LaporanKayuKeluar::class,
                    \App\Filament\Pages\LaporanJurnalKayuMasuk::class,
                    \App\Filament\Pages\PersentaseKayu::class,
                    \App\Filament\Pages\LaporanProduksi::class,
                    \App\Filament\Pages\LaporanPressDryer::class,
                    \App\Filament\Pages\LaporanJoin::class,
                    \App\Filament\Pages\LaporanPotAfalanJoin::class,
                    \App\Filament\Pages\LaporanPotJelek::class,
                    \App\Filament\Pages\LaporanPotSiku::class,
                    \App\Filament\Pages\LaporanRepairs::class,
                    \App\Filament\Pages\LaporanSanding::class,
                    \App\Filament\Pages\LaporanSandingJoin::class,
                    \App\Filament\Pages\LaporanGuellotine::class,
                    \App\Filament\Pages\LaporanProduksiGrajiBalken::class,
                    \App\Filament\Pages\LaporanGrajiTriplek::class,
                    \App\Filament\Pages\LaporanProduksiGrajiTriplek::class,
                    \App\Filament\Pages\LaporanPilihVeneer::class,
                    \App\Filament\Pages\LaporanProduksiPilihPlywood::class,
                    \App\Filament\Pages\LaporanKedi::class,
                    \App\Filament\Pages\LaporanProduksiNyusup::class,
                    \App\Filament\Pages\LaporanStik::class,
                    \App\Filament\Pages\LaporanProduksiDempul::class,
                    \App\Filament\Pages\LaporanProduksiHotPress::class,
                ],
            ],
            [
                'label' => 'Administrasi & SDM',
                'color' => 'pink',
                'icon' => 'heroicon-o-user-group',
                'sections' => [
                    'Kepegawaian' => [
                        \App\Filament\Resources\Pegawais\PegawaiResource::class,
                        \App\Filament\Resources\Absensis\AbsensiResource::class,
                        \App\Filament\Pages\NewAbsensi::class,
                        \App\Filament\Resources\HargaPegawais\HargaPegawaiResource::class,
                        \App\Filament\Pages\OngkosPekerja130::class,
                        \App\Filament\Pages\OngkosPekerja260::class,
                        \App\Filament\Resources\KontrakKerjas\KontrakKerjaResource::class,
                        \App\Filament\Resources\HariLiburs\HariLiburResource::class,
                        \App\Filament\Pages\LeaderBoardSupplier::class,
                    ],
                    'Sistem & Akses' => [
                        \App\Filament\Resources\Users\UserResource::class,
                        \BezhanSalleh\FilamentShield\Resources\Roles\RoleResource::class,
                        \App\Filament\Resources\ActivityLogs\ActivityLogResource::class,
                    ],
                ],
            ],
        ];
    }

    public function getModuleGroups(): array
    {
        return collect($this->groups())
            ->map(function (array $group) {
                if (isset($group['sections'])) {
                    $group['sections'] = collect($group['sections'])
                        ->map(fn ($items) => $this->resolveItems($items))
                        ->filter(fn ($items) => $items->isNotEmpty())
                        ->toArray();

                    $group['count'] = collect($group['sections'])->flatten(1)->count();

                    return $group;
                }

                $resolved = $this->resolveItems($group['items']);
                $group['items'] = $resolved->toArray();
                $group['count'] = $resolved->count();

                return $group;
            })
            ->filter(fn ($group) => ($group['count'] ?? 0) > 0)
            ->values()
            ->toArray();
    }

    protected function resolveItems(array $classes)
    {
        return collect($classes)
            ->filter(fn (string $class) => class_exists($class) && $class::canAccess())
            ->map(function (string $class) {
                $isResource = str_contains($class, '\\Resources\\');
                $short = class_basename($class);
                if ($isResource && str_ends_with($short, 'Resource')) {
                    $short = substr($short, 0, -8);
                }

                return [
                    'label' => method_exists($class, 'getNavigationLabel')
                        ? $class::getNavigationLabel()
                        : $short,
                    'url' => method_exists($class, 'getUrl') ? $class::getUrl() : '#',
                    'class' => $class,
                    'shortClass' => ($isResource ? 'Resource' : 'Page') . ' · ' . $short,
                ];
            });
    }
}