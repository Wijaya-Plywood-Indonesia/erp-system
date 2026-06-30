<?php

namespace App\Filament\Resources\KontrakKerjas\Pages;

use App\Filament\Resources\KontrakKerjas\KontrakKerjaResource;
use Filament\Actions\CreateAction;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use App\Exports\KontrakKerjaExport;
use Maatwebsite\Excel\Facades\Excel;
use Filament\Resources\Pages\ListRecords;
use App\Models\KontrakKerja;

class ListKontrakKerjas extends ListRecords
{
    protected static string $resource = KontrakKerjaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export_excel')
                ->label('Export Excel')
                ->icon('heroicon-o-document-arrow-down')
                ->color('success')
                ->form([
                    Select::make('status')
                        ->label('Status Kontrak')
                        ->options([
                            'active' => 'Aktif sekarang',
                            'soon' => 'Akan Habis',
                            'expired' => 'Tidak Aktif',
                            'all' => 'Semua',
                        ])
                        ->required()
                        ->default('all'),

                    Select::make('karyawan_di')
                        ->label('Lokasi Karyawan')
                        ->options(function () {
                            $options = KontrakKerja::whereNotNull('karyawan_di')
                                ->where('karyawan_di', '!=', '')
                                ->distinct()
                                ->pluck('karyawan_di', 'karyawan_di')
                                ->toArray();
                                
                            return ['all' => 'Semua Lokasi'] + $options;
                        })
                        ->required()
                        ->default('all'),
                ])
                ->action(function (array $data) {
                    $status = $data['status'];
                    $karyawan_di = $data['karyawan_di'] ?? 'all';
                    $filename = 'Kontrak_Kerja_' . date('Ymd_His') . '.xlsx';
                    
                    return Excel::download(new KontrakKerjaExport($status, $karyawan_di), $filename);
                }),
            CreateAction::make(),
        ];
    }
}
