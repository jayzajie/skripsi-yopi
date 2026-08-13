<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class Pengguna extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $table = 'users';

    protected $fillable = ['name', 'username', 'email', 'password', 'peran', 'aktif'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return ['email_verified_at' => 'datetime', 'password' => 'hashed', 'aktif' => 'boolean'];
    }

    public function suratDibuat(): HasMany
    {
        return $this->hasMany(Surat::class, 'dibuat_oleh');
    }

    public function tugasSurat(): HasMany
    {
        return $this->hasMany(Surat::class, 'ditugaskan_ke');
    }
}
