<?php

namespace App\Filament\Resources\DetailBarangDikerjakans\Pages;

use App\Filament\Resources\DetailBarangDikerjakans\DetailBarangDikerjakanResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditDetailBarangDikerjakan extends EditRecord
{
    protected static string $resource = DetailBarangDikerjakanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
