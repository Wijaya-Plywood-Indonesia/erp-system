<?php
// app/Enums/StrategiPembagian.php
namespace App\Enums;

enum StrategiPembagian: string
{
    case Kolektif = 'kolektif';           // Opsi A
    case Proporsional = 'proporsional';   // Opsi B
    case IndividualTarget = 'individual_target'; // kasus Repair
}
