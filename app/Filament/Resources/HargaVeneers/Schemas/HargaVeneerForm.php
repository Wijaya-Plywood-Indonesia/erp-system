<?php

namespace App\Filament\Resources\HargaVeneers\Schemas;

use App\Models\JenisKayu;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rules\Unique;

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
                    ->live()
                    ->placeholder('Pilih Ukuran/Posisi Veneer')
                    ->unique(
                        table: 'harga_veneers',
                        column: 'ukuran',
                        ignoreRecord: true,
                        modifyRuleUsing: fn (Unique $rule, $get) => $rule
                            ->where('id_jenis_kayu', $get('id_jenis_kayu')),
                    )
                    ->validationMessages([
                        'unique' => 'Harga untuk ukuran dan jenis kayu ini sudah ada.',
                    ]),

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
                    ->live() // 🔥 WAJIB juga: biar dua arah saling ke-trigger
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