<?php

namespace App\Filament\Resources\PurchaseOrders\Schemas;

use App\Models\Customer;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;

class PurchaseOrderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(2)
                    ->schema([
                        Select::make('customer_id')
                            ->label('Customer')
                            ->options(fn () => Customer::query()->pluck('nama', 'id'))
                            ->searchable()
                            ->preload()
                            ->required()
                            ->columnSpanFull(),

                        DatePicker::make('tgl_order')
                            ->label('Tgl Order (Masuk)')
                            ->native(false)
                            ->default(now()) 
                            ->required(),

                        DatePicker::make('tgl_produksi')
                            ->label('Tgl Produksi')
                            ->native(false)
                            ->placeholder('Pilih tanggal...'), 

                        DatePicker::make('tgl_kirim')
                            ->label('Tgl Rencana Kirim')
                            ->native(false)
                            ->placeholder('Pilih tanggal...'), 

                        Textarea::make('keterangan')
                            ->label('Keterangan')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}