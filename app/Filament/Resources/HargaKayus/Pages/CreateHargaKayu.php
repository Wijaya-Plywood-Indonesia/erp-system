<?php

namespace App\Filament\Resources\HargaKayus\Pages;

use App\Filament\Resources\HargaKayus\HargaKayuResource;
use Filament\Resources\Pages\CreateRecord;

class CreateHargaKayu extends CreateRecord
{
    protected static string $resource = HargaKayuResource::class;
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
