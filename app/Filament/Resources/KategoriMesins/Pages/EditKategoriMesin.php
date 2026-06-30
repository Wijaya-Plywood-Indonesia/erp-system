<?php

namespace App\Filament\Resources\KategoriMesins\Pages;

use App\Filament\Resources\KategoriMesins\KategoriMesinResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditKategoriMesin extends EditRecord
{
    protected static string $resource = KategoriMesinResource::class;

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
