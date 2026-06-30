<?php

namespace App\Filament\Resources\IndukAkuns\Pages;

use App\Filament\Resources\IndukAkuns\IndukAkunResource;
use Filament\Resources\Pages\CreateRecord;

class CreateIndukAkun extends CreateRecord
{
    protected static string $resource = IndukAkunResource::class;
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

}
