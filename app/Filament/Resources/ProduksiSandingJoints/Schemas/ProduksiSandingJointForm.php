<?php

namespace App\Filament\Resources\ProduksiSandingJoints\Schemas;

use App\Models\ProduksiSanding;
use App\Models\ProduksiSandingJoint;
use Filament\Forms\Components\DatePicker;
use Filament\Schemas\Schema;

class ProduksiSandingJointForm
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
                                $exists = ProduksiSandingJoint::whereDate('tanggal_produksi', $value)->exists();

                                if ($exists) {
                                    $fail('Tanggal ini sudah digunakan. Pilih tanggal lain.');
                                }
                            };
                        },
                    ])
            ]);
    }
}
