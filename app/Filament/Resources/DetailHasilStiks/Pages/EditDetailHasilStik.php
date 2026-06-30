<?php

namespace App\Filament\Resources\DetailHasilStiks\Pages;

use App\Filament\Resources\DetailHasilStiks\DetailHasilStikResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditDetailHasilStik extends EditRecord
{
    protected static string $resource = DetailHasilStikResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
