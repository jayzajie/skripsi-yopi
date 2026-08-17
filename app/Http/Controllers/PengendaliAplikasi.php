<?php

namespace App\Http\Controllers;

use App\Models\KategoriDokumen;
use App\Models\Pengguna;
use App\Models\Surat;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PengendaliAplikasi extends Controller
{
    public function dasbor(Request $request): View
    {
        $dasar = Surat::terlihatOleh($request->user());
        $terbaru = (clone $dasar)->latest('tanggal_surat')->limit(5)->get();
        $kartu = [
            ['Total Dokumen', (clone $dasar)->count(), 'i-arsip', 'ungu'],
            ['Surat Masuk', (clone $dasar)->where('jenis', 'masuk')->count(), 'i-masuk', 'biru'],
            ['Surat Keluar', (clone $dasar)->where('jenis', 'keluar')->count(), 'i-keluar', 'hijau'],
        ];
        if ($request->user()->peran === 'super_admin') {
            $kartu[] = ['Menunggu Persetujuan', Surat::whereIn('status', ['Diterima', 'Konsep'])->count(), 'i-verifikasi', 'jingga'];
            $kartu[] = ['Pengguna Aktif', Pengguna::where('aktif', true)->count(), 'i-akun', 'toska'];
        } elseif ($request->user()->peran === 'admin') {
            $kartu[] = ['Menunggu Persetujuan', Surat::whereIn('status', ['Diterima', 'Konsep'])->count(), 'i-verifikasi', 'jingga'];
        } else {
            $kartu[] = ['Tugas Aktif', (clone $dasar)->where('status', 'Didisposisikan')->count(), 'i-verifikasi', 'jingga'];
        }

        return view('aplikasi', compact('terbaru', 'kartu'))->with('halaman', 'dasbor');
    }

    public function surat(Request $request, string $jenis): View
    {
        abort_unless(in_array($jenis, ['masuk', 'keluar'], true), 404);
        $surat = Surat::with(['kategori', 'penanggungJawab'])->where('jenis', $jenis)
            ->when($request->string('cari')->value(), function ($query, string $cari) {
                $query->where(function ($bagian) use ($cari) {
                    $bagian->where('nomor_agenda', 'like', "%{$cari}%")
                        ->orWhere('nomor_surat', 'like', "%{$cari}%")
                        ->orWhere('perihal', 'like', "%{$cari}%")
                        ->orWhere('pihak', 'like', "%{$cari}%");
                });
            })
            ->when($request->string('status')->value(), fn ($q, $status) => $q->where('status', $status))
            ->latest('tanggal_surat')->paginate(10)->withQueryString();

        $kategori = KategoriDokumen::where('aktif', true)->orderBy('nama')->get();
        $pegawai = Pengguna::where(['peran' => 'pegawai', 'aktif' => true])->orderBy('name')->get();
        $suratMasuk = $jenis === 'keluar'
            ? Surat::where('jenis', 'masuk')->latest('tanggal_surat')->get()
            : collect();
        $nomorOtomatis = $jenis === 'masuk' ? $this->nomorOtomatisSuratMasuk() : [];

        return view('aplikasi', compact('surat', 'jenis', 'kategori', 'pegawai', 'suratMasuk', 'nomorOtomatis'))->with('halaman', 'surat');
    }

    public function simpanSurat(Request $request, string $jenis): RedirectResponse
    {
        abort_unless(in_array($jenis, ['masuk', 'keluar'], true), 404);
        $data = $request->validate([
            'kategori_id' => ['required', Rule::exists('kategori_dokumen', 'id')->where('aktif', true)],
            'surat_masuk_id' => $jenis === 'keluar'
                ? ['nullable', Rule::exists('surat', 'id')->where('jenis', 'masuk')]
                : ['prohibited'],
            'nomor_agenda' => [$jenis === 'masuk' ? 'nullable' : 'required', 'string', 'max:100', 'unique:surat,nomor_agenda'],
            'nomor_surat' => [$jenis === 'masuk' ? 'nullable' : 'required', 'string', 'max:150'],
            'tanggal_surat' => ['required', 'date'],
            'pihak' => ['required', 'string', 'max:150'],
            'perihal' => ['required', 'string', 'max:255'],
            'file' => ['nullable', 'file', 'mimes:pdf', 'max:10240'],
        ]);

        if ($jenis === 'masuk' && (empty($data['nomor_agenda']) || empty($data['nomor_surat']))) {
            $data = [...$data, ...$this->nomorOtomatisSuratMasuk()];
        }

        $data['jenis'] = $jenis;
        $data['status'] = $jenis === 'masuk' ? 'Diterima' : 'Konsep';
        $data['dibuat_oleh'] = $request->user()->id;
        $data['file'] = $request->file('file')?->store('surat');
        Surat::create($data);

        return back()->with('sukses', 'Surat berhasil disimpan.');
    }

    private function nomorOtomatisSuratMasuk(): array
    {
        $prefix = 'SM/'.now()->format('Y/m').'/';
        $urutan = Surat::where('nomor_agenda', 'like', "{$prefix}%")
            ->pluck('nomor_agenda')->map(fn (string $nomor) => (int) Str::afterLast($nomor, '/'))->max() + 1;

        return [
            'nomor_agenda' => $prefix.str_pad((string) $urutan, 3, '0', STR_PAD_LEFT),
            'nomor_surat' => str_pad((string) $urutan, 3, '0', STR_PAD_LEFT).'/SM/'.now()->format('m/Y'),
        ];
    }

    public function perbaruiSurat(Request $request, Surat $surat): RedirectResponse
    {
        abort_unless(in_array($surat->status, ['Diterima', 'Konsep'], true), 422, 'Surat yang sudah diproses tidak dapat diedit.');
        $data = $request->validate([
            'kategori_id' => ['required', Rule::exists('kategori_dokumen', 'id')->where('aktif', true)],
            'surat_masuk_id' => $surat->jenis === 'keluar'
                ? ['nullable', Rule::exists('surat', 'id')->where('jenis', 'masuk')]
                : ['prohibited'],
            'nomor_agenda' => ['required', 'string', 'max:100', Rule::unique('surat')->ignore($surat)],
            'nomor_surat' => ['required', 'string', 'max:150'],
            'tanggal_surat' => ['required', 'date'],
            'pihak' => ['required', 'string', 'max:150'],
            'perihal' => ['required', 'string', 'max:255'],
            'file' => ['nullable', 'file', 'mimes:pdf', 'max:10240'],
        ]);

        if ($request->hasFile('file')) {
            $fileLama = $surat->file;
            $data['file'] = $request->file('file')->store('surat');
            $surat->verifikasi()->delete();
        }
        $surat->update($data);
        if (isset($fileLama)) {
            Storage::delete($fileLama);
        }

        return back()->with('sukses', 'Surat berhasil diperbarui.');
    }

    public function hapusSurat(Request $request, Surat $surat): RedirectResponse
    {
        abort_unless(
            $request->user()->peran === 'super_admin' || in_array($surat->status, ['Diterima', 'Konsep'], true),
            422,
            'Surat yang sudah diproses tidak dapat dihapus.',
        );
        if ($surat->file) {
            Storage::delete($surat->file);
        }
        $surat->delete();

        return back()->with('sukses', 'Surat berhasil dihapus.');
    }

    public function prosesSurat(Request $request, Surat $surat): RedirectResponse
    {
        abort_unless(
            in_array($surat->status, ['Diterima', 'Konsep'], true)
            || ($surat->jenis === 'keluar' && $surat->status === 'Terkirim' && ! $surat->ditugaskan_ke),
            422,
            'Surat sudah diproses.',
        );
        $data = $request->validate([
            'ditugaskan_ke' => [
                'required',
                Rule::exists('users', 'id')->where(fn ($query) => $query->where('peran', 'pegawai')->where('aktif', true)),
            ],
            'disposisi' => ['required', 'string', 'max:1000'],
        ]);
        $surat->update([
            ...$data,
            'status' => $surat->jenis === 'masuk' ? 'Didisposisikan' : ($surat->status === 'Terkirim' ? 'Terkirim' : 'Disetujui'),
        ]);

        return back()->with('sukses', $surat->jenis === 'masuk' ? 'Surat berhasil didisposisikan.' : 'Surat keluar berhasil disetujui.');
    }

    public function tandaiTerkirim(Surat $surat): RedirectResponse
    {
        abort_unless($surat->jenis === 'keluar' && $surat->status === 'Disetujui', 422, 'Surat belum disetujui atau sudah dikirim.');
        $surat->update(['status' => 'Terkirim']);

        return back()->with('sukses', 'Surat keluar ditandai sudah terkirim.');
    }

    public function selesaikanSurat(Request $request, Surat $surat): RedirectResponse
    {
        abort_unless(
            $surat->jenis === 'masuk'
            && $surat->status === 'Didisposisikan'
            && $surat->ditugaskan_ke === $request->user()->id,
            403,
        );
        $surat->update(['status' => 'Selesai']);

        return back()->with('sukses', 'Tugas surat berhasil diselesaikan.');
    }

    public function arsip(Request $request): View
    {
        $surat = Surat::with(['kategori', 'penanggungJawab'])->terlihatOleh($request->user())
            ->when($request->string('jenis')->value(), fn ($q, $jenis) => $q->where('jenis', $jenis))
            ->when($request->string('cari')->value(), fn ($q, $cari) => $q
                ->where(fn ($bagian) => $bagian->where('nomor_agenda', 'like', "%{$cari}%")
                    ->orWhere('perihal', 'like', "%{$cari}%")))
            ->latest('tanggal_surat')->paginate(10)->withQueryString();

        return view('aplikasi', compact('surat'))->with('halaman', 'arsip');
    }

    public function detailArsip(Surat $surat): View
    {
        $this->pastikanDapatMengakses($surat);

        return view('aplikasi', compact('surat'))->with('halaman', 'detail-arsip');
    }

    public function unduhSurat(Surat $surat): StreamedResponse|RedirectResponse
    {
        $this->pastikanDapatMengakses($surat);

        if (! $surat->file || ! Storage::exists($surat->file)) {
            return back()->withErrors(['file' => 'Dokumen arsip belum tersedia.']);
        }

        return Storage::download($surat->file, basename($surat->file));
    }

    public function pratinjauSurat(Surat $surat): BinaryFileResponse
    {
        $this->pastikanDapatMengakses($surat);
        abort_unless($surat->file && Storage::exists($surat->file), 404);

        return response()->file(Storage::path($surat->file), [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.basename($surat->file).'"',
        ]);
    }

    public function laporan(Request $request): View
    {
        $query = Surat::terlihatOleh($request->user())
            ->when($request->string('jenis')->value(), fn ($q, $jenis) => $q->where('jenis', $jenis))
            ->when($request->date('mulai'), fn ($q, $mulai) => $q->whereDate('tanggal_surat', '>=', $mulai))
            ->when($request->date('selesai'), fn ($q, $selesai) => $q->whereDate('tanggal_surat', '<=', $selesai));
        $surat = $query->latest('tanggal_surat')->get();

        return view('aplikasi', compact('surat'))->with('halaman', 'laporan');
    }

    public function eksporLaporan(Request $request): StreamedResponse
    {
        $surat = Surat::terlihatOleh($request->user())
            ->when($request->string('jenis')->value(), fn ($q, $jenis) => $q->where('jenis', $jenis))
            ->when($request->date('mulai'), fn ($q, $mulai) => $q->whereDate('tanggal_surat', '>=', $mulai))
            ->when($request->date('selesai'), fn ($q, $selesai) => $q->whereDate('tanggal_surat', '<=', $selesai))
            ->latest('tanggal_surat')->get();

        return response()->streamDownload(function () use ($surat) {
            echo '<table><thead><tr><th>Jenis</th><th>Nomor Agenda</th><th>Nomor Surat</th><th>Perihal</th><th>Asal/Tujuan</th><th>Tanggal</th><th>Status</th></tr></thead><tbody>';
            foreach ($surat as $item) {
                $kolom = [$item->jenis, $item->nomor_agenda, $item->nomor_surat, $item->perihal, $item->pihak, $item->tanggal_surat->format('d/m/Y'), $item->status];
                echo '<tr><td>'.implode('</td><td>', array_map(fn ($nilai) => htmlspecialchars($nilai, ENT_QUOTES, 'UTF-8'), $kolom)).'</td></tr>';
            }
            echo '</tbody></table>';
        }, 'laporan-arsip.xls', ['Content-Type' => 'application/vnd.ms-excel; charset=UTF-8']);
    }

    private function pastikanDapatMengakses(Surat $surat): void
    {
        abort_unless(Surat::terlihatOleh(auth()->user())->whereKey($surat)->exists(), 403);
    }
}
