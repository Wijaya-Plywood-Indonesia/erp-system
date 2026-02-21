<?php

namespace App\Filament\Pages;

use App\Models\JurnalUmum;
use Carbon\CarbonPeriod;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;

use BackedEnum;
use UnitEnum;

class NeracaAktivaPasifa extends Page
{
    protected string $view = 'filament.pages.neraca-aktiva-pasifa';
    protected Width|string|null $maxContentWidth = Width::Full;
    protected static ?string $navigationLabel = 'Neraca Aset';
    protected static ?string $title = 'Neraca Aset';
    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-scale';
    protected static string|UnitEnum|null $navigationGroup = 'Jurnal';


    // FILTER STATE
    public int $bulan_mulai;
    public int $tahun_mulai;

    public int $bulan_akhir;
    public int $tahun_akhir;

    public array $periodeData = []; // Hasil akhir

    public function mount(): void
    {
        $now = now();

        // Default bulan ini
        $this->bulan_mulai = $now->month;
        $this->tahun_mulai = $now->year;

        $this->bulan_akhir = $now->month;
        $this->tahun_akhir = $now->year;

        $this->loadData();
    }

    // ======================================================================
    //                             LOAD DATA PERIODE
    // ======================================================================

    public function loadData()
    {
        $start = now()->setDate($this->tahun_mulai, $this->bulan_mulai, 1)->startOfMonth();
        $end = now()->setDate($this->tahun_akhir, $this->bulan_akhir, 1)->endOfMonth();

        $period = CarbonPeriod::create($start, '1 month', $end);

        if (iterator_count($period) > 12) {
            $this->addError('periode', 'Maksimal range 12 bulan!');
            return;
        }

        $this->periodeData = [];

        foreach ($period as $bulan) {

            $this->periodeData[] = [
                'label' => $bulan->translatedFormat('F Y'), // Januari 2025
                'aktiva' => $this->hitungKelompok(
                    $bulan->copy()->startOfMonth(),
                    $bulan->copy()->endOfMonth(),
                    'aktiva'
                ),
                'pasiva' => $this->hitungKelompok(
                    $bulan->copy()->startOfMonth(),
                    $bulan->copy()->endOfMonth(),
                    'pasiva'
                ),
            ];
        }
    }

    // ======================================================================
    //                           HITUNG PER GRUP AKUN
    // ======================================================================

    private function hitungKelompok($start, $end, string $mode): array
    {
        $rows = JurnalUmum::query()
            ->with(['subAkun.anakAkun.parentAkun'])
            ->whereBetween('tgl', [$start, $end])
            ->get();

        // TEMPLATE AKUN
        $template = $mode === 'aktiva'
            ? $this->generateTemplate(1100, 1900)
            : $this->generateTemplate(2100, 3900, skip: [3000]);

        foreach ($rows as $row) {

            $anak = $row->subAkun->anakAkun ?? null;
            if (!$anak) {
                continue;
            }

            // === NAIK KE PARENT PALING ATAS
            while ($anak->parent) {
                $anak = $anak->parentAkun;
            }

            $kode = (int) $anak->kode_anak_akun;
            $groupKode = floor($kode / 100) * 100;

            if (!isset($template[$groupKode])) {
                continue;
            }

            $template[$groupKode]['nama'] = $anak->nama_anak_akun;
            $template[$groupKode]['debit'] += $row->debit;
            $template[$groupKode]['kredit'] += $row->kredit;
        }

        // === HITUNG SALDO
        foreach ($template as &$row) {
            $row['saldo'] = $mode === 'aktiva'
                ? ($row['debit'] - $row['kredit'])
                : ($row['kredit'] - $row['debit']);
        }

        return $template;
    }

    // ======================================================================
    //                          TEMPLATE AKUN DEFAULT
    // ======================================================================

    private function generateTemplate(int $start, int $end, array $skip = []): array
    {
        $data = [];

        for ($i = $start; $i <= $end; $i += 100) {
            if (in_array($i, $skip)) {
                continue;
            }

            $data[$i] = [
                'kode' => $i,
                'nama' => '',
                'debit' => 0,
                'kredit' => 0,
                'saldo' => 0,
            ];
        }

        return $data;
    }
}

//    // ================= STATE =================
//     public array $aktiva = [];
//     public array $pasiva = [];

//     public float $totalAktiva = 0;
//     public float $totalPasiva = 0;

//     public ?string $tanggal_awal = null;
//     public ?string $tanggal_akhir = null;

//     // ================= INIT =================
//     public function mount(): void
//     {
//         $this->tanggal_awal = now()->startOfMonth()->toDateString();
//         $this->tanggal_akhir = now()->endOfMonth()->toDateString();

//         $this->loadNeraca();
//     }
//     private function hitungSaldo(array $data, string $mode): array
//     {
//         foreach ($data as &$row) {
//             $row['saldo'] = $mode === 'aktiva'
//                 ? $row['debit'] - $row['kredit']
//                 : $row['kredit'] - $row['debit'];
//         }

//         return $data;
//     }
//     // ================= LOAD DATA =================
// private function loadNeraca(): void
// {
//     $rows = JurnalUmum::query()
//         ->with(['subAkun.anakAkun.parentAkun'])
//         ->whereBetween('tgl', [$this->tanggal_awal, $this->tanggal_akhir])
//         ->get();

//     // ==== TEMPLATE DEFAULT ====
//     $aktiva = $this->generateTemplate(1100, 1900);
//     $pasiva = $this->generateTemplate(2100, 3900, skip: [3000]);

//     // ==== PROSES DATA ====
//     foreach ($rows as $row) {

//         // ambil anak akun
//         $anak = $row->subAkun->anakAkun ?? null;
//         if (!$anak)
//             continue;

//         // naik ke parent paling atas
//         while ($anak->parent) {
//             $anak = $anak->parentAkun;
//         }

//         $kode = (int) $anak->kode_anak_akun;

//         if ($kode < 1100 || $kode > 3900)
//             continue;

//         $groupKode = floor($kode / 100) * 100;
//         $nilai = $row->nilai;

//         // === AKTIVA (saldo = debit - kredit)
//         if (isset($aktiva[$groupKode])) {
//             $aktiva[$groupKode]['nama'] = $anak->nama_anak_akun;
//             $aktiva[$groupKode]['debit'] += $row->debit > 0 ? $nilai : 0;
//             $aktiva[$groupKode]['kredit'] += $row->kredit > 0 ? $nilai : 0;
//         }

//         // === PASIVA (saldo = kredit - debit)
//         if (isset($pasiva[$groupKode])) {
//             $pasiva[$groupKode]['nama'] = $anak->nama_anak_akun;
//             $pasiva[$groupKode]['debit'] += $row->debit > 0 ? $nilai : 0;
//             $pasiva[$groupKode]['kredit'] += $row->kredit > 0 ? $nilai : 0;
//         }
//     }

//     // HITUNG SALDO
//     $aktiva = $this->hitungSaldo($aktiva, mode: 'aktiva');
//     $pasiva = $this->hitungSaldo($pasiva, mode: 'pasiva');

//     $this->aktiva = $aktiva;
//     $this->pasiva = $pasiva;

//     $this->totalAktiva = collect($aktiva)->sum('saldo');
//     $this->totalPasiva = collect($pasiva)->sum('saldo');
// }

// private function generateTemplate(int $start, int $end, array $skip = []): array
// {
//     $data = [];

//     for ($i = $start; $i <= $end; $i += 100) {
//         if (in_array($i, $skip))
//             continue;

//         $data[$i] = [
//             'kode' => $i,
//             'nama' => '',
//             'debit' => 0,
//             'kredit' => 0,
//             'saldo' => 0,
//         ];
//     }

//     return $data;
// }
// // ================= HITUNG NILAI =================
// private function hitungNilai($row): float
// {
//     $harga = $row->harga ?? 0;

//     return match ($row->hit_kbk) {
//         'm' => $harga * ($row->m3 ?? 0),
//         'b' => $harga * ($row->banyak ?? 0),
//         default => $harga,
//     };
// }