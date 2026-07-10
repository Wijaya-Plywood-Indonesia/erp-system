<?php

namespace App\Filament\Resources\ProduksiTembelTripleks\Schemas;

use App\Models\ProduksiTembelTriplek;
use Filament\Schemas\Schema;

// 👇 INI ADALAH BARIS YANG WAJIB DITAMBAHKAN UNTUK MEMPERBAIKI ERROR
use Filament\Forms\Components\DatePicker; 

class ProduksiTembelTriplekForm
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
                    // Validasi tanggal tidak boleh sama (opsional, disamakan dengan modul Dempul)
                    ->rules([
                        function () {
                            return function (string $attribute, $value, $fail) {
                                $exists = ProduksiTembelTriplek::whereDate('tanggal', $value)->exists();

                                if ($exists) {
                                    $fail('Tanggal ini sudah digunakan. Pilih tanggal lain.');
                                }
                            };
                        },
                    ])
            ]);
    }
}