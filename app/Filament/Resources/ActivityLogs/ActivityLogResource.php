<?php

namespace App\Filament\Resources\ActivityLogs;

use App\Filament\Resources\ActivityLogs\Pages\ManageActivityLogs;
use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Spatie\Activitylog\Models\Activity;

class ActivityLogResource extends Resource
{
    protected static ?string $model = Activity::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'AcitivityLog';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('AcitivityLog')
                    ->required()
                    ->maxLength(255),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                // Kolom Waktu: Format 04 Feb, 09:15
                TextColumn::make('created_at')
                    ->label('Waktu')
                    ->dateTime('d M Y, H:i:s')
                    ->sortable(),

                // Kolom User: Mengambil nama dari causer
                TextColumn::make('causer.name')
                    ->label('User')
                    ->searchable(),

                // Kolom Modul: Mengambil dari useLogName('Jurnal 3rd')
                TextColumn::make('log_name')
                    ->label('Asal')
                    ->badge()
                    ->color('gray'),

                // Kolom Event: Menampilkan 'Sync', 'Created', atau 'Updated'
                TextColumn::make('event')
                    ->label('Event')
                    ->badge() // Mengaktifkan tampilan badge
                    ->weight('bold')
                    ->formatStateUsing(fn(string $state) => ucfirst($state))
                    ->color(fn(string $state): string => match ($state) {
                        'created' => 'success', // Hijau untuk data baru
                        'updated' => 'warning', // Kuning/Oranye untuk perubahan data
                        'deleted' => 'danger',  // Merah untuk penghapusan (Sangat Penting!)
                        'sync'    => 'info',    // Biru untuk proses sinkronisasi massal
                        default   => 'gray',
                    }),

                // Kolom Deskripsi: Menampilkan pesan log custom Anda
                TextColumn::make('description')
                    ->label('Deskripsi Perubahan')
                    ->wrap()
                    ->description(function ($record): string {
                        // 1. Logika untuk Event Sinkronisasi (Perpindahan Modul)
                        if ($record->event === 'Sync' || isset($record->properties['modul_asal'])) {
                            $asal = $record->properties['modul_asal'] ?? 'Jurnal 3rd';
                            $tujuan = $record->properties['modul_tujuan'] ?? 'Neraca';

                            return "ALUR: Data telah disinkronkan dari [{$asal}] ke [{$tujuan}]. Status: SINKRON.";
                        }

                        // 2. Logika untuk Event Created (Data baru di Jurnal)
                        if ($record->event === 'created') {
                            return "STATUS: Belum Sinkron. Data baru menetap di [Jurnal 3rd] menunggu validasi.";
                        }

                        // 3. Logika untuk Event Update (Perubahan saat masih di Jurnal)
                        if ($record->event === 'updated' && isset($record->properties['attributes'])) {
                            $status = $record->properties['attributes']['status'] ?? null;

                            if ($status === 'sinkron') {
                                return "STATUS: Sinkron. Perubahan divalidasi dan dikirim ke [Neraca].";
                            }

                            $changes = collect($record->properties['attributes'])
                                ->map(fn($value, $key) => "{$key}: {$value}")
                                ->implode(', ');

                            return "STATUS: Belum Sinkron. Update di [Jurnal 3rd]: " . str($changes)->limit(40);
                        }

                        // 4. Logika untuk Event Delete
                        if ($record->event === 'deleted' && isset($record->properties['old'])) {
                            // Mengambil detail data yang dihapus dari array 'old'
                            $oldData = $record->properties['old'];

                            $akun = $oldData['akun_seratus'] ?? 'N/A';
                            $m3 = number_format($oldData['kubikasi'] ?? 0, 4, ',', '.');
                            $total = number_format($oldData['total'] ?? 0, 0, ',', '.');
                            $user = $oldData['createdBy'] ?? 'Unknown';

                            return "ALUR: Data dihapus dari [Jurnal 3rd]. (Detail: Akun {$akun}, {$m3} m3, {$total}, Input oleh: {$user}). ";
                        }

                        return $record->description;
                    }),
            ])
            ->defaultSort('created_at', 'desc');
    }
    public static function getPages(): array
    {
        return [
            'index' => ManageActivityLogs::route('/'),
        ];
    }
}
