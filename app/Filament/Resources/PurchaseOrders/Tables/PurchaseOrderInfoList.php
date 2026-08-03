<?php

namespace App\Filament\Resources\PurchaseOrders\Schemas;

use App\Models\PurchaseOrder;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;

class PurchaseOrderInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(4)
                    ->schema([
                        TextEntry::make('customer.nama')
                            ->label('Customer')
                            ->columnSpan(2)
                            ->weight('bold'),

                        TextEntry::make('tgl_order')
                            ->label('Tgl Order')
                            ->date('d M Y'),

                        TextEntry::make('tgl_produksi')
                            ->label('Tgl Produksi')
                            ->date('d M Y')
                            ->placeholder('Belum diatur'),

                        TextEntry::make('status_label')
                            ->label('Status Keseluruhan PO')
                            ->badge()
                            ->color(fn(PurchaseOrder $record) => $record->status_color)
                            ->columnSpan(2),

                        TextEntry::make('items_progress')
                            ->label('')
                            ->state(function (PurchaseOrder $record) {
                                $total = $record->items->count();
                                $selesai = $record->items->where('status', true)->count();
                                return "Progres: {$selesai} / {$total} Selesai";
                            })
                            ->columnSpan(2),

                        TextEntry::make('keterangan')
                            ->label('Keterangan')
                            ->placeholder('-')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
