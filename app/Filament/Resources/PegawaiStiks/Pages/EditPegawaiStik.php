<?php

namespace App\Filament\Resources\PegawaiStiks\Pages;

use App\Filament\Resources\PegawaiStiks\PegawaiStikResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPegawaiStik extends EditRecord
{
    protected static string $resource = PegawaiStikResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
