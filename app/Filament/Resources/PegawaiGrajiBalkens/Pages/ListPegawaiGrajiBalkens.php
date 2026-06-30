<?php

namespace App\Filament\Resources\PegawaiGrajiBalkens\Pages;

use App\Filament\Resources\PegawaiGrajiBalkens\PegawaiGrajiBalkenResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPegawaiGrajiBalkens extends ListRecords
{
    protected static string $resource = PegawaiGrajiBalkenResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
