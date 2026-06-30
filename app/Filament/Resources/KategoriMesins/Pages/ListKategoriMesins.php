<?php

namespace App\Filament\Resources\KategoriMesins\Pages;

use App\Filament\Resources\KategoriMesins\KategoriMesinResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListKategoriMesins extends ListRecords
{
    protected static string $resource = KategoriMesinResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
