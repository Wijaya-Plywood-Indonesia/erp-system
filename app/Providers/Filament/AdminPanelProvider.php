<?php

namespace App\Providers\Filament;

use App\Filament\Pages\DashboardHppDryer;
use App\Filament\Pages\LaporanKayuKeluar;
use App\Filament\Pages\OpnameStokKayu;
use App\Filament\Pages\OpnameStokPage;
use App\Filament\Pages\LaporanJurnalKayuMasuk;
use App\Filament\Pages\PortalWahana;
use App\Filament\Resources\ProduksiDempuls\Widgets\RekapProduksiDempul;
use App\Filament\Resources\ProduksiGrajiTripleks\Widgets\RekapProduksiGraji;
use App\Filament\Resources\ProduksiGuellotines\Widgets\RekapProduksiGuellotine;
use App\Filament\Resources\ProduksiHotPresses\Widgets\RekapProduksiHotPress;
use App\Filament\Resources\ProduksiJoints\Widgets\RekapProduksiJoint;
use App\Filament\Resources\ProduksiKedis\Widgets\RekapProduksiKedi;
use App\Filament\Resources\ProduksiNyusups\Widgets\RekapProduksiNyusup;
use App\Filament\Resources\ProduksiPilihVeneers\Widgets\RekapProduksiPilihVeneer;
use App\Filament\Resources\ProduksiPotAfJoints\Widgets\RekapProduksiPotAfJoint;
use App\Filament\Resources\ProduksiPotJeleks\Widgets\RekapProduksiPotJelek;
use App\Filament\Resources\ProduksiPotSikus\Widgets\RekapProduksiPotSiku;
use App\Filament\Resources\ProduksiPressDryers\Widgets\RekapProduksiDryer;
use App\Filament\Resources\ProduksiRepairs\Widgets\RekapProduksiRepair;
use App\Filament\Resources\ProduksiRotaries\Widgets\RekapProduksiRotary;
use App\Filament\Resources\ProduksiSandingJoints\Widgets\RekapProduksiSandingJoint;
use App\Filament\Resources\ProduksiSandings\Widgets\RekapProduksiSanding;
use App\Filament\Resources\ProduksiStiks\Widgets\RekapProduksiStik;
use App\Filament\Resources\ProduksiTembelTripleks\Widgets\RekapProduksiTembelTriplek;
use App\Http\Middleware\RunDailyScheduler;
use App\Http\Middleware\RedirectToPortalForAdmins;
use App\Livewire\AbsenWajibModal;
use App\Livewire\GradingWizard;
use Filament\Http\Middleware\Authenticate;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;

use Filament\Navigation\NavigationGroup;


use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;


use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Illuminate\Support\Facades\Blade;

// Reverb and Vite Config
use Filament\Support\Assets\Js;
use Illuminate\Support\Facades\Vite;

class AdminPanelProvider extends PanelProvider
{

    public function panel(Panel $panel): Panel
    {
        $currentHost = request()->getHost();
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->registration()
            ->globalSearch(false)
            ->viteTheme('resources/css/app.css')
            ->assets([
                // Gunakan Vite::asset agar Filament tahu file mana yang harus dimuat
                Js::make('app-js', Vite::asset('resources/js/app.js'))->module(),
            ])
            ->colors([
                'primary' => Color::Amber,
                'kuninng-loh' => '#ffff00',

            ])
            ->databaseNotifications()
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
                DashboardHppDryer::class,
                OpnameStokKayu::class,
                LaporanKayuKeluar::class,
                LaporanJurnalKayuMasuk::class,
                OpnameStokPage::class,
                PortalWahana::class,
            ])
            ->brandName(
                in_array($currentHost, ['kayu.wijayaplywoods.com', 'prarelease.wijayaplywoods.com'])
                    ? 'Wijaya'
                    : 'Wahana'
            )
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                // AccountWidget::class,
                // FilamentInfoWidget::class,
                RekapProduksiNyusup::class,
                RekapProduksiGraji::class,
                RekapProduksiDempul::class,
                RekapProduksiSanding::class,
                RekapProduksiTembelTriplek::class,
                RekapProduksiPilihVeneer::class,
                RekapProduksiGuellotine::class,
                RekapProduksiHotPress::class,
                RekapProduksiSandingJoint::class,
                RekapProduksiPotAfJoint::class,
                RekapProduksiJoint::class,
                RekapProduksiRepair::class,
                RekapProduksiStik::class,
                RekapProduksiKedi::class,
                RekapProduksiDryer::class,
                RekapProduksiRotary::class,
                RekapProduksiPotSiku::class,
                RekapProduksiPotJelek::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
                RunDailyScheduler::class,
                RedirectToPortalForAdmins::class,
            ])
            ->plugins([
                FilamentShieldPlugin::make()
                    ->navigationGroup(''),
            ])
            ->authMiddleware([
                Authenticate::class,
            ])
            ->sidebarCollapsibleOnDesktop()

            ->livewireComponents([
                GradingWizard::class,
                AbsenWajibModal::class,
            ])

            // Modal wajib absen dirender di setiap halaman panel (setelah login).
            // Hanya tampil jika auth()->user() punya id_pegawai dan belum absen hari ini
            // (logic pengecekan ada di dalam komponen AbsenWajibModal itu sendiri).
            ->renderHook(
                PanelsRenderHook::BODY_END,
                fn(): string => auth()->check()
                    ? Blade::render('@livewire(\'absen-wajib-modal\')')
                    : ''
            )

            ->navigationGroups([
                NavigationGroup::make('Absen dan Gaji')
                    ->icon('heroicon-o-clipboard-document-list')
                    ->collapsed(),

                //Kategori Menu Produksi
                NavigationGroup::make('Gudang')
                    ->icon('heroicon-o-building-storefront')
                    ->collapsed(),

                NavigationGroup::make('Kontrak')
                    ->icon('heroicon-o-clipboard-document-check')->collapsed(),

                NavigationGroup::make('Pengajuan')
                    ->icon('heroicon-o-document-text')
                    ->collapsed(),

                NavigationGroup::make('Opname')
                    ->icon('heroicon-o-clipboard-document-check')->collapsed(),

                NavigationGroup::make('Log')
                    ->icon('heroicon-o-cog')
                    ->collapsed(),

                NavigationGroup::make('Grade')
                    ->icon('heroicon-o-check-badge')->collapsed(),

                NavigationGroup::make('BK-BM')
                    ->icon('heroicon-o-adjustments-horizontal')
                    ->collapsed(),

                NavigationGroup::make('Kayu')
                    ->icon('heroicon-o-circle-stack')
                    ->collapsed(),

                NavigationGroup::make('Rotary')
                    ->icon('heroicon-o-cog')
                    ->collapsed(),

                NavigationGroup::make('Dryer')
                    ->icon('heroicon-o-fire')->collapsed(),

                NavigationGroup::make('Repair')
                    ->icon('heroicon-o-pencil')->collapsed(),

                NavigationGroup::make('Hot Press')
                    ->icon('heroicon-o-cpu-chip')
                    ->collapsed(),
                NavigationGroup::make('Finishing')
                    ->icon('heroicon-o-check-badge')
                    ->collapsed(),
                NavigationGroup::make('Lain Lain')
                    ->icon('heroicon-o-ellipsis-horizontal-circle')->collapsed(),


                NavigationGroup::make('Laporan')
                    ->icon('heroicon-o-clipboard-document-list')
                    ->collapsed(),

                NavigationGroup::make('Ongkos')
                    ->icon('heroicon-o-clipboard-document-list')
                    ->collapsed(),
                // Kategori Per Master-an
                NavigationGroup::make('Master')
                    ->icon('heroicon-o-swatch')->collapsed(),

                NavigationGroup::make('Jurnal')
                    ->icon('heroicon-o-book-open')
                    ->collapsed(),

                NavigationGroup::make('Logs')
                    ->icon('heroicon-o-finger-print')
                    ->collapsed(),

                NavigationGroup::make('Master Akun')
                    ->icon('heroicon-o-inbox-stack')->collapsed(),

                NavigationGroup::make('Akses Pengguna')
                    ->icon('heroicon-o-lock-closed')->collapsed(),

            ])
        ;
    }
}
