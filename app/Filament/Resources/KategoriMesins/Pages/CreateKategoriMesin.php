<?php

namespace App\Filament\Resources\KategoriMesins\Pages;

use App\Filament\Resources\KategoriMesins\KategoriMesinResource;
use Filament\Resources\Pages\CreateRecord;

class CreateKategoriMesin extends CreateRecord
{
    protected static string $resource = KategoriMesinResource::class;
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
