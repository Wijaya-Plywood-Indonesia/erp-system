<?php

namespace App\Filament\Resources\NotaBarangMasuks\Pages;

use App\Filament\Resources\NotaBarangMasuks\NotaBarangMasukResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Actions\Action;

class ListNotaBarangMasuks extends ListRecords
{
    protected static string $resource = NotaBarangMasukResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('rekap_nota_bm')
                ->label('Rekap Nota Barang Masuk')
                ->icon('heroicon-o-document-text')
                ->color('info')
                ->url(route('nota-bm.rekap'))
                ->openUrlInNewTab(),

            CreateAction::make()
                ->label('Buat Nota BM'),
        ];
    }
}