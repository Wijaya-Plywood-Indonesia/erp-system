<?php

namespace App\Filament\Resources\NotaBarangKeluars\Pages;

use App\Filament\Resources\NotaBarangKeluars\NotaBarangKeluarResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListNotaBarangKeluars extends ListRecords
{
    protected static string $resource = NotaBarangKeluarResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('rekap_nota_bk')
                ->label('Rekap Nota Barang Keluar')
                ->icon('heroicon-o-document-text')
                ->color('info')
                ->url(route('nota-bk.rekap'))
                ->openUrlInNewTab(),

            CreateAction::make()
                ->label('Buat Nota BK'),
        ];
    }
}