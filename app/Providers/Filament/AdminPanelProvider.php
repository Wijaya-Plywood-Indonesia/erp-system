<?php

namespace App\Providers\Filament;

use App\Filament\Pages\DashboardHppDryer;
use App\Filament\Pages\LaporanKayuKeluar;
use App\Filament\Pages\OpnameStokKayu;
use App\Filament\Pages\OpnameStokPage;
use App\Filament\Pages\LaporanJurnalKayuMasuk;
use App\Filament\Pages\PortalWahana;
use App\Http\Middleware\RunDailyScheduler;
use App\Http\Middleware\RedirectToPortalForAdmins;
use App\Livewire\AbsenWajibModal;
use App\Livewire\GradingWizard;
use App\Support\Brand;
use Filament\Http\Middleware\Authenticate;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;

use Filament\Navigation\NavigationBuilder;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;


use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;


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
        // ============================================================
        // BRAND (Wijaya / Wahana)
        // Ditentukan di satu tempat: App\Support\Brand + config/brand.php
        // Tes lokal: isi BRAND_OVERRIDE=wijaya di .env
        // ============================================================
        $data = Brand::current();

        $brand = [
            'name'               => $data['name'],
            'logo'               => $data['logo'],
            'background'         => $data['background'],
            'logo_height'        => $data['login_logo_height'],
            'logo_height_mobile' => $data['login_logo_height_mobile'],
            'overlay_start'      => $data['overlay_start'],
            'overlay_end'        => $data['overlay_end'],
            'card_bg'            => $data['card_bg'],
            'card_bg_mobile'     => $data['card_bg_mobile'],
            'card_blur'          => $data['card_blur'],
            'card_saturate'      => $data['card_saturate'],
            'bg_position_mobile' => $data['bg_position_mobile'],
        ];

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
            ->brandName($brand['name'])
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
            ->renderHook(
                PanelsRenderHook::BODY_END,
                fn (): string => auth()->check()
                    ? Blade::render('@livewire(\'absen-wajib-modal\')')
                    : ''
            )

            // ============================================================
            // LOGO (dirender di form, lalu dipindah ke atas "Sign in" oleh JS di bawah)
            // ============================================================
            ->renderHook(
                PanelsRenderHook::AUTH_LOGIN_FORM_BEFORE,
                fn (): string => '<div class="login-brand-logo">
                    <img src="' . asset($brand['logo']) . '" alt="Logo ' . e($brand['name']) . '">
                </div>'
            )
            ->renderHook(
                PanelsRenderHook::AUTH_REGISTER_FORM_BEFORE,
                fn (): string => '<div class="login-brand-logo">
                    <img src="' . asset($brand['logo']) . '" alt="Logo ' . e($brand['name']) . '">
                </div>'
            )

            // ============================================================
            // JS: pindahkan logo ke atas "Sign in", hapus latar putih logo,
            // lalu potong ruang kosong di sekelilingnya (auto-crop)
            // ============================================================
            ->renderHook(
                PanelsRenderHook::SCRIPTS_AFTER,
                fn (): string => '<script>
                    (function () {
                        function processLogo(img) {
                            if (!img || img.dataset.done) return;
                            img.dataset.done = "1";

                            function run() {
                                try {
                                    var scale = Math.min(1, 800 / img.naturalWidth);
                                    var w = Math.round(img.naturalWidth * scale);
                                    var h = Math.round(img.naturalHeight * scale);
                                    var c = document.createElement("canvas");
                                    c.width = w;
                                    c.height = h;
                                    var ctx = c.getContext("2d");
                                    ctx.drawImage(img, 0, 0, w, h);
                                    var data = ctx.getImageData(0, 0, w, h);
                                    var p = data.data;

                                    var minX = w, minY = h, maxX = 0, maxY = 0;
                                    for (var y = 0; y < h; y++) {
                                        for (var x = 0; x < w; x++) {
                                            var i = (y * w + x) * 4;
                                            var lum = p[i] * 0.299 + p[i + 1] * 0.587 + p[i + 2] * 0.114;
                                            var a = Math.max(0, Math.min(255, (255 - lum) * 1.6));
                                            a = a * (p[i + 3] / 255);
                                            p[i] = 255;
                                            p[i + 1] = 255;
                                            p[i + 2] = 255;
                                            p[i + 3] = a;
                                            if (a > 40) {
                                                if (x < minX) minX = x;
                                                if (x > maxX) maxX = x;
                                                if (y < minY) minY = y;
                                                if (y > maxY) maxY = y;
                                            }
                                        }
                                    }
                                    ctx.putImageData(data, 0, 0);

                                    if (maxX > minX && maxY > minY) {
                                        var pad = 6;
                                        minX = Math.max(0, minX - pad);
                                        minY = Math.max(0, minY - pad);
                                        maxX = Math.min(w - 1, maxX + pad);
                                        maxY = Math.min(h - 1, maxY + pad);
                                        var cw = maxX - minX + 1;
                                        var ch = maxY - minY + 1;
                                        var c2 = document.createElement("canvas");
                                        c2.width = cw;
                                        c2.height = ch;
                                        c2.getContext("2d").drawImage(c, minX, minY, cw, ch, 0, 0, cw, ch);
                                        img.src = c2.toDataURL("image/png");
                                    } else {
                                        img.src = c.toDataURL("image/png");
                                    }
                                } catch (e) {
                                    console.warn("Logo tidak bisa diproses", e);
                                }
                            }

                            if (img.complete && img.naturalWidth) {
                                run();
                            } else {
                                img.addEventListener("load", run, { once: true });
                            }
                        }

                        function moveLogo() {
                            var logo = document.querySelector(".login-brand-logo");
                            if (!logo) return;

                            var heading = document.querySelector(".fi-simple-main h1, .fi-simple-header-heading");
                            if (!heading) return;

                            var target = heading.closest(".fi-simple-header") || heading.parentElement;
                            if (target && target.parentNode && logo.nextElementSibling !== target) {
                                target.parentNode.insertBefore(logo, target);
                            }
                            processLogo(logo.querySelector("img"));
                        }

                        if (document.readyState === "loading") {
                            document.addEventListener("DOMContentLoaded", moveLogo);
                        } else {
                            moveLogo();
                        }
                        document.addEventListener("livewire:navigated", moveLogo);
                    })();
                </script>'
            )

            // ============================================================
            // STYLE HALAMAN LOGIN (background foto + card glassmorphism)
            // Nilai overlay / card / posisi diambil dari config/brand.php
            // ============================================================
            ->renderHook(
                PanelsRenderHook::STYLES_AFTER,
                fn (): string => '<style>
                    /* ===== BACKGROUND ===== */
                    .fi-simple-layout {
                        background-color: #0f172a !important;
                        background-image:
                            linear-gradient(135deg, rgba(15,23,42,' . $brand['overlay_start'] . ') 0%, rgba(15,23,42,' . $brand['overlay_end'] . ') 100%),
                            url("' . asset($brand['background']) . '") !important;
                        background-size: cover !important;
                        background-position: center !important;
                        background-repeat: no-repeat !important;
                        background-attachment: fixed !important;
                        min-height: 100vh;
                    }

                    /* ===== CARD GLASS ===== */
                    .fi-simple-main {
                        background: ' . $brand['card_bg'] . ' !important;
                        -webkit-backdrop-filter: blur(' . $brand['card_blur'] . ') saturate(' . $brand['card_saturate'] . ');
                        backdrop-filter: blur(' . $brand['card_blur'] . ') saturate(' . $brand['card_saturate'] . ');
                        border: 1px solid rgba(255, 255, 255, 0.18) !important;
                        border-radius: 1.75rem !important;
                        box-shadow:
                            0 30px 60px -15px rgba(0, 0, 0, 0.6),
                            inset 0 1px 0 rgba(255, 255, 255, 0.20) !important;
                        --tw-ring-shadow: 0 0 #0000 !important;
                        --tw-ring-color: transparent !important;
                        padding-top: 2.5rem !important;
                        padding-bottom: 2.5rem !important;
                    }

                    /* ===== LOGO BRAND (latar dihapus & dipotong oleh JS) ===== */
                    .login-brand-logo {
                        display: flex;
                        justify-content: center;
                        margin: 0 0 1rem 0;
                    }
                    .login-brand-logo img {
                        display: block;
                        height: ' . $brand['logo_height'] . ';
                        width: auto;
                        max-width: 85%;
                        object-fit: contain;
                        background: transparent;
                        padding: 0;
                        border-radius: 0;
                        box-shadow: none;
                        filter: drop-shadow(0 4px 12px rgba(0, 0, 0, 0.45));
                    }

                    /* Jarak header "Sign in" */
                    .fi-simple-main .fi-simple-header {
                        margin-top: 0 !important;
                        padding-top: 0 !important;
                        margin-bottom: 1.5rem !important;
                    }
                    .fi-simple-main .fi-simple-header-heading {
                        margin-top: 0 !important;
                    }

                    /* Sembunyikan teks brand bawaan (sudah diganti logo) */
                    .fi-simple-main .fi-logo {
                        display: none !important;
                    }

                    /* ===== TEKS DI DALAM CARD ===== */
                    .fi-simple-main .fi-simple-header-heading,
                    .fi-simple-main h1,
                    .fi-simple-main label,
                    .fi-simple-main .fi-fo-field-wrp-label,
                    .fi-simple-main .fi-fo-field-wrp-label span,
                    .fi-simple-main .fi-fo-checkbox-list-option-label,
                    .fi-simple-main .fi-checkbox-input + span,
                    .fi-simple-main label span {
                        color: #ffffff !important;
                    }

                    .fi-simple-main .fi-simple-header-subheading,
                    .fi-simple-main .fi-simple-header-subheading span {
                        color: rgba(255, 255, 255, 0.75) !important;
                    }

                    .fi-simple-main a {
                        color: #fbbf24 !important;
                    }
                    .fi-simple-main a:hover {
                        color: #fde68a !important;
                    }

                    /* ===== INPUT ===== */
                    .fi-simple-main .fi-input-wrp {
                        background-color: rgba(255, 255, 255, 0.12) !important;
                        border-radius: 0.75rem !important;
                        --tw-ring-color: rgba(255, 255, 255, 0.25) !important;
                        box-shadow: none !important;
                        transition: all 0.2s ease;
                    }
                    .fi-simple-main .fi-input-wrp:focus-within {
                        background-color: rgba(255, 255, 255, 0.20) !important;
                        --tw-ring-color: #fbbf24 !important;
                        box-shadow: 0 0 0 3px rgba(251, 191, 36, 0.25) !important;
                    }
                    .fi-simple-main input.fi-input {
                        color: #ffffff !important;
                        -webkit-text-fill-color: #ffffff !important;
                    }
                    .fi-simple-main input.fi-input::placeholder {
                        color: rgba(255, 255, 255, 0.5) !important;
                    }
                    .fi-simple-main input:-webkit-autofill {
                        -webkit-text-fill-color: #ffffff !important;
                        transition: background-color 9999s ease-in-out 0s;
                    }

                    /* Ikon mata (show password) */
                    .fi-simple-main .fi-input-wrp button,
                    .fi-simple-main .fi-input-wrp svg {
                        color: rgba(255, 255, 255, 0.8) !important;
                    }

                    /* ===== CHECKBOX ===== */
                    .fi-simple-main input[type="checkbox"] {
                        background-color: rgba(255, 255, 255, 0.15) !important;
                        border-color: rgba(255, 255, 255, 0.4) !important;
                    }

                    /* ===== TOMBOL SIGN IN ===== */
                    .fi-simple-main .fi-btn {
                        border-radius: 0.75rem !important;
                        font-weight: 600;
                        box-shadow: 0 10px 25px -8px rgba(245, 158, 11, 0.6);
                        transition: transform 0.15s ease, box-shadow 0.15s ease;
                    }
                    .fi-simple-main .fi-btn:hover {
                        transform: translateY(-2px);
                        box-shadow: 0 14px 30px -8px rgba(245, 158, 11, 0.8);
                    }

                    /* ===== PESAN ERROR ===== */
                    .fi-simple-main .fi-fo-field-wrp-error-message {
                        color: #fca5a5 !important;
                    }

                    /* ============================================================
                       MOBILE / HP (layar <= 640px)
                       ============================================================ */
                    @media (max-width: 640px) {
                        /* Tinggi layar yang benar di browser HP (address bar) */
                        .fi-simple-layout {
                            min-height: 100vh;
                            min-height: 100dvh;
                            background-attachment: scroll !important;
                            /* bagian foto yang tampil di HP (diatur per brand) */
                            background-position: ' . $brand['bg_position_mobile'] . ' !important;
                            background-image:
                                linear-gradient(180deg, rgba(15,23,42,' . $brand['overlay_start'] . ') 0%, rgba(15,23,42,' . $brand['overlay_end'] . ') 100%),
                                url("' . asset($brand['background']) . '") !important;
                        }

                        /* Wadah card: beri ruang kiri-kanan */
                        .fi-simple-main-ctn {
                            width: 100%;
                            padding-left: 1rem !important;
                            padding-right: 1rem !important;
                        }

                        /* Card tidak lagi menempel ke tepi layar */
                        .fi-simple-main {
                            width: 100% !important;
                            max-width: 26rem !important;
                            margin: 1.5rem auto !important;
                            padding: 2rem 1.5rem !important;
                            border-radius: 1.5rem !important;
                            background: ' . $brand['card_bg_mobile'] . ' !important;
                        }

                        .login-brand-logo {
                            margin-bottom: 0.75rem;
                        }
                        .login-brand-logo img {
                            height: ' . $brand['logo_height_mobile'] . ';
                            max-width: 70%;
                        }

                        .fi-simple-main .fi-simple-header {
                            margin-bottom: 1.25rem !important;
                        }
                        .fi-simple-main .fi-simple-header-heading {
                            font-size: 1.5rem !important;
                            line-height: 2rem !important;
                        }

                        /* Font 16px agar iOS tidak auto-zoom saat input difokus */
                        .fi-simple-main input.fi-input {
                            font-size: 16px !important;
                            padding-top: 0.7rem !important;
                            padding-bottom: 0.7rem !important;
                        }

                        /* Tombol lebih besar & mudah ditekan */
                        .fi-simple-main .fi-btn {
                            width: 100%;
                            padding-top: 0.75rem !important;
                            padding-bottom: 0.75rem !important;
                        }
                        .fi-simple-main .fi-btn:hover {
                            transform: none;
                        }
                    }
                </style>'
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