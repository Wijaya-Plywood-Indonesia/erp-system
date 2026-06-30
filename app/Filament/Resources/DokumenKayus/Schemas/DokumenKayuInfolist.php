<?php

namespace App\Filament\Resources\DokumenKayus\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class DokumenKayuInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Identitas')
                    ->schema([
                        TextEntry::make('nama_legal')
                            ->label('Nama Legal'),

                        TextEntry::make('nama_tempat')
                            ->label('Nama Tempat'),
                        TextEntry::make('alamat_lengkap')
                            ->label('Alamat Lengkap'),
                    ])
                    ->columns(2),

                Section::make('Dokumen')
                    ->schema([
                        TextEntry::make('dokumen_legal')
                            ->label('Dokumen Legal'),
                        TextEntry::make('no_dokumen_legal')
                            ->label('No Dokumen'),
                        TextEntry::make('upload_dokumen')
                            ->label('Upload Dokumen')
                            ->badge()
                            ->formatStateUsing(fn($state) => $state ? 'Lihat File' : 'Kosong')
                            ->color(fn($state) => $state ? 'success' : 'danger')
                            ->url(fn($state) => $state ? asset('storage/' . $state) : null)
                            ->openUrlInNewTab(),

                        TextEntry::make('upload_ktp')
                            ->label('Upload KTP')
                            ->badge()
                            ->formatStateUsing(fn($state) => $state ? 'Lihat File' : 'Kosong')
                            ->color(fn($state) => $state ? 'success' : 'danger')
                            ->url(fn($state) => $state ? asset('storage/' . $state) : null)
                            ->openUrlInNewTab(),

                        TextEntry::make('foto_lokasi')
                            ->label('Foto Lokasi')
                            ->badge()
                            ->formatStateUsing(fn($state) => $state ? 'Lihat Foto' : 'Kosong')
                            ->color(fn($state) => $state ? 'success' : 'danger')
                            ->url(fn($state) => $state ? asset('storage/' . $state) : null)
                            ->openUrlInNewTab(),
                    ])
                    ->columns(2),

                Section::make('Data Lokasi')
                    ->schema([
                        TextEntry::make('latitude')
                            ->numeric()
                            ->label('Latitude'),
                        TextEntry::make('longitude')
                            ->numeric()
                            ->label('Longitude'),
                    ])
                    ->columns(2),

                Section::make('Metadata')
                    ->schema([
                        TextEntry::make('created_at')
                            ->label('Dibuat Pada')
                            ->dateTime(),
                        TextEntry::make('updated_at')
                            ->label('Diperbarui Pada')
                            ->dateTime(),
                    ])
                    ->columns(2),
            ]);
    }
}
