<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username')->unique()->after('name');
            $table->string('peran')->default('pegawai')->after('password');
            $table->boolean('aktif')->default(true)->after('peran');
        });

        Schema::create('kategori_dokumen', function (Blueprint $table) {
            $table->id();
            $table->string('nama');
            $table->string('kode', 20)->unique();
            $table->text('deskripsi')->nullable();
            $table->boolean('aktif')->default(true);
            $table->timestamps();
        });

        Schema::create('surat', function (Blueprint $table) {
            $table->id();
            $table->string('jenis');
            $table->string('nomor_agenda')->unique();
            $table->string('nomor_surat');
            $table->date('tanggal_surat');
            $table->string('pihak');
            $table->string('perihal');
            $table->string('status');
            $table->string('file')->nullable();
            $table->text('disposisi')->nullable();
            $table->foreignId('dibuat_oleh')->constrained('users');
            $table->timestamps();
        });

        Schema::create('verifikasi_ttd', function (Blueprint $table) {
            $table->id();
            $table->string('nama_file');
            $table->string('file');
            $table->boolean('valid');
            $table->text('keterangan');
            $table->foreignId('diverifikasi_oleh')->constrained('users');
            $table->timestamps();
        });

        Schema::create('cadangan_data', function (Blueprint $table) {
            $table->id();
            $table->string('nama_file');
            $table->unsignedBigInteger('ukuran');
            $table->foreignId('dibuat_oleh')->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cadangan_data');
        Schema::dropIfExists('verifikasi_ttd');
        Schema::dropIfExists('surat');
        Schema::dropIfExists('kategori_dokumen');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['username', 'peran', 'aktif']);
        });
    }
};
