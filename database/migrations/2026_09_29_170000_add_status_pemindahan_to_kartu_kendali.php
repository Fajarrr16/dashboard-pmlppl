<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kartu_kendali', function (Blueprint $table) {
            $table->string('status_pemindahan')->default('belum_dipindah')->after('keterangan')->index();
        });
    }

    public function down(): void
    {
        Schema::table('kartu_kendali', function (Blueprint $table) {
            $table->dropColumn('status_pemindahan');
        });
    }
};
