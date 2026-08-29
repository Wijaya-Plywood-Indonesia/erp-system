<?php

namespace App\Filament\Resources\ProduksiPalets\RelationManagers;

use App\Models\PegawaiPalet;
use App\Models\StokLogCore;
use App\Services\ValidasiProduksiPaletService;
use Filament\Actions\AssociateAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\DissociateAction;
use Filament\Actions\DissociateBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class HasilProduksiPaletsRelationManager extends RelationManager
{
    protected static string $relationship = 'hasilProduksiPalets';

    public function isReadOnly(): bool
    {
        $owner = $this->getOwnerRecord();
        return $owner ? ValidasiProduksiPaletService::isLocked($owner) : false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('id_pegawai_palet')
                    ->label('Pegawai Palet')
                    ->required()
                    ->searchable()
                    ->options(function ($livewire) {
                        $produksi = $livewire->getOwnerRecord();

                        if (!$produksi) {
                            return [];
                        }

                        return PegawaiPalet::with('pegawai')
                            ->where('id_produksi_palet', $produksi->id)
                            ->get()
                            ->mapWithKeys(function ($p) {
                                $nama = $p->pegawai
                                    ? "{$p->pegawai->kode_pegawai} - {$p->pegawai->nama_pegawai}"
                                    : "Pegawai #{$p->id_pegawai}";

                                return [$p->id => $nama];
                            });
                    }),
                Select::make('id_stok_log_core')
                    ->label('Stok Log Core')
                    ->required()
                    ->searchable()
                    ->options(function (callable $get) {
                        $query = StokLogCore::query()
                            ->with('jenisKayu')
                            ->where('stok_qty', '>', 0);

                        if ($get('jenis_kayu_id_filter')) {
                            $query->where('id_jenis_kayu', $get('jenis_kayu_id_filter'));
                        }

                        return $query->get()->mapWithKeys(function ($item) {
                            $namaKayu = $item->jenisKayu?->nama_kayu ?? 'Kayu';
                            return [
                                $item->id => "{$namaKayu} | Panjang: {$item->panjang} cm | Sisa: {$item->stok_qty} Pcs",
                            ];
                        });
                    }),

                TextInput::make('modal')
                    ->label('Modal Dipakai (Btg)')
                    ->numeric()
                    ->required(),

                TextInput::make('hasil')
                    ->label('Hasil (Palet)')
                    ->numeric()
                    ->required(),
            ]);
    }

    public function table(Table $table): Table
    {
        $owner = $this->getOwnerRecord();
        return $table
            ->recordTitleAttribute('HasilProduksiPalet')
            ->columns([
                TextColumn::make('pegawaiPalet.pegawai.nama_pegawai')
                    ->label('Pegawai')
                    ->formatStateUsing(function ($record) {
                        $pegawai = $record->pegawaiPalet?->pegawai;

                        return $pegawai
                            ? "{$pegawai->kode_pegawai} - {$pegawai->nama_pegawai}"
                            : 'N/A';
                    })
                    ->badge()
                    ->color('gray')
                    ->searchable(
                        query: fn($query, $search) => $query->whereHas(
                            'pegawaiPalet.pegawai',
                            fn($q) => $q
                                ->where('nama_pegawai', 'like', "%{$search}%")
                                ->orWhere('kode_pegawai', 'like', "%{$search}%")
                        )
                    )
                    ->sortable(),

                TextColumn::make('stokLogCore.jenisKayu.nama_kayu')
                    ->label('Panjang & Jenis Kayu')
                    ->formatStateUsing(function ($record) {
                        $stok = $record->stokLogCore;
                        $panjang = $stok?->panjang ? "{$stok->panjang}" : '-';
                        $jenisKayu = $stok?->jenisKayu?->nama_kayu ?? '-';

                        return "{$panjang} - {$jenisKayu}";
                    })
                    ->badge()
                    ->color('info')
                    ->searchable(
                        query: fn($query, $search) => $query->whereHas(
                            'stokLogCore.jenisKayu',
                            fn($q) => $q->where('nama_kayu', 'like', "%{$search}%")
                        )
                    )
                    ->sortable(),

                TextColumn::make('modal')
                    ->label('Modal (Btg)')
                    ->numeric()
                    ->badge()
                    ->color('warning')
                    ->sortable(),

                TextColumn::make('hasil')
                    ->label('Hasil (Palet)')
                    ->numeric()
                    ->badge()
                    ->color('success')
                    ->sortable(),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make()
                    ->hidden(fn() => $owner && ValidasiProduksiPaletService::isStatusDivalidasi($owner))
                    ->after(fn($record) => ValidasiProduksiPaletService::prosesValidasi($record)),
            ])
            ->recordActions([
                EditAction::make()
                    ->hidden(fn() => $owner && ValidasiProduksiPaletService::isStatusDivalidasi($owner))
                    ->after(fn($record) => ValidasiProduksiPaletService::prosesValidasi($record)),
                DeleteAction::make()
                    ->hidden(fn() => $owner && ValidasiProduksiPaletService::isStatusDivalidasi($owner))
                    ->after(fn($record) => ValidasiProduksiPaletService::prosesValidasi($record)),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->hidden(fn() => $owner && ValidasiProduksiPaletService::isStatusDivalidasi($owner))
                        ->after(fn($record) => ValidasiProduksiPaletService::prosesValidasi($record)),
                ]),
            ]);
    }
}
