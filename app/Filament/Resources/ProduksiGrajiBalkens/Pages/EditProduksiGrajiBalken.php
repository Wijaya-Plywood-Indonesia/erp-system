<?php

namespace App\Filament\Resources\ProduksiGrajiBalkens\Pages;

use App\Filament\Resources\ProduksiGrajiBalkens\ProduksiGrajiBalkenResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditProduksiGrajiBalken extends EditRecord
{
    protected static string $resource = ProduksiGrajiBalkenResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
