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
                    ->options(function ($component) {
                        // 1. Ambil list ID lahan yang berstatus 'sudah diterima'
                        $lahanIds = TempatKayu::query()
                            ->where('status', 'sudah diterima')
                            ->pluck('id_lahan')
                            ->unique()
                            ->toArray();

                        // 2. KUNCI PERBAIKAN EDIT: Ambil record saat ini melalui $component
                        $record = $component->getRecord();
                        if ($record && $record->id_lahan) {
                            $lahanIds[] = $record->id_lahan;
                        }

                        // 3. Kembalikan opsi lahan
                        return Lahan::query()
                            ->whereIn('id', array_unique($lahanIds))
                            ->get()
                            ->mapWithKeys(fn($lahan) => [
                                $lahan->id => "{$lahan->kode_lahan} - {$lahan->nama_lahan}",
                            ]);
                    })
                    ->searchable()
                    ->required()
                    ->live()
                    ->afterStateUpdated(function ($state, callable $set, $livewire, $component) {

                        // 1. Jika lahan dikosongkan
                        if (!$state) {
                            $set('id_jenis_kayu', null);
                            return;
                        }

                        $produksiId = $livewire->getOwnerRecord()?->id;

                        // 2. Validasi duplikat yang aman untuk Create & Edit
                        $query = PenggunaanLahanRotary::query()
                            ->where('id_produksi', $produksiId)
                            ->where('id_lahan', $state);

                        $record = $component->getRecord();
                        if ($record && $record->exists) {
                            $query->where('id', '!=', $record->id);
                        }

                        if ($query->exists()) {
                            Notification::make()
                                ->title('Data sudah terdaftar')
                                ->body('Lahan ini sudah digunakan pada produksi ini.')
                                ->danger()
                                ->send();

                            $set('id_lahan', null);
                            $set('id_jenis_kayu', null);
                            return;
                        }

                        // 3. AUTO-FILL JENIS KAYU (Sekarang dijamin jalan saat Create)
                        $detailTerbaru = DetailKayuMasuk::query()
                            ->where('id_lahan', $state)
                            ->latest('id')
                            ->first();

                        if ($detailTerbaru && $detailTerbaru->id_jenis_kayu) {
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
