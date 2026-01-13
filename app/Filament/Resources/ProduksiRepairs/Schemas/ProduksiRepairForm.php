<?php

namespace App\Filament\Resources\ProduksiRepairs\Schemas;

use App\Models\ProduksiRepair;
use Filament\Schemas\Schema;
use Filament\Forms\Components\DatePicker;

class ProduksiRepairForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                DatePicker::make('tanggal')
                    ->label('Tanggal')
                    ->default(fn () => now()->addDay())
                    ->displayFormat('d F Y')
                    ->required()
                    ->reactive() // ⬅️ Menggunakan reactive() sesuai contoh ProduksiStik Anda

                    // ✅ VALIDASI TANGGAL TIDAK BOLEH SAMA
                    ->rules([
                        function () {
                            return function (string $attribute, $value, $fail) {
                                $exists = ProduksiRepair::whereDate('tanggal', $value)->exists();

                                if ($exists) {
                                    $fail('Tanggal ini sudah digunakan. Pilih tanggal lain.');
                                }
                            };
                        },
                    ]),
            ]);
    }
}