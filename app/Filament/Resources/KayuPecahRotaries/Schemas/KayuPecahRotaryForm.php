<?php

namespace App\Filament\Resources\KayuPecahRotaries\Schemas;

use App\Models\PenggunaanLahanRotary;
use App\Forms\Components\CompressedFileUpload; // Gunakan Komponen Custom
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Utilities\Get; // Import Get
use Illuminate\Support\Str;

class KayuPecahRotaryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([

                Select::make('id_penggunaan_lahan')
                    ->label('Kode Lahan')
                    ->options(function (RelationManager $livewire) {
                        $parent = $livewire->getOwnerRecord();
                        $idProduksi = $parent->id;

                        return PenggunaanLahanRotary::with('lahan')
                            ->where('id_produksi', $idProduksi)
                            ->get()
                            ->mapWithKeys(function ($item) {
                                return [$item->id => $item->lahan->kode_lahan ?? 'Tanpa Kode'];
                            });
                    })
                    ->searchable()
                    ->preload()
                    ->required()
                    ->live(), // 🔥 WAJIB: Agar perubahan terdeteksi oleh FileUpload

                TextInput::make('ukuran')
                    ->label('Diameter')
                    ->required()
                    ->numeric()
                    ->live(onBlur: true), // 🔥 WAJIB: Agar data terbaca setelah mengetik

                // ==========================================
                // FOTO (AUTO RENAME & COMPRESS)
                // ==========================================
                CompressedFileUpload::make('foto')
                    ->label('Foto Kayu Pecah Dengan Meteran')
                    ->disk('public')
                    ->directory('kayu_pecah')
                    ->required()
                    ->imageEditor()

                    // 🪄 LOGIKA PENAMAAN FILE
                    ->fileName(function (Get $get) {
                        // 1. Ambil Data Ukuran
                        $ukuran = $get('ukuran') ?: '0';

                        // 2. Ambil Kode Lahan (Query DB berdasarkan ID yang dipilih)
                        $kodeLahan = 'Tanpa-Lahan';
                        $idPenggunaan = $get('id_penggunaan_lahan');

                        if ($idPenggunaan) {
                            $lahanRotary = PenggunaanLahanRotary::with('lahan')->find($idPenggunaan);
                            if ($lahanRotary && $lahanRotary->lahan) {
                                $kodeLahan = $lahanRotary->lahan->kode_lahan;
                            }
                        }

                        // 3. Gabungkan: "Lahan-A1_Diameter-50"
                        // Helper Str::slug otomatis mengubah spasi/"/" menjadi "-"
                        return "{$kodeLahan}_Diameter-{$ukuran}";
                    }),
            ]);
    }
}
