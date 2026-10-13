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
        Schema::create('pml_kartu_kendalis', function (Blueprint $table) {
            $table->id();
            $table->string('timestamp_google')->nullable();
            $table->string('email_address')->nullable();
            $table->date('tanggal_pendataan')->index();
            $table->string('nama_pml')->index();
            $table->string('kecamatan')->index();
            $table->string('kelurahan')->index();
            $table->string('sls')->nullable()->index();
            $table->string('jenis_responden')->index();
            $table->text('dokumentasi');
            $table->dateTime('timestamp_masuk')->nullable()->index();
            $table->string('source_sheet');
            $table->string('external_id');
            $table->timestamps();

            $table->unique(['source_sheet', 'external_id']);
            $table->index(['kecamatan', 'tanggal_pendataan']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pml_kartu_kendalis');
    }
};
