<?php

namespace App\Filament\Resources\DetailHasilPaletRotaries\Schemas;

use App\Models\PenggunaanLahanRotary;
use App\Models\Ukuran;
use Filament\Forms\Components\Hidden; // <--- Ubah import ini
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;

class DetailHasilPaletRotaryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([

            // Ubah DateTimePicker menjadi Hidden
            Hidden::make('timestamp_laporan')
                ->default(
                    fn(RelationManager $livewire) =>
                    optional(
                        $livewire->getOwnerRecord()
                            ->detailPaletRotary()
                            ->latest()
                            ->first()
                    )->timestamp_laporan ?? now()
                ),

            Select::make('id_ukuran')
                ->label('Ukuran')
                // ... (kode lainnya tetap sama)
                ->options(
                    Ukuran::get()->mapWithKeys(fn($u) => [
                        $u->id => $u->dimensi
                    ])
                )
                ->default(
                    fn(RelationManager $livewire) =>
                    optional(
                        $livewire->getOwnerRecord()
                            ->detailPaletRotary()
                            ->latest()
                            ->first()
                    )->id_ukuran
                )
                ->searchable()
                ->required(),

            TextInput::make('kw')
                ->default(
                    fn(RelationManager $livewire) =>
                    optional(
                        $livewire->getOwnerRecord()
                            ->detailPaletRotary()
                            ->latest()
                            ->first()
                    )->kw
                )
                ->required(),

            Select::make('id_penggunaan_lahan')
                ->label('Kode Lahan')
                ->options(function (RelationManager $livewire) {
                    $parent = $livewire->getOwnerRecord();

                    return PenggunaanLahanRotary::with('lahan')
                        ->where('id_produksi', $parent->id)
                        ->get()
                        ->mapWithKeys(fn($item) => [
                            $item->id => $item->lahan->kode_lahan ?? 'Tanpa Kode'
                        ]);
                })
                ->default(
                    fn(RelationManager $livewire) =>
                    optional(
                        $livewire->getOwnerRecord()
                            ->detailPaletRotary()
                            ->latest()
                            ->first()
                    )->id_penggunaan_lahan
                )
                ->searchable()
                ->required(),

            TextInput::make('palet')
                ->default(
                    fn(RelationManager $livewire) =>
                    optional(
                        $livewire->getOwnerRecord()
                            ->detailPaletRotary()
                            ->latest()
                            ->first()
                    )->palet
                )
                ->required(),

            TextInput::make('total_lembar')
                ->numeric()
                ->default(
                    fn(RelationManager $livewire) =>
                    optional(
                        $livewire->getOwnerRecord()
                            ->detailPaletRotary()
                            ->latest()
                            ->first()
                    )->total_lembar ?? 0
                )
                ->required(),
        ]);
    }
}
