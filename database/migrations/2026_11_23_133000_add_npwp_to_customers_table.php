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
        if (!Schema::hasColumn('customers', 'npwp')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->string('npwp', 100)->nullable()->after('alamat');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('customers', 'npwp')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->dropColumn('npwp');
            });
        }
    }
};
