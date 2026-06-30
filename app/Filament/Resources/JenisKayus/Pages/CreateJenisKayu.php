<?php

namespace App\Filament\Resources\JenisKayus\Pages;

use App\Filament\Resources\JenisKayus\JenisKayuResource;
use Filament\Resources\Pages\CreateRecord;

class CreateJenisKayu extends CreateRecord
{
    protected static string $resource = JenisKayuResource::class;
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
