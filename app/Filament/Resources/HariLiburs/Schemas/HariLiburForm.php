<?php

namespace App\Filament\Resources\HariLiburs\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class HariLiburForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                DatePicker::make('date')
                    ->label('Tanggal')
                    ->placeholder('Pilih tanggal libur')
                    ->required()
                    ->native(false) // UI lebih rapi
                    ->displayFormat('Y-m-d'),

                TextInput::make('name')
                    ->label('Nama Hari Libur')
                    ->placeholder('Contoh: Hari Kemerdekaan')
                    ->required(),

                Select::make('type')
                    ->label('Kategori Libur')
                    ->options([
                        'national' => 'National',
                        'cuti_bersama' => 'Cuti Bersama',
                        'religion' => 'Religion',
                        'company' => 'Company',
                        'custom' => 'Custom',
                    ])
                    ->searchable()
                    ->required()
                    ->default('national'),

                Toggle::make('is_repeat_yearly')
                    ->label('Berulang Tiap Tahun?')
                    ->default(false),

                TextInput::make('source')
                    ->label('Sumber (opsional)')
                    ->placeholder('Misal: Pemerintah, internal, dll'),
            ]);
    }
}
