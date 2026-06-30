<?php

namespace App\Filament\Resources\ProduksiKedis\Pages;

use App\Filament\Resources\ProduksiKedis\ProduksiKediResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditProduksiKedi extends EditRecord
{
    protected static string $resource = ProduksiKediResource::class;
    
    protected function getHeaderWidgets(): array
    {
        return [
            \App\Filament\Resources\ProduksiKedis\Widgets\ProduksiKediSummaryWidget::class,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
