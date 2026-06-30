<?php

namespace App\Filament\Resources\PegawaiDryers\Pages;

use App\Filament\Resources\PegawaiDryers\PegawaiDryerResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPegawaiDryer extends EditRecord
{
    protected static string $resource = PegawaiDryerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
