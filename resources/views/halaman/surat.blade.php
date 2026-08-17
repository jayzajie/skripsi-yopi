<section class="toolbar panel-tipis">
    <form method="get" class="pencarian">
        <div class="input-ikon">
            <svg><use href="#i-cari"/></svg>
            <input name="cari" value="{{ request('cari') }}" placeholder="Cari surat {{ $jenis }}...">
        </div>
        <select name="status" aria-label="Filter status">
            <option value="">Semua status</option>
            @foreach($jenis === 'masuk' ? ['Diterima','Didisposisikan','Selesai'] : ['Konsep','Disetujui','Terkirim'] as $status)
                <option @selected(request('status') === $status)>{{ $status }}</option>
            @endforeach
        </select>
        <button class="tombol tombol-sekunder">Filter</button>
    </form>
    @if(auth()->user()->peran === 'admin')
        <button class="tombol tombol-primer" data-buka-modal="form-surat" data-tambah-surat>
            <svg><use href="#i-tambah"/></svg> Tambah Surat {{ ucfirst($jenis) }}
        </button>
    @endif
</section>
<section class="panel">
    <div class="bungkus-tabel">
        <table>
            <thead>
                <tr>
                    <th>No</th>
                    <th>Kategori</th>
                    <th>No. Agenda</th>
                    <th>No. Surat</th>
                    <th>{{ $jenis === 'masuk' ? 'Asal' : 'Tujuan' }}</th>
                    <th>Perihal</th>
                    <th>Penanggung Jawab</th>
                    <th>Status</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($surat as $item)
                    <tr>
                        <td>{{ $surat->firstItem()+$loop->index }}</td>
                        <td>{{ $item->kategori?->nama ?? 'Belum dikategorikan' }}</td>
                        <td>{{ $item->nomor_agenda }}</td>
                        <td>{{ $item->nomor_surat }}</td>
                        <td>{{ $item->pihak }}</td>
                        <td>{{ $item->perihal }}</td>
                        <td>{{ $item->penanggungJawab?->name ?? '—' }}</td>
                        <td><span class="lencana {{ strtolower($item->status) }}">{{ $item->status }}</span></td>
                        <td>
                            <div class="aksi-tabel">
                                @if(auth()->user()->peran === 'admin' && in_array($item->status, ['Diterima','Konsep']))
                                    <button
                                        class="tombol-ikon"
                                        aria-label="Edit surat"
                                        data-buka-modal="form-surat"
                                        data-edit-surat
                                        data-action="{{ route('surat.perbarui', $item) }}"
                                        data-isian="{{ base64_encode($item->toJson()) }}"
                                    >
                                        <svg><use href="#i-edit"/></svg>
                                    </button>
                                    <form method="post" action="{{ route('surat.hapus', $item) }}" data-konfirmasi="Hapus surat ini?">
                                        @csrf
                                        @method('DELETE')
                                        <button class="tombol-ikon bahaya" aria-label="Hapus surat">
                                            <svg><use href="#i-hapus"/></svg>
                                        </button>
                                    </form>
                                @elseif(auth()->user()->peran === 'admin' && $item->status === 'Disetujui')
                                    <form method="post" action="{{ route('surat.terkirim', $item) }}" data-konfirmasi="Pastikan surat sudah dikirim. Lanjutkan?">
                                        @csrf
                                        <button class="tombol tombol-mini tombol-primer">Tandai Terkirim</button>
                                    </form>
                                @elseif(
                                    auth()->user()->peran === 'super_admin'
                                    && (in_array($item->status, ['Diterima','Konsep']) || ($item->status === 'Terkirim' && !$item->ditugaskan_ke))
                                )
                                    <button
                                        class="tombol tombol-mini tombol-primer"
                                        data-buka-modal="proses-surat"
                                        data-proses-surat
                                        data-action="{{ route('surat.proses', $item) }}"
                                        data-nomor="{{ $item->nomor_agenda }}"
                                    >
                                        {{ $item->jenis === 'masuk' ? 'Disposisikan' : ($item->status === 'Terkirim' ? 'Tetapkan PJ' : 'Setujui') }}
                                    </button>
                                @else
                                    <span>—</span>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="kosong">Surat tidak ditemukan.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="bawah-tabel">
        <span>Menampilkan {{ $surat->firstItem() ?? 0 }}–{{ $surat->lastItem() ?? 0 }} dari {{ $surat->total() }} data</span>
        {{ $surat->links() }}
    </div>
</section>
@if(auth()->user()->peran === 'admin')
    <dialog class="modal" id="form-surat">
        <form method="post" action="{{ route('surat.simpan', $jenis) }}" enctype="multipart/form-data" data-form-surat data-action-baru="{{ route('surat.simpan', $jenis) }}">
            @csrf
            <input type="hidden" name="_method" value="POST" data-metode>
            <div class="kepala-modal">
                <h2 data-judul-modal>Tambah Surat {{ ucfirst($jenis) }}</h2>
                <button type="button" class="tombol-ikon" data-tutup-modal aria-label="Tutup">
                    <svg><use href="#i-tutup"/></svg>
                </button>
            </div>
            <div class="isi-modal grid-form">
                @if($jenis === 'keluar')
                    <label class="rentang-2">
                        Balasan dari Surat Masuk
                        <select name="surat_masuk_id" data-surat-masuk data-kategori-keluar="{{ $kategori->firstWhere('kode', 'SK')?->id }}">
                            <option value="">Bukan balasan surat masuk</option>
                            @foreach($suratMasuk as $item)
                                <option
                                    value="{{ $item->id }}"
                                    data-agenda="{{ $item->nomor_agenda }}"
                                    data-nomor="{{ $item->nomor_surat }}"
                                    data-tanggal="{{ $item->tanggal_surat->format('Y-m-d') }}"
                                    data-pihak="{{ $item->pihak }}"
                                    data-perihal="{{ $item->perihal }}"
                                >{{ $item->nomor_agenda }} — {{ $item->perihal }}</option>
                            @endforeach
                        </select>
                    </label>
                @endif
                <label>
                    Nomor Agenda <b>*</b>
                    <input name="nomor_agenda" value="{{ $nomorOtomatis['nomor_agenda'] ?? '' }}" required @readonly($jenis === 'masuk') placeholder="Dibuat otomatis">
                </label>
                <label>
                    Kategori <b>*</b>
                    <select name="kategori_id" required>
                        <option value="">Pilih kategori</option>
                        @foreach($kategori as $item)
                            <option value="{{ $item->id }}">{{ $item->nama }}</option>
                        @endforeach
                    </select>
                </label>
                <label>
                    Nomor Surat <b>*</b>
                    <input name="nomor_surat" value="{{ $nomorOtomatis['nomor_surat'] ?? '' }}" required @readonly($jenis === 'masuk') placeholder="Dibuat otomatis">
                </label>
                <label>Tanggal Surat <b>*</b><input type="date" name="tanggal_surat" required></label>
                <label>
                    {{ $jenis === 'masuk' ? 'Asal' : 'Tujuan' }} Surat <b>*</b>
                    <input name="pihak" required placeholder="Masukkan {{ $jenis === 'masuk' ? 'asal' : 'tujuan' }} surat">
                </label>
                <label class="rentang-2">Perihal <b>*</b><input name="perihal" required placeholder="Masukkan perihal surat"></label>
                <label>
                    Upload File
                    <input type="file" name="file" accept="application/pdf">
                    <small>Format PDF, maks. 10 MB</small>
                </label>
                <p class="catatan-teknis rentang-2">Status awal ditetapkan otomatis menjadi {{ $jenis === 'masuk' ? 'Diterima' : 'Konsep' }} dan diteruskan ke Super Admin.</p>
            </div>
            <div class="kaki-modal">
                <button type="button" class="tombol tombol-sekunder" data-tutup-modal>Batal</button>
                <button class="tombol tombol-primer">Simpan</button>
            </div>
        </form>
    </dialog>
@endif
@if(auth()->user()->peran === 'super_admin')
    <dialog class="modal" id="proses-surat">
        <form method="post" data-form-proses-surat>
            @csrf
            <div class="kepala-modal">
                <h2>Proses Surat <span data-nomor-surat></span></h2>
                <button type="button" class="tombol-ikon" data-tutup-modal aria-label="Tutup">
                    <svg><use href="#i-tutup"/></svg>
                </button>
            </div>
            <div class="isi-modal grid-form">
                <label>
                    Penanggung Jawab <b>*</b>
                    <select name="ditugaskan_ke" required>
                        <option value="">Pilih pegawai</option>
                        @foreach($pegawai as $item)
                            <option value="{{ $item->id }}">{{ $item->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="rentang-2">
                    Arahan / Disposisi <b>*</b>
                    <textarea name="disposisi" rows="4" required placeholder="Tuliskan arahan yang harus ditindaklanjuti"></textarea>
                </label>
            </div>
            <div class="kaki-modal">
                <button type="button" class="tombol tombol-sekunder" data-tutup-modal>Batal</button>
                <button class="tombol tombol-primer">Simpan Proses</button>
            </div>
        </form>
    </dialog>
@endif
