<?php

namespace App\Filament\Resources\ProduksiRotaries\Schemas;

use App\Models\Mesin;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class ProduksiRotaryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                DatePicker::make('tgl_produksi')
                    ->label('Tanggal Produksi')
                    ->default(fn() => now()->addDay()) // 👈 default besok
                    ->displayFormat('d F Y') // 👈 tampil seperti: 01 Januari 2025
                    ->required(),

                Select::make('id_mesin')
                    ->label('Mesin')
                    // ->multiple()
                    ->options(function () {
                        return Mesin::query()
                            ->where('kategori_mesin_id', 1)
                            ->orWhereHas('kategoriMesin', fn($q) => $q->where('nama_kategori_mesin', 'ROTARY'))
                            ->pluck('nama_mesin', 'id');
                    })
                    ->required(),

                // Textarea::make('kendala')
                //     ->columnSpanFull(),
            ]);
    }
}
