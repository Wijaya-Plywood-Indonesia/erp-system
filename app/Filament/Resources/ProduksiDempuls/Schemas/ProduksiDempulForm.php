<?php

namespace App\Filament\Resources\ProduksiDempuls\Schemas;

use App\Models\ProduksiDempul;
use Filament\Schemas\Schema;
use Filament\Forms\Components\DatePicker;

class ProduksiDempulForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                DatePicker::make('tanggal')
                    ->label('Tanggal Produksi')
                    ->default(fn () => now()->addDay())
                    ->displayFormat('d F Y')
                    ->required()

                    // ✅ VALIDASI TANGGAL TIDAK BOLEH SAMA
                    ->rules([
                        function () {
                            return function (string $attribute, $value, $fail) {
                                $exists = ProduksiDempul::whereDate('tanggal', $value)->exists();

                                if ($exists) {
                                    $fail('Tanggal ini sudah digunakan. Pilih tanggal lain.');
                                }
                            };
                        },
                    ])
            ]);
    }
}
