<?php

namespace App\Filament\Resources\ProduksiSandingJoints\Pages;

use App\Filament\Resources\ProduksiSandingJoints\ProduksiSandingJointResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListProduksiSandingJoints extends ListRecords
{
    protected static string $resource = ProduksiSandingJointResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
