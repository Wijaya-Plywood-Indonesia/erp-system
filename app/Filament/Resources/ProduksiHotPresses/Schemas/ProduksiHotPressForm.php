<?php

namespace App\Filament\Resources\ProduksiHotPresses\Schemas;

use Filament\Schemas\Schema;
use Filament\Forms\Components\DatePicker;
use App\Models\ProduksiHp;
use Filament\Forms\Components\Select;

class ProduksiHotPressForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                DatePicker::make('tanggal_produksi')
                    ->label('Tanggal Produksi')
                    ->default(fn() => now()->addDay())
                    ->displayFormat('d F Y')
                    ->required()
                    ->reactive() // 🔥 penting

                    // ✅ VALIDASI TANGGAL + SHIFT
                    ->rules([
                        function ($get, $record) {
                            return function (string $attribute, $value, $fail) use ($get, $record) {

                                $query = ProduksiHp::whereDate('tanggal_produksi', $value)
                                    ->where('shift', $get('shift'));

                                // ⛔ skip saat edit
                                if ($record) {
                                    $query->where('id', '!=', $record->id);
                                }

                                if ($query->exists()) {
                                    $fail('Tanggal dan shift ini sudah ada.');
                                }
                            };
                        },
                    ]),

                Select::make('shift')
                    ->label('Shift')
                    ->options([
                        'pagi' => 'Pagi',
                        'malam' => 'Malam',
                    ])
                    ->required()
                    ->reactive(), // 🔥 penting
            ]);
    }
}