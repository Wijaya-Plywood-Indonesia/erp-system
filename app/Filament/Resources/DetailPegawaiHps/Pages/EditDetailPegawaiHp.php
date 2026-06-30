<?php

namespace App\Filament\Resources\DetailPegawaiHps\Pages;

use App\Filament\Resources\DetailPegawaiHps\DetailPegawaiHpResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditDetailPegawaiHp extends EditRecord
{
    protected static string $resource = DetailPegawaiHpResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
