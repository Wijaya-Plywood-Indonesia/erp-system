<?php

namespace App\Filament\Resources\PlatformBahanHps\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Tables\Grouping\Group; // <-- PENTING: Import Group
use Filament\Forms\Components\TextInput;

class PlatformBahanHpsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            /**
             * =============================
             * 🔥 GROUP BY LAPISAN
             * =============================
             */
            ->groups([
                Group::make('detailKomposisi.lapisan')
                    ->label('Lapisan')
                    // Menggunakan getTitleFromRecordUsing() untuk memformat header grup
                    ->getTitleFromRecordUsing(function ($record) {
                        // Mengambil data lapisan dan keterangan dari relasi
                        $lapisan = $record->detailKomposisi?->lapisan ?? 'N/A';
                        $keterangan = $record->detailKomposisi?->keterangan ?? '';

                        // Format tampilan: "Lapisan - X (Keterangan)"
                        return " {$lapisan} ";
                        // (" . strtoupper($keterangan) . ")
                    })
                    ->collapsible(), // Tambahkan agar grup bisa dilipat
            ])
            
            ->columns([
                
                /*
                |------------------------------------------------------------
                | LAPISAN (Disembunyikan, hanya untuk Group Header)
                |------------------------------------------------------------
                */
                TextColumn::make('detailKomposisi.lapisan')
                    ->label('Lapisan ke-')
                    ->sortable()
                    ->placeholder('N/A')
                    // Kolom ini disembunyikan karena sudah diwakili oleh Group Header
                    ->toggleable(isToggledHiddenByDefault: true), 
                    
                /*
                |------------------------------------------------------------
                | JENIS BAHAN (Keterangan + Jenis Kayu Digabung)
                |------------------------------------------------------------
                */
                TextColumn::make('jenis_bahan_detail')
                    ->label('Jenis Bahan/Kayu')
                    // Gabungkan Keterangan (FACE/CORE) dan Jenis Kayu
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

                // Kolom untuk Ukuran (Melalui BarangSetengahJadiHp)
                TextColumn::make('barangSetengahJadiHp.ukuran.nama_ukuran')
                    ->label('Ukuran')
                    ->searchable(false)
                    ->placeholder('Ukuran'),

                // Kolom untuk Kualitas/Grade (Melalui BarangSetengahJadiHp)
                TextColumn::make('barangSetengahJadiHp.grade.nama_grade')
                    ->label('Kw')
                    ->searchable()
                    ->placeholder('N/A'),

                // Kolom untuk Jumlah Lembar (Langsung dari PlatformBahanHp)
                TextColumn::make('isi')
                    ->label('Jumlah Lembar')
                    ->numeric()
                    ->alignCenter(),

                // Note: Kolom no_palet (Jika Anda membutuhkannya)
                // TextColumn::make('no_palet') 
                //     ->label('No. Palet'), 
            ])
            
            // Atur agar tabel secara default ter-grouping saat dimuat
            ->defaultGroup('detailKomposisi.lapisan')

            ->filters([
                // Tempat filter jika Anda membutuhkannya
            ])
            
            ->headerActions([
                // Create Action — HILANG jika status sudah divalidasi
                // CreateAction::make()
                //     ->hidden(
                //         fn($livewire) =>
                //         $livewire->ownerRecord?->validasiTerakhir?->status === 'divalidasi'
                //     ),
            ])
            ->actions([
                // Edit Action — HILANG jika status sudah divalidasi
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

                // Delete Action — HILANG jika status sudah divalidasi
                // DeleteAction::make()
                //     ->hidden(
                //         fn($livewire) =>
                //         $livewire->ownerRecord?->validasiTerakhir?->status === 'divalidasi'
                //     ),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->hidden(
                            fn($livewire) =>
                            $livewire->ownerRecord?->validasiTerakhir?->status === 'divalidasi'
                        ),
                ]),
            ])
            
            // Default sort dipertahankan agar sesuai dengan urutan lapisan
            ->defaultSort('detailKomposisi.lapisan', 'asc');
    }
}