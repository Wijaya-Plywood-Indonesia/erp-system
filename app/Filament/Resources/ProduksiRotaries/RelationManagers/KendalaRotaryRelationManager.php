<?php

namespace App\Filament\Resources\ProduksiRotaries\RelationManagers;

use App\Models\ValidasiHasilRotary;
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
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Schemas\Components\Grid;

class KendalaRotaryRelationManager extends RelationManager
{
    protected static string $relationship = 'kendalaRotaries';
    protected static ?string $title = 'Kendala';

    public function isReadOnly(): bool
    {
        $user = \Filament\Facades\Filament::auth()->user();

        // Hanya role ini yang terdampak lock
        $rolesAffectedByLock = [
            'pengawas_rotary_1',
            'pengawas_rotary_2',
            'kepala_produksi_wijaya',
        ];

        // Jika user bukan salah satu dari role di atas, tidak terkunci
        if (!$user?->hasAnyRole($rolesAffectedByLock)) {
            return false;
        }

        $ownerRecord = $this->getOwnerRecord();

        $validated = \App\Models\ValidasiHasilRotary::where('id_produksi', $ownerRecord->id)
            ->where('status', 'disetujui')
            ->pluck('role')
            ->toArray();

        $kepalaSudah = collect($validated)->contains(
            fn($role) => str_contains(strtolower($role), 'kepala_produksi')
        );

        $pengawasSudah = collect($validated)->contains(
            fn($role) => str_contains(strtolower($role), 'pengawas_rotary')
        );

        return $kepalaSudah && $pengawasSudah;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
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

                TextColumn::make('waktu_selesai')
                    ->label('Waktu Selesai')
                    ->dateTime('H:i')
                    ->placeholder('-')
                    ->sortable(),

                ImageColumn::make('foto_selesai')
                    ->label('Bukti Selesai')
                    ->square()
                    ->size(50)
                    ->extraImgAttributes(fn($record): array => [
                        'class'   => 'cursor-zoom-in hover:opacity-80 transition-opacity',
                        'title'   => 'Klik untuk memperbesar',
                        'onclick' => "event.stopPropagation(); window.open(this.src, '_blank');",
                    ]),

                TextColumn::make('durasi_menit')
                    ->label('Durasi')
                    ->placeholder('-')
                    ->numeric()
                    ->suffix(' menit'),

                TextColumn::make('kendala')
                    ->label('Detail Kendala')
                    ->wrap(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'pending' => 'danger',
                        'selesai' => 'success',
                        default   => 'gray',
                    })
                    ->formatStateUsing(fn(string $state): string => ucfirst($state)),
            ])
            ->filters([])
            ->headerActions([
                CreateAction::make()
                    ->label('Tambah Kendala')
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['status'] = 'pending';
                        $parent = $this->getOwnerRecord();
                        $parentDate = $parent?->tgl_produksi ?? now()->format('Y-m-d');
                        $parentDateStr = Carbon::parse($parentDate)->format('Y-m-d');
                        $data['waktu_mulai'] = $parentDateStr . ' ' . Carbon::parse($data['waktu_mulai'])->format('H:i') . ':00';

                        $data['mesin_id'] = $parent->id_mesin;

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
                EditAction::make()
                    ->form([
                        Grid::make(2)
                            ->schema([
                                TimePicker::make('waktu_mulai')
                                    ->label('Waktu Mulai')
                                    ->required(),

                                // 2. Bukti Kendala (Image Upload)
                                FileUpload::make('foto_kendala')
                                    ->label('Bukti Kendala')
                                    ->image()
                                    ->directory('kendala-files')
                                    ->imageEditor(),

                                // 3. Waktu Selesai
                                TimePicker::make('waktu_selesai')
                                    ->label('Waktu Selesai'),

                                // 4. Bukti Selesai (Image Upload)
                                FileUpload::make('foto_selesai')
                                    ->label('Bukti Selesai')
                                    ->image()
                                    ->directory('selesai-files'),

                                // 5. Durasi Menit
                                TextInput::make('durasi_menit')
                                    ->label('Durasi')
                                    ->numeric()
                                    ->suffix('menit'),

                                // 6. Status
                                Select::make('status')
                                    ->label('Status')
                                    ->options([
                                        'pending' => 'Pending',
                                        'selesai' => 'Selesai',
                                    ])
                                    ->required(),

                                Textarea::make('kendala')
                                    ->label('Detail Kendala')
                                    ->rows(3)
                                    ->columnSpanFull(),
                            ]),
                    ]),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
