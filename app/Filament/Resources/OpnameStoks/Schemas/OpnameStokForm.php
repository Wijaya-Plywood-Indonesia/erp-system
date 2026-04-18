<?php

namespace App\Filament\Resources\OpnameStoks\Schemas;

use App\Models\Ukuran; // Gunakan model Ukuran langsung
use App\Models\JenisKayu;
use App\Models\HppVeneerBasahSummary;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class OpnameStokForm
{
    public static function configure(Schema $schema): Schema
    {
            return $schema->components([
                    Select::make('jenis_stok')
                        ->label('Jenis Stok')
                        ->options(['veneer_basah' => 'Veneer Basah'])
                        ->default('veneer_basah')
                        ->required()
                        ->live(),

                    // Perbaikan: Pakai 'nama_kayu' sesuai Model JenisKayu
                    Select::make('id_jenis_kayu')
                        ->label('Jenis Kayu')
                        ->options(fn () => JenisKayu::pluck('nama_kayu', 'id'))
                        ->required()
                        ->searchable()
                        ->live(),

                    Select::make('kw')
                        ->label('KW / Grade')
                        ->options(['1' => '1', '2' => '2', '3' => '3', '4' => '4'])
                        ->required()
                        ->live(),

                    // Perbaikan: Ambil langsung dari model Ukuran agar pasti muncul
                    Select::make('id_ukuran')
                        ->label('Ukuran Barang (P x L x T)')
                        ->options(fn () => Ukuran::all()->pluck('dimensi', 'id'))
                        ->searchable()
                        ->required()
                        ->live()
                        ->afterStateUpdated(function (Get $get, Set $set, $state) {
                            if (!$state || !$get('id_jenis_kayu') || !$get('kw')) {
                                $set('stok_sistem', 0);
                                return;
                            }

                            $ukuran = Ukuran::find($state);
                            
                            $summary = HppVeneerBasahSummary::where([
                                'id_jenis_kayu' => $get('id_jenis_kayu'),
                                'panjang' => $ukuran->panjang,
                                'lebar' => $ukuran->lebar,
                                'tebal' => $ukuran->tebal,
                                'kw' => $get('kw'),
                            ])->first();

                            $set('stok_sistem', $summary ? $summary->stok_lembar : 0);
                        }),

                    TextInput::make('stok_sistem')
                        ->label('Stok Sistem')
                        ->numeric()
                        ->readOnly()
                        ->dehydrated()
                        ->suffix('Lembar'),

                    TextInput::make('stok_fisik')
                        ->label('Stok Fisik')
                        ->numeric()
                        ->required()
                        ->suffix('Lembar'),

                    Textarea::make('catatan')
                        ->label('Catatan')
                        ->columnSpanFull(),
                ])->columns(2);
    }
}