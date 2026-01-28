<?php

namespace App\Filament\Resources\ProduksiGrajiBalkens\Pages;

use App\Filament\Resources\ProduksiGrajiBalkens\ProduksiGrajiBalkenResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListProduksiGrajiBalkens extends ListRecords
{
    protected static string $resource = ProduksiGrajiBalkenResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
