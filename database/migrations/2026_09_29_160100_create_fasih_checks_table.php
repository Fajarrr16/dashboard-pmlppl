<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fasih_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kartu_kendali_id')->constrained('kartu_kendali')->cascadeOnDelete();
            $table->string('status')->default('belum_dicek');
            $table->json('field_tidak_sesuai')->nullable();
            $table->text('keterangan')->nullable();
            $table->string('petugas')->nullable();
            $table->timestamp('checked_at')->nullable();
            $table->timestamps();

            $table->unique('kartu_kendali_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fasih_checks');
    }
};
