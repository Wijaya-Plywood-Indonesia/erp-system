<?php

namespace App\Filament\Resources\GradeRules\Pages;

use App\Filament\Resources\GradeRules\GradeRuleResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewGradeRule extends ViewRecord
{
    protected static string $resource = GradeRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
