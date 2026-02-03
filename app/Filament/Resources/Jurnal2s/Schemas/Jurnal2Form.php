<?php

namespace App\Filament\Resources\Jurnal2s\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Filament\Facades\Filament;

class Jurnal2Form
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('modif100')
                    ->label('Modif 100')
                    ->required(),

                TextInput::make('no_akun')
                    ->label('No Akun')
                    ->required(),

                TextInput::make('nama_akun')
                    ->label('Nama Akun')
                    ->required(),

                TextInput::make('banyak')
                    ->label('Banyak'),

                TextInput::make('kubikasi')
                    ->label('Kubikasi'),

                TextInput::make('harga')
                    ->label('Harga'),

                TextInput::make('total')
                    ->label('Total')
                    ->disabled()
                    ->dehydrated(false),

                // ⬇️ ini bagian yang kamu mau
                TextInput::make('dibuat_oleh')
                    ->label('Dibuat Oleh')
                    ->default(function () {
                        $user = Filament::auth()->user();

                        if (! $user) {
                            return 'Tidak diketahui';
                        }

                        return $user->name ?? 'Tidak diketahui';
                    })
                    ->disabled()
                    ->dehydrated(false), // TIDAK disimpan (karena user_id disimpan di model)
            ]);
    }
}
