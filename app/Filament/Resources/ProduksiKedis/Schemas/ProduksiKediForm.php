<?php

namespace App\Filament\Resources\ProduksiKedis\Schemas;

use App\Models\ProduksiKedi;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rule;

class ProduksiKediForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([

            /**
             * ==========================
             * 📅 TANGGAL PRODUKSI
             * ==========================
             */
            DatePicker::make('tanggal')
                ->label('Tanggal Produksi')
                ->default(fn () => now()->addDay())
                ->displayFormat('d F Y')
                ->required()
                ->reactive()
                ->rule(function (callable $get, ?ProduksiKedi $record) {
                    return Rule::unique('produksi_kedi', 'tanggal')
                        ->where(fn ($query) =>
                            $query->where('status', $get('status'))
                        )
                        ->ignore($record?->id);
                }),

            /**
             * ==========================
             * ⚙️ STATUS PRODUKSI
             * ==========================
             */
            Select::make('status')
                ->label('Status Produksi')
                ->options([
                    'masuk'   => 'Masuk',
                    'bongkar' => 'Bongkar',
                ])
                ->required()
                ->reactive()
                ->rule(function (callable $get, ?ProduksiKedi $record) {
                    return Rule::unique('produksi_kedi', 'status')
                        ->where(fn ($query) =>
                            $query->whereDate('tanggal', $get('tanggal'))
                        )
                        ->ignore($record?->id);
                })
                ->validationMessages([
                    'required' => 'Status produksi wajib dipilih.',
                ]),
        ]);
    }
}
