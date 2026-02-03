<?php

namespace App\Filament\Resources\Jurnal1sts\Pages;

use App\Filament\Resources\Jurnal1sts\Jurnal1stResource;
use Filament\Resources\Pages\CreateRecord;

class CreateJurnal1st extends CreateRecord
{
    protected static string $resource = Jurnal1stResource::class;
    protected function getRedirectUrl(): string
    {
        // redirect ke halaman list / table (index)
        return $this->getResource()::getUrl('index');
    }
}
