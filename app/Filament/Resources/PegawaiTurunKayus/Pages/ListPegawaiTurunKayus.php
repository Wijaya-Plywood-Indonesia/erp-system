<?php

namespace App\Filament\Resources\PegawaiTurunKayus\Pages;

use App\Filament\Resources\PegawaiTurunKayus\PegawaiTurunKayuResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPegawaiTurunKayus extends ListRecords
{
    protected static string $resource = PegawaiTurunKayuResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
