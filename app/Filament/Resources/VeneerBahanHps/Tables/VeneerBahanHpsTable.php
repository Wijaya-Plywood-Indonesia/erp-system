<?php

namespace App\Filament\Resources\VeneerBahanHps\Tables;

use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Grouping\Group; // <-- PENTING: Import Group
use Filament\Forms\Components\TextInput;

class VeneerBahanHpsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            /**
             * =============================
             * 🔥 GROUP BY LAPISAN (Menggunakan GetTitleFromRecordUsing)
             * =============================
             */
            ->groups([
                Group::make('detailKomposisi.lapisan')
                    ->label('Lapisan ke')
                    // Menggunakan getTitleFromRecordUsing() untuk memformat header grup
                    ->getTitleFromRecordUsing(function ($record) {
                        // $record adalah model VeneerBahanHp
                        $lapisan = $record->detailKomposisi?->lapisan ?? '-';
                        $keterangan = $record->detailKomposisi?->keterangan ?? '';

                        // Format tampilan menjadi 'Lapisan - X (Keterangan)'
                        return " {$lapisan} ";
                        // (" . strtoupper($keterangan) . ")
                    })
                    ->collapsible(), // Tambahkan collapsible agar bisa dilipat/dibuka
            ])

            ->columns([

                /*
                |------------------------------------------------------------
                | LAPISAN (Disembunyikan, hanya untuk Group Header)
                |------------------------------------------------------------
                */
                TextColumn::make('detailKomposisi.lapisan')
                    ->label('Lapisan')
                    ->sortable()
                    ->alignCenter()
                    // Kolom ini disembunyikan karena sudah ada di header grup
                    ->toggleable(isToggledHiddenByDefault: true), 

                /*
                |------------------------------------------------------------
                | JENIS BAHAN (FACE / CORE + JENIS KAYU)
                |------------------------------------------------------------
                */
                TextColumn::make('jenis_bahan')
                    ->label('Jenis Bahan')
                    ->getStateUsing(fn ($record) =>
                        strtoupper(
                            ($record->detailKomposisi?->keterangan ?? '-') .
                            ' | ' .
                            ($record->barangSetengahJadiHp?->jenisBarang?->nama_jenis_barang ?? '-')
                        )
                    )
                    ->weight('bold')
                    ->wrap()
                    ->searchable(),

                /*
                |------------------------------------------------------------
                | UKURAN
                |------------------------------------------------------------
                */
                TextColumn::make('barangSetengahJadiHp.ukuran.nama_ukuran')
                    ->label('Ukuran')
                    ->wrap(),

                /*
                |------------------------------------------------------------
                | GRADE
                |------------------------------------------------------------
                */
                BadgeColumn::make('barangSetengahJadiHp.grade.nama_grade')
                    ->label('Kw')
                    ->alignCenter()
                    ->colors([
                        'warning',
                    ]),

                /*
                |------------------------------------------------------------
                | JUMLAH LEMBAR
                |------------------------------------------------------------
                */
                TextColumn::make('isi')
                    ->label('Jumlah Lembar')
                    ->numeric()
                    ->alignCenter()
                    ->weight('bold'),
            ])
            
            // Atur agar tabel secara default ter-grouping saat dimuat
            ->defaultGroup('detailKomposisi.lapisan')

            /*
            |------------------------------------------------------------
            | HEADER ACTION
            |------------------------------------------------------------
            */
            ->headerActions([
                // CreateAction::make()
                //     ->label('Tambah Veneer')
                //     ->hidden(fn ($livewire) =>
                //         $livewire->ownerRecord?->validasiTerakhir?->status === 'divalidasi'
                //     ),
            ])

            /*
            |------------------------------------------------------------
            | RECORD ACTION
            |------------------------------------------------------------
            */
            ->actions([
                EditAction::make()
        ->label('Edit Jumlah')
        ->form([
            TextInput::make('isi')
                ->label('Jumlah Lembar')
                ->numeric()
                ->required()
                ->minValue(1),
        ])
        ->hidden(fn ($livewire) =>
            $livewire->ownerRecord?->validasiTerakhir?->status === 'divalidasi'
        ),

                // DeleteAction::make()
                //     ->hidden(fn ($livewire) =>
                //         $livewire->ownerRecord?->validasiTerakhir?->status === 'divalidasi'
                //     ),
            ])

            /*
            |------------------------------------------------------------
            | BULK ACTION
            |------------------------------------------------------------
            */
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->hidden(fn ($livewire) =>
                            $livewire->ownerRecord?->validasiTerakhir?->status === 'divalidasi'
                        ),
                ]),
            ])

            ->defaultSort('detailKomposisi.lapisan', 'asc');
    }
}