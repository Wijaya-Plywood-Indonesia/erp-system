<?php

namespace App\Filament\Resources\ReturPenjualans\Pages;

use App\Filament\Resources\ReturPenjualans\ReturPenjualanResource;
use Filament\Resources\Pages\CreateRecord;

class CreateReturPenjualan extends CreateRecord
{
    protected static string $resource = ReturPenjualanResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['dibuat_oleh'] = auth()->id();
        return $data;
    }
}
