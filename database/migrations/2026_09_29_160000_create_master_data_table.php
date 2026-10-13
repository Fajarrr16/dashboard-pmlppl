<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('master_data', function (Blueprint $table) {
            $table->id();
            $table->string('kecamatan')->index();
            $table->string('kelurahan')->index();
            $table->string('sls')->index();
            $table->string('nama_responden');
            $table->string('nama_ppl');
            $table->string('nama_pml');
            $table->string('status_pemindahan')->default('belum_dipindah');
            $table->text('keterangan')->nullable();
            $table->timestamps();

            $table->index(['kecamatan', 'kelurahan', 'sls']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('master_data');
    }
};
