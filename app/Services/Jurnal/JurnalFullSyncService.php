<?php

namespace App\Services\Jurnal;

use Illuminate\Support\Facades\DB;

class JurnalFullSyncService
{
    public function syncAll(): void
    {
        DB::transaction(function () {

            // 1️⃣ Jurnal Umum ➜ Jurnal 1
            app(JurnalUmumToJurnal1Service::class)->sync();

            // 2️⃣ Jurnal 1 ➜ Jurnal 2
            app(Jurnal1ToJurnal2Service::class)->sync();

            // 3️⃣ Jurnal 2 ➜ Jurnal 3
            app(Jurnal2ToJurnal3Service::class)->sync();

            // 4️⃣ Jurnal 3 ➜ Neraca
            app(Jurnal3ToNeracaService::class)->sync();

        });
    }
}
