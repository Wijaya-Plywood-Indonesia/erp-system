<?php

namespace App\Filament\Resources\Targets\Schemas;

use App\Models\Ukuran;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rules\Unique;

class TargetForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('id_mesin')
                    ->label('Mesin')
                    ->relationship('mesin', 'nama_mesin')
                    ->required()
                    ->reactive()
                    ->dehydrated(),

                Select::make('id_ukuran')
                    ->label('Ukuran')
                    ->options(
                        Ukuran::all()
                            ->pluck('dimensi', 'id') // ← memanggil accessor getDimensiAttribute()
                    )
                    ->searchable()
                    ->reactive()
                    ->required(),

                Select::make('id_jenis_kayu')
                    ->label('Jenis Kayu')
                    ->relationship('jenisKayu', 'nama_kayu')
                    ->required()
                    ->dehydrated()
                    ->reactive()
                    ->unique(
                        table: 'targets',
                        column: 'id_jenis_kayu',
                        ignoreRecord: true,
                        modifyRuleUsing: fn (Unique $rule, $get) => $rule
                            ->where('id_mesin', $get('id_mesin'))
                            ->where('id_ukuran', $get('id_ukuran'))
                            ->where('grade', $get('grade')),
                    )
                    ->validationMessages([
                        'unique' => 'Target untuk kombinasi mesin, ukuran, jenis kayu, dan grade ini sudah ada.',
                    ]),

                TextInput::make('ukuran')
                    ->label('Kode Ukuran')
                    ->disabled()
                    ->dehydrated()
                    ->reactive(),

                Select::make('id_kategori_barang')
                    ->label('Jenis Barang')
                    ->relationship('kategoriBarang', 'nama_kategori')
                    ->searchable()
                    ->preload()
                    ->nullable(),

                TextInput::make('grade')
                    ->label('KW')
                    ->maxLength(50)
                    ->nullable(),

                TextInput::make('target')
                    ->label('Target')
                    ->numeric()
                    ->step(0.0001)
                    ->required(),

                TextInput::make('orang')
                    ->label('Jumlah Orang')
                    ->numeric()
                    ->required(),

                Select::make('jam_mulai')
                    ->label('Jam Mulai')
                    ->options(self::getHourOptions())
                    ->required()
                    ->reactive()
                    ->afterStateUpdated(fn($state, $get, $set) => self::updateJamKerja($get, $set)),

                Select::make('jam_selesai')
                    ->label('Jam Selesai')
                    ->options(self::getHourOptions())
                    ->required()
                    ->reactive()
                    ->afterStateUpdated(fn($state, $get, $set) => self::updateJamKerja($get, $set)),

                TextInput::make('jam')
                    ->label('Jam Kerja (Akumulasi)')
                    ->numeric()
                    ->disabled()
                    ->dehydrated()
                    ->required(),

                TextInput::make('gaji')
                    ->label('Gaji')
                    ->numeric()
                    ->prefix('Rp')
                    ->required(),
            ]);
    }

    public static function getHourOptions(): array
    {
        $options = [];
        for ($i = 0; $i < 24; $i++) {
            $key = sprintf('%02d:00:00', $i);
            $label = sprintf('%02d:00', $i);
            $options[$key] = $label;
        }
        return $options;
    }

    public static function updateJamKerja($get, $set)
    {
        $mulai = $get('jam_mulai');
        $selesai = $get('jam_selesai');

        if ($mulai && $selesai) {
            try {
                $start = \Carbon\Carbon::createFromFormat('H:i:s', $mulai);
                $end = \Carbon\Carbon::createFromFormat('H:i:s', $selesai);

                if ($end->lessThan($start)) {
                    $end->addDay();
                }

                $diff = $start->diffInHours($end);
                $set('jam', $diff);
            } catch (\Exception $e) {
                $set('jam', null);
            }
        } else {
            $set('jam', null);
        }
    }
}
