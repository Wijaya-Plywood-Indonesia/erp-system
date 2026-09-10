<?php

namespace App\Console\Commands;

use App\Actions\HitungPotonganProduksiAction;
use App\DataTransferObjects\PekerjaKerjaInput;
use App\Enums\Mesin;
use App\Models\Target;
use App\Models\Ukuran;
use Illuminate\Console\Command;

class DebugRotaryTarget extends Command
{
    /**
     * php artisan debug:rotary-target --id_mesin=1 --panjang=244 --lebar=122 --tebal=0.5
     * php artisan debug:rotary-target --id_mesin=1 --panjang=244 --lebar=122 --tebal=0.5 --jumlah_pekerja=3 --hasil_aktual=1500
     */
    protected $signature = 'debug:rotary-target
        {--id_mesin= : id_mesin dari tabel mesin (mis. 1 = SPINDLESS)}
        {--panjang= : Panjang ukuran}
        {--lebar= : Lebar ukuran}
        {--tebal= : Tebal ukuran}
        {--jumlah_pekerja=2 : Jumlah pekerja aktual, untuk simulasi HitungPotonganProduksiAction}
        {--hasil_aktual= : Hasil aktual (total_lembar), untuk simulasi potongan penuh. Kalau kosong, hanya cek resolusi target}';

    protected $description = 'Debug pencarian Target & simulasi potongan untuk Rotary (mengikuti logic ProduksiDataMap)';

    public function handle(): int
    {
        $idMesin = $this->option('id_mesin');
        $panjang = $this->option('panjang');
        $lebar = $this->option('lebar');
        $tebal = $this->option('tebal');

        if (! $idMesin) {
            $this->error('Wajib isi --id_mesin (lihat tabel `mesin` untuk id-nya).');

            return self::FAILURE;
        }

        if (! $panjang || ! $lebar || ! $tebal) {
            $this->error('Wajib isi --panjang --lebar --tebal');

            return self::FAILURE;
        }

        $ukuran = Ukuran::where('panjang', $panjang)
            ->where('lebar', $lebar)
            ->where('tebal', $tebal)
            ->first();

        if (! $ukuran) {
            $this->error("Ukuran {$panjang}x{$lebar}x{$tebal} tidak ditemukan.");

            return self::FAILURE;
        }

        $this->info("Ukuran ditemukan: id={$ukuran->id}");
        $this->newLine();

        // ---------------------------------------------------------
        // 1. Tampilkan semua row target untuk mesin ini (semua ukuran)
        //    supaya kelihatan konteks lengkap, bukan cuma yang match.
        // ---------------------------------------------------------
        $this->info("=== TARGETS untuk id_mesin={$idMesin} (semua ukuran) ===");
        $allTargetsForMesin = Target::where('id_mesin', $idMesin)
            ->get(['id', 'id_ukuran', 'kode_ukuran', 'target', 'orang', 'jam', 'potongan']);

        if ($allTargetsForMesin->isEmpty()) {
            $this->warn('Tidak ada target sama sekali untuk id_mesin ini.');
        } else {
            $this->table(
                ['id', 'id_ukuran', 'kode_ukuran', 'target', 'orang', 'jam', 'potongan'],
                $allTargetsForMesin->map(fn ($t) => [
                    $t->id, $t->id_ukuran ?? 'NULL', $t->kode_ukuran, $t->target, $t->orang, $t->jam, $t->potongan,
                ])->toArray()
            );
        }

        $this->newLine();

        // ---------------------------------------------------------
        // 2. Resolusi target PERSIS seperti ProduksiDataMap::make()
        //    - Cari id_mesin + id_ukuran spesifik dulu
        //    - Kalau tidak ada, fallback ke id_mesin + id_ukuran NULL
        // ---------------------------------------------------------
        $this->info('=== Resolusi target (logic ProduksiDataMap) ===');

        $targetModel = Target::where('id_mesin', $idMesin)
            ->where('id_ukuran', $ukuran->id)
            ->first();

        $fallbackUsed = false;
        if (! $targetModel) {
            $targetModel = Target::where('id_mesin', $idMesin)
                ->whereNull('id_ukuran')
                ->first();
            $fallbackUsed = true;
        }

        if (! $targetModel) {
            $this->error('TIDAK MATCH sama sekali (baik id_ukuran spesifik maupun fallback NULL). targetDisesuaikan akan = 0, potongan tidak dihitung.');

            return self::SUCCESS;
        }

        $this->info(($fallbackUsed ? 'MATCH via FALLBACK (id_ukuran NULL)' : 'MATCH langsung via id_ukuran spesifik')
            ." -> target id={$targetModel->id}, kode_ukuran='{$targetModel->kode_ukuran}', target={$targetModel->target}, orang={$targetModel->orang}, jam={$targetModel->jam}, potongan={$targetModel->potongan}");

        $this->newLine();
        $this->comment('Raw query yang dijalankan (untuk debug manual):');
        $query = Target::query()
            ->where('id_mesin', $idMesin)
            ->where('id_ukuran', $ukuran->id);
        $this->line($query->toSql());
        $this->line('Bindings: '.json_encode($query->getBindings()));

        // ---------------------------------------------------------
        // 3. Kalau --hasil_aktual diisi, simulasikan PENUH via
        //    HitungPotonganProduksiAction (bukan scaling manual),
        //    karena rotary memang menghitung lewat action ini.
        // ---------------------------------------------------------
        $hasilAktualOpt = $this->option('hasil_aktual');

        if ($hasilAktualOpt !== null) {
            $this->newLine();
            $this->info('=== Simulasi penuh HitungPotonganProduksiAction (logic ProduksiDataMap) ===');

            $mesinEnum = Mesin::tryFrom((int) $idMesin);
            if (! $mesinEnum) {
                $this->error("id_mesin={$idMesin} tidak punya case yang cocok di enum Mesin. Simulasi potongan tidak bisa dijalankan (di ProduksiDataMap ini akan menghasilkan potongan=0 juga).");

                return self::SUCCESS;
            }

            $jumlahPekerjaAktual = (int) ($this->option('jumlah_pekerja') ?? 2);
            $jamKerjaMenit = ((float) $targetModel->jam) * 60;

            $pekerjaInput = [];
            for ($i = 1; $i <= $jumlahPekerjaAktual; $i++) {
                $pekerjaInput[] = new PekerjaKerjaInput(
                    idPegawai: "SIMULASI-{$i}",
                    menitKerja: $jamKerjaMenit,
                    hasilIndividu: 0,
                );
            }

            $action = new HitungPotonganProduksiAction;
            $hitung = $action->execute(
                mesin: $mesinEnum,
                strategi: $mesinEnum->strategiPembagian(),
                pekerja: $pekerjaInput,
                hasilAktual: (float) $hasilAktualOpt,
                idUkuran: $ukuran->id,
                idJenisKayu: null,
                grade: null,
                customTarget: $targetModel,
            );

            if (! $hitung) {
                $this->error('HitungPotonganProduksiAction gagal resolve (return null) — cek strategiPembagian() untuk mesin ini.');

                return self::SUCCESS;
            }

            $this->table(
                ['Field', 'Value'],
                [
                    ['Mesin enum', $mesinEnum->name],
                    ['Strategi pembagian', $mesinEnum->strategiPembagian()->name ?? (string) $mesinEnum->strategiPembagian()],
                    ['target (raw dari DB)', $targetModel->target],
                    ['jam (raw dari DB)', $targetModel->jam],
                    ['jumlah pekerja AKTUAL (--jumlah_pekerja)', $jumlahPekerjaAktual],
                    ['hasil aktual (--hasil_aktual)', $hasilAktualOpt],
                    ['=> targetAdjusted', round($hitung->targetAdjusted)],
                    ['=> selisih (hasil - targetAdjusted)', $hasilAktualOpt - $hitung->targetAdjusted],
                    ['=> potongan TOTAL', $hitung->potongan],
                    ['=> potongan per orang (total / jumlah pekerja)', $jumlahPekerjaAktual > 0 ? round($hitung->potongan / $jumlahPekerjaAktual) : 0],
                ]
            );

            $this->newLine();
            $this->comment('Detail potonganPerPegawai (raw dari action):');
            $this->table(
                ['id_pegawai (simulasi)', 'potongan'],
                collect($hitung->potonganPerPegawai)->map(fn ($v, $k) => [$k, $v])->values()->toArray()
            );
        } else {
            $this->comment('Tip: jalankan lagi dengan --hasil_aktual=1500 untuk simulasi potongan penuh seperti kasus nyata.');
        }

        return self::SUCCESS;
    }
}
