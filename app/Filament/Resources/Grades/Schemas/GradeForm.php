<?php

namespace App\Filament\Resources\Grades\Schemas;

use Filament\Schemas\Schema;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use App\Models\KategoriBarang;

class GradeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('id_kategori_barang')
                    ->label('Kategori Barang')
                    ->options(
                        KategoriBarang::orderBy('nama_kategori')
                            ->pluck('nama_kategori', 'id')
                    )
                    ->searchable()
                    ->required(),

                TextInput::make('nama_grade')
                    ->label('Nama Grade')
                    ->required()
                    ->maxLength(255),
            ]);
    }
}
