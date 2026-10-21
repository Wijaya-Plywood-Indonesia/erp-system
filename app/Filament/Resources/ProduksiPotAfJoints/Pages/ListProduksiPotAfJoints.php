<?php

namespace App\Filament\Resources\ProduksiPotAfJoints\Pages;

use App\Filament\Resources\ProduksiPotAfJoints\ProduksiPotAfJointResource;
use App\Filament\Resources\ProduksiPotAfJoints\Widgets\RekapProduksiPotAfJoint;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListProduksiPotAfJoints extends ListRecords
{
    protected static string $resource = ProduksiPotAfJointResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [RekapProduksiPotAfJoint::class];
    }
}
