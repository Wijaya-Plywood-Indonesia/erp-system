<?php

namespace App\Filament\Resources\KontrakKerjas\Tables;

use App\Forms\Components\CompressedFileUpload;
use App\Models\KontrakKerja;
use App\Services\NomorKontrakService;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Support\Facades\DB;

class KontrakKerjasTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('no_kontrak', 'desc')
            ->columns([
                TextColumn::make('no_kontrak')
                    ->label('No Dokumen Kontrak')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('kode')
                    ->label('Kode Pegawai')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('nama')
                    ->label('Nama Pegawai')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('kontrak_mulai')
                    ->label('Mulai')
                    ->date()
                    ->sortable(),

                TextColumn::make('kontrak_selesai')
                    ->label('Selesai')
                    ->date()
                    ->sortable(),

                TextColumn::make('durasi_kontrak')
                    ->label('Durasi (hari)')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('status_dokumen')
                    ->label('Dokumen')
                    ->badge()
                    ->color(fn($state) => match ($state) {
                        'draft' => 'gray',
                        'dicetak' => 'warning',
                        'ditandatangani' => 'success',
                        default => 'gray',
                    }),

                TextColumn::make('status_kontrak')
                    ->label('Status')
                    ->badge()
                    ->sortable()
                    ->color(fn($state) => match ($state) {
                        'active' => 'success',
                        'soon' => 'warning',
                        'expired' => 'danger',
                        'extended' => 'extended',
                        default => 'gray',
                    }),

                TextColumn::make('dibuat_oleh')
                    ->label('Dibuat Oleh')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('divalidasi_oleh')
                    ->label('Divalidasi Oleh')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->paginated(false)
            ->filters([
                SelectFilter::make('status_kontrak')
                    ->label('Status Pegawai')
                    ->options([
                        'active' => 'Aktif',
                        'soon' => 'Segera Habis',
                        'expired' => 'Habis',
                        'extended' => 'Perpanjangan'
                    ]),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),

                Action::make('updateStatusDokumen')
                    ->label('Update Bukti & Status')
                    ->icon('heroicon-o-document-check')
                    ->color('success')

                    // --- LOGIKA DISABLE TOMBOL ---
                    ->disabled(function ($record) {
                        $user = auth()->user();
                        $currentUser = $user->name;

                        // 1. Super Admin BEBAS (Tidak pernah disable)
                        if ($user->hasAnyRole(['super_admin', 'Super Admin'])) {
                            return false;
                        }

                        // 2. Jika sudah Selesai, user biasa dilarang klik
                        if ($record->status_dokumen === 'ditandatangani') {
                            return true;
                        }

                        // 3. TAHAP 1 SELESAI: Jika sudah dicetak dan dia pembuatnya, dia dilarang validasi sendiri
                        if ($record->status_dokumen === 'dicetak' && $record->dibuat_oleh === $currentUser) {
                            return true;
                        }

                        return false;
                    })

                    // --- LOGIKA LABEL TOMBOL MODAL ---
                    ->modalSubmitActionLabel(function ($record) {
                        $user = auth()->user();

                        if ($user->hasAnyRole(['super_admin', 'Super Admin'])) {
                            return 'Super Admin';
                        }

                        return ($record->status_dokumen === 'draft') ? 'Konfirmasi Pencetakan' : 'Validasi Dokumen';
                    })

                    ->mountUsing(fn(\Filament\Schemas\Schema $form, $record) => $form->fill([
                        'status_dokumen' => $record->status_dokumen,
                        'bukti_ttd' => $record->bukti_ttd,
                    ]))

                    ->form([
                        Grid::make(1)
                            ->schema([
                                Select::make('status_dokumen')
                                    ->label('Status Dokumen')
                                    ->options([
                                        'draft' => 'Draft',
                                        'dicetak' => 'Dicetak',
                                        'ditandatangani' => 'Ditandatangani',
                                    ])
                                    // Hanya Admin yang bisa ganti status manual lewat Select
                                    ->disabled(fn() => !auth()->user()->hasAnyRole(['super_admin', 'Super Admin']))
                                    ->dehydrated()
                                    ->columnSpanFull(),

                                CompressedFileUpload::make('bukti_ttd')
                                    ->label('Upload Bukti (Foto/Scan)')
                                    ->disk('public')
                                    ->directory('bukti_kontrak')
                                    ->imageEditor()
                                    ->required()
                                    ->columnSpanFull(),
                            ]),
                    ])
                    ->action(function ($record, array $data) {
                        $user = auth()->user();
                        $currentUser = $user->name;

                        $statusBaru = $record->status_dokumen;
                        $validator = $record->divalidasi_oleh;

                        // 1. Logika Super Admin (Langsung/Manual)
                        if ($user->hasAnyRole(['super_admin', 'Super Admin'])) {
                            $statusBaru = $data['status_dokumen'];
                            $validator = $currentUser;
                        }
                        // 2. Logika User Biasa (Sesuai Tahapan)
                        else {
                            if ($record->dibuat_oleh === $currentUser) {
                                // Tahap 1: Pembuat merubah dari draft -> dicetak
                                $statusBaru = 'dicetak';
                                $validator = null;
                            } else {
                                // Tahap 2: Validator (Orang lain) merubah dari dicetak -> ditandatangani
                                $statusBaru = 'ditandatangani';
                                $validator = $currentUser;
                            }
                        }

                        $record->update([
                            'status_dokumen' => $statusBaru,
                            'bukti_ttd' => $data['bukti_ttd'],
                            'divalidasi_oleh' => $validator,
                        ]);

                        Notification::make()
                            ->title('Status Berhasil Diperbarui: ' . ucfirst($statusBaru))
                            ->success()
                            ->send();
                    })
                    ->modalWidth('xl')
                    ->modalHeading('Pembaharuan Status Dokumen'),

                Action::make('print')
                    ->label('Cetak Kontrak')
                    ->icon('heroicon-o-printer')
                    ->url(fn($record): string => route('kontrak.print', $record))
                    ->openUrlInNewTab(),

                Action::make('perpanjang')
                    ->label('Perpanjang')
                    ->icon('heroicon-o-arrow-path')
                    ->form([
                        Select::make('durasi')
                            ->label('Durasi Perpanjangan')
                            ->options([
                                30 => '30 Hari',
                                60 => '60 Hari',
                                90 => '90 Hari',
                            ])
                            ->required(),
                    ])
                    ->action(function ($record, array $data) {
                        $durasi = intval($data['durasi']);
                        $mulaiBaru = \App\Helpers\HolidayHelper::nextWorkingDay(Carbon::parse($record->kontrak_selesai)->addDay());
                        $selesaiBaru = \App\Helpers\HolidayHelper::previousWorkingDay($mulaiBaru->copy()->addDays($durasi));

                        KontrakKerja::create([
                            'kode' => $record->kode,
                            'nama' => $record->nama,
                            'jenis_kelamin' => $record->jenis_kelamin,
                            'tanggal_masuk' => $record->tanggal_masuk,
                            'karyawan_di' => $record->karyawan_di,
                            'alamat_perusahaan' => $record->alamat_perusahaan,
                            'jabatan' => $record->jabatan,
                            'nik' => $record->nik,
                            'tempat_tanggal_lahir' => $record->tempat_tanggal_lahir,
                            'alamat' => $record->alamat,
                            'no_telepon' => $record->no_telepon,
                            'kontrak_mulai' => $mulaiBaru,
                            'kontrak_selesai' => $selesaiBaru,
                            'durasi_kontrak' => $durasi,
                            'tanggal_kontrak' => \App\Helpers\HolidayHelper::nextWorkingDay(now()),
                            'no_kontrak' => NomorKontrakService::generate(),
                            'status_dokumen' => 'draft',
                            'status_kontrak' => 'extended',
                            'dibuat_oleh' => auth()->user()->name,
                            'divalidasi_oleh' => null,
                        ]);
                    })
                    ->color('warning'),
            ])
            ->defaultSort('id', 'desc')
            ->toolbarActions([
                Action::make('update_kontrak')
                    ->label('Update Status & Durasi Kontrak')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->action(function () {
                        DB::statement("
                            UPDATE kontrak_kerja
                            SET 
                                durasi_kontrak = CASE
                                    WHEN kontrak_mulai IS NULL OR kontrak_selesai IS NULL THEN 0
                                    ELSE DATEDIFF(kontrak_selesai, kontrak_mulai)
                                END,
                                status_kontrak = CASE
                                    WHEN kontrak_mulai IS NULL OR kontrak_selesai IS NULL THEN 'expired'
                                    WHEN CURDATE() > kontrak_selesai THEN 'expired'
                                    WHEN DATEDIFF(kontrak_selesai, CURDATE()) <= 30 THEN 'soon'
                                    ELSE 'active'
                                END
                            WHERE status_kontrak != 'extended'
                        ");

                        Notification::make()
                            ->title('Status & durasi kontrak berhasil diperbarui')
                            ->success()
                            ->send();
                    }),

                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    BulkAction::make('bulk_print')
                        ->label('Cetak Kontrak')
                        ->icon('heroicon-o-printer')
                        ->color('success')
                        ->action(fn($records) => redirect()->route('kontrak.bulk.print', ['ids' => implode(',', $records->pluck('id')->toArray())]))
                        ->openUrlInNewTab(),
                ]),
            ]);
    }
}
