<?php

namespace App\Filament\Resources\DetailBarangDikerjakanPotSikus\Pages;

use App\Filament\Resources\DetailBarangDikerjakanPotSikus\DetailBarangDikerjakanPotSikuResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditDetailBarangDikerjakanPotSiku extends EditRecord
{
    protected static string $resource = DetailBarangDikerjakanPotSikuResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
