<?php

namespace App\Exports;

use App\Services\CoaAliasService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * SHEET BARU (tambahan) -- COA general, TIDAK menggantikan
 * LaporanJurnalKayuMasukSheet2 (sheet lama, COA WHN/WJY per jenis kayu).
 *
 * Perbedaan dari sheet lama:
 * - Tidak ada split WHN vs WJY.
 * - Meranti/rijek/baloan/lunak/keras digabung jadi 2 akun umum
 *   berdasarkan panjang: 1402.1 (130) / 1402.2 (selain 130).
 * - Logcore tetap akun sendiri: 1402.21 (130) / 1402.22 (selain 130).
 * - Hutang ongkos turun kayu -> 2195.2 (sesuai COA baru), bukan 2400.01.
 * - Kolom No Akun dipaksa jadi teks supaya titik tidak berubah jadi koma.
 *
 * NOTE REFACTOR: Semua nomor & nama akun (kayu masuk, hutang ongkos,
 * pendapatan, kas mut) sekarang diambil dari App\Services\CoaAliasService
 * supaya satu sumber kebenaran bersama sheet v2 lain (dryer/hotpress/
 * repair/kedi). Tidak ada perubahan logic penentuan kelompok akun
 * (log core vs bukan, 130 vs selain 130) — hanya dipindah ke service.
 */
class LaporanJurnalKayuMasukSheet2New extends DefaultValueBinder implements FromCollection, WithCustomValueBinder, WithStyles, WithTitle
{
    protected CoaAliasService $coaAlias;

    public function bindValue(Cell $cell, $value)
    {
        // Kolom D (No Akun) HARUS selalu jadi teks, apapun isinya.
        // Jangan biarkan is_numeric() men-convert "1402.1" jadi float lalu
        // diformat '0.00' -- itu yang bikin Excel menampilkannya sebagai
        // "1402,10" (desimal lokal Indonesia pakai koma) padahal aslinya titik.
        if ($cell->getColumn() === 'D') {
            $cell->setValueExplicit((string) $value, DataType::TYPE_STRING);

            return true;
        }

        return parent::bindValue($cell, $value);
    }

    protected array $jurnalTables;

    /** Cache agar tidak query API berulang untuk kombinasi jenis_kayu + panjang yang sama */
    private array $idBarangCache = [];

    public function __construct(array $jurnalTables, ?CoaAliasService $coaAlias = null)
    {
        $this->jurnalTables = $jurnalTables;
        $this->coaAlias = $coaAlias ?? app(CoaAliasService::class);
    }

    /**
     * Resolve id_barang kayu dari API akuntansi.
     * Endpoint: GET /api/barang/resolve-kayu?ukuran={130|260}&jenis_kayu={nama}
     * Contoh hasil: "Persediaan Kayu 260 Sengon" → id_barang = <int>
     */
    private function resolveIdBarang(string $jenisKayu, int $panjang): ?int
    {
        $cacheKey = $panjang . '_' . strtolower(trim($jenisKayu));

        if (array_key_exists($cacheKey, $this->idBarangCache)) {
            return $this->idBarangCache[$cacheKey];
        }

        $id = null;

        try {
            $urlApi = rtrim(config('services.akuntansi.url', 'http://localhost:8080'), '/')
                . '/api/barang/resolve-kayu';

            $response = Http::withoutVerifying()
                ->timeout(5)
                ->get($urlApi, [
                    'ukuran'     => $panjang,
                    'jenis_kayu' => $jenisKayu,
                ]);

            if ($response->successful()) {
                $id = $response->json('id_barang');
            } else {
                Log::warning('[JurnalKayuMasuk] Gagal resolve id_barang', [
                    'jenis_kayu' => $jenisKayu,
                    'panjang'    => $panjang,
                    'http_status'=> $response->status(),
                    'body'       => $response->body(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('[JurnalKayuMasuk] Exception resolve id_barang: ' . $e->getMessage(), [
                'jenis_kayu' => $jenisKayu,
                'panjang'    => $panjang,
            ]);
        }

        $this->idBarangCache[$cacheKey] = $id;

        return $id;
    }

    /**
     * @deprecated Logic sudah dipindah ke CoaAliasService::getAkunKayuMasuk().
     * Method ini dipertahankan sebagai thin-wrapper supaya kalau ada
     * pemanggil eksternal lain (mis. test lama) tidak langsung patah,
     * tapi implementasinya sekarang cuma mendelegasikan ke service.
     */
    public function getAccountDetails(string $jenisKayuNama, $panjang): array
    {
        $akun = $this->coaAlias->getAkunKayuMasuk($jenisKayuNama, (int) $panjang);

        return ['no_akun' => $akun['no'], 'nama_akun' => $akun['nama']];
    }

    public function collection()
    {
        $flatRows = [];
        $currentRow = 1;

        $akunHutangOngkos = $this->coaAlias->getAkunHutangOngkosKayu();
        $akunPendapatan = $this->coaAlias->getAkunPendapatan();
        $akunKasMut = $this->coaAlias->getAkunKasMut();

        foreach ($this->jurnalTables as $table) {
            $noJurnal = 'MASUK/'.Carbon::parse($table['tgl_kayu_masuk'])->format('Ymd').'/'.$table['no_nota'];
            $tglVal = Carbon::parse($table['tgl_kayu_masuk'])->format('d/m/Y');

            // Table Header Block
            $flatRows[] = ['No. Jurnal: '.$noJurnal, '', '', '', '', '', '', '', '', '', '', '', '', ''];
            $currentRow++;

            // Column Headers
            $flatRows[] = [
                'Nama Akun', 'tgl', 'jur', 'No Akun', 'No', 'mm',
                'Nama Suplier', 'Lahan', 'm', 'hit kbk', 'Banyak', 'M3', 'Harga', 'Total', 'ID Barang',
            ];
            $currentRow++;

            // 1. Add Debit entries from groups
            foreach ($table['groups'] as $group) {
                $jenisKayuNama = $group['jenis'] ?? '';
                $panjang = $group['panjang'] ?? 130;
                $kodeLahan = $group['kode_lahan'] ?? '';

                if (empty($jenisKayuNama) && ! empty($group['header'])) {
                    $header = $group['header'];

                    if (preg_match('/(\d+)\s*cm/', $header, $matches)) {
                        $panjang = (int) $matches[1];
                    }

                    if (preg_match('/cm\s+(.+?)\s+\(/', $header, $matches)) {
                        $jenisKayuNama = trim($matches[1]);
                    } elseif (preg_match('/cm\s+(.+)$/', $header, $matches)) {
                        $jenisKayuNama = trim($matches[1]);
                    }
                }

                if (empty($kodeLahan) && ! empty($group['header'])) {
                    $tokens = preg_split('/\s+/', trim($group['header']));
                    if (! empty($tokens)) {
                        $kodeLahan = $tokens[0];
                    }
                }

                $akun = $this->coaAlias->getAkunKayuMasuk($jenisKayuNama, (int) $panjang);

                $hargaVal = $group['total_harga'];
                $totalVal = "=IF(J{$currentRow}=\"m\",M{$currentRow}*L{$currentRow},IF(J{$currentRow}=\"b\",M{$currentRow}*K{$currentRow},M{$currentRow}))";

                $flatRows[] = [
                    $akun['nama'],
                    $tglVal,
                    '',
                    $akun['no'],
                    $table['seri'],
                    '',
                    $table['nama_supplier'],
                    $kodeLahan,
                    'd',
                    null,
                    $group['total_batang'],
                    $group['total_kubikasi'],
                    $hargaVal,
                    $totalVal, $this->resolveIdBarang($jenisKayuNama, (int) $panjang),
                ];
                $currentRow++;
            }

            // 2. Add Credit Row 1: hutang ongkos turun kayu -> 2195.2 (COA baru)
            $totalValRow1 = "=IF(J{$currentRow}=\"m\",M{$currentRow}*L{$currentRow},IF(J{$currentRow}=\"b\",M{$currentRow}*K{$currentRow},M{$currentRow}))";
            $flatRows[] = [
                $akunHutangOngkos['nama'],
                $tglVal, '', $akunHutangOngkos['no'], $table['seri'], '', $table['nama_supplier'], '',
                'k', '', '', '', $table['selisih'], $totalValRow1, null,
            ];
            $currentRow++;

            // 3. Add Credit Row 2: pendapatan
            $flatRows[] = [
                $akunPendapatan['nama'],
                $tglVal, '', $akunPendapatan['no'], $table['seri'], '', $table['nama_supplier'], '',
                'k', '', '', '', '', '',
            ];
            $currentRow++;

            // 4. Add Credit Row 3: Kas Mut
            $totalValKasMut = "=IF(J{$currentRow}=\"m\",M{$currentRow}*L{$currentRow},IF(J{$currentRow}=\"b\",M{$currentRow}*K{$currentRow},M{$currentRow}))";
            $flatRows[] = [
                $akunKasMut['nama'],
                $tglVal, '', $akunKasMut['no'], $table['seri'], '', $table['nama_supplier'], '',
                'k', '', $table['totalBatang'], $table['totalKubikasi'], $table['hargaFinal'], $totalValKasMut, null,
            ];
            $currentRow++;

            // Spacer Rows between multiple tables
            $flatRows[] = ['', '', '', '', '', '', '', '', '', '', '', '', '', '', ''];
            $currentRow++;
            $flatRows[] = ['', '', '', '', '', '', '', '', '', '', '', '', '', '', ''];
            $currentRow++;
        }

        return collect($flatRows);
    }

    public function title(): string
    {
        return 'jurnal produksi v2';
    }

    public function styles(Worksheet $sheet)
    {
        $highestRow = $sheet->getHighestRow();

        for ($row = 1; $row <= $highestRow; $row++) {
            $cellValue = $sheet->getCell("A{$row}")->getValue();

            if (str_starts_with((string) $cellValue, 'No. Jurnal:')) {
                $sheet->mergeCells("A{$row}:O{$row}");
                $sheet->getStyle("A{$row}:O{$row}")->applyFromArray([
                    'font' => ['bold' => true, 'size' => 11, 'color' => ['argb' => 'FF1D2939']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFD2E4F0']],
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                ]);
            } elseif ($cellValue === 'Nama Akun') {
                $sheet->getStyle("A{$row}:O{$row}")->applyFromArray([
                    'font' => ['bold' => true, 'size' => 10],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFE5E8EB']],
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                ]);
            } elseif (! empty($cellValue)) {
                $sheet->getStyle("A{$row}:O{$row}")->applyFromArray([
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                ]);

                $sheet->getStyle("B{$row}:F{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("G{$row}:H{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                $sheet->getStyle("I{$row}:J{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("K{$row}:O{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

                $sheet->getStyle("K{$row}")->getNumberFormat()->setFormatCode('#,##0');
                $sheet->getStyle("L{$row}")->getNumberFormat()->setFormatCode('#,##0.0000');
                $sheet->getStyle("M{$row}:O{$row}")->getNumberFormat()->setFormatCode('#,##0');
            }
        }

        $sheet->getColumnDimension('A')->setWidth(25);
        $sheet->getColumnDimension('B')->setWidth(15);
        $sheet->getColumnDimension('C')->setWidth(10);
        $sheet->getColumnDimension('D')->setWidth(15);
        $sheet->getColumnDimension('E')->setWidth(10);
        $sheet->getColumnDimension('F')->setWidth(10);
        $sheet->getColumnDimension('G')->setWidth(25);
        $sheet->getColumnDimension('H')->setWidth(10);
        $sheet->getColumnDimension('I')->setWidth(10);
        $sheet->getColumnDimension('J')->setWidth(12);
        $sheet->getColumnDimension('K')->setWidth(12);
        $sheet->getColumnDimension('L')->setWidth(15);
        $sheet->getColumnDimension('M')->setWidth(18);
        $sheet->getColumnDimension('N')->setWidth(18);
                $sheet->getColumnDimension('O')->setWidth(15);
                $sheet->getColumnDimension('O')->setVisible(false);

        return [];
    }
}
