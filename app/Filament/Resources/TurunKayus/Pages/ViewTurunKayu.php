<?php

namespace App\Filament\Resources\TurunKayus\Pages;

use App\Filament\Resources\TurunKayus\Widgets\TurunKayuSummaryWidget;
use App\Filament\Resources\TurunKayus\TurunKayuResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewTurunKayu extends ViewRecord
{
    protected static string $resource = TurunKayuResource::class;

    protected function getHeaderWidgets(): array
    {
        return [
            TurunKayuSummaryWidget::class,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
