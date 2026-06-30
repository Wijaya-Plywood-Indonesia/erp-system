<?php

namespace App\Filament\Resources\HargaVeneers\Schemas;

use App\Models\JenisKayu;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class HargaVeneerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('ukuran')
                    ->label('Ukuran / Posisi Veneer')
                    ->options([
                        'faceback' => 'Faceback',
                        'face' => 'Face',
                        'back' => 'Back',
                        'core' => 'Core',
                        'ppc_faceback' => '0.5 PPC',
                        'ppc_core' => '3.7 PPC',
                    ])
                    ->native(false)
                    ->required()
                    ->placeholder('Pilih Ukuran/Posisi Veneer'),

                Select::make('id_jenis_kayu')
                    ->label('Jenis Kayu')
                    ->options(
                        JenisKayu::query()
                            ->get()
                            ->mapWithKeys(function ($jenisKayu) {
                                return [
                                    $jenisKayu->id => "{$jenisKayu->kode_kayu} - {$jenisKayu->nama_kayu}",
                                ];
                            })
                    )
                    ->searchable()
                    ->preload()
                    ->native(false)
                    ->required()
                    ->placeholder('Pilih Jenis Kayu'),

                TextInput::make('harga_basah')
                    ->label('Harga Basah (Per m³)')
                    ->numeric()
                    ->prefix('Rp')
                    ->required(),

                TextInput::make('harga_kering')
                    ->label('Harga Kering (Per m³)')
                    ->numeric()
                    ->prefix('Rp')
                    ->required(),

                TextInput::make('harga_jadi')
                    ->label('Harga Jadi (Per m³)')
                    ->numeric()
                    ->prefix('Rp')
                    ->required(),
            ]);
    }
}
