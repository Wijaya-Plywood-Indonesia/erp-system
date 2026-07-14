<?php

namespace App\Filament\Resources\ProduksiPressDryers\RelationManagers;

use App\Models\DetailHasil;
use App\Models\DetailMasuk;
use App\Models\SerahTerimaVeneerKering;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;

class DetailHasilsRelationManager extends RelationManager
{
    protected static ?string $title = 'Hasil';

    protected static string $relationship = 'detailHasils';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                TextInput::make('no_palet')
    ->label('Nomor Palet')
    ->numeric()
    ->default(function () {
        // Ambil palet tertinggi dari SELURUH hasil produksi dryer
        $paletTerakhir = DetailHasil::max('no_palet');

        return $paletTerakhir ? $paletTerakhir + 1 : 1;
    })
    ->required(),

                Select::make('id_ukuran')
                    ->label('Ukuran Kayu')
                    ->options(function () {
                        $produksi = $this->getOwnerRecord();

                        return DetailMasuk::where('id_produksi_dryer', $produksi->id)
                            ->with('ukuran')
                            ->get()
                            ->pluck('ukuran.nama_ukuran', 'id_ukuran')
                            ->unique();
                    })
                    ->searchable()
                    ->afterStateUpdated(function ($state) {
                        session(['last_ukuran' => $state]);
                    })
                    ->default(fn () => session('last_ukuran'))
                    ->required(),

                Select::make('id_jenis_kayu')
                    ->label('Jenis Kayu')
                    ->options(function () {
                        $produksi = $this->getOwnerRecord();

                        return DetailMasuk::where('id_produksi_dryer', $produksi->id)
                            ->select('id_jenis_kayu')
                            ->distinct()
                            ->with('jenisKayu:id,nama_kayu')
                            ->get()
                            ->pluck('jenisKayu.nama_kayu', 'id_jenis_kayu');
                    })
                    ->searchable()
                    ->afterStateUpdated(function ($state) {
                        session(['last_jenis_kayu' => $state]);
                    })
                    ->default(fn () => session('last_jenis_kayu'))
                    ->required(),

                TextInput::make('kw')
                    ->label('Kualitas (KW)')
                    ->required()
                    ->placeholder('Cth: 1, 2, 3 dll.'),

                TextInput::make('isi')
                    ->label('Isi')
                    ->required()
                    ->numeric()
                    ->placeholder('Cth: 1.5 atau 100'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with([
                'ukuran',
                'jenisKayu',
                'produksiDryer',
                'serahTerimaVeneerKering', // eager load untuk cek status serah/repair
            ]))
            ->columns([
                TextColumn::make('no_palet')
                    ->label('No. Palet')
                    ->searchable()
                    ->badge()
                    ->formatStateUsing(fn ($state) => 'dry-' . $state)
                    ->color(function ($record) {
                        $serahTerima = $record->serahTerimaVeneerKering;

                        if (! $serahTerima) {
                            return 'gray';
                        }

                        return $serahTerima->diterima_oleh === '-' ? 'warning' : 'success';
                    })
                    ->description(function ($record) {
                        $serahTerima = $record->serahTerimaVeneerKering;

                        if (! $serahTerima) {
                            return 'Belum Serah';
                        }

                        return $serahTerima->diterima_oleh === '-' ? 'Sudah Diserahkan' : 'Sudah Diterima Repair';
                    }),

                TextColumn::make('jenisKayu.nama_kayu')
                    ->label('Jenis Kayu')
                    ->searchable(),

                TextColumn::make('ukuran.nama_ukuran')
                    ->label('Ukuran')
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('ukuran', function (Builder $q) use ($search) {
                            $q->where('panjang', 'like', "%{$search}%")
                                ->orWhere('lebar', 'like', "%{$search}%")
                                ->orWhere('tebal', 'like', "%{$search}%")
                                ->orWhereRaw("CONCAT(panjang, ' x ', lebar, ' x ', tebal) LIKE ?", ["%{$search}%"]);
                        });
                    })
                    ->sortable()
                    ->placeholder('N/A'),

                TextColumn::make('kw')
                    ->label('Kualitas (KW)')
                    ->searchable(),

                TextColumn::make('isi')
                    ->label('Isi'),

                // Kolom status serah/repair — informatif
                TextColumn::make('status_repair')
                    ->label('Status')
                    ->badge()
                    ->getStateUsing(function (DetailHasil $record) {
                        $serahTerima = $record->serahTerimaVeneerKering;
                        if (! $serahTerima) {
                            return 'Belum Diserahkan';
                        }

                        return $serahTerima->diterima_oleh === '-' ? 'Sudah Diserahkan' : 'Sudah Diterima Repair';
                    })
                    ->color(fn ($state) => match ($state) {
                        'Sudah Diterima Repair' => 'success',
                        'Sudah Diserahkan' => 'warning',
                        default => 'gray',
                    }),

                TextColumn::make('created_at')
                    ->label('Tanggal Input')
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([])
            ->headerActions([
                CreateAction::make()
                    ->hidden(
                        fn ($livewire) => $livewire->ownerRecord?->validasiTerakhir?->status === 'divalidasi'
                    ),
            ])
            ->recordActions([
                Action::make('serah')
                    ->label('Serah')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Serahkan Veneer Kering ini ke Repair?')
                    ->modalDescription('Pastikan data berikut sudah sesuai sebelum diserahkan.')
                    ->modalContent(function (DetailHasil $record) {
                        $jenisKayu = $record->jenisKayu?->nama_kayu ?? '-';
                        $ukuranModel = $record->ukuran;
                        $ukuran = $ukuranModel
                            ? "{$ukuranModel->panjang} x {$ukuranModel->lebar} x {$ukuranModel->tebal}"
                            : '-';

                        return new HtmlString(<<<HTML
            <div class="space-y-2 text-sm">
                <div class="grid grid-cols-3 gap-1">
                    <span class="font-medium text-gray-500">No. Palet</span>
                    <span class="col-span-2">: {$record->no_palet}</span>

                    <span class="font-medium text-gray-500">Jenis Kayu</span>
                    <span class="col-span-2">: {$jenisKayu}</span>

                    <span class="font-medium text-gray-500">Ukuran</span>
                    <span class="col-span-2">: {$ukuran}</span>

                    <span class="font-medium text-gray-500">Kualitas (KW)</span>
                    <span class="col-span-2">: {$record->kw}</span>

                    <span class="font-medium text-gray-500">Isi</span>
                    <span class="col-span-2">: {$record->isi}</span>
                </div>
            </div>
        HTML);
                    })
                    ->visible(fn (DetailHasil $record) => ! $record->serahTerimaVeneerKering)
                    ->action(function (DetailHasil $record) {
                        try {
                            DB::transaction(function () use ($record) {
                                SerahTerimaVeneerKering::create([
                                    'id_detail_hasil' => $record->id,
                                    'id_detail_bongkar_kedi' => null,
                                    'tipe_sumber' => 'dryer',
                                    'id_produksi_repair' => null,
                                    'diserahkan_oleh' => Auth::user()->name,
                                    'diterima_oleh' => '-',
                                    'status' => 'Serah Veneer',
                                ]);
                            });

                            $record->unsetRelation('serahTerimaVeneerKering');
                            $record->refresh();

                            Notification::make()
                                ->title('Penyerahan Berhasil')
                                ->body('Palet telah masuk ke daftar Serah Terima Veneer Kering.')
                                ->success()
                                ->send();

                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('Gagal')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                EditAction::make()
                    ->hidden(function ($livewire, DetailHasil $record) {
                        $serahTerima = $record->serahTerimaVeneerKering;
                        $sudahDiterima = $serahTerima && $serahTerima->diterima_oleh !== '-';

                        return $sudahDiterima
                            || $livewire->ownerRecord?->validasiTerakhir?->status === 'divalidasi';
                    }),

                DeleteAction::make()
                    ->hidden(
                        fn ($livewire, DetailHasil $record) => $record->serahTerimaVeneerKering
                            || $livewire->ownerRecord?->validasiTerakhir?->status === 'divalidasi'
                    ),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->hidden(
                            fn ($livewire) => $livewire->ownerRecord?->validasiTerakhir?->status === 'divalidasi'
                        ),
                ]),
            ]);
    }
}
