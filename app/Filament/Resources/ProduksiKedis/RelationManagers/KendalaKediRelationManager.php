<?php

namespace App\Filament\Resources\ProduksiKedis\RelationManagers;

use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

class KendalaKediRelationManager extends RelationManager
{
    protected static string $relationship = 'kendalaKedis';
    protected static ?string $title = 'Downtime & Kendala Mesin';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('mesin_id')
                    ->label('Mesin')
                    ->relationship('mesin', 'nama_mesin', function (Builder $query) {
                        $query->whereHas('kategoriMesin', function ($q) {
                            $q->where('nama_kategori_mesin', 'like', '%kedi%')
                              ->orWhere('nama_kategori_mesin', 'like', '%dryer%');
                        });
                    })
                    ->required()
                    ->searchable()
                    ->preload(),

                DateTimePicker::make('waktu_mulai')
                    ->label('Waktu Kendala Mulai')
                    ->default(now())
                    ->required()
                    ->displayFormat('H:i')
                    ->native(false)
                    ->date(false)
                    ->seconds(false)
                    ->hoursStep(1)
                    ->minutesStep(1),

                Textarea::make('kendala')
                    ->label('Detail Kendala')
                    ->required()
                    ->maxLength(65535)
                    ->columnSpanFull(),

                FileUpload::make('foto_kendala')
                    ->label('Foto Bukti Kendala')
                    ->directory('downtime/kendala')
                    ->image()
                    ->required(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('kendala')
            ->columns([
                TextColumn::make('mesin.nama_mesin')
                    ->label('Mesin')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('waktu_mulai')
                    ->label('Waktu Mulai')
                    ->dateTime('H:i')
                    ->sortable(),

                ImageColumn::make('foto_kendala')
                    ->label('Bukti Kendala')
                    ->square()
                    ->size(50)
                    ->extraImgAttributes(fn($record): array => [
                        'class'   => 'cursor-zoom-in hover:opacity-80 transition-opacity',
                        'title'   => 'Klik untuk memperbesar',
                        'onclick' => "event.stopPropagation(); window.open(this.src, '_blank');",
                    ]),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'pending' => 'danger',
                        'selesai' => 'success',
                        default   => 'gray',
                    })
                    ->formatStateUsing(fn(string $state): string => ucfirst($state)),

                ImageColumn::make('foto_selesai')
                    ->label('Bukti Selesai')
                    ->square()
                    ->size(50)
                    ->extraImgAttributes(fn($record): array => [
                        'class'   => 'cursor-zoom-in hover:opacity-80 transition-opacity',
                        'title'   => 'Klik untuk memperbesar',
                        'onclick' => "event.stopPropagation(); window.open(this.src, '_blank');",
                    ]),

                TextColumn::make('waktu_selesai')
                    ->label('Waktu Selesai')
                    ->dateTime('H:i')
                    ->placeholder('-')
                    ->sortable(),

                TextColumn::make('durasi_menit')
                    ->label('Durasi')
                    ->placeholder('-')
                    ->numeric()
                    ->suffix(' menit'),
            ])
            ->filters([])
            ->headerActions([
                CreateAction::make()
                    ->label('Tambah Kendala')
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['status'] = 'pending';
                        $data['waktu_mulai'] = now()->format('Y-m-d') . ' ' . Carbon::parse($data['waktu_mulai'])->format('H:i') . ':00';
                        return $data;
                    }),
            ])
            ->recordActions([
                Action::make('selesaikanKendala')
                    ->label('Selesai')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn($record) => $record->status === 'pending')
                    ->form([
                        DateTimePicker::make('waktu_selesai')
                            ->label('Waktu Mesin Selesai Diperbaiki')
                            ->default(now())
                            ->required()
                            ->displayFormat('H:i')
                            ->native(false)
                            ->date(false)
                            ->seconds(false)
                            ->hoursStep(1)
                            ->minutesStep(1),

                        FileUpload::make('foto_selesai')
                            ->label('Foto Bukti Selesai')
                            ->directory('downtime/selesai')
                            ->image()
                            ->required(),
                    ])
                    ->action(function ($record, array $data): void {
                        $tanggal          = Carbon::parse($record->waktu_mulai)->format('Y-m-d');
                        $waktuSelesaiFull = $tanggal . ' ' . Carbon::parse($data['waktu_selesai'])->format('H:i') . ':00';

                        $waktuMulai   = Carbon::parse($record->waktu_mulai);
                        $waktuSelesai = Carbon::parse($waktuSelesaiFull);
                        $durasiMenit  = $waktuMulai->diffInMinutes($waktuSelesai);

                        $record->update([
                            'waktu_selesai' => $waktuSelesaiFull,
                            'foto_selesai'  => $data['foto_selesai'],
                            'status'        => 'selesai',
                            'durasi_menit'  => $durasiMenit,
                        ]);
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Tandai Kendala Selesai'),

                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
