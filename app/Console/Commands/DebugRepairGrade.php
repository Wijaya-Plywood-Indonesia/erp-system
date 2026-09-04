<?php

namespace App\Console\Commands;

use App\Enums\Mesin;
use App\Models\DetailHasilRepair;
use App\Models\Target;
use App\Models\Ukuran;
use App\Services\Target\Resolvers\UkuranBasedResolver;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DebugRepairGrade extends Command
{
    /**
     * php artisan debug:repair-grade --panjang=244 --lebar=122 --tebal=0.5
     * php artisan debug:repair-grade --panjang=244 --lebar=122 --tebal=0.5 --grade=af
     */
    protected $signature = 'debug:repair-grade
        {--panjang= : Panjang ukuran}
        {--lebar= : Lebar ukuran}
        {--tebal= : Tebal ukuran}
        {--grade= : Kalau diisi, langsung simulasikan resolver dengan grade ini}
        {--id_jenis_kayu= : Id jenis kayu untuk simulasi resolver (opsional)}
        {--jumlah_pekerja=2 : Jumlah pekerja aktual di baris ini, untuk simulasi scaling target}';

    protected $description = 'Debug matching grade/kw untuk target Repair';

    public function handle(): int
    {
        $panjang = $this->option('panjang');
        $lebar = $this->option('lebar');
        $tebal = $this->option('tebal');

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

        // 1. Tampilkan semua row target untuk ukuran ini
        $this->info('=== TARGETS untuk ukuran ini (Mesin::Repair) ===');
        $targets = Target::where('id_mesin', Mesin::Repair->value)
            ->where('id_ukuran', $ukuran->id)
            ->get(['id', 'id_jenis_kayu', 'grade', 'kode_ukuran', 'target', 'orang', 'jam']);

        if ($targets->isEmpty()) {
            $this->warn('Tidak ada target sama sekali untuk ukuran ini.');
        }

        $rows = $targets->map(fn ($t) => [
            'id' => $t->id,
            'id_jenis_kayu' => $t->id_jenis_kayu,
            'grade' => $t->grade,
            'grade_len' => strlen((string) $t->grade),
            'grade_hex' => bin2hex((string) $t->grade),
            'kode_ukuran' => $t->kode_ukuran,
            'target' => $t->target,
        ])->toArray();

        $this->table(
            ['id', 'id_jenis_kayu', 'grade', 'len', 'hex', 'kode_ukuran', 'target'],
            $rows
        );

        $this->newLine();

        // 2. Tampilkan semua kw yang tersimpan di detail hasil repair untuk ukuran ini
        $this->info('=== KW di detail_hasil_repairs untuk ukuran ini (10 terbaru) ===');

        // Deteksi otomatis nama kolom FK ke produksi (bisa beda-beda nama)
        $allColumns = Schema::getColumnListing('detail_hasil_repairs');
        $this->comment('Kolom tersedia di detail_hasil_repairs: '.implode(', ', $allColumns));

        $fkProduksiCandidates = ['id_produksi', 'produksi_id', 'id_hasil_produksi', 'hasil_produksi_id'];
        $fkProduksi = collect($fkProduksiCandidates)->first(fn ($c) => in_array($c, $allColumns));

        $selectCols = array_values(array_filter(['id', $fkProduksi, 'kw', 'id_ukuran'], fn ($c) => $c && in_array($c, $allColumns)));

        $details = DetailHasilRepair::where('id_ukuran', $ukuran->id)
            ->latest('id')
            ->limit(15)
            ->get($selectCols);

        if ($details->isEmpty()) {
            $this->warn('Tidak ada detail hasil repair untuk ukuran ini.');
        }

        $detailRows = $details->map(fn ($d) => array_merge(
            $fkProduksi ? ['id' => $d->id, $fkProduksi => $d->{$fkProduksi}] : ['id' => $d->id],
            [
                'kw' => $d->kw,
                'kw_len' => strlen((string) $d->kw),
                'kw_hex' => bin2hex((string) $d->kw),
            ]
        ))->toArray();

        $headers = array_keys($detailRows[0] ?? ['id' => null, 'kw' => null, 'kw_len' => null, 'kw_hex' => null]);
        $this->table($headers, $detailRows);

        // 2b. Distinct semua nilai kw yang pernah dipakai untuk ukuran ini (biar kelihatan varian casing)
        $this->newLine();
        $this->info('=== Semua nilai kw DISTINCT yang pernah tersimpan untuk ukuran ini ===');
        $distinctKw = DetailHasilRepair::where('id_ukuran', $ukuran->id)
            ->distinct()
            ->pluck('kw');

        $distinctRows = $distinctKw->map(fn ($k) => [
            'kw' => $k,
            'len' => strlen((string) $k),
            'hex' => bin2hex((string) $k),
        ])->toArray();

        $this->table(['kw', 'len', 'hex'], $distinctRows);

        $this->newLine();

        // 3b. Cek collation kolom `grade` — penting untuk tahu apakah MySQL
        // menganggap "AF" == "af" secara otomatis atau tidak.
        $this->newLine();
        $this->info('=== Collation kolom `grade` di tabel targets ===');
        $collationInfo = DB::select("SHOW FULL COLUMNS FROM targets WHERE Field = 'grade'");
        $this->table(['Field', 'Type', 'Collation'], collect($collationInfo)->map(fn ($c) => [
            $c->Field, $c->Type, $c->Collation,
        ])->toArray());

        // 4. Kalau --grade diisi, simulasikan resolver langsung
        $gradeOpt = $this->option('grade');
        $idJenisKayuOpt = $this->option('id_jenis_kayu');

        if ($gradeOpt !== null) {
            $this->newLine();
            $this->info("=== Simulasi resolver dengan grade='{$gradeOpt}'".($idJenisKayuOpt ? ", id_jenis_kayu={$idJenisKayuOpt}" : ' (tanpa id_jenis_kayu)').' ===');

            $resolver = new UkuranBasedResolver;

            $result = $resolver->resolve(
                Mesin::Repair->value,
                $ukuran->id,
                $idJenisKayuOpt ? (int) $idJenisKayuOpt : null,
                $gradeOpt
            );

            if ($result) {
                $this->info("MATCH -> target id={$result->id}, grade='{$result->grade}', id_jenis_kayu={$result->id_jenis_kayu}, target={$result->target}");
            } else {
                $this->error('TIDAK MATCH (null).');
            }

            // Juga tampilkan raw query SQL yang dijalankan supaya kelihatan persis
            $this->newLine();
            $this->comment('Raw query yang dijalankan (untuk debug manual):');
            $query = Target::query()
                ->where('id_mesin', Mesin::Repair->value)
                ->where('id_ukuran', $ukuran->id)
                ->when($idJenisKayuOpt, fn ($q) => $q->where('id_jenis_kayu', (int) $idJenisKayuOpt))
                ->when($gradeOpt, fn ($q) => $q->where('grade', $gradeOpt))
                ->orderByDesc('id');
            $this->line($query->toSql());
            $this->line('Bindings: '.json_encode($query->getBindings()));

        } else {
            $this->comment('Tip: jalankan lagi dengan --grade=AF --id_jenis_kayu=1 untuk simulasi resolver persis seperti kasus nyata.');
        }

        // 5. Simulasi PENUH logic RepairDataMap (bukan cuma resolver mentah)
        // untuk pastikan angka 260 itu asalnya dari scaling orang (2x130),
        // bukan dari salah ambil row grade lain.
        if ($gradeOpt !== null && $idJenisKayuOpt) {
            $this->newLine();
            $this->info('=== Simulasi penuh RepairDataMap logic (target per-orang x jumlah pekerja aktual) ===');

            $jumlahPekerjaAktual = (int) ($this->option('jumlah_pekerja') ?? 2);

            $resolver = new UkuranBasedResolver;
            $target = $resolver->resolve(Mesin::Repair->value, $ukuran->id, (int) $idJenisKayuOpt, $gradeOpt);

            if (! $target) {
                $this->error('Target tidak ketemu, tidak bisa simulasikan.');

                return self::SUCCESS;
            }

            $targetBaris = (float) $target->target;
            $orangNormal = (int) $target->orang;
            $jamNormal = (float) $target->jam;

            $targetPerOrang = $orangNormal > 0 ? $targetBaris / $orangNormal : $targetBaris;

            // asumsikan rasio jam = 1.0 (kerja normal, tidak pulang cepat)
            $rasioJam = 1.0;
            $targetPerOrangJamAdjusted = $targetPerOrang * $rasioJam;

            $targetEfektifBaris = ($orangNormal > 0 && $jumlahPekerjaAktual > 0)
                ? $targetPerOrangJamAdjusted * $jumlahPekerjaAktual
                : $targetBaris;

            $this->table(
                ['Field', 'Value'],
                [
                    ['Target row id yang dipakai', $target->id],
                    ['Target row grade', $target->grade],
                    ['target (raw dari DB)', $targetBaris],
                    ['orang (raw dari DB, "normal")', $orangNormal],
                    ['jam (raw dari DB)', $jamNormal],
                    ['target Per Orang (raw / orangNormal)', $targetPerOrang],
                    ['rasio Jam (diasumsikan normal)', $rasioJam],
                    ['jumlah Pekerja AKTUAL di baris ini (--jumlah_pekerja)', $jumlahPekerjaAktual],
                    ['=> targetEfektifBaris (yang tampil di laporan)', $targetEfektifBaris],
                ]
            );

            $this->newLine();
            if (abs($targetEfektifBaris - 260) < 0.01) {
                $this->info('COCOK dengan angka 260 di laporan. Konfirmasi: ini murni scaling (targetPerOrang x jumlahPekerjaAktual), BUKAN salah ambil row grade lain.');
            } else {
                $this->warn("Tidak cocok dengan 260 (hasil simulasi: {$targetEfektifBaris}). Coba ubah --jumlah_pekerja sesuai jumlah pekerja aktual di baris tsb.");
            }
        }

        return self::SUCCESS;
    }
}
