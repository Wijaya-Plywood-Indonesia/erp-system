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
        Schema::create('scheduled_notifications', function (Blueprint $table) {
            $table->id();
            $table->string('key'); // contoh: kontrak_habis_30_hari
            $table->datetime('scheduled_at'); // jam berapa scheduler seharusnya jalan
            $table->datetime('last_run_at')->nullable(); // kapan terakhir berhasil jalan
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scheduled_notifications');
        Schema::dropIfExists('scheduled_notifications');
    }
};
