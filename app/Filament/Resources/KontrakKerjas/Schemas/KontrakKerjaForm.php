<?php

namespace App\Filament\Resources\KontrakKerjas\Schemas;

use Carbon\Carbon;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class KontrakKerjaForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('kode')
                    ->label('Kode Pegawai')
                    ->required(),

                TextInput::make('nama')
                    ->label('Nama Pegawai')
                    ->required(),

                TextInput::make('jenis_kelamin')
                    ->label('Jenis Kelamin'),

                DatePicker::make('tanggal_masuk')
                    ->label('Tanggal Masuk'),

                TextInput::make('karyawan_di')
                    ->label('Karyawan Di'),

                TextInput::make('alamat_perusahaan')
                    ->label('Alamat Perusahaan'),

                TextInput::make('jabatan')
                    ->label('Jabatan'),

                TextInput::make('nik')
                    ->label('NIK'),

                TextInput::make('tempat_tanggal_lahir')
                    ->label('Tempat / Tanggal Lahir'),

                Textarea::make('alamat')
                    ->label('Alamat')
                    ->columnSpanFull(),

                TextInput::make('no_telepon')
                    ->label('No Telepon')
                    ->tel(),


                // =============================
                //       INFORMASI KONTRAK
                // =============================

                DatePicker::make('kontrak_mulai')
                    ->label('Kontrak Mulai')
                    ->reactive()
                    ->afterStateUpdated(function ($state, callable $set, callable $get) {
                        $durasi = $get('durasi_kontrak');

                        if ($state && $durasi) {
                            $set('kontrak_selesai', Carbon::parse($state)->addDays($durasi * 30));
                        }
                    }),

                TextInput::make('durasi_kontrak')
                    ->label('Durasi Kontrak (bulan)')
                    ->numeric()
                    ->reactive()
                    ->afterStateUpdated(function ($state, callable $set, callable $get) {
                        $mulai = $get('kontrak_mulai');

                        if ($mulai && $state) {
                            $set('kontrak_selesai', Carbon::parse($mulai)->addDays($state * 30));
                        }
                    }),

                DatePicker::make('kontrak_selesai')
                    ->label('Kontrak Selesai')
                    ->readOnly(), // karena diisi otomatis


                DatePicker::make('tanggal_kontrak')
                    ->label('Tanggal Kontrak'),

                TextInput::make('no_kontrak')
                    ->label('Nomor Kontrak'),


                // =============================
                //       STATUS DOKUMEN
                // =============================

                Select::make('status_dokumen')
                    ->label('Status Dokumen')
                    ->options([
                        'draft' => 'Draft',
                        'dicetak' => 'Dicetak',
                        'ditandatangani' => 'Ditandatangani',
                    ])
                    ->default('draft')
                    ->required(),

                TextInput::make('bukti_ttd')
                    ->label('Bukti TTD (Path File)'),


                // =============================
                //  PENANGGUNG JAWAB (Snapshot)
                // =============================

                TextInput::make('dibuat_oleh')
                    ->label('Dibuat Oleh')
                    ->default(fn() => auth()->user()->name)   // otomatis isi saat create
                    ->disabled()                               // tidak bisa diubah manual
                    ->dehydrated(),

                // =============================
                //        STATUS KONTRAK
                // =============================

                Select::make('status_kontrak')
                    ->label('Status Kontrak')
                    ->options([
                        'active' => 'Aktif',
                        'soon' => 'Segera Habis',
                        'expired' => 'Expired',
                        'extended' => 'Extended',
                    ])
                    ->default('active')
                    ->required(),


                Textarea::make('keterangan')
                    ->label('Keterangan')
                    ->columnSpanFull(),
            ]);
    }
}
