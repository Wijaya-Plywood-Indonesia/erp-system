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
    Schema::table('jurnal_umum', function (Blueprint $table) {
        $table->timestamp('synced_at')->nullable()->after('status');
        $table->unsignedBigInteger('synced_by')->nullable()->after('synced_at');

        $table->foreign('synced_by')
              ->references('id')
              ->on('users')
              ->nullOnDelete();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down()
{
    Schema::table('jurnal_umum', function (Blueprint $table) {
        $table->dropForeign(['synced_by']);
        $table->dropColumn(['synced_at', 'synced_by']);
    });
}
};
