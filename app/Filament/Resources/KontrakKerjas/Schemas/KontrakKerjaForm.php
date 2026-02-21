<?php

namespace App\Filament\Resources\KontrakKerjas\Schemas;

use App\Forms\Components\CompressedFileUpload;
use App\Helpers\HolidayHelper;
use App\Models\JabatanPerusahaan;
use App\Models\Pegawai;
use Carbon\Carbon;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class KontrakKerjaForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([

            /* ============================================================
             | 2. DATA PEGAWAI SNAPSHOT
             ============================================================ */
            Section::make('Data Pegawai')
                ->schema([
                    Select::make('pegawai_lookup')
                        ->columnSpanFull()
                        ->label('Ambil Data Dari Pegawai')
                        ->searchable()
                        ->reactive()
                        ->options(self::pegawaiOptions())
                        ->dehydrated(false)
                        ->afterStateUpdated(fn($state, $set) => self::fillPegawaiSnapshot($state, $set)),

                    TextInput::make('kode')->label('Kode Pegawai')->required(),
                    TextInput::make('nama')->label('Nama Pegawai')->required(),
                    Select::make('jenis_kelamin')
                        ->label('Jenis Kelamin')
                        ->options([
                            'Laki-Laki' => 'Laki-Laki',
                            'Perempuan' => 'Perempuan',
                        ])
                        ->live(),
                    DatePicker::make('tanggal_masuk')->label('Tanggal Masuk'),

                    TextInput::make('nik')->label('NIK'),
                    TextInput::make('tempat_tanggal_lahir')->label('Tempat / Tanggal Lahir'),

                    Textarea::make('alamat')->label('Alamat')->columnSpanFull(),
                    TextInput::make('no_telepon')
                        ->label('No Telepon')
                        ->tel()
                        ->live(),
                ])
                ->columns(2),

            /* ============================================================
             | 3. LOOKUP PERUSAHAAN + JABATAN
             ============================================================ */
            Section::make('Perusahaan & Jabatan')
                ->schema([
                    Select::make('perusahaan_jabatan_lookup')
                        ->label('Pilih Perusahaan + Jabatan')
                        ->hint("Gunakan Fitur Ini Jika Ada Perubahan")
                        ->searchable()
                        ->reactive()
                        ->options(self::jabatanPerusahaanOptions())
                        ->dehydrated(false)
                        ->afterStateUpdated(fn($state, $set) => self::fillPerusahaanSnapshot($state, $set))
                        ->columnSpanFull(),

                    TextInput::make('karyawan_di')
                        ->label('Perusahaan')
                        ->disabled()
                        ->dehydrated(),

                    TextInput::make('alamat_perusahaan')
                        ->label('Alamat Perusahaan')
                        ->disabled()
                        ->dehydrated(),

                    TextInput::make('jabatan')
                        ->label('Jabatan')
                        ->disabled()
                        ->dehydrated(),
                ])
                ->columns(3),

            /* ============================================================
             | 4. INFORMASI KONTRAK
             ============================================================ */
            Section::make('Informasi Kontrak')
                ->schema([
                    DatePicker::make('kontrak_mulai')
                        ->label('Kontrak Mulai')
                        ->native(false)
                        ->displayFormat('d/m/Y')
                        ->format('Y-m-d')
                        ->suffixIcon('heroicon-m-calendar-days')
                        ->suffixIconColor('primary')
                        ->closeOnDateSelection()
                        ->live()
                        // Menambahkan HelperText secara dinamis menggunakan HolidayHelper
                        ->helperText(function ($state) {
                            if (!$state) return null;

                            $dateString = \Carbon\Carbon::parse($state)->toDateString();

                            // 1. Cek Libur Nasional menggunakan HolidayHelper statis
                            if (HolidayHelper::isHoliday($dateString)) {
                                $holiday = HolidayHelper::getHoliday($dateString);
                                return new \Illuminate\Support\HtmlString(
                                    "<span class='text-danger-600 font-medium'>Tanggal ini adalah Hari Libur Nasional: <strong>{$holiday->nama_libur}</strong></span>"
                                );
                            }

                            // 2. Cek apakah Hari Minggu menggunakan Carbon
                            if (\Carbon\Carbon::parse($state)->isSunday()) {
                                return new \Illuminate\Support\HtmlString(
                                    "<span class='text-warning-600'> Tanggal yang dipilih adalah hari Minggu.</span>"
                                );
                            }

                            return "Tanggal kontrak dimulai pada hari kerja.";
                        })
                        ->afterStateUpdated(function ($state, $set, $get) {
                            if ($state) {
                                $cleanDate = \Carbon\Carbon::parse($state)->format('Y-m-d');

                                // Logika Update Selesai & Nomor Kontrak
                                self::updateTanggalSelesai($cleanDate, $get('durasi_kontrak'), $set);
                                $set('tanggal_kontrak', "Malang, " . $cleanDate);

                                if (!$get('no_kontrak')) {
                                    $set('no_kontrak', \App\Services\NomorKontrakService::generate());
                                }
                            }
                        }),

                    Select::make('durasi_kontrak')
                        ->label('Durasi Kontrak (Hari)')
                        ->options([
                            30 => '30 Hari',
                            60 => '60 Hari',
                            90 => '90 Hari',
                        ])
                        ->native(false)
                        ->live()
                        ->afterStateUpdated(function ($state, $set, $get) {
                            if ($get('kontrak_mulai') && $state) {
                                $cleanDate = \Carbon\Carbon::parse($get('kontrak_mulai'))->format('Y-m-d');
                                self::updateTanggalSelesai($cleanDate, $state, $set);
                            }
                        }),

                    DatePicker::make('kontrak_selesai')
                        ->label('Kontrak Selesai')
                        ->native(false)
                        ->displayFormat('d/m/Y')
                        ->format('Y-m-d')
                        ->readOnly(),

                    TextInput::make('tanggal_kontrak')
                        ->label('Tanggal Kontrak')
                        ->placeholder('Malang, YYYY-MM-DD')
                        ->readOnly(),

                    TextInput::make('no_kontrak')
                        ->label('Nomor Kontrak'),
                ])
                ->columns(2),

            Section::make('Metadata Kontrak')
                ->schema([
                    TextInput::make('dibuat_oleh')
                        ->label('Dibuat Oleh')
                        ->default(fn() => auth()->user()->name)
                        ->disabled()
                        ->dehydrated(),

                    Select::make('status_kontrak')
                        ->label('Status Kontrak')
                        ->options([
                            'active' => 'Aktif',
                            'soon' => 'Segera Habis',
                            'expired' => 'Expired',
                            'extended' => 'Extended',
                        ])
                        ->default('active'),
                ])
                ->columns(2),
        ]);
    }

    /* ============================================================
     | STATIC HELPERS
     ============================================================ */

    /** Lookup Pegawai */
    private static function pegawaiOptions()
    {
        return Pegawai::query()
            ->orderBy('nama_pegawai')
            ->get()
            ->mapWithKeys(fn($p) => [
                $p->id => "{$p->kode_pegawai} | {$p->nama_pegawai}"
            ]);
    }

    /** Lookup Perusahaan + Jabatan */
    private static function jabatanPerusahaanOptions()
    {
        return cache()->rememberForever('lookup_jabatan_perusahaan', function () {
            return JabatanPerusahaan::with('perusahaan')
                ->orderBy('perusahaan_id')
                ->orderBy('nama_jabatan')
                ->get()
                ->mapWithKeys(fn($jab) => [
                    $jab->id => "{$jab->perusahaan->nama} – {$jab->perusahaan->alamat} – {$jab->nama_jabatan}"
                ]);
        });
    }

    /** Fill snapshot dari Pegawai */
    private static function fillPegawaiSnapshot($id, $set)
    {
        $pegawai = Pegawai::find($id);
        if (!$pegawai)
            return;

        $tanggalMasuk = self::parseDate($pegawai->tanggal_masuk);

        $jenisKelamin = match ((int) $pegawai->jenis_kelamin_pegawai) {
            1 => 'Laki-Laki',
            0 => 'Perempuan',
            default => null,
        };

        $set('kode', $pegawai->kode_pegawai);
        $set('nama', $pegawai->nama_pegawai);
        $set('alamat', $pegawai->alamat);
        $set('jenis_kelamin', $jenisKelamin);
        $set('tanggal_masuk', $tanggalMasuk);
        $set('karyawan_di', $pegawai->karyawan_di);
        $set('alamat_perusahaan', $pegawai->alamat_perusahaan);
        $set('jabatan', $pegawai->jabatan);
        $set('nik', $pegawai->nik);
        $set('no_telepon', $pegawai->no_telepon_pegawai);
        $set('tempat_tanggal_lahir', $pegawai->tempat_tanggal_lahir);
    }

    /** Fill snapshot dari JabatanPerusahaan */
    private static function fillPerusahaanSnapshot($id, $set)
    {
        $jab = JabatanPerusahaan::with('perusahaan')->find($id);
        if (!$jab)
            return;

        $set('karyawan_di', $jab->perusahaan->nama);
        $set('alamat_perusahaan', $jab->perusahaan->alamat);
        $set('jabatan', $jab->nama_jabatan);
    }

    /** Aman parse tanggal dari DB */
    private static function parseDate($val)
    {
        if (empty($val) || $val === "0") {
            return null;
        }

        try {
            return Carbon::parse($val)->format('Y-m-d');
        } catch (\Exception $e) {
            return null;
        }
    }

    private static function updateTanggalSelesai($mulai, $durasi, $set)
    {
        if (!$mulai || !$durasi)
            return;

        try {
            $tanggalSelesai = Carbon::parse($mulai)->addDays((int) $durasi)->format('Y-m-d');

            $set('kontrak_selesai', $tanggalSelesai);
        } catch (\Exception $e) {
        }
    }
}
