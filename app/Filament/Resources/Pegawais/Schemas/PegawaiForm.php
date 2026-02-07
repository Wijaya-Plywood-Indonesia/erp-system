<?php

namespace App\Filament\Resources\Pegawais\Schemas;

use App\Forms\Components\CompressedFileUpload; // Gunakan Komponen Custom
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Utilities\Get; // Import Get

class PegawaiForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                // =====================================================
                // SECTION: INFORMASI DASAR PEGAWAI
                // =====================================================
                Section::make('Informasi Dasar Pegawai')
                    ->description('Data identitas utama pegawai.')
                    ->columns(2)
                    ->schema([

                        TextInput::make('kode_pegawai')
                            ->label('Kode Pegawai')
                            ->required()
                            ->unique(
                                table: 'pegawais',
                                column: 'kode_pegawai',
                                ignoreRecord: true
                            )
                            ->live(onBlur: true),

                        TextInput::make('nama_pegawai')
                            ->label('Nama Pegawai')
                            ->required()
                            ->live(onBlur: true),

                        Textarea::make('alamat')
                            ->label('Alamat')
                            ->columnSpanFull(),

                        TextInput::make('no_telepon_pegawai')
                            ->label('Nomor Telepon')
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

                    ]),

                // =====================================================
                // SECTION: INFORMASI PEKERJAAN
                // =====================================================
                Section::make('Informasi Pekerjaan')
                    ->description('Detail pekerjaan dan posisi pegawai.')
                    ->columns(2)
                    ->schema([

                        TextInput::make('karyawan_di')
                            ->label('Karyawan di'),

                        TextInput::make('alamat_perusahaan')
                            ->label('Alamat Perusahaan'),

                        TextInput::make('jabatan')
                            ->label('Jabatan / Posisi')
                            ->columnSpanFull(),
                    ]),

                // =====================================================
                // SECTION: INFORMASI KEPENDUDUKAN
                // =====================================================
                Section::make('Informasi Kependudukan')
                    ->description('Data identitas resmi pegawai.')
                    ->columns(2)
                    ->schema([

                        TextInput::make('nik')
                            ->label('NIK')
                            ->numeric()
                            ->minLength(16)
                            ->maxLength(16),

                        TextInput::make('tempat_tanggal_lahir')
                            ->label('Tempat & Tanggal Lahir')
                            ->placeholder('Contoh: Surabaya, 12 Agustus 1999')
                            ->columnSpanFull(),
                    ]),

                // =====================================================
                // SECTION: DOKUMEN PENDUKUNG
                // =====================================================
                Section::make('Dokumen Pendukung')
                    ->description('Unggah dokumen yang diperlukan.')
                    ->columns(2)
                    ->schema([

                        CompressedFileUpload::make('scan_ktp')
                            ->label('Scan KTP')
                            ->disk('public')
                            ->directory('pegawai/ktp')
                            ->imageEditor()
                            ->fileName(function (Get $get) {
                                return ($get('kode_pegawai') ?: 'NoCode') . '_KTP';
                            }),

                        CompressedFileUpload::make('scan_kk')
                            ->label('Scan KK')
                            ->disk('public')
                            ->directory('pegawai/kk')
                            ->imageEditor()
                            ->fileName(function (Get $get) {
                                return ($get('kode_pegawai') ?: 'NoCode') . '_KK';
                            }),

                        CompressedFileUpload::make('foto')
                            ->label('Foto Pegawai')
                            ->disk('public')
                            ->directory('pegawai')
                            ->imageEditor()
                            ->imageCropAspectRatio('3:4')

                            ->fileName(function (Get $get) {
                                $kode = $get('kode_pegawai') ?: 'NoCode';
                                $nama = $get('nama_pegawai') ?: 'TanpaNama';
                                return "{$kode}_{$nama}";
                            }),
                    ]),
            ]);
    }
}
