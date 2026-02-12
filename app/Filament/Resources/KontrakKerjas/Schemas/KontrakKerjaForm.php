<?php

namespace App\Filament\Resources\KontrakKerjas\Schemas;

use App\Models\JabatanPerusahaan;
use App\Models\Pegawai;
use App\Models\Perusahaan;
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
                Select::make('pegawai_lookup')
                    ->label('Ambil Data Dari Pegawai')
                    ->searchable()
                    ->reactive() // 🔥 wajib
                    ->options(
                        Pegawai::query()
                            ->get()
                            ->mapWithKeys(fn($pegawai) => [
                                $pegawai->id => "{$pegawai->kode_pegawai} | {$pegawai->nama_pegawai}"
                            ])
                    )
                    ->dehydrated(false)
                    ->afterStateUpdated(function ($state, callable $set) {

                        $pegawai = Pegawai::find($state);
                        if (!$pegawai)
                            return;

                        $data = [
                            'kode' => $pegawai->kode_pegawai,
                            'nama' => $pegawai->nama_pegawai,
                            'alamat' => $pegawai->alamat,
                            'no_telepon' => $pegawai->no_telepon_pegawai,
                            'jenis_kelamin' => $pegawai->jenis_kelamin_pegawai == 1
                                ? 'Laki-Laki'
                                : 'Perempuan',
                            'tanggal_masuk' => optional($pegawai->tanggal_masuk)?->format('Y-m-d'),
                            'karyawan_di' => $pegawai->karyawan_di,
                            'alamat_perusahaan' => $pegawai->alamat_perusahaan,
                            'jabatan' => $pegawai->jabatan,
                            'nik' => $pegawai->nik,
                            'tempat_tanggal_lahir' => $pegawai->tempat_tanggal_lahir,
                        ];

                        foreach ($data as $field => $value) {
                            $set($field, $value);
                        }
                    }),

                TextInput::make('kode')
                    ->label('Kode Pegawai')
                    ->required(),

                TextInput::make('nama')
                    ->label('Nama Pegawai')
                    ->required(),

                Select::make('jenis_kelamin')
                    ->label('Jenis Kelamin')
                    ->options([
                        'Laki-Laki' => 'Laki-Laki',
                        'Perempuan' => 'Perempuan',
                    ])
                    ->reactive(),

                DatePicker::make('tanggal_masuk')
                    ->label('Tanggal Masuk'),

                // ==================
// SELECT PERUSAHAAN
// ==================
                Select::make('karyawan_di')
                    ->label('Karyawan Di')
                    ->options(Perusahaan::pluck('nama', 'id'))
                    ->searchable()
                    ->reactive()
                    ->required()
                    ->afterStateUpdated(function ($state, callable $set) {
                        // Reset jabatan ketika perusahaan berubah
                        $set('jabatan', null);

                        if ($state) {
                            // Auto set alamat perusahaan
                            $perusahaan = Perusahaan::find($state);
                            $set('alamat_perusahaan', $perusahaan?->alamat);
                        }
                    }),

                // ========================
// ALAMAT PERUSAHAAN (AUTO)
// ========================
                TextInput::make('alamat_perusahaan')
                    ->label('Alamat Perusahaan')
                    ->disabled()          // tidak bisa diubah manual
                    ->dehydrated()        // tetap disimpan ke database
                    ->reactive()
                    ->required()
                    ->visible(fn($get) => filled($get('karyawan_di')))
                    ->afterStateHydrated(function ($component, $state, $record) {
                        if ($record) {
                            $component->state($record->alamat_perusahaan);
                        }
                    }),

                // ========================
// SELECT JABATAN DINAMIS
// ========================
                Select::make('jabatan')
                    ->label('Jabatan')
                    ->options(function (callable $get) {
                        $perusahaanId = $get('karyawan_di');

                        if (!$perusahaanId) {
                            return [];
                        }

                        return JabatanPerusahaan::where('perusahaan_id', $perusahaanId)
                            ->pluck('nama_jabatan', 'id');
                    })
                    ->searchable()
                    ->reactive()
                    ->required()
                    ->visible(fn($get) => filled($get('karyawan_di'))),

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
