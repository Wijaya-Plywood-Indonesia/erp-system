<?php

namespace App\Filament\Resources\KontrakKerjas\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class KontrakKerjaInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Data Pegawai')
                    ->schema([
                        Grid::make(2)->schema([
                            TextEntry::make('kode')->label('Kode Pegawai'),
                            TextEntry::make('nama')->label('Nama Pegawai'),

                            TextEntry::make('jenis_kelamin')->label('Jenis Kelamin'),
                            TextEntry::make('tanggal_masuk')->label('Tanggal Masuk'),

                            TextEntry::make('karyawan_di')->label('Karyawan Di'),
                            TextEntry::make('alamat_perusahaan')->label('Alamat Perusahaan'),

                            TextEntry::make('jabatan')->label('Jabatan'),
                            TextEntry::make('nik')->label('NIK'),

                            TextEntry::make('tempat_tanggal_lahir')->label('Tempat/Tanggal Lahir'),
                            TextEntry::make('no_telepon')->label('No. Telepon'),
                        ]),
                    ]),

                /** 🔹 INFORMASI KONTRAK */
                Section::make('Informasi Kontrak')
                    ->schema([
                        Grid::make(2)->schema([
                            TextEntry::make('kontrak_mulai')->label('Kontrak Mulai'),
                            TextEntry::make('kontrak_selesai')->label('Kontrak Selesai'),

                            TextEntry::make('durasi_kontrak')->label('Durasi Kontrak (hari)'),
                            TextEntry::make('tanggal_kontrak')->label('Tanggal Kontrak'),

                            TextEntry::make('no_kontrak')->label('Nomor Kontrak'),
                        ]),

                        TextEntry::make('status_dokumen')
                            ->label('Status Dokumen')
                            ->badge()
                            ->color(fn($state) => match ($state) {
                                'draft' => 'gray',
                                'dicetak' => 'warning',
                                'ditandatangani' => 'success',
                            }),
                        TextEntry::make('status_kontrak')
                            ->label('Status Kontrak')
                            ->badge()
                            ->formatStateUsing(fn($state) => match ($state) {
                                'active' => 'Aktif',
                                'soon' => 'Segera Habis',
                                'expired' => 'Kadaluarsa',
                                'extended' => 'Perpanjangan',
                                default => $state,
                            })
                            ->color(fn($state) => match ($state) {
                                'active' => 'success',
                                'soon' => 'warning',
                                'expired' => 'danger',
                                'extended' => 'gray',
                            }),

                        TextEntry::make('bukti_ttd')->label('Bukti TTD'),

                    ]),

                /** 🔹 PENANGGUNG JAWAB */
                Section::make('Penanggung Jawab')
                    ->schema([
                        Grid::make(2)->schema([
                            TextEntry::make('dibuat_oleh')->label('Dibuat Oleh'),
                            TextEntry::make('divalidasi_oleh')->label('Divalidasi Oleh'),
                        ]),
                    ]),

                /** 🔹 TIMESTAMP */
                Section::make('Riwayat')
                    ->schema([
                        Grid::make(2)->schema([
                            TextEntry::make('created_at')->label('Dibuat Pada')->dateTime(),
                            TextEntry::make('updated_at')->label('Diperbarui Pada')->dateTime(),
                        ]),
                    ]),
            ]);
    }
}
