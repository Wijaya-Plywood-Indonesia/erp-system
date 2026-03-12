<?php

namespace App\Filament\Resources\HppLogHarians\Pages;

use App\Filament\Resources\HppLogHarians\HppLogHarianResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditHppLogHarian extends EditRecord
{
    protected static string $resource = HppLogHarianResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
