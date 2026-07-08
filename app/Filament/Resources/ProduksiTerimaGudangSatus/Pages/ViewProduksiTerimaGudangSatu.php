<?php

namespace App\Filament\Resources\ProduksiTerimaGudangSatus\Pages;

use App\Filament\Resources\ProduksiTerimaGudangSatus\ProduksiTerimaGudangSatuResource;
use App\Filament\Resources\ProduksiTerimaGudangSatus\Widgets\ProduksiTerimaGudangSatuSummaryWidget;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewProduksiTerimaGudangSatu extends ViewRecord
{
    protected static string $resource = ProduksiTerimaGudangSatuResource::class;

    protected function getHeaderWidgets(): array
    {
        return [
            ProduksiTerimaGudangSatuSummaryWidget::class,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
