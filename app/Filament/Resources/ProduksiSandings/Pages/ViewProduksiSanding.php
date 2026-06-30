<?php

namespace App\Filament\Resources\ProduksiSandings\Pages;

use App\Filament\Resources\ProduksiSandings\ProduksiSandingResource;
use App\Filament\Resources\ProduksiSandings\Widgets\ProduksiSandingSummaryWidget;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewProduksiSanding extends ViewRecord
{
    protected static string $resource = ProduksiSandingResource::class;

    protected function getHeaderWidgets(): array
    {
        return [
            ProduksiSandingSummaryWidget::class,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
