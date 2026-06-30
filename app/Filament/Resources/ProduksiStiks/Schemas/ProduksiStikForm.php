<?php

namespace App\Filament\Resources\ProduksiStiks\Schemas;

use App\Models\ProduksiStik;
use Filament\Schemas\Schema;
use Filament\Forms\Components\DatePicker;

class ProduksiStikForm
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
                    ->reactive() // ⬅️ PENTING (biar error langsung muncul)

                    // ✅ VALIDASI TANGGAL TIDAK BOLEH SAMA
                    ->rules([
                        function () {
                            return function (string $attribute, $value, $fail) {
                                $exists = ProduksiStik::whereDate('tanggal_produksi', $value)->exists();

                                if ($exists) {
                                    $fail('Tanggal ini sudah digunakan. Pilih tanggal lain.');
                                }
                            };
                        },
                    ]),
            ]);
    }
}
