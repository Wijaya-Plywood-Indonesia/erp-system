<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('targets', function (Blueprint $table) {
            $table->id(); // gunakan standar Laravel

            // Foreign keys
            $table->foreignId('id_mesin')
                ->constrained('mesins')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            $table->foreignId('id_ukuran')
                ->constrained('ukurans')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            $table->foreignId('id_jenis_kayu')
                ->constrained('jenis_kayus')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            $table->string('kode_ukuran')->nullable();
            $table->integer('target');
            $table->integer('orang');
            $table->integer('jam');

            // Generated columns
            $table->decimal('targetperjam', 15, 2)->virtualAs('`target` / `jam`');
            $table->decimal('targetperorang', 15, 2)->virtualAs('`target` / `orang`');

            $table->decimal('gaji', 15, 2);
            $table->decimal('potongan', 15, 2)->virtualAs('`gaji` / `targetperorang`');

            $table->string('status')->default('diajukan');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('targets');
    }
};
