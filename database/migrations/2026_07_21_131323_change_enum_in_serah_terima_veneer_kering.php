<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Nama tabel & kolom yang di-rename value enum-nya.
     * Diletakkan sebagai konstanta supaya gampang dibaca ulang.
     */
    private string $table = 'serah_terima_veneer_kering';

    private string $column = 'tipe_sumber';

    private string $old = 'sanding_joint';

    private string $new = 'joint';

    public function up(): void
    {
        // 1) Perbesar dulu enum: tambahkan value baru 'joint' TANPA membuang
        //    'sanding_joint', supaya data existing tidak pernah invalid
        //    di titik manapun selama migrasi berjalan.
        DB::statement("
            ALTER TABLE `{$this->table}`
            MODIFY `{$this->column}` ENUM('dryer', 'kedi', 'gudang', 'gudang_jadi', '{$this->old}', '{$this->new}')
            NULL
        ");

        // 2) Update semua baris lama dari 'sanding_joint' -> 'joint'
        DB::table($this->table)
            ->where($this->column, $this->old)
            ->update([$this->column => $this->new]);

        // 3) Persempit enum lagi: buang 'sanding_joint' karena sudah tidak
        //    dipakai sama sekali setelah step 2 di atas.
        DB::statement("
            ALTER TABLE `{$this->table}`
            MODIFY `{$this->column}` ENUM('dryer', 'kedi', 'gudang', 'gudang_jadi', '{$this->new}')
            NULL
        ");
    }

    public function down(): void
    {
        // Kebalikannya: perbesar lagi, migrasikan data mundur, lalu persempit.
        DB::statement("
            ALTER TABLE `{$this->table}`
            MODIFY `{$this->column}` ENUM('dryer', 'kedi', 'gudang', 'gudang_jadi', '{$this->old}', '{$this->new}')
            NULL
        ");

        DB::table($this->table)
            ->where($this->column, $this->new)
            ->update([$this->column => $this->old]);

        DB::statement("
            ALTER TABLE `{$this->table}`
            MODIFY `{$this->column}` ENUM('dryer', 'kedi', 'gudang', 'gudang_jadi', '{$this->old}')
            NULL
        ");
    }
};
