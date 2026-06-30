<?php

namespace App\Filament\Resources\Jurnal1sts\Pages;

use App\Filament\Resources\Jurnal1sts\Jurnal1stResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditJurnal1st extends EditRecord
{
    protected static string $resource = Jurnal1stResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
    protected function getRedirectUrl(): string
    {
        // redirect ke halaman list / table (index)
        return $this->getResource()::getUrl('index');
    }
}
