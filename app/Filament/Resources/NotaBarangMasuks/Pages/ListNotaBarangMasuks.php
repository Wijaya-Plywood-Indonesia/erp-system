<?php

namespace App\Filament\Resources\NotaBarangMasuks\Pages;

use App\Filament\Actions\ExportRekapAction;
use App\Filament\Resources\NotaBarangMasuks\NotaBarangMasukResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListNotaBarangMasuks extends ListRecords
{
    protected static string $resource = NotaBarangMasukResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('rekap')
                ->label('Rekap Nota Barang Masuk')
                ->url('/nota-barang-masuk/rekap'),

            ExportRekapAction::make('bm'),

            CreateAction::make()
                ->label('Buat Nota BM'),
        ];
    }
}
