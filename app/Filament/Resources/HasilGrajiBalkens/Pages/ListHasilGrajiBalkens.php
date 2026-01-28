<?php

namespace App\Filament\Resources\HasilGrajiBalkens\Pages;

use App\Filament\Resources\HasilGrajiBalkens\HasilGrajiBalkenResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListHasilGrajiBalkens extends ListRecords
{
    protected static string $resource = HasilGrajiBalkenResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
