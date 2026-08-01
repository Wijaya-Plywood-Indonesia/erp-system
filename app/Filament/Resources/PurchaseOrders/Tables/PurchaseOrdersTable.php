<?php

namespace App\Filament\Resources\PurchaseOrders\Tables;

use App\Models\PurchaseOrder;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PurchaseOrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('po_number')
                    ->label('No. PO')
                    ->searchable()
                    ->weight('bold'),

                TextColumn::make('customer.nama')
                    ->label('Customer')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('tgl_order')
                    ->label('Tgl Order')
                    ->date('d M Y')
                    ->sortable(),

                TextColumn::make('keterangan')
                    ->label('Keterangan')
                    ->limit(30)
                    ->placeholder('-'),

                TextColumn::make('status_label')
                    ->label('Status Barang')
                    ->badge()
                    ->color(fn (PurchaseOrder $record) => $record->status_color),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                ViewAction::make()->label('Lihat Detail'),
            ])
            ->defaultSort('created_at', 'desc');
    }
}