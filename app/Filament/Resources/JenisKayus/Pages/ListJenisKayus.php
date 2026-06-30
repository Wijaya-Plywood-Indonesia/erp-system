<?php

namespace App\Filament\Resources\JenisKayus\Pages;

use App\Filament\Resources\JenisKayus\JenisKayuResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListJenisKayus extends ListRecords
{
    protected static string $resource = JenisKayuResource::class;

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
