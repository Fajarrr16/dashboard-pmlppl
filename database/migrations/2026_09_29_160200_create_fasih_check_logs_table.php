<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fasih_check_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kartu_kendali_id')->constrained('kartu_kendali')->cascadeOnDelete();
            $table->string('status_sebelumnya')->nullable();
            $table->string('status_baru');
            $table->string('petugas')->nullable();
            $table->text('keterangan')->nullable();
            $table->timestamp('changed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fasih_check_logs');
    }
};
