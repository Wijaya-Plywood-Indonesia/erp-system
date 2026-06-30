<?php

namespace App\Filament\Resources\OpnameStoks\Schemas;

use App\Models\Ukuran;
use App\Models\JenisKayu;
use App\Models\HppVeneerBasahSummary;
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

            Select::make('id_jenis_kayu')
                ->label('Jenis Kayu')
                ->options(fn () => JenisKayu::pluck('nama_kayu', 'id'))
                ->required()
                ->searchable()
                ->live()
                ->afterStateUpdated(fn (Get $get, Set $set) => self::updateStokInfo($get, $set)),

            Select::make('kw')
                ->label('KW / Grade')
                ->options(['1' => '1', '2' => '2', '3' => '3', '4' => '4'])
                ->required()
                ->live()
                ->afterStateUpdated(fn (Get $get, Set $set) => self::updateStokInfo($get, $set)),

            Select::make('id_ukuran')
                ->label('Ukuran Barang (P x L x T)')
                ->options(fn () => Ukuran::all()->pluck('dimensi', 'id'))
                ->searchable()
                ->required()
                ->live()
                ->afterStateUpdated(fn (Get $get, Set $set) => self::updateStokInfo($get, $set)),

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

            TextInput::make('kubikasi_sistem')
                ->label('Kubikasi Sistem')
                ->numeric()
                ->readOnly()
                ->dehydrated()
                ->suffix('m³'),

            TextInput::make('kubikasi_fisik')
                ->label('Kubikasi Fisik')
                ->numeric()
                ->required()
                ->suffix('m³'),

            Textarea::make('catatan')
                ->label('Catatan')
                ->columnSpanFull(),

        ])->columns(2);
    }

    private static function updateStokInfo(Get $get, Set $set): void
    {
        $idUkuran    = $get('id_ukuran');
        $idJenisKayu = $get('id_jenis_kayu');
        $kw          = $get('kw');

        if (!$idUkuran || !$idJenisKayu || !$kw) {
            $set('stok_sistem',     0);
            $set('kubikasi_sistem', 0);
            return;
        }

        $ukuran = Ukuran::find($idUkuran);
        if (!$ukuran) return;

        $summary = HppVeneerBasahSummary::where([
            'id_jenis_kayu' => $idJenisKayu,
            'panjang'       => $ukuran->panjang,
            'lebar'         => $ukuran->lebar,
            'tebal'         => $ukuran->tebal,
            'kw'            => $kw,
        ])->first();

        $set('stok_sistem',     $summary ? (int) $summary->stok_lembar : 0);
        $set('kubikasi_sistem', $summary ? round((float) $summary->stok_kubikasi, 6) : 0);
    }
}