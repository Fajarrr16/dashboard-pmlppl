<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KartuKendali extends Model
{
    use HasFactory;

    /**
     * Kecamatan yang didukung oleh sumber data kartu kendali.
     *
     * @var array<int, string>
     */
    public const KECAMATAN = [
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

    public const STATUS_PEMINDAHAN = [
        'sudah_dipindah',
        'belum_dipindah',
        'sudah_sesuai_sls',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'kecamatan',
        'timestamp_google',
        'timestamp_masuk',
        'tanggal_pendataan',
        'waktu_pendataan',
        'nama_ppl',
        'nama_pml',
        'kelurahan',
        'sls',
        'nama_responden',
        'jenis_responden',
        'dokumentasi',
        'keterangan',
        'status_pemindahan',
        'source_sheet',
        'external_id',
    ];

    /**
     * The default attribute values for the model.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status_pemindahan' => 'belum_dipindah',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'timestamp_masuk' => 'datetime',
        'tanggal_pendataan' => 'date',
        'waktu_pendataan' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::saving(function (KartuKendali $model): void {
            if (! in_array($model->status_pemindahan, self::STATUS_PEMINDAHAN, true)) {
                throw new \InvalidArgumentException(
                    'Status pemindahan tidak valid: '.$model->status_pemindahan
                );
            }
        });
    }
}
