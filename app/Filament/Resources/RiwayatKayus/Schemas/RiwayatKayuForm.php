<?php

namespace App\Filament\Resources\RiwayatKayus\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Schemas\Schema;
use Filament\Forms\Components\Select;
use App\Models\TempatKayu;
class RiwayatKayuForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                DatePicker::make('tanggal_masuk')
                    ->native(false)
                    ->default(now())
                    ->required()
                    ->displayFormat('d/m/Y'),
                DatePicker::make('tanggal_digunakan')
                    ->native(false)
                    ->default(now()->startOfMonth())
                    ->required()
                    ->displayFormat('d/m/Y'),
                DatePicker::make('tanggal_habis')
                    ->native(false)
                    ->default(now()->startOfMonth())
                    ->required()
                    ->displayFormat('d/m/Y'),
                Select::make('id_tempat_kayu')
                    ->label('Tempat Kayu')
                    ->options(
                        TempatKayu::with('kayuMasuk.detailMasukanKayu')->get()
                            ->mapWithKeys(function ($kayu) {
                                $kodelahan = $kayu->lahan?->kode_lahan ?? '-';
                                $jumlahBatang = $kayu->jumlah_batang ?? 0;

                                $diameter_cm = $kayu->kayuMasuk?->detailMasukanKayu?->first()?->diameter ?? 0;

                                // Hitung kubikasi
                                $kubikasi_cm3 = $diameter_cm * $jumlahBatang * 0.785 * 1000000;
                                $kubikasi_cm3 = round($kubikasi_cm3, 2);

                                return [
                                    $kayu->id => "{$kodelahan} - {$jumlahBatang} batang - {$kubikasi_cm3} cm³"
                                ];
                            })
                            ->toArray()
                    )
                    ->searchable()
                    ->required(),
            ]);
    }
}
