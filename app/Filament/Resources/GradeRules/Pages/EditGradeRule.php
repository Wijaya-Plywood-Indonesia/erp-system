<?php

namespace App\Filament\Resources\GradeRules\Pages;

use App\Filament\Resources\GradeRules\GradeRuleResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditGradeRule extends EditRecord
{
    protected static string $resource = GradeRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
