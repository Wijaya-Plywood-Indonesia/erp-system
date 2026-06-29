<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
{
    Schema::table('lain_lain', function (Blueprint $table) {
        // Menambahkan relasi ke tabel users
        $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
    });
}

public function down()
{
    Schema::table('lain_lain', function (Blueprint $table) {
        $table->dropForeign(['created_by']);
        $table->dropColumn('created_by');
    });
}
};
