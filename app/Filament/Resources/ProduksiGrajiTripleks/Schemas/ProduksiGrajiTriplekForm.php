<?php

namespace App\Filament\Resources\ProduksiPressDryers\Schemas;

use Filament\Schemas\Schema;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Illuminate\Validation\Rule;
use App\Models\ProduksiPressDryer;

class ProduksiPressDryerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([

            /**
             * ==========================
             * 📅 TANGGAL PRODUKSI
             * ==========================
             */
            DatePicker::make('tanggal_produksi')
                ->label('Tanggal Produksi')
                ->default(fn () => now()->addDay())
                ->displayFormat('d F Y')
                ->required()
                ->reactive()
                ->rule(function (callable $get, ?ProduksiPressDryer $record) {
                    return Rule::unique('produksi_press_dryers', 'tanggal_produksi')
                        ->where(fn ($query) =>
                            $query->where('shift', $get('shift'))
                        )
                        ->ignore($record?->id);
                }),

            /**
             * ==========================
             * ⚙️ SHIFT
             * ==========================
             */
            Select::make('shift')
                ->label('Shift')
                ->options([
                    'PAGI'  => 'Pagi',
                    'MALAM' => 'Malam',
                ])
                ->required()
                ->reactive()
                ->rule(function (callable $get, ?ProduksiPressDryer $record) {
                    return Rule::unique('produksi_press_dryers', 'shift')
                        ->where(fn ($query) =>
                            $query->whereDate('tanggal_produksi', $get('tanggal_produksi'))
                        )
                        ->ignore($record?->id);
                }),
        ]);
    }
}
