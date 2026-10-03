<?php

namespace App\Filament\Pages;

use App\Services\DashboardPengawas\DashboardPengawasService;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Livewire\Attributes\Url;

class DashboardPengawas extends Page implements HasForms
{
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

    protected function getViewData(): array
    {
        $service = app(DashboardPengawasService::class);
        $user = auth()->user();

        return [
            'dashboardData' => $service->getDataForUser($user, $this->tanggal),
        ];
    }
}
