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
        Schema::table('induk_akuns', function (Blueprint $table) {
            if (!Schema::hasColumn('induk_akuns', 'saldo_normal')) {
                $table->string('saldo_normal')->nullable();
            }
            if (!Schema::hasColumn('induk_akuns', 'status')) {
                $table->string('status')->default('aktif');
            }
            if (!Schema::hasColumn('induk_akuns', 'created_by')) {
                $table->foreignId('created_by')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();
            }
        });

        Schema::table('anak_akuns', function (Blueprint $table) {
            if (!Schema::hasColumn('anak_akuns', 'saldo_normal')) {
                $table->string('saldo_normal')->nullable();
            }
        });

        Schema::table('sub_anak_akuns', function (Blueprint $table) {
            if (!Schema::hasColumn('sub_anak_akuns', 'saldo_normal')) {
                $table->string('saldo_normal')->nullable();
            }
            if (!Schema::hasColumn('sub_anak_akuns', 'status')) {
                $table->string('status')->default('aktif');
            }
            if (!Schema::hasColumn('sub_anak_akuns', 'created_by')) {
                $table->foreignId('created_by')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sub_anak_akuns', function (Blueprint $table) {
            if (Schema::hasColumn('sub_anak_akuns', 'created_by')) {
                $table->dropForeign(['created_by']);
                $table->dropColumn('created_by');
            }
            if (Schema::hasColumn('sub_anak_akuns', 'status')) {
                $table->dropColumn('status');
            }
            if (Schema::hasColumn('sub_anak_akuns', 'saldo_normal')) {
                $table->dropColumn('saldo_normal');
            }
        });

        Schema::table('anak_akuns', function (Blueprint $table) {
            if (Schema::hasColumn('anak_akuns', 'saldo_normal')) {
                $table->dropColumn('saldo_normal');
            }
        });

        Schema::table('induk_akuns', function (Blueprint $table) {
            if (Schema::hasColumn('induk_akuns', 'created_by')) {
                $table->dropForeign(['created_by']);
                $table->dropColumn('created_by');
            }
            if (Schema::hasColumn('induk_akuns', 'status')) {
                $table->dropColumn('status');
            }
            if (Schema::hasColumn('induk_akuns', 'saldo_normal')) {
                $table->dropColumn('saldo_normal');
            }
        });
    }
};
