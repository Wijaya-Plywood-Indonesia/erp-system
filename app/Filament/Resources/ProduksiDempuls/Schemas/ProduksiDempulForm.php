<?php

namespace App\Filament\Resources\ProduksiDempuls\Schemas;

use App\Models\ProduksiDempul;
use Filament\Schemas\Schema;
use Filament\Forms\Components\DatePicker;

class ProduksiDempulForm
{
    public static function configure(Schema $schema): Schema
    {
        $currentHost = request()->getHost();
        $kolomTanggal = in_array($currentHost, ['kayu.wijayaplywoods.com', 'prarelease.wijayaplywoods.com'])
            ? 'tanggal'
            : 'tanggal_produksi';

        return $schema
            ->components([
                DatePicker::make($kolomTanggal)
                    ->label('Tanggal Produksi')
                    ->default(fn() => now()->addDay())
                    ->displayFormat('d F Y')
                    ->required()
                    ->rules([
                        function () use ($kolomTanggal) {
                            return function (string $attribute, $value, $fail) use ($kolomTanggal) {
                                $exists = ProduksiDempul::whereDate($kolomTanggal, $value)->exists();

                                if ($exists) {
                                    $fail('Tanggal ini sudah digunakan. Pilih tanggal lain.');
                                }
                            };
                        },
                    ])
            ]);
    }
}
