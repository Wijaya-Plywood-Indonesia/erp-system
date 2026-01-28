<?php

namespace App\Filament\Resources\ValidasiGrajiStiks\Schemas;

use Filament\Schemas\Schema;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Facades\Filament;

class ValidasiGrajiStikForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('role')
                    ->label('Role Login')
                    ->default(function () {
                        $user = Filament::auth()->user();

                        if (!$user) {
                            return 'Tidak diketahui';
                        }

                        // Ambil role pertama dari user (karena bisa punya lebih dari satu)
                        /** @var User&HasRoles $user */
                        return $user->getRoleNames()->first() ?? 'Tidak diketahui';
                    })
                    ->disabled()
                    ->dehydrated(true), // tetap ikut disimpan ke database
                Select::make('status')
                    ->label('Status Validasi')
                    ->options([
                        'divalidasi' => 'Divalidasi',
                        'disetujui' => 'Disetujui',
                        'ditangguhkan' => 'Ditangguhkan',
                        'ditolak' => 'Ditolak',
                    ])

                    ->required()
                    ->native(false)
                    ->searchable(),
            ]);
    }
}
