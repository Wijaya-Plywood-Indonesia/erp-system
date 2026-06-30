<?php

namespace App\Filament\Resources\DetailBarangDikerjakanPotJeleks\Pages;

use App\Filament\Resources\DetailBarangDikerjakanPotJeleks\DetailBarangDikerjakanPotJelekResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditDetailBarangDikerjakanPotJelek extends EditRecord
{
    protected static string $resource = DetailBarangDikerjakanPotJelekResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
