<?php

namespace App\Filament\Resources\KayuPecahRotaries\Schemas;

use App\Models\PenggunaanLahanRotary;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Slider;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

use Illuminate\Support\Str;

use Spatie\Image\Image;
use Spatie\Image\Manipulations; // ⬅️ ini yang penting
use Illuminate\Support\Facades\Storage;
use PhpParser\Node\Stmt\Label;

class KayuPecahRotaryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([

                Select::make('id_penggunaan_lahan')
                    ->label('Kode Lahan')
                    ->options(function (RelationManager $livewire) {
                        $parent = $livewire->getOwnerRecord(); // ← ambil parent record (ProduksiRotary)
                        $idProduksi = $parent->id; // gunakan id produksinya

                        return PenggunaanLahanRotary::with('lahan')
                            ->where('id_produksi', $idProduksi)
                            ->get()
                            ->mapWithKeys(function ($item) {
                                return [$item->id => $item->lahan->kode_lahan ?? 'Tanpa Kode'];
                            });
                    })
                    ->required(),
                TextInput::make('ukuran')
                    ->label('Diameter')
                    ->required()
                    ->numeric(),

                FileUpload::make('foto')
                    ->label('Foto Kayu Pecah Dengan Meteran')
                    ->image()
                    ->disk('public')
                    ->directory('kayu_pecah')
                    //->maxSize(4096)
                    ->required()
                    ->imageEditor()
                    ->getUploadedFileNameForStorageUsing(function (TemporaryUploadedFile $file, $get) {
                        $nama = $get('id_produksi') ?? 'produksi_rotaries';
                        $nama_slug = Str::slug($nama);
                        return $nama_slug . '.' . $file->getClientOriginalExtension();
                    }),
                // Slider::make('diameter')
                //     ->label('Diameter (cm)')
                //     ->range(minValue: 0, maxValue: 100)
                //     ->step(1) // ⬅️ Membatasi ke angka bulat saja
                //     ->tooltips()
            ]);
    }
}
