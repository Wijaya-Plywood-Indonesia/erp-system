<?php

namespace App\Filament\Resources\Pegawais\Schemas;

use App\Forms\Components\CompressedFileUpload; // Gunakan Komponen Custom
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Utilities\Get; // Import Get

class PegawaiForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([

                TextInput::make('kode_pegawai')
                    ->required()
                    ->unique(
                        table: 'pegawais',
                        column: 'kode_pegawai',
                        ignoreRecord: true
                    )
                    ->live(onBlur: true) // Supaya terbaca untuk nama file
                    ->validationMessages([
                        'unique' => 'Kode pegawai ini sudah digunakan.',
                    ]),

                TextInput::make('nama_pegawai')
                    ->required()
                    ->live(onBlur: true), // 🔥 WAJIB: Agar nama terbaca saat upload

                Textarea::make('alamat')
                    ->columnSpanFull(),

                TextInput::make('no_telepon_pegawai')
                    ->tel(),

                Select::make('jenis_kelamin_pegawai')
                    ->label('Jenis Kelamin')
                    ->options([
                        '0' => 'Perempuan',
                        '1' => 'Laki-laki',
                    ])
                    ->default('0')
                    ->required(),

                DatePicker::make('tanggal_masuk')
                    ->label('Tanggal Masuk')
                    ->required(),

                // ==========================================
                // FOTO PEGAWAI (AUTO RENAME & COMPRESS)
                // ==========================================
                CompressedFileUpload::make('foto')
                    ->label('Foto 3x4 atau 4x6')
                    ->disk('public')
                    ->directory('pegawai')
                    ->required()
                    ->imageEditor()
                    ->imageCropAspectRatio('3:4') // Rasio pas foto

                    // 🪄 PENAMAAN FILE: "K001_Budi-Santoso.webp"
                    ->fileName(function (Get $get) {
                        $kode = $get('kode_pegawai') ?: 'No-Code';
                        $nama = $get('nama_pegawai') ?: 'Tanpa-Nama';

                        // Gabungkan Kode dan Nama agar unik dan mudah dicari
                        return "{$kode}_{$nama}";
                    }),
            ]);
    }
}
