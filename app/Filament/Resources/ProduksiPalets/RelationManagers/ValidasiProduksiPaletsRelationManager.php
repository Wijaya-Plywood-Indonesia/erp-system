<?php

namespace App\Filament\Resources\ProduksiPalets\RelationManagers;

use App\Services\ValidasiProduksiPaletService;
use Filament\Actions\Action;
use Filament\Actions\AssociateAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\DissociateAction;
use Filament\Actions\DissociateBulkAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class ValidasiProduksiPaletsRelationManager extends RelationManager
{
    protected static string $relationship = 'validasiProduksiPalets';

    public function isReadOnly(): bool
    {
        $owner = $this->getOwnerRecord();
        return $owner ? ValidasiProduksiPaletService::isLocked($owner) : false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('role')
                    ->label('Role Login')
                    ->default(function () {
                        $user = Filament::auth()->user();

                        if (!$user) {
                            return 'Tidak diketahui';
                        }

                        // Ambil role pertama dari user (karena bisa punya lebih dari satu)
                        /** @var User&HasRoles $user */
                        return $user->getRoleNames()->first() ?? 'Tidak diketahui';
                    })
                    ->disabled()
                    ->dehydrated(true), // tetap ikut disimpan ke database
                Select::make('status')
                    ->label('Status Validasi')
                    ->options([
                        'divalidasi' => 'Divalidasi',
                        'disetujui' => 'Disetujui',
                        'ditangguhkan' => 'Ditangguhkan',
                        'ditolak' => 'Ditolak',
                    ])
                    ->required()
                    ->native(false)
                    ->searchable(),
            ]);
    }

    public function table(Table $table): Table
    {
        $owner = $this->getOwnerRecord();
        return $table
            ->recordTitleAttribute('ValidasiProduksiPalet')
            ->columns([
                TextColumn::make('status')
                    ->searchable(),
                TextColumn::make('role')
                    ->searchable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
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
                Action::make('batal_validasi')
                    ->label('Batal Validasi')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Batalkan Validasi Produksi?')
                    ->modalDescription('Apakah Anda yakin ingin membatalkan validasi? Stok Log Core yang dipotong akan dikembalikan otomatis dan log transaksi akan dibersihkan.')
                    ->modalSubmitActionLabel('Ya, Batalkan Validasi')
                    // Hanya tampil jika User adalah Super Admin DAN Produksi dalam kondisi Divalidasi/Disetujui
                    ->visible(function () use ($owner) {
                        $user = Auth::user();
                        $isSuperAdmin = $user && method_exists($user, 'hasRole') && $user->hasRole(['super_admin', 'Super Admin']);
                        $isDivalidasi = $owner ? ValidasiProduksiPaletService::isStatusDivalidasi($owner) : false;

                        return $isSuperAdmin && $isDivalidasi;
                    })
                    // Panggil fungsi eksekusi Batal Validasi di Service
                    ->action(function () use ($owner) {
                        if (!$owner) return;

                        ValidasiProduksiPaletService::batalkanValidasi($owner);

                        Notification::make()
                            ->title('Validasi Berhasil Dibatalkan')
                            ->body('Stok Log Core telah dikembalikan dan status validasi di-reset.')
                            ->success()
                            ->send();
                    }),
            ])
            ->recordActions([
                EditAction::make()
                    ->hidden(fn() => $owner && ValidasiProduksiPaletService::isStatusDivalidasi($owner))
                    ->after(fn($record) => ValidasiProduksiPaletService::prosesValidasi($record)),
                DeleteAction::make()
                    ->hidden(fn() => $owner && ValidasiProduksiPaletService::isStatusDivalidasi($owner)),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
