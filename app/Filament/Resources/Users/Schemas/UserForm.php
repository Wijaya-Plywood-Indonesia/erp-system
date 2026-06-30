<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([

                Section::make('User Information')
                    ->description('Masukkan informasi dasar untuk pengguna.')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('name')
                                    ->label('Nama Lengkap')
                                    ->required(),

                                TextInput::make('email')
                                    ->label('Alamat Email')
                                    ->email()
                                    ->required()
                                    ->afterStateUpdated(function ($state, callable $set) {
                                        // Jika user tidak mengetik "@"
                                        if ($state && !str_contains($state, '@')) {
                                            $set('email', $state . '@wijayaplywoods.com');
                                        }
                                    })
                            ]),
                    ])
                    ->collapsible(),


                Section::make('Security')
                    ->description('Atur password akun pengguna.')
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('password')
                                ->label('Password')
                                ->password()
                                ->revealable()
                                ->confirmed()
                                ->dehydrateStateUsing(fn($state) => filled($state) ? bcrypt($state) : null)
                                ->dehydrated(fn($state) => filled($state)) // hanya update kalau diisi
                                ->required(fn($livewire) => $livewire instanceof \Filament\Resources\Pages\CreateRecord),

                            TextInput::make('password_confirmation')
                                ->label('Konfirmasi Password')
                                ->password()
                                ->revealable()
                                ->same('password')
                                ->dehydrated(false)
                                ->required(fn($livewire) => $livewire instanceof \Filament\Resources\Pages\CreateRecord),
                        ]),
                    ])
                    ->collapsible(),

                Section::make('Roles & Permissions')
                    ->schema([
                        Select::make('roles')
                            ->label('Roles')
                            ->multiple()
                            ->relationship('roles', 'name')
                            ->preload()
                            ->searchable()
                            ->placeholder('Pilih satu atau lebih role'),
                    ])
                    ->collapsible(),

            ]);
    }
}
