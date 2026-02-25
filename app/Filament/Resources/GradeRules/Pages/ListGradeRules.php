<?php

namespace App\Filament\Resources\GradeRules\Pages;

use App\Filament\Resources\GradeRules\GradeRuleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListGradeRules extends ListRecords
{
    protected static string $resource = GradeRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
