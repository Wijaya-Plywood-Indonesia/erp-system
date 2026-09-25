<?php

namespace App\Filament\Resources\ProduksiDempuls\Schemas;

use App\Models\ProduksiDempul;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Schemas\Schema;

class ProduksiDempulForm
{
    public static function configure(Schema $schema): Schema
    {
        $kolomTanggal = ProduksiDempul::kolomTanggalAktif();

        return $schema
            ->components([
                DatePicker::make($kolomTanggal)
                    ->label('Tanggal Produksi')
                    ->default(fn () => now()->addDay())
                    ->displayFormat('d F Y')
                    ->required()
                    ->rules([
                        function ($get, $record) use ($kolomTanggal) {
                            return function (string $attribute, $value, $fail) use ($get, $record, $kolomTanggal) {
                                $shift = $get('shift') ?? 'PAGI';
                                $query = ProduksiDempul::whereDate($kolomTanggal, $value)
                                    ->where('shift', $shift);

                                if ($record) {
                                    $query->where('id', '!=', $record->id);
                                }

                                if ($query->exists()) {
                                    $fail('Tanggal dan shift ini sudah digunakan. Pilih yang lain.');
                                }
                            };
                        },
                    ]),
                Select::make('shift')
                    ->label('Shift')
                    ->options([
                        'PAGI' => 'Pagi',
                        'MALAM' => 'Malam',
                    ])
                    ->default('PAGI')
                    ->required()
                    ->rules([
                        function ($get, $record) use ($kolomTanggal) {
                            return function (string $attribute, $value, $fail) use ($get, $record, $kolomTanggal) {
                                $tanggal = $get($kolomTanggal);
                                if (!$tanggal) return;

                                $query = ProduksiDempul::whereDate($kolomTanggal, $tanggal)
                                    ->where('shift', $value);

                                if ($record) {
                                    $query->where('id', '!=', $record->id);
                                }

                                if ($query->exists()) {
                                    $fail('Tanggal dan shift ini sudah digunakan. Pilih yang lain.');
                                }
                            };
                        },
                    ])
                    ->native(false),
            ]);
    }
}
