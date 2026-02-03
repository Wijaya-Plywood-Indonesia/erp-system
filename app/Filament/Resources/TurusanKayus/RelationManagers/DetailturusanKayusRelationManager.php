<?php

namespace App\Filament\Resources\TurusanKayus\RelationManagers;

use App\Filament\Resources\DetailTurusanKayus\Schemas\DetailTurusanKayuForm;
use App\Filament\Resources\DetailTurusanKayus\Tables\DetailTurusanKayusTable;
use App\Models\DetailTurunKayu;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class DetailturusanKayusRelationManager extends RelationManager
{
    protected static string $relationship = 'DetailturusanKayus';

    protected $listeners = ['refreshDatatable' => '$refresh'];

    public static function canViewForRecord($ownerRecord, $pageClass): bool
    {
        $detailTurun = DetailTurunKayu::where('id_kayu_masuk', $ownerRecord->id)->first();
        if (!$detailTurun) return false;

        return $detailTurun->status === 'menunggu' || $detailTurun->status === 'selesai';
    }

    public function form(Schema $schema): Schema
    {
        // Tetap gunakan schema form yang sudah ada di Relation Manager Anda jika perlu
        return DetailTurusanKayuForm::configure($schema);
    }

    public function table(Table $table): Table
    {
        // Cukup panggil configure pusat dan kirimkan $this
        return DetailTurusanKayusTable::configure($table, $this);
    }
}
