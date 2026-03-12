<?php

namespace App\Filament\Resources\BahanPenolongRotaries\Schemas;

use App\Models\BahanPenolongProduksi;
use Filament\Schemas\Schema;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;

class BahanPenolongRotaryForm
{
    public static function getBahanOptions(): array
    {
        return [
            'reeling_tape' => 'Reeling Tape (roll)',
        ];
    }
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('bahan_penolong_id')
                    ->label('Nama Bahan')
                    ->options(
                        fn() =>
                        BahanPenolongProduksi::where('kategori_produksi', 'rotary')
                            ->get()
                            ->mapWithKeys(fn($item) => [
                                $item->id =>
                                $item->nama_bahan_penolong . ' (' . $item->satuan . ')'
                            ])
                            ->toArray()
                    )
                    ->searchable()
                    ->required(),

                TextInput::make('jumlah')
                    ->label('Banyak')
                    ->required()
                    ->numeric(),
            ]);
    }
}
