<?php

namespace App\Filament\Resources\PegawaiDryers\Pages;

use App\Filament\Resources\PegawaiDryers\PegawaiDryerResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePegawaiDryer extends CreateRecord
{
    protected static string $resource = PegawaiDryerResource::class;
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
