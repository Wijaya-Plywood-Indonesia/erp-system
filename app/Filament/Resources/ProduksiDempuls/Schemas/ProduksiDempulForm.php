<?php

namespace App\Filament\Resources\ProduksiDempuls\Schemas;

use App\Models\ProduksiDempul;
use Filament\Forms\Components\DatePicker;
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
                        function ($record) use ($kolomTanggal) {
                            return function (string $attribute, $value, $fail) use ($record, $kolomTanggal) {
                                $query = ProduksiDempul::whereDate($kolomTanggal, $value);

                                if ($record) {
                                    $query->where('id', '!=', $record->id);
                                }

                                if ($query->exists()) {
                                    $fail('Tanggal ini sudah digunakan. Pilih tanggal lain.');
                                }
                            };
                        },
                    ]),
            ]);
    }
}
