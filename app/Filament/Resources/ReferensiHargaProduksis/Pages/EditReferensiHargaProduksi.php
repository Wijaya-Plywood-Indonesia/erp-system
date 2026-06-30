<?php

namespace App\Filament\Resources\ReferensiHargaProduksis\Pages;

use App\Filament\Resources\ReferensiHargaProduksis\ReferensiHargaProduksiResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditReferensiHargaProduksi extends EditRecord
{
    protected static string $resource = ReferensiHargaProduksiResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
