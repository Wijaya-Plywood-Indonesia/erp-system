<?php

namespace App\Filament\Resources\DetailTurusanKayus\Pages;

use App\Filament\Resources\DetailTurusanKayus\DetailTurusanKayuResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditDetailTurusanKayu extends EditRecord
{
    protected static string $resource = DetailTurusanKayuResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
