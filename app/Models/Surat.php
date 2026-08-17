<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Surat extends Model
{
    protected $table = 'surat';

    protected $fillable = [
        'kategori_id', 'surat_masuk_id', 'jenis', 'nomor_agenda', 'nomor_surat', 'tanggal_surat',
        'pihak', 'perihal', 'status', 'file', 'disposisi', 'dibuat_oleh', 'ditugaskan_ke',
    ];

    protected function casts(): array
    {
        return ['tanggal_surat' => 'date'];
    }

    public function pembuat(): BelongsTo
    {
        return $this->belongsTo(Pengguna::class, 'dibuat_oleh');
    }

    public function kategori(): BelongsTo
    {
        return $this->belongsTo(KategoriDokumen::class, 'kategori_id');
    }

    public function penanggungJawab(): BelongsTo
    {
        return $this->belongsTo(Pengguna::class, 'ditugaskan_ke');
    }

    public function verifikasi(): HasOne
    {
        return $this->hasOne(VerifikasiTtd::class, 'surat_id');
    }

    public function scopeTerlihatOleh(Builder $query, Pengguna $pengguna): Builder
    {
        if ($pengguna->peran !== 'pegawai') {
            return $query;
        }

        return $query->where('ditugaskan_ke', $pengguna->id)
            ->where(function (Builder $bagian) {
                $bagian->where(fn (Builder $masuk) => $masuk->where('jenis', 'masuk')->whereIn('status', ['Didisposisikan', 'Selesai']))
                    ->orWhere(fn (Builder $keluar) => $keluar->where('jenis', 'keluar')->where('status', 'Terkirim'));
            });
    }
}
