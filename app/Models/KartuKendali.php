<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KartuKendali extends Model
{
    use HasFactory;

    /**
     * Wilayah yang didukung oleh sumber data kartu kendali.
     *
     * @var array<int, string>
     */
    public const WILAYAH = [
        'SETU',
        'SERPONG',
        'PAMULANG',
        'CIPUTAT',
        'CIPUTAT TIMUR',
        'PONDOK AREN',
        'SERPONG UTARA',
    ];

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'kartu_kendali';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'wilayah',
        'timestamp_google',
        'tanggal_pendataan',
        'nama_ppl',
        'nama_pml',
        'kelurahan',
        'sls',
        'nama_responden',
        'jenis_responden',
        'dokumentasi',
        'keterangan',
        'source_sheet',
        'external_id',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'tanggal_pendataan' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
