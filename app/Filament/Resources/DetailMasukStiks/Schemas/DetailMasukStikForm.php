<?php

namespace App\Filament\Resources\DetailMasukStiks\Schemas;

use Filament\Schemas\Schema;
use App\Models\JenisKayu;
use App\Models\Ukuran;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;

/**
 * Form Modal Stik — MANUAL SEPENUHNYA.
 *
 * Serah terima dari Rotary (via detail_hasil_palet_rotary_serah_terima_pivot)
 * sudah dihapus. Operator mengisi sendiri nomor palet, jenis kayu, ukuran,
 * KW, dan isi.
 */
class DetailMasukStikForm
{
    public static function configure(Schema $schema, ?int $idProduksiStik = null): Schema
    {
        return $schema
            ->schema([
                TextInput::make('no_palet')
                    ->label('Nomor Palet')
                    ->numeric()
                    ->required(),

                Select::make('id_jenis_kayu')
                    ->label('Jenis Kayu')
                    ->options(
                        JenisKayu::orderBy('nama_kayu')->pluck('nama_kayu', 'id')
                    )
                    ->searchable()
                    ->afterStateUpdated(fn($state) => session(['last_jenis_kayu' => $state]))
                    ->default(fn() => session('last_jenis_kayu'))
                    ->required(),

                Select::make('id_ukuran')
                    ->label('Ukuran')
                    ->options(Ukuran::all()->pluck('dimensi', 'id'))
                    ->searchable()
                    ->afterStateUpdated(fn($state) => session(['last_ukuran' => $state]))
                    ->default(fn() => session('last_ukuran'))
                    ->required(),

                TextInput::make('kw')
                    ->label('KW (Kualitas)')
                    ->required()
                    ->maxLength(255)
                    ->placeholder('Cth: 1, 2, 3, dll.'),

                TextInput::make('isi')
                    ->label('Isi')
                    ->required()
                    ->numeric()
                    ->placeholder('Cth: 1.5 atau 100'),
            ]);
    }
}