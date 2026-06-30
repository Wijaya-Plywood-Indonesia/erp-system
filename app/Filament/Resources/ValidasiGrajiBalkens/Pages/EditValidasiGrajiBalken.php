<?php

namespace App\Filament\Resources\ValidasiGrajiBalkens\Pages;

use App\Filament\Resources\ValidasiGrajiBalkens\ValidasiGrajiBalkenResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditValidasiGrajiBalken extends EditRecord
{
    protected static string $resource = ValidasiGrajiBalkenResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
