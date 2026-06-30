<?php

namespace App\Filament\Resources\PegawaiGrajiBalkens\Pages;

use App\Filament\Resources\PegawaiGrajiBalkens\PegawaiGrajiBalkenResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPegawaiGrajiBalken extends EditRecord
{
    protected static string $resource = PegawaiGrajiBalkenResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
