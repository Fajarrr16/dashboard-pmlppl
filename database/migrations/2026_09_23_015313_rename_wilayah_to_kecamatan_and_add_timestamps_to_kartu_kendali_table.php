<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('kartu_kendali', function (Blueprint $table): void {
            $table->renameColumn('wilayah', 'kecamatan');
            $table->dateTime('timestamp_masuk')->nullable()->index();
            $table->dateTime('waktu_pendataan')->nullable()->index();
        });

        DB::table('kartu_kendali')->update([
            'timestamp_masuk' => DB::raw('created_at'),
            'waktu_pendataan' => DB::raw('tanggal_pendataan'),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('kartu_kendali', function (Blueprint $table): void {
            $table->dropColumn(['timestamp_masuk', 'waktu_pendataan']);
            $table->renameColumn('kecamatan', 'wilayah');
        });
    }
};
