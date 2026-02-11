<?php

namespace App\Filament\Resources\Absensis\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

use function Symfony\Component\Clock\now;

class AbsensiForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                DatePicker::make('tanggal')
                    ->required()
                    ->native(false)
                    ->closeOnDateSelection()
                    ->displayFormat('d/m/Y')
                    ->format('Y-m-d')
                    ->maxDate(now()->format('Y-m-d'))
                    ->default(now()->format('Y-m-d'))
                    ->suffixIcon('heroicon-o-calendar')
                    ->suffixIconColor('primary'),
                FileUpload::make('file_path')
                    ->label('Upload File Logs')
                    ->disk('public') // Pastikan sudah php artisan storage:link
                    ->directory('absensi-logs')
                    ->acceptedFileTypes([
                        'text/plain',
                        'application/octet-stream',
                        'text/tab-separated-values',
                        'text/dat',
                        'application/dat'
                    ])
                    ->preserveFilenames()
                    ->required(),
                TextInput::make('uploaded_by')
                    ->default(auth()->user()->name)
                    ->disabled()
                    ->dehydrated(),
            ]);
    }
}
