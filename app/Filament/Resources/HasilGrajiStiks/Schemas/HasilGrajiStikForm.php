<?php

namespace App\Filament\Resources\HasilGrajiStiks\Schemas;

use App\Models\ModalGrajiStik;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Utilities\Set;

class HasilGrajiStikForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                // 1. Pilih Modal (Nomor Palet)
                Select::make('id_modal_graji_stiks')
                    ->label('Pilih Modal (Palet)')
                    ->options(function ($get, $livewire) {
                        $grajiStikId = $livewire->ownerRecord->id ?? $get('id_graji_stiks');
                        if (!$grajiStikId) return [];

                        return ModalGrajiStik::query()
                            ->where('id_graji_stiks', $grajiStikId)
                            ->with('ukuran')
                            ->get()
                            ->mapWithKeys(fn($m) => [
                                $m->id => "Palet {$m->nomor_palet} - Ukuran: " . ($m->ukuran->dimensi ?? '-')
                            ]);
                    })
                    ->live()
                    ->searchable()
                    ->required()
                    // LOGIKA REAKTIF: Saat palet dipilih, cari jumlah bahan dan set ke kolom info
                    ->afterStateUpdated(function ($state, Set $set) {
                        if ($state) {
                            $modal = ModalGrajiStik::find($state);
                            $set('jumlah_bahan_display', $modal?->jumlah_bahan . ' Pcs');
                        } else {
                            $set('jumlah_bahan_display', null);
                        }
                    }),

                // 2. Info Bahan (Sekarang akan terisi otomatis via afterStateUpdated)
                TextInput::make('jumlah_bahan_display')
                    ->label('Jumlah Bahan Awal (Modal)')
                    ->disabled()
                    ->dehydrated(false)
                    ->placeholder('Otomatis terisi setelah pilih palet...'),

                // 3. Input Hasil
                TextInput::make('hasil_graji')
                    ->label('Hasil Jadi (Stik)')
                    ->required()
                    ->numeric()
                    ->suffix('Pcs'),
            ]);
    }
}
