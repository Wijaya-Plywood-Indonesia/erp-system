<?php

namespace App\Filament\Resources\ProduksiGrajiTripleks\Schemas;

use Filament\Schemas\Schema;
use Filament\Forms\Components\DatePicker;
use App\Models\ProduksiGrajitriplek;
use Filament\Forms\Components\Select;

class ProduksiGrajiTriplekForm
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
                                $exists = ProduksiGrajitriplek::whereDate('tanggal_produksi', $value)->exists();

                                if ($exists) {
                                    $fail('Tanggal ini sudah digunakan. Pilih tanggal lain.');
                                }
                            };
                        },
                    ]),

                Select::make('status')
                    ->label('Status Produksi')
                    ->options([
                        'graji manual'   => 'Graji Manual',
                        'graji otomatis' => 'Graji Otomatis',
                    ])
                    ->required()
                    ->validationMessages([
                        'required' => 'Status produksi wajib dipilih.',
                    ]),
            ]);
    }
}