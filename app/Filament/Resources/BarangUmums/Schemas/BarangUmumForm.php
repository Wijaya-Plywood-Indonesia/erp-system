<?php

namespace App\Filament\Resources\BarangUmums\Schemas;

use App\Models\BarangUmum;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class BarangUmumForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('nama_barang')
                    ->label('Nama Barang')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),

                TextInput::make('satuan')
                    ->label('Satuan')
                    ->required()
                    ->maxLength(50)
                    ->datalist(fn() => BarangUmum::query()->distinct()->pluck('satuan')->filter()->values()->all())
                    ->helperText('Contoh: kg, liter, pcs, dus, roll — bisa ketik satuan baru kapan saja.'),

                TextInput::make('kategori')
                    ->label('Kategori (opsional)')
                    ->maxLength(100)
                    ->datalist(fn() => BarangUmum::query()->distinct()->pluck('kategori')->filter()->values()->all())
                    ->helperText('Opsional, buat pengelompokan/filter saja.'),

                Textarea::make('keterangan')
                    ->label('Keterangan')
                    ->rows(2)
                    ->columnSpanFull(),
            ]);
    }
}