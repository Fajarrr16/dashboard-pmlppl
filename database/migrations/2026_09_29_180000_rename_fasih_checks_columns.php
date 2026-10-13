<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('fasih_checks', 'master_data_id') && ! Schema::hasColumn('fasih_checks', 'kartu_kendali_id')) {
            Schema::table('fasih_checks', function (Blueprint $table) {
                $table->renameColumn('master_data_id', 'kartu_kendali_id');
            });
        }

        if (Schema::hasColumn('fasih_check_logs', 'master_data_id') && ! Schema::hasColumn('fasih_check_logs', 'kartu_kendali_id')) {
            Schema::table('fasih_check_logs', function (Blueprint $table) {
                $table->renameColumn('master_data_id', 'kartu_kendali_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('fasih_checks', 'kartu_kendali_id') && ! Schema::hasColumn('fasih_checks', 'master_data_id')) {
            Schema::table('fasih_checks', function (Blueprint $table) {
                $table->renameColumn('kartu_kendali_id', 'master_data_id');
            });
        }

        if (Schema::hasColumn('fasih_check_logs', 'kartu_kendali_id') && ! Schema::hasColumn('fasih_check_logs', 'master_data_id')) {
            Schema::table('fasih_check_logs', function (Blueprint $table) {
                $table->renameColumn('kartu_kendali_id', 'master_data_id');
            });
        }
    }
};
