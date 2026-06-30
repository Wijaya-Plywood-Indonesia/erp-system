<?php

namespace App\Filament\Resources\TurusanKayus\Pages;

use App\Filament\Resources\TurusanKayus\TurusanKayuResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditTurusanKayu extends EditRecord
{
    protected static string $resource = TurusanKayuResource::class;

    protected function getHeaderActions(): array
    {
        return [

        ];
    }
}
