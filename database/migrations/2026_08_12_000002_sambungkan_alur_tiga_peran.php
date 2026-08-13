<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('surat', function (Blueprint $table) {
            $table->foreignId('kategori_id')->nullable()->after('id')->constrained('kategori_dokumen')->nullOnDelete();
            $table->foreignId('ditugaskan_ke')->nullable()->after('dibuat_oleh')->constrained('users')->nullOnDelete();
        });

        Schema::table('verifikasi_ttd', function (Blueprint $table) {
            $table->foreignId('surat_id')->nullable()->unique()->after('id')->constrained('surat')->cascadeOnDelete();
        });

        DB::table('surat')->where('status', 'Diproses')->update(['status' => 'Diterima']);
    }

    public function down(): void
    {
        Schema::table('verifikasi_ttd', function (Blueprint $table) {
            $table->dropConstrainedForeignId('surat_id');
        });

        Schema::table('surat', function (Blueprint $table) {
            $table->dropConstrainedForeignId('ditugaskan_ke');
            $table->dropConstrainedForeignId('kategori_id');
        });
    }
};
