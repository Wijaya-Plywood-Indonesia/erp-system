<?php

namespace App\Filament\Resources\Bms\Pages;

use App\Filament\Resources\Bms\BmResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Resources\Pages\ListRecords;

class ListBms extends ListRecords
{
    protected static string $resource = BmResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export_rekap')->label('Export Rekap')->icon('heroicon-o-arrow-down-tray')->color('success')
                ->modalHeading('Export Rekap BM')->modalSubmitActionLabel('Unduh Excel')
                ->schema([
                    DatePicker::make('dari')->label('Dari tanggal')->default(now()->startOfMonth())->required(),
                    DatePicker::make('sampai')->label('Sampai tanggal')->default(today())->required(),
                    Select::make('jenis')->label('Jenis (kosongkan = semua)')->multiple()
                        ->options(config('bkbm.kategori_bm')),
                ])
                ->action(fn (array $data) => redirect()->route('bkbm.export.bm', [
                    'dari' => $data['dari'], 'sampai' => $data['sampai'], 'jenis' => $data['jenis'] ?? [],
                ])),
            \App\Filament\Resources\Bms\Actions\BuatBmlDariBklAction::make(),
            CreateAction::make()->label('Buat BM'),
        ];
    }
}