<?php

namespace App\Providers\Filament;

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
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;


use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

// Reverb and Vite Config
use Filament\Support\Assets\Js;
use Illuminate\Support\Facades\Vite;

class AdminPanelProvider extends PanelProvider
{

    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
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
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->brandName('Wijaya')
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                // AccountWidget::class,
                // FilamentInfoWidget::class,
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
            ])
            ->plugins([
                FilamentShieldPlugin::make()
                    ->navigationGroup(''),
            ])
            ->authMiddleware([
                Authenticate::class,
            ])
            ->sidebarCollapsibleOnDesktop()

            ->navigationGroups([
                //Kategori Menu Produksi

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

                //Laporan 
                NavigationGroup::make('Laporan')
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
