<?php

namespace App\Filament\Resources\Targets\Pages;

use App\Exports\TargetExport;
use App\Filament\Resources\Targets\TargetResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Maatwebsite\Excel\Facades\Excel;

class ListTargets extends ListRecords
{
    protected static string $resource = TargetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Export mengikuti search, filter & urutan yang sedang aktif di tabel.
            Action::make('exportExcel')
                ->label('Export Excel')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->action(function () {
                    $targets = $this->getFilteredSortedTableQuery()
                        ->with(['mesin', 'ukuranModel', 'jenisKayu', 'kategoriBarang'])
                        ->get();

                    return Excel::download(
                        new TargetExport($targets),
                        'target-' . now()->format('Y-m-d_His') . '.xlsx'
                    );
                }),

            CreateAction::make(),
        ];
    }
}