<?php

namespace App\Filament\Resources\PenggunaanLahanRotaries\Pages;

use App\Filament\Resources\PenggunaanLahanRotaries\PenggunaanLahanRotaryResource;
use App\Services\HppAverageService;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPenggunaanLahanRotary extends EditRecord
{
    protected static string $resource = PenggunaanLahanRotaryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
