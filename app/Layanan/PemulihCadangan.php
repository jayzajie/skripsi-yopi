<?php

namespace App\Layanan;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;
use ZipArchive;

class PemulihCadangan
{
    private const TABEL = ['users', 'kategori_dokumen', 'surat', 'verifikasi_ttd', 'cadangan_data'];

    private const KOLOM_WAJIB = [
        'users' => ['id', 'name', 'username', 'email', 'password', 'peran', 'aktif'],
        'kategori_dokumen' => ['id', 'nama', 'kode', 'aktif'],
        'surat' => ['id', 'jenis', 'nomor_agenda', 'nomor_surat', 'tanggal_surat', 'pihak', 'perihal', 'status', 'dibuat_oleh', 'kategori_id', 'ditugaskan_ke'],
        'verifikasi_ttd' => ['id', 'surat_id', 'nama_file', 'file', 'valid', 'keterangan', 'diverifikasi_oleh'],
        'cadangan_data' => ['id', 'nama_file', 'ukuran', 'dibuat_oleh'],
    ];

    public function pulihkan(UploadedFile $cadangan): void
    {
        $zip = new ZipArchive;
        if ($zip->open($cadangan->getRealPath()) !== true) {
            throw ValidationException::withMessages(['cadangan' => 'File backup tidak valid.']);
        }

        $this->validasiDaftarFile($zip);
        $json = $zip->getFromName('database/data.json');
        $data = is_string($json) ? json_decode($json, true) : null;
        if (! $this->dataValid($data)) {
            $this->tolak($zip, 'Data pada backup tidak lengkap atau rusak.');
        }

        $id = Str::uuid()->toString();
        $panggung = Storage::path("pemulihan-{$id}");
        File::ensureDirectoryExists("{$panggung}/surat");
        File::ensureDirectoryExists("{$panggung}/verifikasi");
        for ($index = 0; $index < $zip->numFiles; $index++) {
            $nama = $zip->statIndex($index)['name'];
            if (! str_starts_with($nama, 'arsip/') || str_ends_with($nama, '/')) {
                continue;
            }
            $isi = $zip->getFromIndex($index);
            if (! is_string($isi)) {
                $this->tolak($zip, 'File arsip pada backup rusak.', $panggung);
            }
            $tujuan = "{$panggung}/".substr($nama, 6);
            File::ensureDirectoryExists(dirname($tujuan));
            File::put($tujuan, $isi);
        }
        $zip->close();

        $this->pulihkanDataDanFile($data['tabel'], $panggung, $id);
    }

    private function validasiDaftarFile(ZipArchive $zip): void
    {
        $ukuran = 0;
        for ($index = 0; $index < $zip->numFiles; $index++) {
            $statistik = $zip->statIndex($index);
            $nama = $statistik['name'];
            $valid = $nama === 'database/data.json'
                || str_starts_with($nama, 'arsip/surat/')
                || str_starts_with($nama, 'arsip/verifikasi/');
            if (! $valid || str_contains($nama, '..') || str_contains($nama, '\\') || str_contains($nama, "\0")) {
                $this->tolak($zip, 'Struktur backup tidak valid.');
            }
            $ukuran += $statistik['size'];
            if ($ukuran > 209715200) {
                $this->tolak($zip, 'Isi backup terlalu besar.');
            }
        }
    }

    private function dataValid(mixed $data): bool
    {
        if (! is_array($data) || ($data['format'] ?? null) !== 'sipadok-mysql-v1' || ! is_array($data['tabel'] ?? null)) {
            return false;
        }
        foreach (self::KOLOM_WAJIB as $tabel => $kolom) {
            if (! isset($data['tabel'][$tabel]) || ! is_array($data['tabel'][$tabel])) {
                return false;
            }
            foreach ($data['tabel'][$tabel] as $baris) {
                if (! is_array($baris) || array_diff($kolom, array_keys($baris))) {
                    return false;
                }
            }
        }

        $surat = array_column($data['tabel']['surat'], 'id');
        foreach ($data['tabel']['verifikasi_ttd'] as $verifikasi) {
            if ($verifikasi['surat_id'] !== null && ! in_array($verifikasi['surat_id'], $surat, true)) {
                return false;
            }
        }

        return $data['tabel']['users'] !== [];
    }

    private function pulihkanDataDanFile(array $data, string $panggung, string $id): void
    {
        $folderLama = [];
        try {
            foreach (['surat', 'verifikasi'] as $folder) {
                $aktif = Storage::path($folder);
                $lama = Storage::path("lama-{$folder}-{$id}");
                if (File::isDirectory($aktif) && ! File::moveDirectory($aktif, $lama)) {
                    throw new \RuntimeException('Folder arsip tidak dapat diamankan.');
                }
                $folderLama[$aktif] = $lama;
                if (! File::moveDirectory("{$panggung}/{$folder}", $aktif)) {
                    throw new \RuntimeException('Folder arsip tidak dapat dipulihkan.');
                }
            }

            DB::transaction(function () use ($data) {
                foreach (array_reverse(self::TABEL) as $tabel) {
                    DB::table($tabel)->delete();
                }
                foreach (self::TABEL as $tabel) {
                    if ($data[$tabel]) {
                        DB::table($tabel)->insert($data[$tabel]);
                    }
                }
            });
        } catch (Throwable $kesalahan) {
            foreach ($folderLama as $aktif => $lama) {
                File::deleteDirectory($aktif);
                if (File::isDirectory($lama)) {
                    File::moveDirectory($lama, $aktif);
                }
            }
            File::deleteDirectory($panggung);
            throw ValidationException::withMessages(['cadangan' => $kesalahan->getMessage()]);
        }

        foreach ($folderLama as $lama) {
            File::deleteDirectory($lama);
        }
        File::deleteDirectory($panggung);
    }

    private function tolak(ZipArchive $zip, string $pesan, ?string $folder = null): never
    {
        $zip->close();
        if ($folder) {
            File::deleteDirectory($folder);
        }
        throw ValidationException::withMessages(['cadangan' => $pesan]);
    }
}
