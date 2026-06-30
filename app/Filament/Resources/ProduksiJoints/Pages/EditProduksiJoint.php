<?php

namespace App\Filament\Resources\ProduksiJoints\Pages;

use App\Filament\Resources\ProduksiJoints\ProduksiJointResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditProduksiJoint extends EditRecord
{
    protected static string $resource = ProduksiJointResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
