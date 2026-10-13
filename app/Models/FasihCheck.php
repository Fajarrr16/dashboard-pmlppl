<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FasihCheck extends Model
{
    use HasFactory;

    public const STATUS_SESUAI = 'sesuai';

    public const STATUS_TIDAK_SESUAI = 'tidak_sesuai';

    public const STATUS_TIDAK_DITEMUKAN = 'tidak_ditemukan';

    public const STATUS_BELUM_DICEK = 'belum_dicek';

    protected $table = 'fasih_checks';

    protected $fillable = [
        'kartu_kendali_id',
        'status',
        'field_tidak_sesuai',
        'keterangan',
        'petugas',
        'checked_at',
    ];

    protected $casts = [
        'field_tidak_sesuai' => 'array',
        'checked_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function kartuKendali(): BelongsTo
    {
        return $this->belongsTo(KartuKendali::class);
    }
}
