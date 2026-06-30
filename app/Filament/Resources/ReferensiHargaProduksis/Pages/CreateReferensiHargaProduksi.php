<?php

namespace App\Filament\Resources\ReferensiHargaProduksis\Pages;

use App\Filament\Resources\ReferensiHargaProduksis\ReferensiHargaProduksiResource;
use Filament\Resources\Pages\CreateRecord;

class CreateReferensiHargaProduksi extends CreateRecord
{
    protected static string $resource = ReferensiHargaProduksiResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
