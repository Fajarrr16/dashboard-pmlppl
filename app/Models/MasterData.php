<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class MasterData extends Model
{
    use HasFactory;

    public const STATUS_PEMINDAHAN = [
        'sudah_dipindah',
        'belum_dipindah',
        'sudah_sesuai_sls',
    ];

    protected $table = 'master_data';

    protected $fillable = [
        'kecamatan',
        'kelurahan',
        'sls',
        'nama_responden',
        'nama_ppl',
        'nama_pml',
        'status_pemindahan',
        'keterangan',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function fasihCheck(): HasOne
    {
        return $hasOne(FasihCheck::class);
    }
}
