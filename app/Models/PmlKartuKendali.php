<?php

namespace App\Models;

use Database\Factories\PmlKartuKendaliFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PmlKartuKendali extends Model
{
    /** @use HasFactory<PmlKartuKendaliFactory> */
    use HasFactory;

    public const KECAMATAN = KartuKendali::KECAMATAN;

    protected $fillable = [
        'timestamp_google',
        'email_address',
        'tanggal_pendataan',
        'nama_pml',
        'kecamatan',
        'kelurahan',
        'sls',
        'jenis_responden',
        'dokumentasi',
        'timestamp_masuk',
        'source_sheet',
        'external_id',
    ];

    protected $casts = [
        'tanggal_pendataan' => 'date',
        'timestamp_masuk' => 'datetime',
    ];
}
