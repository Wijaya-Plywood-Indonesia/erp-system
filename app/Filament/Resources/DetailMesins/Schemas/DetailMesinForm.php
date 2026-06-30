<?php

namespace App\Filament\Resources\DetailMesins\Schemas;

use Filament\Schemas\Schema;
use App\Models\Mesin;
use App\Models\ProduksiPressDryer;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Set;
use Livewire\Component;

class DetailMesinForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->schema([
                // 1. INPUT PARENT (Produksi Press Dryer)
                // Input ini hanya muncul jika Form dibuka di luar Relation Manager (misal menu Create biasa).
                Select::make('id_produksi_dryer')
                    ->label('Produksi Press Dryer')
                    ->options(ProduksiPressDryer::all()->mapWithKeys(function ($item) {
                        return [$item->id => $item->label]; // Menggunakan accessor label dari model
                    }))
                    ->searchable()
                    ->live() // Reaktif agar bisa memicu perubahan input lain
                    ->afterStateUpdated(function ($state, Set $set) {
                        // Logika jika User memilih Parent secara manual
                        if (!$state)
                            return;

                        $produksi = ProduksiPressDryer::find($state);
                        if ($produksi) {
                            $shift = strtolower($produksi->shift ?? '');
                            // Set jam kerja otomatis
                            $set('jam_kerja_mesin', $shift === 'pagi' ? 11 : 12);
                        }
                    })
                    // Sembunyikan field ini jika dibuka di Relation Manager (karena Parent sudah otomatis ada)
                    ->hidden(fn(Component $livewire) => property_exists($livewire, 'ownerRecord')),

                // 2. INPUT MESIN
                Select::make('id_mesin_dryer')
                    ->label('Mesin Dryer')
                    ->options(
                        Mesin::whereHas('kategoriMesin', function ($query) {
                            $query->where('nama_kategori_mesin', 'DRYER');
                        })
                        ->orderBy('nama_mesin')
                        ->pluck('nama_mesin', 'id')
                    )
                    ->searchable()
                    ->required(),

                // 3. INPUT JAM KERJA (Otomatis & Tersembunyi)
                TextInput::make('jam_kerja_mesin')
                    ->label('Jam Kerja Mesin')
                    ->numeric()
                    // LOGIKA DEFAULT (Khusus Relation Manager)
                    ->default(function (Component $livewire) {
                        // Cek apakah ada Parent Record (Context Relation Manager)
                        if (property_exists($livewire, 'ownerRecord') && $livewire->ownerRecord instanceof ProduksiPressDryer) {
                            $shift = strtolower($livewire->ownerRecord->shift ?? '');
                            return $shift === 'pagi' ? 11 : 12;
                        }
                        // Default jika create manual tanpa pilih parent dulu
                        return 12;
                    })
                    ->hidden()       // Sembunyikan dari user
                    ->dehydrated(),  // WAJIB: Agar nilai tetap dikirim ke database meski hidden
            ]);
    }
}