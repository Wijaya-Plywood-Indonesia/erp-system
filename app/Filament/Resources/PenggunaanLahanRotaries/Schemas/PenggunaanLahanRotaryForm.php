<?php

namespace App\Filament\Resources\PenggunaanLahanRotaries\Schemas;

use App\Models\JenisKayu;
use App\Models\Lahan;
use App\Models\PenggunaanLahanRotary;
use App\Models\DetailKayuMasuk; // Tambahkan ini
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use App\Models\TempatKayu;
use Filament\Notifications\Notification;

class PenggunaanLahanRotaryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('id_lahan')
                    ->label('Lahan')
                    ->options(function () {
                        $lahanIds = TempatKayu::query()
                            ->where('status', 'sudah diterima')
                            ->pluck('id_lahan')
                            ->unique();

                        return Lahan::query()
                            ->whereIn('id', $lahanIds)
                            ->get()
                            ->mapWithKeys(fn($lahan) => [
                                $lahan->id => "{$lahan->kode_lahan} - {$lahan->nama_lahan}",
                            ]);
                    })
                    ->searchable()
                    ->required()
                    ->live() // Memicu re-render saat lahan diubah
                    ->afterStateUpdated(function ($state, callable $get, callable $set, $livewire) {
                        
                        // 1. Jika lahan dikosongkan, kosongkan juga jenis kayunya
                        if (!$state) {
                            $set('id_jenis_kayu', null);
                            return;
                        }

                        $produksiId = $livewire->ownerRecord->id;

                        $exists = PenggunaanLahanRotary::query()
                            ->where('id_produksi', $produksiId)
                            ->where('id_lahan', $state)
                            ->exists();

                        // 2. Validasi duplikat
                        if ($exists) {
                            Notification::make()
                                ->title('Data sudah terdaftar')
                                ->body('Lahan ini sudah digunakan pada produksi ini.')
                                ->danger()
                                ->send();

                            $set('id_lahan', null);
                            $set('id_jenis_kayu', null);
                            return;
                        }

                        // 3. AUTO-FILL JENIS KAYU
                        // Cari detail kayu masuk terakhir untuk lahan yang dipilih
                        $detailTerbaru = DetailKayuMasuk::query()
                            ->where('id_lahan', $state)
                            ->latest('id')
                            ->first();

                        if ($detailTerbaru && $detailTerbaru->id_jenis_kayu) {
                            // Set nilai jenis kayu sesuai dengan ID yang ditemukan
                            $set('id_jenis_kayu', $detailTerbaru->id_jenis_kayu);
                        } else {
                            $set('id_jenis_kayu', null);
                        }
                    }),
                    
                Select::make('id_jenis_kayu')
                    ->label('Jenis Kayu')
                    ->options(
                        JenisKayu::query()
                            ->get()
                            ->mapWithKeys(function ($JenisKayu) {
                                return [
                                    $JenisKayu->id => "{$JenisKayu->kode_kayu} - {$JenisKayu->nama_kayu}",
                                ];
                            })
                    )
                    ->searchable()
                    ->required()
                    ->disabled()
                    ->dehydrated(),
            ]);
    }
}