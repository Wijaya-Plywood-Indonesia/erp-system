<?php

namespace App\Filament\Resources\IndukAkuns\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class IndukAkunInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informasi Induk Akun')
                    ->schema([
                        Grid::make(4)->schema([

                            TextEntry::make('kode_induk_akun')
                                ->label('Kode'),

                            TextEntry::make('nama_induk_akun')
                                ->label('Nama'),

                            TextEntry::make('saldo_normal')
                                ->label('Saldo Normal')
                                ->badge()
                                ->color(fn($state) => match($state) {
                                    'debet'  => 'success',
                                    'kredit' => 'danger',
                                    default  => 'gray',
                                }),

                            TextEntry::make('anakAkuns_count')
                                ->label('Jumlah Anak Akun')
                                ->state(fn($record) => $record->anakAkuns->count())
                                ->badge()
                                ->color('primary'),

                        ]),

                        TextEntry::make('keterangan')
                            ->label('Keterangan')
                            ->placeholder('-')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}