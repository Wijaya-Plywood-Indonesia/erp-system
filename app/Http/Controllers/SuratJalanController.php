<?php

namespace App\Http\Controllers;

use App\Models\NotaBarangKeluar;

class SuratJalanController extends Controller
{
    /**
     * Cetak Surat Jalan untuk Nota Barang Keluar.
     */
    public function printBk(NotaBarangKeluar $nota, $jenis = null)
    {
        $nota->load(['detail', 'pembuat', 'plywoodMutasi.details.ukuran', 'plywoodMutasi.details.jenisKayu', 'mutasi.details']);

        $details = $nota->detail->map(function ($d) use ($nota, $jenis) {
            if ($jenis === null) {
                return $d;
            }

            if (str_starts_with($d->nama_barang, 'Plywood ')) {
                $matchedDetail = null;
                if ($nota->plywoodMutasi) {
                    foreach ($nota->plywoodMutasi->details as $mutasiDetail) {
                        $ukuran = $mutasiDetail->ukuran;
                        $jenisKayu = $mutasiDetail->jenisKayu;
                        if (!$ukuran || !$jenisKayu) continue;

                        $expectedName = 'Plywood - '.$ukuran->nama_ukuran
                            .' - '.$jenisKayu->nama_kayu
                            .' - KW '.$mutasiDetail->kw_grade;

                        if ($expectedName === $d->nama_barang && (int) $mutasiDetail->qty === (int) $d->jumlah) {
                            $matchedDetail = $mutasiDetail;
                            break;
                        }
                    }
                }

                $tebalVal = null;
                if ($matchedDetail && $matchedDetail->ukuran && filled($matchedDetail->ukuran->tebal)) {
                    $tebalVal = $matchedDetail->ukuran->tebal;
                } elseif (preg_match('/(\d+(?:[\.,]\d+)?)\s*(?:mm|m)?\b/i', $d->nama_barang, $matches)) {
                    $tebalVal = str_replace(',', '.', $matches[1]);
                }

                $tebalStr = null;
                if ($tebalVal !== null && $tebalVal !== '') {
                    $tebalFormatted = (float) $tebalVal == (int) $tebalVal
                        ? (int) $tebalVal
                        : rtrim(rtrim((string) $tebalVal, '0'), '.');
                    $tebalStr = "{$tebalFormatted} mm";
                }

                if ($jenis === 'sales') {
                    $rawMerek = $matchedDetail ? ($matchedDetail->barang?->merek ?? null) : null;
                    $merek = (! empty(trim($rawMerek ?? ''))) ? trim($rawMerek) : 'Plywood';
                    $d->nama_barang = $tebalStr !== null ? "{$tebalStr} {$merek}" : $merek;
                } else {
                    $bshp = $d->barang ?? null;
                    $gradeModel = $bshp?->grade ?? ($bshp?->id_grade ? \App\Models\Grade::find($bshp->id_grade) : null);
                    $gradeName = ! empty(trim($gradeModel?->nama_grade ?? '')) ? trim($gradeModel->nama_grade) : null;
                    
                    if (! $gradeName && $matchedDetail && ! empty(trim($matchedDetail->kw_grade ?? ''))) {
                        $gradeName = trim($matchedDetail->kw_grade);
                    }
                    
                    if ($tebalStr !== null && $gradeName !== null) {
                        $d->nama_barang = "{$tebalStr} {$gradeName}";
                    } elseif ($tebalStr !== null) {
                        $d->nama_barang = $tebalStr;
                    } elseif ($gradeName !== null) {
                        $d->nama_barang = $gradeName;
                    } else {
                        $d->nama_barang = $bshp?->label ?? ($d->nama_barang ?? '-');
                    }
                }
            } elseif (str_starts_with($d->nama_barang, 'Veneer ') && $jenis !== null) {
                $matchedMutasiDetail = null;
                if ($nota->mutasi) {
                    foreach ($nota->mutasi->details as $mutasiDetail) {
                        if ((float) $mutasiDetail->qty === (float) $d->jumlah) {
                            $matchedMutasiDetail = $mutasiDetail;
                            break;
                        }
                    }
                }

                $kw = null;
                $merek = null;
                $tebalValForVeneer = null;
                
                if ($matchedMutasiDetail) {
                    $kw = $matchedMutasiDetail->kw;
                    $ukuranId = $matchedMutasiDetail->id_ukuran;
                    if ($ukuranId) {
                        $ukuranObjForVeneer = \App\Models\Ukuran::find($ukuranId);
                        if ($ukuranObjForVeneer && filled($ukuranObjForVeneer->tebal)) {
                            $tebalValForVeneer = $ukuranObjForVeneer->tebal;
                        }
                        
                        $grade = \App\Models\Grade::whereRaw('LOWER(TRIM(nama_grade)) = ?', [strtolower(trim($kw))])->first();
                        $bshp = \App\Models\BarangSetengahJadiHp::where('id_ukuran', $ukuranId)
                            ->when($grade, fn($q) => $q->where('id_grade', $grade->id))
                            ->first();
                        $merek = $bshp?->merek;
                    }
                } else {
                    if (preg_match('/KW\s+(.*)$/i', $d->nama_barang, $m)) {
                        $kw = trim($m[1]);
                    }
                    if (preg_match('/x\s*(\d+(?:[\.,]\d+)?)\s*(?:mm|m)?\b/i', $d->nama_barang, $m)) {
                        $tebalValForVeneer = str_replace(',', '.', $m[1]);
                    } elseif (preg_match_all('/(\d+(?:[\.,]\d+)?)\s*(?:mm|m)?\b/i', $d->nama_barang, $m)) {
                        $tebalValForVeneer = str_replace(',', '.', end($m[1]));
                    }
                }

                $tebalForVeneerStr = '';
                if ($tebalValForVeneer !== null && $tebalValForVeneer !== '') {
                    $tebalForVeneerStr = (float) $tebalValForVeneer == (int) $tebalValForVeneer
                        ? (int) $tebalValForVeneer
                        : rtrim(rtrim((string) $tebalValForVeneer, '0'), '.');
                }

                if (! empty(trim($merek ?? ''))) {
                    $d->nama_barang = trim("{$tebalForVeneerStr} {$merek}");
                } else {
                    $kwStr = $kw ? "kw {$kw}" : '';
                    $d->nama_barang = trim("{$tebalForVeneerStr} {$kwStr}");
                }
            }
            
            return $d;
        });

        $view = $jenis === 'sales' ? 'surat-jalan.cetak-sales' : 'surat-jalan.cetak-kantor';
        return view($view, [
            'nota'    => $nota,
            'details' => $details,
        ]);
    }
}