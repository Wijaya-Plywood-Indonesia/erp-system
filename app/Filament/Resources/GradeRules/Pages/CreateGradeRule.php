<?php

namespace App\Filament\Resources\GradeRules\Pages;

use App\Filament\Resources\GradeRules\GradeRuleResource;
use App\Models\GradeRule;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\DB;

class CreateGradeRule extends CreateRecord
{
    protected static string $resource = GradeRuleResource::class;

    protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model
    {
        $idCriteria = $data['id_criteria'];
        $rules = $data['rules_repeater'];

        return DB::transaction(function () use ($idCriteria, $rules) {
            $lastRecord = null;

            foreach ($rules as $rule) {
                // Simpan atau perbarui aturan untuk setiap grade
                $lastRecord = GradeRule::updateOrCreate(
                    [
                        'id_grade' => $rule['id_grade'],
                        'id_criteria' => $idCriteria,
                    ],
                    [
                        'kondisi' => $rule['kondisi'],
                        'poin_lulus' => $rule['poin_lulus'],
                        'poin_parsial' => $rule['poin_parsial'] ?? 0,
                        'penjelasan' => $rule['penjelasan'],
                    ]
                );
            }

            // Kembalikan salah satu record agar Filament tidak error
            return $lastRecord;
        });
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
