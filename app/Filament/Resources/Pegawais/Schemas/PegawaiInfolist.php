<?php

namespace App\Filament\Resources\Pegawais\Schemas;

use Carbon\Carbon;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PegawaiInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                // =====================================================
                // SECTION: INFORMASI DASAR PEGAWAI
                // =====================================================
                Section::make('Informasi Dasar Pegawai')
                    ->columns(2)
                    ->schema([

                        TextEntry::make('kode_pegawai')
                            ->label('Kode Pegawai'),

                        TextEntry::make('nama_pegawai')
                            ->label('Nama Pegawai'),

                        TextEntry::make('no_telepon_pegawai')
                            ->label('Telepon'),

                        TextEntry::make('jenis_kelamin_pegawai')
                            ->label('Jenis Kelamin')
                            ->formatStateUsing(
                                fn($state) =>
                                $state == 1 ? 'Laki-laki' : 'Perempuan'
                            ),

                        TextEntry::make('tanggal_masuk')
                            ->label('Tanggal Masuk')
                            ->formatStateUsing(function ($state) {
                                $tanggal = Carbon::parse($state);
                                $now = Carbon::now();

                                $lama = $tanggal->diff($now);

                                $durasi = "{$lama->y} tahun {$lama->m} bulan";

                                return $tanggal->format('d M Y') . " (bergabung $durasi)";
                            }),

                        ImageEntry::make('foto')
                            ->label('Foto Pegawai')
                            ->disk('public')
                            ->square()
                            ->columnSpanFull(),
                    ]),

                // =====================================================
                // SECTION: INFORMASI PEKERJAAN
                // =====================================================
                Section::make('Informasi Pekerjaan')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('karyawan_di')
                            ->label('Karyawan di'),

                        TextEntry::make('alamat_perusahaan')
                            ->label('Alamat Perusahaan'),

                        TextEntry::make('jabatan')
                            ->label('Jabatan / Posisi')
                            ->columnSpanFull(),
                    ]),

                // =====================================================
                // SECTION: INFORMASI KEPENDUDUKAN
                // =====================================================
                Section::make('Informasi Kependudukan')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('nik')
                            ->label('NIK'),

                        TextEntry::make('tempat_tanggal_lahir')
                            ->label('Tempat & Tanggal Lahir')
                            ->columnSpanFull(),
                    ]),

                // =====================================================
                // SECTION: DOKUMEN PENDUKUNG
                // =====================================================
                Section::make('Dokumen Pendukung')
                    ->columns(2)
                    ->schema([

                        ImageEntry::make('scan_ktp')
                            ->label('Scan KTP')
                            ->disk('public')
                            ->hidden(fn($state) => !$state),

                        ImageEntry::make('scan_kk')
                            ->label('Scan KK')
                            ->disk('public')
                            ->hidden(fn($state) => !$state),

                        ImageEntry::make('foto')
                            ->label('Foto Pegawai')
                            ->disk('public')
                            ->square()
                            ->columnSpanFull(),
                    ]),

                // =====================================================
                // TANGGAL PEMBUATAN DATA
                // =====================================================
                Section::make('Metadata')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('created_at')
                            ->label('Dibuat Pada')
                            ->dateTime(),

                        TextEntry::make('updated_at')
                            ->label('Terakhir Diperbarui')
                            ->dateTime(),
                    ]),
            ]);
    }
}
