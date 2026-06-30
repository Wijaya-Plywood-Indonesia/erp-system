<?php

namespace App\Filament\Resources\NotaKayus\RelationManagers;

use App\Models\ComparisonRow;
use App\Models\JenisKayu;
use App\Models\Lahan;
use App\Services\KayuComparator;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;

class KayuMasukRelationManager extends RelationManager
{
    protected static string $relationship = 'kayuMasuk';

    public static function getTitle($ownerRecord, string $pageClass): string
    {
        return 'Perbandingan Detail & Turusan';
    }

    public function getTableQuery(): Builder
    {
        return ComparisonRow::query()
            ->where('id_kayu_masuk', $this->ownerRecord->id_kayu_masuk);
    }
    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('no')
                    ->label('No')
                    ->rowIndex(),
                TextColumn::make('jenis_kayu_label')
                    ->label('Jenis Kayu')
                    ->getStateUsing(function ($record) {
                        $jk = JenisKayu::find($record->id_jenis_kayu);

                        if (!$jk) {
                            return '-';
                        }

                        return "{$jk->nama_kayu}";
                    })
                    ->sortable(),
                TextColumn::make('lahan_label')
                    ->label('Lahan')
                    ->getStateUsing(function ($record) {
                        $jk = Lahan::find($record->id_lahan);

                        if (!$jk) {
                            return '-';
                        }

                        return "{$jk->kode_lahan}";
                    })
                    ->sortable(),
                TextColumn::make('panjang'),
                // TextColumn::make('grade'),
                TextColumn::make('grade')
                    ->label('Grade')
                    ->getStateUsing(function ($record) {
                        return match ((int) $record->grade) {
                            1 => 'A',
                            2 => 'B',
                            default => '-',
                        };
                    })
                    ->color(fn($state) => match ($state) {
                        'A' => 'success',
                        'B' => 'primary',
                        default => 'gray',
                    }),

                TextColumn::make('diameter')
                    ->suffix(' cm'),

                TextColumn::make('detail_jumlah')
                    ->label('Turusan 1')
                    ->suffix(' Btg'),

                TextColumn::make('turusan_jumlah')
                    ->label('Turusan 2')
                    ->suffix(' Btg'),
                TextColumn::make('selisih')
                    ->label('Selisih')
                    ->getStateUsing(fn($record) => (float) $record->selisih)   // pastikan angka
                    ->formatStateUsing(fn($state) => number_format((float) $state)) // fix formatter
                    ->color(fn($state) => (float) $state < 0 ? 'danger' : ((float) $state > 0 ? 'success' : 'gray')),
            ])
            ->defaultSort('selisih');
    }

}
