<?php

namespace App\Filament\Resources\StokVeneerKerings\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class StokVeneerKeringForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('id_produksi_dryer')
                    ->numeric(),
                TextInput::make('id_ukuran')
                    ->required()
                    ->numeric(),
                TextInput::make('id_jenis_kayu')
                    ->required()
                    ->numeric(),
                TextInput::make('kw')
                    ->required(),
                Select::make('jenis_transaksi')
                    ->options(['masuk' => 'Masuk', 'keluar' => 'Keluar', 'koreksi' => 'Koreksi'])
                    ->default('masuk')
                    ->required(),
                DatePicker::make('tanggal_transaksi')
                    ->required(),
                TextInput::make('qty')
                    ->required()
                    ->numeric()
                    ->default(0.0),
                TextInput::make('m3')
                    ->required()
                    ->numeric(),
                TextInput::make('hpp_veneer_basah_per_m3')
                    ->required()
                    ->numeric()
                    ->default(1000000.0),
                TextInput::make('ongkos_dryer_per_m3')
                    ->required()
                    ->numeric()
                    ->default(0.0),
                TextInput::make('hpp_kering_per_m3')
                    ->required()
                    ->numeric()
                    ->default(0.0),
                TextInput::make('nilai_transaksi')
                    ->required()
                    ->numeric()
                    ->default(0.0),
                TextInput::make('stok_m3_sebelum')
                    ->required()
                    ->numeric()
                    ->default(0.0),
                TextInput::make('nilai_stok_sebelum')
                    ->required()
                    ->numeric()
                    ->default(0.0),
                TextInput::make('stok_m3_sesudah')
                    ->required()
                    ->numeric()
                    ->default(0.0),
                TextInput::make('nilai_stok_sesudah')
                    ->required()
                    ->numeric()
                    ->default(0.0),
                TextInput::make('hpp_average')
                    ->required()
                    ->numeric()
                    ->default(0.0),
                Textarea::make('keterangan')
                    ->columnSpanFull(),
            ]);
    }
}
