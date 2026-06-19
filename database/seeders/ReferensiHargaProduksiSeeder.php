<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Ukuran;
use App\Models\JenisKayu;
use App\Models\SubAnakAkun;
use App\Models\ReferensiHargaProduksi;

class ReferensiHargaProduksiSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Kosongkan dulu tabel referensi_harga_produksi
        ReferensiHargaProduksi::query()->delete();

        $rawData = <<<EOF
12fm mentah MTH	1506,04	12fm mentah MTH	 208.000 	122 x 244 x 12	Plywood
12m better lokal MTH	1506,06	12m better lokal MTH	 96.000 	122 x 244 x 12	Plywood
12m pg MTH	1506,07	12m pg MTH	 104.000 	122 x 244 x 12	Plywood
12s better MTH	1506,93	12s better MTH	 98.000 	122 x 244 x 12	Plywood
Veneer Jadi 130 core meranti WHN	1467,01	130 Core jabon uk 1.2	 2.800.000 	122 x 244 x 1,2 / 244 x 122 x 1,2	Veneer
Veneer Jadi 130 core meranti WHN	1467,01	130 Core jabon uk 2.2	 2.800.000 	122 x 244 x 2,2 / 244 x 122 x 2,2	Veneer
Veneer Jadi 130 core meranti WHN	1467,01	130 Core jabon uk 3.7	 2.800.000 	122 x 244 x 3,7 / 244 x 122 x 3,7	Veneer
Veneer Jadi 130 core meranti WHN	1467,01	130 Core mahoni uk 1.8	 2.800.000 	122 x 244 x 1,8 / 244 x 122 x 1,8	Veneer
Veneer Jadi 130 core meranti WJY	1467	130 core meranti uk 1.8	 2.800.000 	122 x 244 x 1,8 / 244 x 122 x 1,8	Veneer
Veneer Jadi 130 core sengon WHN	1466,01	130 Core sengon uk 1.2	 2.250.000 	122 x 244 x 1,2 / 244 x 122 x 1,2	Veneer
Veneer Jadi 130 core sengon WHN	1466,01	130 Core sengon uk 1.8	 2.250.000 	122 x 244 x 1,8 / 244 x 122 x 1,8	Veneer
Veneer Jadi 130 core sengon WHN	1466,01	130 Core sengon uk 2.2	 2.250.000 	122 x 244 x 2,2 / 244 x 122 x 2,2	Veneer
Veneer Jadi 130 core sengon WHN	1466,01	130 Core sengon uk 3.7	 2.250.000 	122 x 244 x 3,7 / 244 x 122 x 3,7	Veneer
Veneer Jadi 130 core sengon WHN	1466,01	130 Core Sengon uk 3.8 // bahan tgl 13/05	 2.250.000 	122 x 244 x 3,8 / 244 x 122 x 3,8	Veneer
Veneer Jadi 130 core meranti WHN	1467,01	130 Core waru uk 1.8	 2.800.000 	122 x 244 x 1,8 / 244 x 122 x 1,8	Veneer
15m better lokal MTH	1506,11	15m better lokal MTH	 127.000 	122 x 244 x 15	Plywood
15m better MTH	1506,1	15m better MTH	 131.000 	122 x 244 x 18	Plywood
18m better lokal MTH	1506,17	18m better lokal MTH	 177.000 	122 x 244 x 18	Plywood
Veneer Jadi 260 face/back meranti WHN	1462,01	260 F/B jabon uk 0.5	 10.000.000 	122 x 244 x 0,5 / 244 x 122 x 0,5	Veneer
Veneer Jadi 260 face/back meranti WHN	1462,01	260 F/B meranti uk 0.3 Face	 12.500.000 	122 x 244 x 0,3 / 244 x 122 x 0,3	Veneer
Veneer Jadi 260 face/back meranti WHN	1462,01	260 F/B meranti uk 0.3 back	 10.000.000 	123 x 244 x 0,3 / 244 x 122 x 0,3	Veneer
Veneer Jadi 260 face/back sengon WHN	1461,01	260 F/B sengon uk 0.5	 4.000.000 	124 x 244 x 0,5 / 244 x 122 x 0,5	Veneer
Veneer Jadi ppc sengon WHN	1472,01	260 F/B sengon uk 0.5 afalan	 1.700.000 	125 x 244 x 0,5 / 244 x 122 x 0,5	Afalan
5s uty lokal MTH	1506,26	5s uty lokal MTH	 47.000 	122 x 244 x 5	Plywood
5s uty lokal MTH	1506,26	5s uty lokal MTH	 47.000 	122 x 244 x 5	Plywood
9fm MTH	1506,35	9fm MTH	 157.000 	122 x 244 x 9	Plywood
9m aj MTH	1506,34	9m aj MTH	 73.000 	122 x 244 x 9	Plywood
9m better lokal MTH	1506,33	9m better local MTH	 91.000 	122 x 244 x 9	Plywood
9m better MTH	1506,32	9m better MTH	 95.000 	122 x 244 x 9	Plywood
9m aj MTH	1506,34	9s aj MTH	 73.000 	122 x 244 x 9	Plywood
Veneer Jadi ppc sengon WHN	1472,01	aff uk 1.5	 1.500.000 	122 x 244 x 1,5	Veneer
Veneer Jadi ppc meranti WJY	1471	aff uk 1.8	 1.800.000 	122 x 244 x 1,8	Veneer
Veneer Jadi ppc sengon WHN	1472,01	aff uk 3.7	 1.500.000 	122 x 244 x 3,7	Veneer
platform 11fm MTH WJY	1506,85	platform 11 fm WJY	 198.000 	122 x 244 x 11	Platform
platform 12 better lokal MTH	1506,41	platform 12 better lokal MTH	 86.000 	123 x 244 x 12	Platform
platform 12 better lokal MTH	1506,41	platform 12 better lokal MTH	 94.000 	124 x 244 x 12	Platform
platform 12 pg MTH	1506,42	platform 12 pg MTH	 94.000 	125 x 244 x 12	Platform
platform 12fm MTH	1506,65	platform 12fm MTH	 198.000 	126 x 244 x 15	Platform
platform 15 better lokal MTH	1506,68	platform 15 better lokal MTH	 117.000 	127 x 244 x 15	Platform
platform 15 better MTH	1506,45	platform 15 better MTH	 121.000 	128 x 244 x 15	Platform
platform 15fm MTH WJY	1506,82	platform 15 fm WJY // hasil tgl 9	 249.000 	129 x 244 x 15	Platform
platform 18 better lokal MTH	1506,5	platform 18 better lokal MTH	 167.000 	130 x 244 x 18	Platform
platform 8 aj MTH	1506,59	platform 8 aj MTH	 63.000 	131 x 244 x 8	Platform
platform 9 better lokal MTH	1506,62	platform 9 better lokal MTH	 81.000 	132 x 244 x 9	Platform
platform 9 better MTH	1506,61	platform 9 better MTH	 85.000 	133 x 244 x 9	Platform
platform 9fm MTH	1506,89	platform 9fm MTH	 147.000 	134 x 244 x 9	Platform
Veneer Jadi 260 face/back sengon WHN	1461,01		 4.000.000 	null	Veneer
Veneer Jadi ppc sengon WHN	1472,01		 1.500.000 	null	Afalan
Hadner	1507,11	Hadner	 10 	null	Barang
hadner WJY	1507,59	hadner WJY	 10 	null	Barang
Isi Staples	1507,13	Isi Staples	 1.525 	null	Barang
Isi Staples WJY	1507,61	Isi Staples WJY	 1.525 	null	Barang
isolasi putih	1507,36	isolasi putih	 1.600 	null	Barang
isolasi putih WJY	1507,66	isolasi putih WJY	 1.600 	null	Barang
Lem Aruki	1507,20	Lem Aruki	 7.000 	null	Barang
Lem PAI	1507,22	Lem PAI	 7.300 	null	Barang
Lem PAI WJY	1507,65	Lem PAI WJY	 7.300 	null	Barang
Pewarna	1507,49	Pewarna	 31 	null	Barang
Tepung 	1507,16	Tepung 	 4.500 	null	Barang
Tepung WJY	1507,62	Tepung WJY	 4.500 	null	Barang
EOF;

        $lines = explode("\n", trim($rawData));
        $missingSubAccounts = [];

        foreach ($lines as $line) {
            $cols = explode("\t", trim($line));
            if (count($cols) < 4) {
                continue;
            }

            $subAccountName = trim($cols[0]);
            $subAccountCode = trim($cols[1]);
            $refName = trim($cols[2]);
            $priceStr = trim($cols[3]);
            $sizeStr = isset($cols[4]) ? trim($cols[4]) : null;

            // 1. Clean up price
            $price = (float) str_replace('.', '', $priceStr);

            // 2. Determine id_sub_anak_akun
            $idSubAnakAkun = null;
            if ($subAccountCode !== '') {
                $code = str_replace(',', '.', $subAccountCode);
                $subAkun = SubAnakAkun::where('kode_sub_anak_akun', $code)->first();
                
                // Fallback: if not found, format numeric codes to two decimal places (e.g. 1467 -> 1467.00)
                if (!$subAkun && is_numeric($code)) {
                    $formattedCode = number_format((float) $code, 2, '.', '');
                    $subAkun = SubAnakAkun::where('kode_sub_anak_akun', $formattedCode)->first();
                }

                if ($subAkun) {
                    $idSubAnakAkun = $subAkun->id;
                } else {
                    $missingSubAccounts[$code] = $subAccountName;
                }
            }

            // 3. Determine id_ukuran
            $idUkuran = null;
            if ($sizeStr && strtolower($sizeStr) !== 'null') {
                $parts = explode('/', $sizeStr);
                $firstPart = trim($parts[0]);
                $firstPart = str_replace(',', '.', $firstPart);

                if (preg_match('/(\d+(?:\.\d+)?)\s*[xX]\s*(\d+(?:\.\d+)?)\s*[xX]\s*(\d+(?:\.\d+)?)/', $firstPart, $matches)) {
                    $dim1 = (float) $matches[1];
                    $dim2 = (float) $matches[2];
                    $tebal = (float) $matches[3];

                    $ukuran = Ukuran::where(function ($q) use ($dim1, $dim2) {
                        $q->where(function ($q2) use ($dim1, $dim2) {
                            $q2->where('panjang', $dim1)->where('lebar', $dim2);
                        })->orWhere(function ($q2) use ($dim1, $dim2) {
                            $q2->where('panjang', $dim2)->where('lebar', $dim1);
                        });
                    })->where('tebal', $tebal)->first();

                    if (!$ukuran) {
                        $ukuran = Ukuran::create([
                            'panjang' => $dim1,
                            'lebar' => $dim2,
                            'tebal' => $tebal,
                        ]);
                    }

                    $idUkuran = $ukuran->id;
                }
            }

            // 5. Determine jenis_barang
            $checkText = strtolower($subAccountName . ' ' . $refName);
            $jenisBarang = 'Lain-Lain';
            if (isset($cols[5]) && trim($cols[5]) !== '') {
                $jenisBarang = trim($cols[5]);
            } else {
                if (stripos($checkText, 'platform') !== false) {
                    $jenisBarang = 'Platform';
                } elseif (stripos($checkText, 'veneer jadi') !== false) {
                    $jenisBarang = 'Veneer Jadi';
                } elseif (stripos($checkText, 'veneer basah') !== false) {
                    $jenisBarang = 'Veneer Basah';
                } elseif (stripos($checkText, 'veneer kering') !== false) {
                    $jenisBarang = 'Veneer Kering';
                } elseif (stripos($checkText, 'afalan') !== false || stripos($checkText, 'aff') !== false) {
                    $jenisBarang = 'Afalan';
                }
            }

            // 4. Determine id_jenis_kayu
            $idJenisKayu = null;
            $searchTerms = ['meranti' => 'meranti', 'sengon' => 'sengon', 'jabon' => 'jabon', 'mahoni' => 'mahoni', 'waru' => 'waru'];
            $matchedKayu = null;

            // Prioritize refName (specific) over subAccountName (general)
            foreach ($searchTerms as $term => $dbName) {
                if (stripos($refName, $term) !== false) {
                    $matchedKayu = $dbName;
                    break;
                }
            }

            if (!$matchedKayu) {
                foreach ($searchTerms as $term => $dbName) {
                    if (stripos($subAccountName, $term) !== false) {
                        $matchedKayu = $dbName;
                        break;
                    }
                }
            }

            if (!$matchedKayu && $jenisBarang === 'Plywood') {
                $textToCheck = $refName !== '' ? $refName : $subAccountName;
                if (preg_match('/\b\d+(f)?(m|s)\b/i', $textToCheck, $matches)) {
                    $char = strtolower($matches[2]);
                    if ($char === 'm') {
                        $matchedKayu = 'meranti';
                    } elseif ($char === 's') {
                        $matchedKayu = 'sengon';
                    }
                }
            }

            if ($matchedKayu) {
                $jenisKayu = JenisKayu::where('nama_kayu', 'like', "%{$matchedKayu}%")->first();
                if ($jenisKayu) {
                    $idJenisKayu = $jenisKayu->id;
                }
            }

            // 6. Determine kw
            $kw = 'Standard';
            if (stripos($checkText, 'mentah') !== false) {
                $kw = 'Mentah';
            } elseif (stripos($checkText, 'better') !== false) {
                $kw = stripos($checkText, 'lokal') !== false ? 'Better Lokal' : 'Better';
            } elseif (stripos($checkText, 'pg') !== false) {
                $kw = 'PG';
            } elseif (stripos($checkText, 'aj') !== false) {
                $kw = 'AJ';
            } elseif (stripos($checkText, 'uty') !== false) {
                $kw = stripos($checkText, 'lokal') !== false ? 'Utility Lokal' : 'Utility';
            } elseif (stripos($checkText, 'core') !== false) {
                $kw = 'Core';
            } elseif (stripos($checkText, 'face/back') !== false || stripos($checkText, 'f/b') !== false) {
                $kw = 'Face/Back';
            }

            // 7. Insert/Create/Update ReferensiHargaProduksi record to avoid unique constraints
            ReferensiHargaProduksi::updateOrCreate(
                [
                    'nama' => $refName !== '' ? $refName : null,
                    'id_ukuran' => $idUkuran,
                    'id_jenis_kayu' => $idJenisKayu,
                    'jenis_barang' => $jenisBarang,
                    'kw' => $kw,
                    'id_sub_anak_akun' => $idSubAnakAkun,
                ],
                [
                    'harga' => $price,
                ]
            );
        }

        // Output missing sub accounts
        if (count($missingSubAccounts) > 0) {
            $this->command->warn('Beberapa Sub Anak Akun tidak ditemukan di database (di-set NULL):');
            foreach ($missingSubAccounts as $code => $name) {
                $this->command->line("- [{$code}] {$name}");
            }
        }
    }
}
