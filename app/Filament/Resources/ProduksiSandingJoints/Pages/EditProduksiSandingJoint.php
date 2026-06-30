<?php

namespace App\Filament\Resources\ProduksiSandingJoints\Pages;

use App\Filament\Resources\ProduksiSandingJoints\ProduksiSandingJointResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditProduksiSandingJoint extends EditRecord
{
    protected static string $resource = ProduksiSandingJointResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
