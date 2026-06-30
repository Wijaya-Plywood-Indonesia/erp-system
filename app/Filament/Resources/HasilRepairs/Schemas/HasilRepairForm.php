<?php

namespace App\Filament\Resources\HasilRepairs\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class HasilRepairForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('id_produksi_repair')
                    ->required()
                    ->numeric(),
                TextInput::make('id_rencana_repair')
                    ->required()
                    ->numeric(),
                TextInput::make('jumlah')
                    ->required()
                    ->numeric(),
            ]);
    }
}
