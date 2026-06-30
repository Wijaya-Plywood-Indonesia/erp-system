<?php

namespace App\Filament\Resources\ProduksiKedis\Pages;

use App\Filament\Resources\ProduksiKedis\ProduksiKediResource;
use Filament\Resources\Pages\CreateRecord;

class CreateProduksiKedi extends CreateRecord
{
    protected static string $resource = ProduksiKediResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['status'] = 'masuk'; // Paksa status 'masuk' untuk record baru
        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
