<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('jurnal2', function (Blueprint $table) {
            $table->id();
            $table->integer('modif100');
            $table->integer('no_akun');
            $table->string('nama_akun');
            $table->integer('banyak')->nullable();
            $table->decimal('kubikasi')->nullable();
            $table->integer('harga')->nullable();
            $table->integer('total')->nullable();
            $table->string('user_id')->nullable();
            $table->string('status_sinkron')->default('belum sinkron');
            $table->timestamp('synced_at')->nullable()->after('status');
            $table->unsignedBigInteger('synced_by')->nullable()->after('synced_at');
            $table->foreign('synced_by')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('jurnal2');
    }
};
