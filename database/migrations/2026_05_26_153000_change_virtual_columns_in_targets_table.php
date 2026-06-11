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
        Schema::table('targets', function (Blueprint $table) {
            $table->dropColumn(['targetperjam', 'targetperorang', 'potongan']);
        });

        Schema::table('targets', function (Blueprint $table) {
            $table->decimal('targetperjam', 15, 4)->virtualAs('`target` / `jam`');
            $table->decimal('targetperorang', 15, 4)->virtualAs('`target` / `orang`');
            $table->decimal('potongan', 15, 4)->virtualAs('`gaji` / `targetperorang`');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('targets', function (Blueprint $table) {
            $table->dropColumn(['targetperjam', 'targetperorang', 'potongan']);
        });

        Schema::table('targets', function (Blueprint $table) {
            $table->decimal('targetperjam', 15, 2)->virtualAs('`target` / `jam`');
            $table->decimal('targetperorang', 15, 2)->virtualAs('`target` / `orang`');
            $table->decimal('potongan', 15, 2)->virtualAs('`gaji` / `targetperorang`');
        });
    }
};
