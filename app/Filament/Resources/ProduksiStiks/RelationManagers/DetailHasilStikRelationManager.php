<?php

namespace App\Filament\Resources\ProduksiStiks\RelationManagers;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Forms\Components\Select;
use App\Concerns\LocksWhenValidated;
use App\Models\JenisKayu;
use App\Models\Ukuran;

class DetailHasilStikRelationManager extends RelationManager
{
    protected static ?string $title = 'Hasil';
    protected static string $relationship = 'detailHasilStik';

    use LocksWhenValidated;

    protected string $validasiRelasi = 'validasiStik';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                TextInput::make('no_palet')
                    ->label('Nomor Palet')
                    ->numeric()
                    ->required(),

                // Relasi ke Jenis Kayu (id_jenis_kayu) — tetap diambil dari
                // Modal (Detail Masuk Stik) produksi ini seperti semula.
                Select::make('id_jenis_kayu')
                    ->label('Jenis Kayu')
                    ->options(function () {
                        $produksi = $this->getOwnerRecord();

                        $options = \App\Models\DetailMasukStik::where('id_produksi_stik', $produksi->id)
                            ->select('id_jenis_kayu')
                            ->distinct()
                            ->with('jenisKayu:id,nama_kayu')
                            ->get()
                            ->pluck('jenisKayu.nama_kayu', 'id_jenis_kayu');

                        // Jaga-jaga: kalau default (dari sesi produksi ini)
                        // belum ada di data Modal produksi ini, tetap
                        // tampilkan labelnya (bukan angka mentah) dengan
                        // ambil dari master. Key sesi di-scope per produksi
                        // supaya TIDAK bocor dari produksi/form lain.
                        $default = session("last_jenis_kayu_stik_{$produksi->id}");
                        if ($default && ! $options->has($default)) {
                            $nama = JenisKayu::find($default)?->nama_kayu;
                            if ($nama) {
                                $options->put($default, $nama);
                            }
                        }

                        return $options;
                    })
                    ->searchable()
                    ->afterStateUpdated(function ($state) {
                        session(["last_jenis_kayu_stik_{$this->getOwnerRecord()->id}" => $state]);
                    })
                    ->default(fn() => session("last_jenis_kayu_stik_{$this->getOwnerRecord()->id}"))
                    ->required(),

                // Relasi ke Ukuran (id_ukuran) — tetap diambil dari Modal
                // (Detail Masuk Stik) produksi ini seperti semula.
                Select::make('id_ukuran')
                    ->label('Ukuran Kayu')
                    ->options(function () {
                        $produksi = $this->getOwnerRecord();

                        $options = \App\Models\DetailMasukStik::where('id_produksi_stik', $produksi->id)
                            ->with('ukuran')
                            ->get()
                            ->pluck('ukuran.nama_ukuran', 'id_ukuran')
                            ->unique();

                        // Sama seperti di atas — di-scope per produksi.
                        $default = session("last_ukuran_stik_{$produksi->id}");
                        if ($default && ! $options->has($default)) {
                            $nama = Ukuran::find($default)?->nama_ukuran;
                            if ($nama) {
                                $options->put($default, $nama);
                            }
                        }

                        return $options;
                    })
                    ->searchable()
                    ->afterStateUpdated(function ($state) {
                        session(["last_ukuran_stik_{$this->getOwnerRecord()->id}" => $state]);
                    })
                    ->default(fn() => session("last_ukuran_stik_{$this->getOwnerRecord()->id}"))
                    ->required(),

                TextInput::make('kw')
                    ->label('Kualitas (KW)')
                    ->required()
                    ->placeholder('Cth: 1, 2, 3 dll.'),

                TextInput::make('total_lembar')
                    ->label('Total Lembar')
                    ->required()
                    ->numeric()
                    ->placeholder('Cth: 1.5 atau 100'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('no_palet')
                    ->label('No. Palet')
                    ->searchable()
                    ->badge()
                    ->formatStateUsing(fn($state) => 'ST-' . $state),

                TextColumn::make('jenisKayu.nama_kayu')
                    ->label('Jenis Kayu')
                    ->searchable(),

                TextColumn::make('ukuran.nama_ukuran')
                    ->label('Ukuran')
                    ->searchable(['panjang', 'lebar', 'tebal'])
                    ->sortable()
                    ->placeholder('N/A'),

                TextColumn::make('kw')
                    ->label('Kualitas (KW)')
                    ->searchable(),

                TextColumn::make('total_lembar')
                    ->label('Total Lembar'),

                TextColumn::make('created_at')
                    ->label('Tanggal Input')
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                // Create Action — HILANG jika sudah divalidasi, KECUALI Super Admin
                CreateAction::make()
                    ->hidden(fn() => $this->terkunci()),
            ])
            ->recordActions([
                // Edit Action — HILANG jika sudah divalidasi, KECUALI Super Admin
                EditAction::make()
                    ->hidden(fn() => $this->terkunci()),

                // Delete Action — HILANG jika sudah divalidasi, KECUALI Super Admin
                DeleteAction::make()
                    ->hidden(fn() => $this->terkunci()),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->hidden(fn() => $this->terkunci()),
                ]),
            ]);
    }
}