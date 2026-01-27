<?php

namespace App\Filament\Resources\GrajiStiks\Schemas;

use Filament\Schemas\Schema;
use Filament\Forms\Components\DatePicker;
use App\Models\GrajiStik;

class GrajiStikForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                //
                DatePicker::make('tanggal')
                    ->label('Tanggal')
                    ->default(fn() => now()->addDay())
                    ->displayFormat('d F Y')
                    ->required()
                    ->native(false)
                    ->rules([
                        function () {
                            return function (string $attribute, $value, $fail) {
                                $exists = GrajiStik::whereDate('tanggal', $value)->exists();

                                if ($exists) {
                                    $fail('Tanggal ini sudah digunakan. Pilih tanggal lain.');
                                }
                            };
                        },
                    ])
            ]);
    }
}
