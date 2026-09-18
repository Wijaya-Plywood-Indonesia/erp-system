<?php

namespace App\Filament\Resources\ValidasiPressDryers\Schemas;

use Filament\Schemas\Schema;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Facades\Filament;

class ValidasiPressDryerForm
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
                        $roleName = $user->getRoleNames()->first() ?? 'Tidak diketahui';

                        // Sertakan nama user supaya kelihatan siapa yang validasi,
                        // bukan cuma role-nya — mis. "kepala_produksi_wijaya - Faris"
                        return $roleName . ' - ' . ($user->name ?? 'Tidak diketahui');
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