<?php

namespace App\Filament\Resources\IndukAkuns\Schemas;

use App\Models\IndukAkun;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Get;


class IndukAkunForm
{
    public static function configure($schema)
    {
        return $schema
            ->components([
                TextInput::make('kode_induk_akun')
                    ->label('No Induk Akun')
                    ->required()
                    ->reactive()
                    ->afterStateUpdated(function ($state, callable $set) {
                        // tidak perlu melakukan apa pun di sini
                        // fungsi hanya dipakai untuk "reactive"
                    })
                    ->live(onBlur: true)
                    ->hint(function ($state) {
                        if (blank($state)) {
                            return null;
                        }

                        $akun = IndukAkun::where('kode_induk_akun', $state)->first();

                        return $akun
                            ? "⚠ Kode ini milik akun: {$akun->nama_induk_akun}"
                            : "ℹ Kode belum digunakan";
                    })
                    ->hintColor(function ($state) {
                        if (blank($state)) {
                            return 'gray';
                        }

                        $exists = IndukAkun::where('kode_induk_akun', $state)->exists();
                        return $exists ? 'danger' : 'info';
                    }),

                /** NAMA AKUN */
                TextInput::make('nama_induk_akun')
                    ->required()
                    ->maxLength(255),

                /** KETERANGAN */
                Textarea::make('keterangan')
                    ->columnSpanFull(),

            ]);
    }
}