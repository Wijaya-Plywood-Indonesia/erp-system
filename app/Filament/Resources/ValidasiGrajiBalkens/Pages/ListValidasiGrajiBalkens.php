<?php

namespace App\Filament\Resources\ValidasiGrajiBalkens\Pages;

use App\Filament\Resources\ValidasiGrajiBalkens\ValidasiGrajiBalkenResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListValidasiGrajiBalkens extends ListRecords
{
    protected static string $resource = ValidasiGrajiBalkenResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
