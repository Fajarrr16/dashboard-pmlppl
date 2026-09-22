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
        Schema::create('kartu_kendali', function (Blueprint $table) {
            $table->id();
            $table->string('wilayah')->index();
            $table->string('timestamp_google')->nullable();
            $table->date('tanggal_pendataan')->nullable()->index();
            $table->string('nama_ppl')->index();
            $table->string('nama_pml')->index();
            $table->string('kelurahan')->index();
            $table->string('sls')->index();
            $table->string('nama_responden');
            $table->string('jenis_responden')->index();
            $table->text('dokumentasi')->nullable();
            $table->text('keterangan')->nullable();
            $table->string('source_sheet')->nullable();
            $table->string('external_id')->nullable();
            $table->timestamps();

            $table->unique(['source_sheet', 'external_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kartu_kendali');
    }
};
