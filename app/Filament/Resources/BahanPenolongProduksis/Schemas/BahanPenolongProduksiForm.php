<?php

namespace App\Filament\Resources\BahanPenolongProduksis\Schemas;

use Filament\Schemas\Schema;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;

class BahanPenolongProduksiForm
{
    public static function getProduksiOptions(): array
    {
        return [
            'rotary' => 'Rotary',
            'pot_siku' => 'Pot Siku',
            'pot_jelek' => 'Pot Jelek',
            'guellotine' => 'Guellotine',
            'press_dryer' => 'Press Dryer',
            'kedi'=>'Kedi',
            'stik'=>'Stik',
            'graji_stik'=>'Graji Stik',
            'repair'=>'Repair',
            'joint'=>'Joint',
            'pot_af_joint'=>'Pot AF Joint',
            'sanding_joint'=>'Sanding Joint',
            'hot_press'=>'Hot Press',
            'graji_balken'=>'Graji Balken',
            'pilih_veneer'=>'Pilih Veneer',
            'sanding'=>'Sanding',
            'dempul'=>'Dempul',
            'graji_triplek'=>'Graji Triplek',
            'nyusup'=>'Nyusup',
        ];
    }
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('nama_bahan_penolong')
                    ->label('Nama Bahan')
                    ->required()
                    ->maxLength(255),
                TextInput::make('satuan')
                    ->label('Satuan')
                    ->required()
                    ->maxLength(255),
                Select::make('kategori_produksi')
                    ->label('Kategori Produksi')
                    // Menggunakan method static untuk options
                    ->options(self::getProduksiOptions())
                    ->required()
                    ->native(false)
                    ->searchable(),
                TextInput::make('harga')
                    ->label('Harga')
                    ->integer()
            ]);
    }
}
