<?php

namespace App\Filament\Resources\ProduksiNyusups\Schemas;

use App\Models\ProduksiNyusup;
use Filament\Schemas\Schema;
use Filament\Forms\Components\DatePicker;

class ProduksiNyusupForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                DatePicker::make('tanggal_produksi')
                    ->label('Tanggal Produksi')
                    ->default(fn () => now()->addDay())
                    ->displayFormat('d F Y')
                    ->required()

                    // ✅ VALIDASI TANGGAL TIDAK BOLEH SAMA
                    ->rules([
                        function () {
                            return function (string $attribute, $value, $fail) {
                                $exists = ProduksiNyusup::whereDate('tanggal_produksi', $value)->exists();

                                if ($exists) {
                                    $fail('Tanggal ini sudah digunakan. Pilih tanggal lain.');
                                }
                            };
                        },
                    ])
            ]);
    }
}
