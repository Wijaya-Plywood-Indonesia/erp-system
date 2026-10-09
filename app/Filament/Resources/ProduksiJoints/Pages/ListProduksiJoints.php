<?php

namespace App\Filament\Resources\ProduksiJoints\Pages;

use App\Filament\Resources\ProduksiJoints\ProduksiJointResource;
use App\Filament\Resources\ProduksiJoints\Widgets\RekapProduksiJoint;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListProduksiJoints extends ListRecords
{
    protected static string $resource = ProduksiJointResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
    protected function getHeaderWidgets(): array
    {
        return [RekapProduksiJoint::class];
    }
}
