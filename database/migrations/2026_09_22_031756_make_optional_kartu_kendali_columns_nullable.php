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
        Schema::table('kartu_kendali', function (Blueprint $table): void {
            $table->string('kelurahan')->nullable()->change();
            $table->string('sls')->nullable()->change();
            $table->string('jenis_responden')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('kartu_kendali', function (Blueprint $table): void {
            $table->string('kelurahan')->nullable(false)->change();
            $table->string('sls')->nullable(false)->change();
            $table->string('jenis_responden')->nullable(false)->change();
        });
    }
};
