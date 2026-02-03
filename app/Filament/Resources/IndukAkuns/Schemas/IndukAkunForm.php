<?php

namespace App\Filament\Resources\IndukAkuns\Schemas;

use App\Models\IndukAkun;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class IndukAkunForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('kode_induk_akun')
                    ->label('No Induk Akun')
                    ->required()
                    ->reactive()
                    ->afterStateUpdated(function ($state, callable $set) {
                        // Cari akun berdasarkan kode
                        $akun = IndukAkun::where('kode_induk_akun', $state)->first();

                        // Isi hint dinamis
                        if ($akun) {
                            $set('kode_hint', "⚠ Kode ini milik akun: {$akun->nama_induk_akun}");
                        } else {
                            $set('kode_hint', "ℹ Kode belum digunakan");
                        }
                    })
                    ->hint(fn($state, $get) => $get('kode_hint'))
                    ->hintColor(
                        fn($state, $get) =>
                        str_starts_with($get('kode_hint'), '⚠') ? 'danger' : 'info'
                    ),
                TextInput::make('nama_induk_akun')
                    ->required(),
                Textarea::make('keterangan')
                    ->columnSpanFull(),
            ]);
    }
}