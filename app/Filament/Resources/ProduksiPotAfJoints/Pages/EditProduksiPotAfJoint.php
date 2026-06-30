<?php

namespace App\Filament\Resources\ProduksiPotAfJoints\Pages;

use App\Filament\Resources\ProduksiPotAfJoints\ProduksiPotAfJointResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditProduksiPotAfJoint extends EditRecord
{
    protected static string $resource = ProduksiPotAfJointResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
