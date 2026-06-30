<?php

namespace App\Filament\Resources\DetailKayuMasuks\Schemas;

use App\Models\DetailKayuMasuk;
use App\Models\JenisKayu;
use App\Models\Lahan;
use Filament\Notifications\Notification;
use Filament\Schemas\Schema;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;

class DetailKayuMasukForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([

                // ==========================
                // LAHAN → Ambil lahan terakhir
                // ==========================
                Select::make('id_lahan')
                    ->label('Lahan')
                    ->options(
                        Lahan::query()
                            ->get()
                            ->mapWithKeys(fn($lahan) => [
                                $lahan->id => "{$lahan->kode_lahan} - {$lahan->nama_lahan}",
                            ])
                    )
                    ->default(fn() => DetailKayuMasuk::latest('id')->value('id_lahan') ?? 1)
                    ->searchable()
                    ->required(),

                // ==========================
                // PANJANG → ambil panjang terakhir berdasarkan lahan terakhir
                // ==========================
                Select::make('panjang')
                    ->label('Panjang')
                    ->options([
                        130 => '130 cm',
                        260 => '260 cm',
                        0 => 'Tidak Diketahui',
                    ])
                    ->required()
                    ->default(function () {
                        $lastLahan = DetailKayuMasuk::latest('id')->value('id_lahan');
                        if (!$lastLahan)
                            return 0;

                        return DetailKayuMasuk::where('id_lahan', $lastLahan)
                            ->latest('id')
                            ->value('panjang') ?? 0;
                    })
                    ->searchable()
                    ->native(false),

                // GRADE → ambil grade terakhir
                // ==========================
                Select::make('grade')
                    ->label('Grade')
                    ->options([
                        1 => 'Grade A',
                        2 => 'Grade B',
                    ])
                    ->required()
                    ->default(fn() => DetailKayuMasuk::latest('id')->value('grade') ?? 1)
                    ->native(false)
                    ->searchable()
                    ->reactive()
                    ->afterStateHydrated(function ($state, $set) {
                        $saved = request()->cookie('filament_local_storage_detail_kayu_masuk.grade')
                            ?? optional(json_decode(request()->header('X-Filament-Local-Storage'), true))['detail_kayu_masuk.grade']
                            ?? null;

                        if ($saved && in_array($saved, [1, 2])) {
                            $set('grade', (int) $saved);
                        }
                    })
                    ->afterStateUpdated(function ($state) {
                        cookie()->queue('filament_local_storage_detail_kayu_masuk.grade', $state, 60 * 24 * 30);
                    }),

                // ==========================
                // JENIS KAYU → ambil jenis kayu terakhir
                // ==========================
                Select::make('id_jenis_kayu')
                    ->label('Jenis Kayu')
                    ->options(
                        JenisKayu::query()
                            ->get()
                            ->mapWithKeys(fn($jenis) => [
                                $jenis->id => "{$jenis->kode_kayu} - {$jenis->nama_kayu}",
                            ])
                    )
                    ->default(fn() => DetailKayuMasuk::latest('id')->value('id_jenis_kayu') ?? 1)
                    ->searchable()
                    ->required(),

                // ==========================
                // DIAMETER
                // ==========================

                TextInput::make('diameter')
                    ->label('Diameter (cm)')
                    ->placeholder('Masukkan Diameter dalam cm')
                    ->required()
                    ->numeric(),

                TextInput::make('jumlah_batang')
                    ->label('Jumlah Batang')
                    ->required()
                    ->default(1)
                    ->numeric(),
            ]);
    }
}
