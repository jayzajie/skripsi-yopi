<?php

namespace App\Layanan;

use Symfony\Component\Process\Process;

class PemeriksaTandaTanganPdf
{
    public function valid(string $pdf): bool
    {
        if (! str_starts_with($pdf, '%PDF') || ! preg_match('/\/ByteRange\s*\[\s*(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s*\]/', $pdf, $cocok)) {
            return false;
        }

        [$awalPertama, $panjangPertama, $awalKedua, $panjangKedua] = array_map('intval', array_slice($cocok, 1));
        if ($awalPertama !== 0 || $panjangPertama > $awalKedua || $awalKedua + $panjangKedua > strlen($pdf)) {
            return false;
        }

        $celah = substr($pdf, $panjangPertama, $awalKedua - $panjangPertama);
        if (! preg_match('/<([0-9a-fA-F\s]+)>/s', $celah, $isi)) {
            return false;
        }
        $tandaTangan = hex2bin(preg_replace('/\s+/', '', $isi[1]));
        $tandaTangan = $this->potongDer($tandaTangan ?: '');
        if (! $tandaTangan) {
            return false;
        }

        $data = substr($pdf, 0, $panjangPertama).substr($pdf, $awalKedua, $panjangKedua);
        $lokasiData = tempnam(sys_get_temp_dir(), 'sipadok-data-');
        $lokasiTandaTangan = tempnam(sys_get_temp_dir(), 'sipadok-ttd-');
        $lokasiHasil = tempnam(sys_get_temp_dir(), 'sipadok-hasil-');
        file_put_contents($lokasiData, $data);
        file_put_contents($lokasiTandaTangan, $tandaTangan);

        try {
            $perintah = [
                'openssl', 'cms', '-verify', '-inform', 'DER', '-in', $lokasiTandaTangan,
                '-content', $lokasiData, '-binary', '-out', $lokasiHasil,
            ];
            if ($trustStore = config('app.pdf_trust_store')) {
                array_push($perintah, '-CAfile', $trustStore);
            }
            $proses = new Process($perintah);
            $proses->run();

            return $proses->isSuccessful();
        } finally {
            @unlink($lokasiData);
            @unlink($lokasiTandaTangan);
            @unlink($lokasiHasil);
        }
    }

    private function potongDer(string $der): string
    {
        if (strlen($der) < 2 || ord($der[0]) !== 0x30) {
            return '';
        }
        $byte = ord($der[1]);
        if ($byte < 0x80) {
            $panjang = $byte;
            $kepala = 2;
        } else {
            $jumlah = $byte & 0x7F;
            if ($jumlah === 0 || $jumlah > 4 || strlen($der) < 2 + $jumlah) {
                return '';
            }
            $panjang = 0;
            for ($index = 0; $index < $jumlah; $index++) {
                $panjang = ($panjang << 8) | ord($der[2 + $index]);
            }
            $kepala = 2 + $jumlah;
        }

        return strlen($der) >= $kepala + $panjang ? substr($der, 0, $kepala + $panjang) : '';
    }
}
