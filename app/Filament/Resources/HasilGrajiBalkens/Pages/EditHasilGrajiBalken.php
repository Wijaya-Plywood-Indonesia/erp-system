<?php

namespace App\Filament\Resources\HasilGrajiBalkens\Pages;

use App\Filament\Resources\HasilGrajiBalkens\HasilGrajiBalkenResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditHasilGrajiBalken extends EditRecord
{
    protected static string $resource = HasilGrajiBalkenResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
