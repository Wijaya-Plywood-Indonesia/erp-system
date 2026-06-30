<?php

namespace App\Filament\Resources\SupplierKayus\Schemas;

use App\Forms\Components\CompressedFileUpload; // Gunakan Komponen Custom
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Utilities\Get; // Import Get
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class SupplierKayuForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('nama_supplier')
                    ->required()
                    ->live(onBlur: true) // 🔥 WAJIB: Agar nama terbaca saat upload
                    ->afterStateUpdated(function ($state, $component) {
                        // Opsional: Validasi real-time jika perlu
                    }),

                TextInput::make('no_telepon')
                    ->tel()
                    ->nullable(),

                TextInput::make('nik')
                    ->label('Nomor Induk Kependudukan')
                    ->required()
                    ->minLength(16)
                    ->maxLength(16),

                // ==========================================
                // UPLOAD KTP (CUSTOM NAME & COMPRESS)
                // ==========================================
                CompressedFileUpload::make('upload_ktp')
                    ->label('Upload Foto KTP')
                    ->disk('public')
                    ->directory('suplier/ktp')
                    ->nullable()
                    ->imageEditor()

                    // ❌ Hapus preserveFilenames() karena kita mau ganti namanya

                    // ✅ Gunakan Logic Penamaan Custom
                    ->fileName(function (Get $get) {
                        // 1. Ambil Nama Supplier dari form
                        $nama = $get('nama_supplier');

                        // 2. Fallback jika nama kosong
                        $slug = $nama ? $nama : 'Tanpa-Nama';

                        // 3. Format Akhir: "KTP-Budi-Santoso"
                        // (Spasi otomatis jadi "-" oleh komponen CompressedFileUpload)
                        return "KTP-{$slug}";
                    }),

                Select::make('jenis_kelamin')
                    ->label('Jenis Kelamin')
                    ->options([
                        '0' => 'Perempuan',
                        '1' => 'Laki-laki',
                    ])
                    ->default('1')
                    ->native(false),

                Textarea::make('alamat'),

                Select::make('jenis_bank')
                    ->label('Jenis Bank')
                    ->options([
                        'Tunai' => 'Tunai',
                        'BCA' => 'BCA (Bank Central Asia)',
                        'BRI' => 'BRI (Bank Rakyat Indonesia)',
                        'BNI' => 'BNI (Bank Negara Indonesia)',
                        'Mandiri' => 'Mandiri',
                        'BSI' => 'BSI (Bank Syariah Indonesia)',
                        'CIMB' => 'CIMB Niaga',
                        'BTN' => 'BTN (Bank Tabungan Negara)',
                        'Lainnya' => 'Lainnya (Ketik manual)',
                    ])
                    ->searchable()
                    ->default('Tunai')
                    ->live(),

                TextInput::make('no_rekening')
                    ->nullable(),

                Select::make('status_supplier')
                    ->label('Status Supplier')
                    ->options([
                        0 => 'Tidak Aktif',
                        1 => 'Aktif',
                    ])
                    ->default('1')
                    ->native(false),
            ]);
    }
}
