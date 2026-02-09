<?php

namespace App\Filament\Resources\HariLiburs\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class HariLiburInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2) // optional, agar lebih rapi
            ->components([

                TextEntry::make('date')
                    ->label('Tanggal')
                    ->date('Y-m-d'),

                TextEntry::make('name')
                    ->label('Nama Hari Libur'),

                TextEntry::make('type')
                    ->badge()
                    ->label('Kategori')
                    ->colors([
                        'primary' => 'national',
                        'warning' => 'cuti_bersama',
                        'info' => 'religion',
                        'success' => 'company',
                        'gray' => 'custom',
                    ]),

                IconEntry::make('is_repeat_yearly')
                    ->label('Berulang Tiap Tahun')
                    ->boolean(),

                TextEntry::make('source')
                    ->label('Sumber')
                    ->placeholder('-'),

                TextEntry::make('created_at')
                    ->label('Dibuat')
                    ->dateTime('Y-m-d H:i'),

                TextEntry::make('updated_at')
                    ->label('Diperbarui')
                    ->dateTime('Y-m-d H:i'),
            ]);
    }
}
