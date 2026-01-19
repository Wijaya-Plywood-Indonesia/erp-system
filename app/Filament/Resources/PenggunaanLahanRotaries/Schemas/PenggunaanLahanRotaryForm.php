<?php

namespace App\Filament\Resources\PenggunaanLahanRotaries\Schemas;

use App\Models\JenisKayu;
use App\Models\Lahan;
use App\Models\PenggunaanLahanRotary;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

use Filament\Notifications\Notification;

class PenggunaanLahanRotaryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('id_lahan')
                    ->label('Lahan')
                    ->options(
                        Lahan::query()
                            ->get()
                            ->mapWithKeys(fn($lahan) => [
                                $lahan->id => "{$lahan->kode_lahan} - {$lahan->nama_lahan}",
                            ])
                    )
                    ->searchable()
                    ->required()
                    ->live()
                    ->afterStateUpdated(function ($state, callable $get, callable $set, $livewire) {

                        if (!$state) {
                            return;
                        }

                        $produksiId = $livewire->ownerRecord->id;

                        $exists = PenggunaanLahanRotary::query()
                            ->where('id_produksi', $produksiId)
                            ->where('id_lahan', $state)
                            ->exists();

                        if ($exists) {
                            Notification::make()
                                ->title('Data sudah terdaftar')
                                ->body('Lahan ini sudah digunakan pada produksi ini.')
                                ->danger()
                                ->send();

                            $set('id_lahan', null);
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
                    ->required(),
                TextInput::make('jumlah_batang')
                    ->required()
                    ->numeric()
                    ->default(0),
            ]);
    }
}
