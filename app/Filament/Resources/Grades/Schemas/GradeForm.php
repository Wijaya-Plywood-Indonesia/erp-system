<?php

namespace App\Filament\Resources\Grades\Schemas;

use Filament\Schemas\Schema;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use App\Models\KategoriBarang;
use Illuminate\Validation\Rules\Unique;

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
                    ->required()
                    ->live(), // 🔥 WAJIB: biar validasi unique di nama_grade ke-refresh saat kategori diganti

                TextInput::make('nama_grade')
                    ->label('Nama Grade')
                    ->required()
                    ->maxLength(255)
                    ->live(onBlur: true)
                    ->unique(
                        table: 'grades',
                        column: 'nama_grade',
                        ignoreRecord: true,
                        modifyRuleUsing: fn (Unique $rule, $get) => $rule
                            ->where('id_kategori_barang', $get('id_kategori_barang')),
                    )
                    ->validationMessages([
                        'unique' => 'Grade dengan nama dan kategori barang ini sudah ada.',
                    ]),
            ]);
    }
}